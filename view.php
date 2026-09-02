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
 * The activity page.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/pagecheck/lib.php');

use mod_pagecheck\local\issue;
use mod_pagecheck\local\submission_manager;

$id = required_param('id', PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'pagecheck');
$pagecheck = $DB->get_record('pagecheck', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/pagecheck:view', $context);

$PAGE->set_url('/mod/pagecheck/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($pagecheck->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_activity_record($pagecheck);

$event = \mod_pagecheck\event\course_module_viewed::create([
    'objectid' => $pagecheck->id,
    'context' => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('pagecheck', $pagecheck);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$manager = new submission_manager($cm, $pagecheck, $context);
$renderer = $PAGE->get_renderer('mod_pagecheck');

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($pagecheck->name));

if (!empty($pagecheck->intro)) {
    echo $OUTPUT->box(format_module_intro('pagecheck', $pagecheck, $cm->id), 'generalbox', 'intro');
}

if (has_capability('mod/pagecheck:viewallsubmissions', $context)) {
    // Teacher view: a summary and the way into the submissions report.
    $submitted = $DB->count_records_select('pagecheck_submissions',
        'pagecheckid = :pagecheckid AND latest = 1 AND timesubmitted > 0',
        ['pagecheckid' => $pagecheck->id]);
    echo $OUTPUT->box(get_string('summarysubmitted', 'mod_pagecheck', (object) [
        'submitted' => $submitted,
        'participants' => count(\mod_pagecheck\local\report::get_participants($context,
            (int) $pagecheck->id)),
    ]));
    echo $OUTPUT->single_button(
        new moodle_url('/mod/pagecheck/submissions.php', ['id' => $cm->id]),
        get_string('viewsubmissions', 'mod_pagecheck'),
        'get'
    );
}

if (has_capability('mod/pagecheck:submit', $context)) {
    $rules = $manager->get_rules($USER->id);
    $submission = $manager->get_submission($USER->id);
    $results = $submission ? $manager->analyse($submission, $rules) : [];

    echo $renderer->submission_status($manager, $submission, $rules, $results);

    if ($submission) {
        $issues = $manager->validate($USER->id, $submission);
        echo $renderer->issue_list($issues);
    } else {
        $issues = [];
    }

    $canedit = $manager->can_edit($USER->id, $submission);
    $hasfiles = $submission && $manager->get_files($submission);

    if ($canedit) {
        echo $OUTPUT->single_button(
            new moodle_url('/mod/pagecheck/edit.php', ['id' => $cm->id]),
            get_string($hasfiles ? 'editsubmission' : 'addsubmission', 'mod_pagecheck'),
            'get'
        );
    }

    $blocked = issue::has_errors($issues) && !has_capability('mod/pagecheck:submitwithissues', $context);
    if ($canedit && $hasfiles && !$blocked
            && $submission->status !== submission_manager::STATUS_SUBMITTED) {
        echo $OUTPUT->single_button(
            new moodle_url('/mod/pagecheck/submit.php', ['id' => $cm->id]),
            get_string('submitforgrading', 'mod_pagecheck'),
            'get'
        );
    }

    if (!$canedit && $submission && $submission->status === submission_manager::STATUS_SUBMITTED) {
        echo $OUTPUT->notification(get_string('alreadysubmitted', 'mod_pagecheck'),
            \core\output\notification::NOTIFY_INFO);

        if ($manager->can_start_new_attempt($USER->id, $submission)) {
            echo $OUTPUT->single_button(
                new moodle_url('/mod/pagecheck/newattempt.php', ['id' => $cm->id]),
                get_string('newattempt', 'mod_pagecheck'),
                'get'
            );
        }
    }
}

echo $OUTPUT->footer();
