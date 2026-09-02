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
 * The submissions report a teacher works from.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/pagecheck/lib.php');

use mod_pagecheck\local\issue;
use mod_pagecheck\local\report;
use mod_pagecheck\local\submission_manager;

$id = required_param('id', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 25, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);
$filter = optional_param('filter', report::FILTER_ALL, PARAM_ALPHA);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'pagecheck');
$pagecheck = $DB->get_record('pagecheck', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('mod/pagecheck:viewallsubmissions', $context);

$manager = new submission_manager($cm, $pagecheck, $context);
$cangrade = has_capability('mod/pagecheck:grade', $context);

$baseurl = new moodle_url('/mod/pagecheck/submissions.php', [
    'id' => $cm->id,
    'perpage' => $perpage,
    'filter' => $filter,
]);

$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($pagecheck->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Everyone who can submit, restricted to the groups the teacher may see.
$groupmode = groups_get_activity_groupmode($cm, $course);
$currentgroup = groups_get_activity_group($cm, true);
$participants = report::get_participants($context, (int) $pagecheck->id, (int) $currentgroup);

$rows = report::build_rows($participants, $manager, $filter);

// Save quick grades.
if ($cangrade && optional_param('savegrades', 0, PARAM_BOOL) && confirm_sesskey()) {
    $updated = 0;
    foreach ($rows as $row) {
        $value = optional_param('grade_' . $row->user->id, null, PARAM_RAW_TRIMMED);
        if ($value === null || $value === '') {
            continue;
        }
        $grade = unformat_float($value);
        if ($grade === false || $grade === null) {
            continue;
        }
        $grade = min((float) $pagecheck->grade, max(0.0, (float) $grade));
        if ($row->grade !== null && (float) $row->grade === $grade) {
            continue;
        }

        $record = $DB->get_record('pagecheck_grades',
            ['pagecheckid' => $pagecheck->id, 'userid' => $row->user->id]);
        if ($record) {
            $record->grade = $grade;
            $record->grader = $USER->id;
            $record->timemodified = time();
            $DB->update_record('pagecheck_grades', $record);
        } else {
            $DB->insert_record('pagecheck_grades', (object) [
                'pagecheckid' => $pagecheck->id,
                'userid' => $row->user->id,
                'grade' => $grade,
                'grader' => $USER->id,
                'timemodified' => time(),
            ]);
        }
        $updated++;
    }

    if ($updated) {
        pagecheck_update_grades($pagecheck);
    }
    redirect($baseurl, get_string('gradessaved', 'mod_pagecheck', $updated), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// Export.
if ($download !== '') {
    $columns = [
        'fullname' => get_string('fullname'),
        'status' => get_string('submissionstatus', 'mod_pagecheck'),
        'pages' => get_string('totalpages', 'mod_pagecheck'),
        'issues' => get_string('issues', 'mod_pagecheck'),
        'grade' => get_string('grade'),
    ];

    $records = [];
    foreach ($rows as $row) {
        $messages = array_map(function(issue $issue) {
            return $issue->get_full_message();
        }, $row->issues);

        $records[] = [
            'fullname' => fullname($row->user),
            'status' => get_string('status_' . $row->status, 'mod_pagecheck'),
            'pages' => $row->pages === null ? '' : $row->pages,
            'issues' => implode(' | ', $messages),
            'grade' => $row->grade === null ? '' : format_float($row->grade, 2),
        ];
    }

    \core\dataformat::download_data(
        clean_filename('pagecheck-' . $pagecheck->id),
        $download,
        $columns,
        $records
    );
    exit;
}

$total = count($rows);
$pagerows = array_slice($rows, $page * $perpage, $perpage);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($pagecheck->name));

if ($groupmode != NOGROUPS) {
    groups_print_activity_menu($cm, $baseurl);
}

// single_select turns every parameter of the URL into a hidden field, so the base URL has to
// arrive without the one the select itself is named after.
$selecturl = new moodle_url($baseurl);
$selecturl->remove_params('filter');
echo $OUTPUT->single_select($selecturl, 'filter',
    report::get_filter_options(), $filter, null, 'filterform');

if (!$pagerows) {
    echo $OUTPUT->notification(get_string('nosubmissions', 'mod_pagecheck'),
        \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('fullname'),
    get_string('submissionstatus', 'mod_pagecheck'),
    get_string('totalpages', 'mod_pagecheck'),
    get_string('submittedfiles', 'mod_pagecheck'),
    get_string('issues', 'mod_pagecheck'),
    get_string('grade'),
];
$table->attributes['class'] = 'generaltable table';

foreach ($pagerows as $row) {
    $files = [];
    if ($row->submission) {
        foreach ($manager->get_files($row->submission) as $file) {
            $url = moodle_url::make_pluginfile_url(
                $context->id,
                'mod_pagecheck',
                PAGECHECK_FILEAREA_SUBMISSION,
                $row->submission->id,
                $file->get_filepath(),
                $file->get_filename(),
                true
            );
            $files[] = html_writer::link($url, $file->get_filename());
        }
    }

    $messages = [];
    foreach ($row->issues as $issue) {
        $class = $issue->is_error() ? 'text-danger' : 'text-warning';
        $messages[] = html_writer::span($issue->get_full_message(), $class);
    }

    if ($cangrade) {
        $gradecell = html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'grade_' . $row->user->id,
            'value' => $row->grade === null ? '' : format_float($row->grade, 2),
            'size' => 5,
            'class' => 'form-control d-inline-block w-auto',
            'aria-label' => get_string('gradefor', 'mod_pagecheck', fullname($row->user)),
        ]);
    } else {
        $gradecell = $row->grade === null ? '-' : format_float($row->grade, 2);
    }

    $name = html_writer::link(new moodle_url('/user/view.php',
        ['id' => $row->user->id, 'course' => $course->id]), fullname($row->user));
    if (!empty($row->user->pagechecknotenrolled)) {
        $name .= ' ' . html_writer::span(get_string('notenrolled', 'mod_pagecheck'),
            'badge bg-warning text-dark');
    }

    $table->data[] = [
        $name,
        get_string('status_' . $row->status, 'mod_pagecheck'),
        $row->pages === null ? '-' : $row->pages,
        $files ? implode(html_writer::empty_tag('br'), $files) : '-',
        $messages ? implode(html_writer::empty_tag('br'), $messages) : '-',
        $gradecell,
    ];
}

if ($cangrade) {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'savegrades', 'value' => 1]);
}

echo html_writer::table($table);
echo $OUTPUT->paging_bar($total, $page, $perpage, $baseurl);

if ($cangrade) {
    echo html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-primary',
        'value' => get_string('savegrades', 'mod_pagecheck'),
    ]);
    echo html_writer::end_tag('form');
}

echo $OUTPUT->single_button(
    new moodle_url($baseurl, ['download' => 'csv']),
    get_string('exportcsv', 'mod_pagecheck'),
    'get'
);

echo $OUTPUT->footer();
