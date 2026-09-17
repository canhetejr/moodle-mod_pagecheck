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
 * Tests for the submission manager.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck;

use mod_pagecheck\local\submission_manager;
use mod_pagecheck\tests\fixtures\file_builder;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/pagecheck/tests/fixtures/file_builder.php');

/**
 * Tests for attempts, and for when a counted file is looked at again.
 *
 * @covers \mod_pagecheck\local\submission_manager
 */
final class submission_manager_test extends \advanced_testcase {
    /** @var \stdClass The course the activity lives in. */
    protected $course;

    /** @var \stdClass The student who submits. */
    protected $student;

    /**
     * Give each test a course and a student.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
    }

    /**
     * Build an activity and a manager for it.
     *
     * @param array $settings activity settings to override
     * @return array [submission_manager, activity instance, module context]
     */
    protected function make_activity(array $settings = []): array {
        $module = $this->getDataGenerator()->create_module(
            'pagecheck',
            array_merge(['course' => $this->course->id], $settings)
        );
        $cm = get_coursemodule_from_instance('pagecheck', $module->id);
        $context = \context_module::instance($module->cmid);

        return [new submission_manager($cm, $module, $context), $module, $context];
    }

    /**
     * Attach a PDF to an attempt, the way saving the form would.
     *
     * @param \context_module $context the module context
     * @param \stdClass $submission the attempt the file belongs to
     * @param string $filename the name to store it under
     * @param int $pages how many pages to draw
     * @param array $options passed to the file builder
     * @return \stored_file
     */
    protected function attach_pdf(
        \context_module $context,
        \stdClass $submission,
        string $filename,
        int $pages,
        array $options = []
    ): \stored_file {
        $path = make_request_directory() . '/' . $filename;
        file_builder::pdf($path, $pages, $options);

        return get_file_storage()->create_file_from_pathname([
            'contextid' => $context->id,
            'component' => 'mod_pagecheck',
            'filearea' => submission_manager::FILEAREA,
            'itemid' => $submission->id,
            'filepath' => '/',
            'filename' => $filename,
        ], $path);
    }

    /**
     * A student has no attempt until one is asked for.
     *
     * @return void
     */
    public function test_an_attempt_is_only_created_when_asked_for(): void {
        [$manager] = $this->make_activity();
        $userid = (int) $this->student->id;

        $this->assertNull($manager->get_submission($userid));

        $submission = $manager->get_submission($userid, true);

        $this->assertSame(submission_manager::STATUS_NEW, $submission->status);
        $this->assertEquals(0, $submission->attemptnumber);
        $this->assertEquals(1, $submission->latest);
        $this->assertEquals(0, $submission->timesubmitted);
    }

    /**
     * Sending an attempt for grading records when it happened.
     *
     * @return void
     */
    public function test_sending_an_attempt_records_the_time(): void {
        [$manager] = $this->make_activity();
        $userid = (int) $this->student->id;

        $submission = $manager->get_submission($userid, true);
        $manager->submit_for_grading($submission);

        $stored = $manager->get_submission($userid);

        $this->assertSame(submission_manager::STATUS_SUBMITTED, $stored->status);
        $this->assertGreaterThan(0, (int) $stored->timesubmitted);
    }

    /**
     * A further attempt supersedes the previous one without discarding it.
     *
     * @return void
     */
    public function test_a_further_attempt_supersedes_the_previous_one(): void {
        [$manager] = $this->make_activity(['maxattempts' => 3]);
        $userid = (int) $this->student->id;

        $first = $manager->get_submission($userid, true);
        $manager->submit_for_grading($first);
        $second = $manager->add_new_attempt($userid);

        $current = $manager->get_submission($userid);

        $this->assertEquals($second->id, $current->id);
        $this->assertEquals(1, $second->attemptnumber);
        $this->assertSame(submission_manager::STATUS_REOPENED, $current->status);
        // The first attempt is still on record, just no longer the current one.
        $this->assertCount(2, $manager->get_attempts($userid));
    }

    /**
     * The allowance counts attempts that were sent, not attempts that exist.
     *
     * @return void
     */
    public function test_the_allowance_counts_only_what_was_sent(): void {
        [$manager] = $this->make_activity(['maxattempts' => 3]);
        $userid = (int) $this->student->id;

        $submission = $manager->get_submission($userid, true);
        $this->assertSame(0, $manager->get_attempts_used($userid));

        $manager->submit_for_grading($submission);
        $this->assertSame(1, $manager->get_attempts_used($userid));

        // A reopened attempt that has not been sent yet does not count either.
        $manager->add_new_attempt($userid);
        $this->assertSame(1, $manager->get_attempts_used($userid));
    }

    /**
     * Work that has been sent for grading can no longer be changed.
     *
     * @return void
     */
    public function test_sent_work_can_no_longer_be_edited(): void {
        [$manager] = $this->make_activity(['maxattempts' => 3]);
        $userid = (int) $this->student->id;

        $submission = $manager->get_submission($userid, true);
        $this->assertTrue($manager->can_edit($userid));

        $manager->submit_for_grading($submission);

        $this->assertFalse($manager->can_edit($userid));
        // But a spare attempt is what makes maxattempts greater than one reachable.
        $this->assertTrue($manager->can_start_new_attempt($userid));
    }

    /**
     * A spent allowance closes both doors.
     *
     * @return void
     */
    public function test_a_spent_allowance_closes_both_doors(): void {
        [$manager] = $this->make_activity(['maxattempts' => 1]);
        $userid = (int) $this->student->id;

        $submission = $manager->get_submission($userid, true);
        $manager->submit_for_grading($submission);

        $this->assertFalse($manager->can_edit($userid));
        $this->assertFalse($manager->can_start_new_attempt($userid));
    }

    /**
     * A closed activity refuses both, whatever the allowance says.
     *
     * @return void
     */
    public function test_a_closed_activity_refuses_everything(): void {
        [$manager] = $this->make_activity([
            'maxattempts' => 5,
            'cutoffdate' => time() - DAYSECS,
        ]);
        $userid = (int) $this->student->id;

        $submission = $manager->get_submission($userid, true);
        $manager->submit_for_grading($submission);

        $this->assertFalse($manager->can_edit($userid));
        $this->assertFalse($manager->can_start_new_attempt($userid));
    }

    /**
     * Counting a file records what was found and the total for the attempt.
     *
     * @return void
     */
    public function test_counting_a_file_records_the_result_and_the_total(): void {
        global $DB;

        [$manager, $module, $context] = $this->make_activity();
        $userid = (int) $this->student->id;

        $submission = $manager->get_submission($userid, true);
        $file = $this->attach_pdf($context, $submission, 'essay.pdf', 7);

        $results = $manager->analyse($submission, $manager->get_rules($userid));

        $this->assertCount(1, $results);
        $result = $results[$file->get_pathnamehash()];
        $this->assertSame(7, $result->pages);
        $this->assertSame('a4', $result->pagesize);

        $row = $DB->get_record('pagecheck_files', ['submissionid' => $submission->id]);
        $this->assertEquals(7, $row->pagecount);
        $this->assertSame($file->get_contenthash(), $row->contenthash);

        $this->assertEquals(7, $DB->get_field(
            'pagecheck_submissions',
            'totalpages',
            ['id' => $submission->id]
        ));
    }

    /**
     * Looking at an unchanged file again reuses what was stored rather than counting it twice.
     *
     * The cached row is edited to a value the counter would never produce, so a result carrying
     * that value can only have come from the cache.
     *
     * @return void
     */
    public function test_an_unchanged_file_is_not_counted_twice(): void {
        global $DB;

        [$manager, $module, $context] = $this->make_activity();
        $userid = (int) $this->student->id;
        $rules = $manager->get_rules($userid);

        $submission = $manager->get_submission($userid, true);
        $file = $this->attach_pdf($context, $submission, 'essay.pdf', 7);
        $manager->analyse($submission, $rules);

        $DB->set_field('pagecheck_files', 'pagecount', 999, ['submissionid' => $submission->id]);

        $results = $manager->analyse($submission, $rules);

        $this->assertSame(999, $results[$file->get_pathnamehash()]->pages);
    }

    /**
     * Forcing a recount ignores what was stored.
     *
     * @return void
     */
    public function test_forcing_a_recount_ignores_the_cache(): void {
        global $DB;

        [$manager, $module, $context] = $this->make_activity();
        $userid = (int) $this->student->id;
        $rules = $manager->get_rules($userid);

        $submission = $manager->get_submission($userid, true);
        $file = $this->attach_pdf($context, $submission, 'essay.pdf', 7);
        $manager->analyse($submission, $rules);
        $DB->set_field('pagecheck_files', 'pagecount', 999, ['submissionid' => $submission->id]);

        $results = $manager->analyse($submission, $rules, true);

        $this->assertSame(7, $results[$file->get_pathnamehash()]->pages);
    }

    /**
     * A row left by an older version, which stored no paper size, is counted again.
     *
     * Null means "written before the plugin could read a paper size", so such a row heals itself
     * the next time the attempt is looked at. This is what stopped the size showing as a dash.
     *
     * @return void
     */
    public function test_a_row_without_a_paper_size_is_counted_again(): void {
        global $DB;

        [$manager, $module, $context] = $this->make_activity();
        $userid = (int) $this->student->id;
        $rules = $manager->get_rules($userid);

        $submission = $manager->get_submission($userid, true);
        $file = $this->attach_pdf($context, $submission, 'essay.pdf', 7);
        $manager->analyse($submission, $rules);

        $DB->set_field('pagecheck_files', 'pagesize', null, ['submissionid' => $submission->id]);
        $DB->set_field('pagecheck_files', 'pagecount', 999, ['submissionid' => $submission->id]);

        $results = $manager->analyse($submission, $rules);

        $this->assertSame(7, $results[$file->get_pathnamehash()]->pages);
        $this->assertSame('a4', $results[$file->get_pathnamehash()]->pagesize);
    }

    /**
     * A format with no paper size of its own is left alone.
     *
     * An empty string means "looked at, and there is nothing to find", which is not a reason to
     * count the file on every visit.
     *
     * @return void
     */
    public function test_a_format_without_a_paper_size_is_left_alone(): void {
        global $DB;

        [$manager, $module, $context] = $this->make_activity();
        $userid = (int) $this->student->id;
        $rules = $manager->get_rules($userid);

        $submission = $manager->get_submission($userid, true);
        $file = $this->attach_pdf($context, $submission, 'essay.pdf', 7);
        $manager->analyse($submission, $rules);

        $DB->set_field('pagecheck_files', 'pagesize', '', ['submissionid' => $submission->id]);
        $DB->set_field('pagecheck_files', 'pagecount', 999, ['submissionid' => $submission->id]);

        $results = $manager->analyse($submission, $rules);

        $this->assertSame(999, $results[$file->get_pathnamehash()]->pages);
        $this->assertNull($results[$file->get_pathnamehash()]->pagesize);
    }

    /**
     * Replacing the file under the same name counts the new one.
     *
     * @return void
     */
    public function test_replacing_the_file_counts_the_new_one(): void {
        [$manager, $module, $context] = $this->make_activity();
        $userid = (int) $this->student->id;
        $rules = $manager->get_rules($userid);

        $submission = $manager->get_submission($userid, true);
        $first = $this->attach_pdf($context, $submission, 'essay.pdf', 7);
        $manager->analyse($submission, $rules);

        $first->delete();
        $second = $this->attach_pdf($context, $submission, 'essay.pdf', 3);

        $results = $manager->analyse($submission, $rules);

        $this->assertSame(3, $results[$second->get_pathnamehash()]->pages);
    }

    /**
     * A file taken off the attempt takes its stored result with it.
     *
     * @return void
     */
    public function test_removing_a_file_forgets_its_result(): void {
        global $DB;

        [$manager, $module, $context] = $this->make_activity(['maxfiles' => 3]);
        $userid = (int) $this->student->id;
        $rules = $manager->get_rules($userid);

        $submission = $manager->get_submission($userid, true);
        $keep = $this->attach_pdf($context, $submission, 'keep.pdf', 4);
        $drop = $this->attach_pdf($context, $submission, 'drop.pdf', 2);
        $manager->analyse($submission, $rules);

        $this->assertEquals(2, $DB->count_records('pagecheck_files', ['submissionid' => $submission->id]));

        $drop->delete();
        $results = $manager->analyse($submission, $rules);

        $this->assertCount(1, $results);
        $this->assertEquals(1, $DB->count_records('pagecheck_files', ['submissionid' => $submission->id]));
        $this->assertEquals(4, $DB->get_field(
            'pagecheck_submissions',
            'totalpages',
            ['id' => $submission->id]
        ));
    }
}
