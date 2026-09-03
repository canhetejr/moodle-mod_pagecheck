<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tests for grading.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck;

use mod_pagecheck\local\grader;

/**
 * Tests for grading.
 *
 * @covers \mod_pagecheck\local\grader
 */
class grader_test extends \advanced_testcase {
    /** @var \stdClass The course the activity lives in. */
    protected $course;

    /**
     * Give each test a course to work in.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Build an activity and a grader for it.
     *
     * @param int $grade the grade setting: points when positive, a scale id when negative
     * @return array [grader, activity instance, module context]
     */
    protected function make_activity(int $grade = 100): array {
        $module = $this->getDataGenerator()->create_module('pagecheck', [
            'course' => $this->course->id,
            'grade' => $grade,
        ]);
        $context = \context_module::instance($module->cmid);

        return [new grader($module, $context), $module, $context];
    }

    /**
     * A grade and a comment are stored together and read back as they were written.
     *
     * @return void
     */
    public function test_saving_a_grade(): void {
        global $DB;

        [$grader, $module] = $this->make_activity(10);
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        $grader->save((int) $student->id, 8.5, '<p>Well argued.</p>', FORMAT_HTML, (int) $teacher->id);

        $record = $grader->get_grade((int) $student->id);

        $this->assertEquals(8.5, (float) $record->grade);
        $this->assertSame('<p>Well argued.</p>', $record->feedback);
        $this->assertEquals($teacher->id, $record->grader);
        $this->assertEquals(1, $DB->count_records('pagecheck_grades', ['pagecheckid' => $module->id]));
    }

    /**
     * Grading the same student again replaces the grade rather than adding a second one.
     *
     * @return void
     */
    public function test_regrading_replaces_the_grade(): void {
        global $DB;

        [$grader, $module] = $this->make_activity(10);
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        $grader->save((int) $student->id, 4.0, 'First look.', FORMAT_HTML, (int) $teacher->id);
        $grader->save((int) $student->id, 9.0, 'On reflection.', FORMAT_HTML, (int) $teacher->id);

        $record = $grader->get_grade((int) $student->id);

        $this->assertEquals(9.0, (float) $record->grade);
        $this->assertSame('On reflection.', $record->feedback);
        $this->assertEquals(1, $DB->count_records('pagecheck_grades', ['pagecheckid' => $module->id]));
    }

    /**
     * A grade saved here reaches the gradebook.
     *
     * @return void
     */
    public function test_the_grade_reaches_the_gradebook(): void {
        global $CFG;

        require_once($CFG->libdir . '/gradelib.php');

        [$grader, $module] = $this->make_activity(10);
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        $grader->save((int) $student->id, 7.0, 'Good.', FORMAT_HTML, (int) $teacher->id);

        $grades = grade_get_grades($this->course->id, 'mod', 'pagecheck', $module->id, $student->id);
        $item = reset($grades->items);

        $this->assertEquals(7.0, (float) $item->grades[$student->id]->grade);
    }

    /**
     * A grade can be taken away again, which is not the same as awarding a zero.
     *
     * @return void
     */
    public function test_clearing_a_grade(): void {
        [$grader] = $this->make_activity(10);
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        $grader->save((int) $student->id, 6.0, '', FORMAT_HTML, (int) $teacher->id);
        $grader->save((int) $student->id, null, 'Come and see me.', FORMAT_HTML, (int) $teacher->id);

        $record = $grader->get_grade((int) $student->id);

        $this->assertNull($record->grade);
        $this->assertSame('Come and see me.', $record->feedback);
    }

    /**
     * A grade out of points is shown against the maximum.
     *
     * @return void
     */
    public function test_formatting_a_point_grade(): void {
        [$grader] = $this->make_activity(10);

        $this->assertStringContainsString('8', $grader->format_grade(8.0));
        $this->assertStringContainsString('10', $grader->format_grade(8.0));
        $this->assertSame(get_string('notgraded', 'mod_pagecheck'), $grader->format_grade(null));
        $this->assertFalse($grader->uses_scale());
    }

    /**
     * A grade on a scale is shown by the name of the scale item, never as a bare number.
     *
     * @return void
     */
    public function test_formatting_a_scale_grade(): void {
        $scale = $this->getDataGenerator()->create_scale([
            'scale' => 'Insufficient, Fair, Good, Excellent',
        ]);
        [$grader] = $this->make_activity(-$scale->id);

        $this->assertTrue($grader->uses_scale());
        $this->assertSame('Good', $grader->format_grade(3));
        $this->assertSame(get_string('notgraded', 'mod_pagecheck'), $grader->format_grade(null));
    }

    /**
     * What a teacher types is accepted only when the activity can award it.
     *
     * @return void
     */
    public function test_parsing_a_grade(): void {
        [$grader] = $this->make_activity(10);

        $this->assertSame([true, null], $grader->parse_grade(''));
        $this->assertSame([true, 7.5], $grader->parse_grade('7.5'));
        $this->assertSame([false, null], $grader->parse_grade('11'));
        $this->assertSame([false, null], $grader->parse_grade('-1'));
        $this->assertSame([false, null], $grader->parse_grade('excellent'));
    }

    /**
     * A grade on a scale is only ever one of the items that scale offers.
     *
     * @return void
     */
    public function test_parsing_a_scale_grade(): void {
        $scale = $this->getDataGenerator()->create_scale([
            'scale' => 'Insufficient, Fair, Good, Excellent',
        ]);
        [$grader] = $this->make_activity(-$scale->id);

        $this->assertSame([true, 4.0], $grader->parse_grade('4'));
        $this->assertSame([false, null], $grader->parse_grade('9'));
    }

    /**
     * Walking the class visits everybody once, and stops at both ends.
     *
     * @return void
     */
    public function test_neighbours(): void {
        $participants = [11 => (object) ['id' => 11], 22 => (object) ['id' => 22],
            33 => (object) ['id' => 33]];

        $first = grader::get_neighbours($participants, 11);
        $middle = grader::get_neighbours($participants, 22);
        $last = grader::get_neighbours($participants, 33);

        $this->assertNull($first['previous']);
        $this->assertSame(22, $first['next']);
        $this->assertSame([11, 33], [$middle['previous'], $middle['next']]);
        $this->assertNull($last['next']);
        $this->assertSame(2, $middle['position']);
        $this->assertSame(3, $middle['total']);
    }

    /**
     * A user who is not in the list is reported as such rather than as the first student.
     *
     * @return void
     */
    public function test_neighbours_of_someone_not_listed(): void {
        $neighbours = grader::get_neighbours([11 => (object) ['id' => 11]], 99);

        $this->assertSame(0, $neighbours['position']);
        $this->assertNull($neighbours['next']);
        $this->assertNull($neighbours['previous']);
    }
}
