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
 * Grade one submission.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/pagecheck/lib.php');
require_once($CFG->libdir . '/formslib.php');

use mod_pagecheck\form\grade_form;
use mod_pagecheck\local\grader;
use mod_pagecheck\local\report;
use mod_pagecheck\local\submission_manager;

$id = required_param('id', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$filter = optional_param('filter', report::FILTER_ALL, PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'pagecheck');
$pagecheck = $DB->get_record('pagecheck', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('mod/pagecheck:grade', $context);

$student = \core_user::get_user($userid, '*', MUST_EXIST);
$manager = new submission_manager($cm, $pagecheck, $context);
$grader = new grader($pagecheck, $context);
$renderer = $PAGE->get_renderer('mod_pagecheck');

$listurl = new moodle_url('/mod/pagecheck/submissions.php', [
    'id' => $cm->id,
    'filter' => $filter,
    'page' => $page,
]);
$pageurl = new moodle_url('/mod/pagecheck/grade.php', [
    'id' => $cm->id,
    'userid' => $userid,
    'filter' => $filter,
    'page' => $page,
]);

$PAGE->set_url($pageurl);
$PAGE->set_title(format_string($pagecheck->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Walking to the next student follows the order of the list the teacher came from.
$currentgroup = groups_get_activity_group($cm, true);
$participants = report::get_participants($context, (int) $pagecheck->id, (int) $currentgroup);
$neighbours = grader::get_neighbours($participants, $userid);

$editoroptions = [
    'subdirs' => 0,
    'maxfiles' => 0,
    'context' => $context,
];

$record = $grader->get_grade($userid);
$form = new grade_form($pageurl, [
    'id' => $cm->id,
    'userid' => $userid,
    'filter' => $filter,
    'page' => $page,
    'grader' => $grader,
    'editoroptions' => $editoroptions,
    'hasnext' => $neighbours['next'] !== null,
]);

$current = ['id' => $cm->id, 'userid' => $userid, 'filter' => $filter, 'page' => $page];
if ($record) {
    if ($grader->is_graded()) {
        if ($grader->uses_scale()) {
            $current['grade'] = $record->grade === null ? -1 : (int) round((float) $record->grade);
        } else {
            $current['grade'] = $record->grade === null ? '' : format_float((float) $record->grade, 2);
        }
    }
    $current['feedback'] = [
        'text' => (string) $record->feedback,
        'format' => (int) $record->feedbackformat,
    ];
}
$form->set_data($current);

if ($form->is_cancelled()) {
    redirect($listurl);
}

if ($data = $form->get_data()) {
    $grade = null;
    if ($grader->is_graded() && isset($data->grade)) {
        if (!$grader->uses_scale() || (int) $data->grade !== -1) {
            [$valid, $grade] = $grader->parse_grade($data->grade);
            if (!$valid) {
                $grade = null;
            }
        }
    }

    $feedback = isset($data->feedback['text']) ? (string) $data->feedback['text'] : '';
    $format = isset($data->feedback['format']) ? (int) $data->feedback['format'] : FORMAT_HTML;

    $grader->save($userid, $grade, $feedback, $format, $USER->id);

    $next = !empty($data->savenext) && $neighbours['next'] !== null;
    if ($next) {
        redirect(new moodle_url('/mod/pagecheck/grade.php', [
            'id' => $cm->id,
            'userid' => $neighbours['next'],
            'filter' => $filter,
            'page' => $page,
        ]));
    }

    redirect(
        $listurl,
        get_string('gradesaved', 'mod_pagecheck', fullname($student)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$rules = $manager->get_rules($userid);
$submission = $manager->get_submission($userid);
$results = $submission ? $manager->analyse($submission, $rules) : [];
$issues = $submission ? $manager->validate($userid, $submission) : [];

echo $OUTPUT->header();
echo $OUTPUT->heading(fullname($student));

// Where this student sits in the class, and the way to their neighbours.
echo $renderer->grading_navigation($cm, $neighbours, $filter, $page);

$graded = $record && ($record->grade !== null || trim((string) $record->feedback) !== '');

echo $renderer->issue_list($issues);
echo $renderer->submission_status($manager, $submission, $rules, $results, $issues, $graded);
echo $renderer->attempt_history($manager, $userid);

echo $OUTPUT->heading(get_string('gradeheading', 'mod_pagecheck'), 3);
if ($record && $record->timemodified) {
    $lastgrader = $record->grader ? \core_user::get_user($record->grader) : null;
    echo html_writer::div(
        get_string('gradedon', 'mod_pagecheck', (object) [
            'date' => userdate($record->timemodified),
            'grader' => $lastgrader ? fullname($lastgrader) : '-',
        ]),
        'pagecheck-gradedon text-muted'
    );
}
$form->display();

echo $OUTPUT->footer();
