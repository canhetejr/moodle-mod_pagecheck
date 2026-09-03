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
 * Tests for how overrides are resolved.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck;

use mod_pagecheck\local\rules;

/**
 * Tests for how overrides are resolved.
 *
 * @covers \mod_pagecheck\local\rules
 */
class rules_test extends \advanced_testcase {
    /** @var \stdClass The course the activity lives in. */
    protected $course;

    /** @var \stdClass The activity instance. */
    protected $pagecheck;

    /**
     * Create a course with one activity limited to between 5 and 10 pages.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('pagecheck', [
            'course' => $this->course->id,
            'minpages' => 5,
            'maxpages' => 10,
            'maxattempts' => 2,
            'duedate' => 1893456000,
        ]);
        $this->pagecheck = $module;
    }

    /**
     * Store an override.
     *
     * @param array $fields the columns to set
     * @return int the id of the new override
     */
    protected function add_override(array $fields): int {
        global $DB;

        $defaults = [
            'pagecheckid' => $this->pagecheck->id,
            'groupid' => 0,
            'userid' => 0,
            'sortorder' => 0,
            'allowsubmissionsfromdate' => null,
            'duedate' => null,
            'cutoffdate' => null,
            'maxattempts' => null,
            'minpages' => null,
            'maxpages' => null,
        ];

        return $DB->insert_record('pagecheck_overrides', (object) ($fields + $defaults));
    }

    /**
     * With no override at all, the instance settings apply as they are.
     *
     * @return void
     */
    public function test_instance_settings_apply_by_default(): void {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $rules = rules::for_user($this->pagecheck, (int) $user->id);

        $this->assertSame(5, $rules->minpages);
        $this->assertSame(10, $rules->maxpages);
        $this->assertSame(2, $rules->maxattempts);
        $this->assertSame(1893456000, $rules->duedate);
    }

    /**
     * A group override applies to the members of that group and to nobody else.
     *
     * @return void
     */
    public function test_group_override_applies_to_its_members(): void {
        $member = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $outsider = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member([
            'groupid' => $group->id,
            'userid' => $member->id,
        ]);

        $this->add_override(['groupid' => $group->id, 'maxpages' => 20]);

        $memberrules = rules::for_user($this->pagecheck, (int) $member->id);
        $outsiderrules = rules::for_user($this->pagecheck, (int) $outsider->id);

        $this->assertSame(20, $memberrules->maxpages);
        $this->assertSame(10, $outsiderrules->maxpages);
        // A column the override left alone is still inherited.
        $this->assertSame(5, $memberrules->minpages);
    }

    /**
     * A user override wins over a group override, whatever their sort order.
     *
     * @return void
     */
    public function test_user_override_beats_group_override(): void {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $group = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member([
            'groupid' => $group->id,
            'userid' => $user->id,
        ]);

        $this->add_override(['groupid' => $group->id, 'maxpages' => 20, 'sortorder' => 0]);
        $this->add_override(['userid' => $user->id, 'maxpages' => 30, 'sortorder' => 9]);

        $rules = rules::for_user($this->pagecheck, (int) $user->id);

        $this->assertSame(30, $rules->maxpages);
    }

    /**
     * When a student is in two overridden groups, the lowest sort order decides.
     *
     * @return void
     */
    public function test_lowest_sort_order_wins_between_groups(): void {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $first = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $second = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        foreach ([$first, $second] as $group) {
            $this->getDataGenerator()->create_group_member([
                'groupid' => $group->id,
                'userid' => $user->id,
            ]);
        }

        $this->add_override(['groupid' => $first->id, 'maxpages' => 12, 'sortorder' => 1]);
        $this->add_override(['groupid' => $second->id, 'maxpages' => 40, 'sortorder' => 5]);

        $rules = rules::for_user($this->pagecheck, (int) $user->id);

        $this->assertSame(12, $rules->maxpages);
    }

    /**
     * An override extends one deadline without disturbing the rest of the configuration.
     *
     * @return void
     */
    public function test_override_of_dates_only(): void {
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $extended = 1924992000;

        $this->add_override(['userid' => $user->id, 'duedate' => $extended]);

        $rules = rules::for_user($this->pagecheck, (int) $user->id);

        $this->assertSame($extended, $rules->duedate);
        $this->assertSame(5, $rules->minpages);
        $this->assertSame(10, $rules->maxpages);
        $this->assertSame(2, $rules->maxattempts);
    }

    /**
     * The helpers that answer "is it open, late or closed" agree with the dates.
     *
     * @return void
     */
    public function test_open_late_and_closed(): void {
        $now = time();

        $notyet = new rules();
        $notyet->allowsubmissionsfromdate = $now + DAYSECS;
        $this->assertTrue($notyet->is_not_open_yet($now));
        $this->assertFalse($notyet->is_closed($now));

        $late = new rules();
        $late->duedate = $now - DAYSECS;
        $this->assertTrue($late->is_late($now));
        $this->assertFalse($late->is_closed($now));

        $late->blockafterdue = true;
        $this->assertTrue($late->is_closed($now));

        $cutoff = new rules();
        $cutoff->cutoffdate = $now - HOURSECS;
        $this->assertTrue($cutoff->is_closed($now));
    }
}
