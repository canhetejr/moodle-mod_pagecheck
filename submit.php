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
 * Confirm and finalise a submission.
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
$confirm = optional_param('confirm', 0, PARAM_BOOL);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'pagecheck');
$pagecheck = $DB->get_record('pagecheck', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('mod/pagecheck:submit', $context);

$manager = new submission_manager($cm, $pagecheck, $context);
$renderer = $PAGE->get_renderer('mod_pagecheck');
$viewurl = new moodle_url('/mod/pagecheck/view.php', ['id' => $cm->id]);

$submission = $manager->get_submission($USER->id);
if (!$submission || !$manager->get_files($submission)) {
    redirect($viewurl, get_string('errornothingtosubmit', 'mod_pagecheck'), null,
        \core\output\notification::NOTIFY_ERROR);
}
if ($submission->status === submission_manager::STATUS_SUBMITTED) {
    redirect($viewurl, get_string('alreadysubmitted', 'mod_pagecheck'), null,
        \core\output\notification::NOTIFY_INFO);
}

// This is the check that counts. Whatever the browser decided, and whatever the student may have
// changed in between, the files are counted again here before the attempt is accepted.
$rules = $manager->get_rules($USER->id);
$issues = $manager->validate($USER->id, $submission, ['forsubmission' => true]);
$blocked = issue::has_errors($issues) && !has_capability('mod/pagecheck:submitwithissues', $context);

$PAGE->set_url('/mod/pagecheck/submit.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($pagecheck->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($blocked) {
    $event = \mod_pagecheck\event\submission_rejected::create([
        'objectid' => $submission->id,
        'context' => $context,
        'other' => ['issues' => array_map(function(issue $issue) {
            return $issue->code;
        }, $issues)],
    ]);
    $event->trigger();

    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($pagecheck->name));
    echo $OUTPUT->notification(get_string('submissionrefused', 'mod_pagecheck'),
        \core\output\notification::NOTIFY_ERROR);
    echo $renderer->issue_list($issues);
    echo $OUTPUT->single_button(
        new moodle_url('/mod/pagecheck/edit.php', ['id' => $cm->id]),
        get_string('editsubmission', 'mod_pagecheck'),
        'get'
    );
    echo $OUTPUT->footer();
    exit;
}

if ($confirm) {
    require_sesskey();

    $manager->submit_for_grading($submission);

    $event = \mod_pagecheck\event\submission_submitted::create([
        'objectid' => $submission->id,
        'context' => $context,
        'other' => ['totalpages' => $submission->totalpages],
    ]);
    $event->trigger();

    $completion = new completion_info($course);
    if ($completion->is_enabled($cm) && !empty($pagecheck->completionsubmit)) {
        $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
    }

    redirect($viewurl, get_string('submissionsent', 'mod_pagecheck'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($pagecheck->name));

if ($issues) {
    // Nothing here blocks the submission, but the student should still see it before confirming.
    echo $renderer->issue_list($issues);
}

echo $renderer->submission_status($manager, $submission, $rules,
    $manager->analyse($submission, $rules), $issues);

$confirmurl = new moodle_url('/mod/pagecheck/submit.php', [
    'id' => $cm->id,
    'confirm' => 1,
    'sesskey' => sesskey(),
]);
echo $OUTPUT->confirm(get_string('confirmsubmission', 'mod_pagecheck'), $confirmurl, $viewurl);
echo $OUTPUT->footer();
