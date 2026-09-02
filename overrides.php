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
 * Manage the overrides of an activity.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/pagecheck/lib.php');
require_once($CFG->libdir . '/formslib.php');

use mod_pagecheck\form\override_form;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', 'list', PARAM_ALPHA);
$mode = optional_param('mode', 'group', PARAM_ALPHA);
$overrideid = optional_param('overrideid', 0, PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'pagecheck');
$pagecheck = $DB->get_record('pagecheck', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('mod/pagecheck:manageoverrides', $context);

$baseurl = new moodle_url('/mod/pagecheck/overrides.php', ['id' => $cm->id]);
$PAGE->set_url($baseurl);
$PAGE->set_title(format_string($pagecheck->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($action === 'delete') {
    require_sesskey();
    $DB->delete_records('pagecheck_overrides', ['id' => $overrideid, 'pagecheckid' => $pagecheck->id]);
    redirect($baseurl, get_string('overridedeleted', 'mod_pagecheck'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'add' || $action === 'edit') {
    if ($mode === 'group') {
        $groups = groups_get_all_groups($course->id, 0, $cm->groupingid, 'g.id, g.name');
        $targets = [];
        foreach ($groups as $group) {
            $targets[$group->id] = format_string($group->name, true, ['context' => $context]);
        }
    } else {
        $users = get_enrolled_users($context, 'mod/pagecheck:submit', 0,
            \mod_pagecheck\local\report::user_fields_sql(), 'u.lastname, u.firstname');
        $targets = [];
        foreach ($users as $user) {
            $targets[$user->id] = fullname($user);
        }
    }

    if (!$targets) {
        redirect($baseurl, get_string('errornotargets', 'mod_pagecheck'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    $form = new override_form(null, [
        'cmid' => $cm->id,
        'mode' => $mode,
        'overrideid' => $overrideid,
        'targets' => $targets,
    ]);

    if ($action === 'edit' && $overrideid) {
        $override = $DB->get_record('pagecheck_overrides',
            ['id' => $overrideid, 'pagecheckid' => $pagecheck->id], '*', MUST_EXIST);
        $data = [
            'target' => $mode === 'group' ? $override->groupid : $override->userid,
            'allowsubmissionsfromdate' => (int) $override->allowsubmissionsfromdate,
            'duedate' => (int) $override->duedate,
            'cutoffdate' => (int) $override->cutoffdate,
        ];
        foreach (['maxattempts', 'minpages', 'maxpages'] as $field) {
            $data['override' . $field] = $override->{$field} === null ? 0 : 1;
            $data[$field] = $override->{$field} === null ? 0 : (int) $override->{$field};
        }
        $form->set_data($data);
    }

    if ($form->is_cancelled()) {
        redirect($baseurl);
    }

    if ($data = $form->get_data()) {
        $record = (object) [
            'pagecheckid' => $pagecheck->id,
            'groupid' => $mode === 'group' ? (int) $data->target : 0,
            'userid' => $mode === 'user' ? (int) $data->target : 0,
            'sortorder' => 0,
            // A date selector that is switched off returns zero, which means "inherit" here.
            'allowsubmissionsfromdate' => empty($data->allowsubmissionsfromdate)
                ? null : (int) $data->allowsubmissionsfromdate,
            'duedate' => empty($data->duedate) ? null : (int) $data->duedate,
            'cutoffdate' => empty($data->cutoffdate) ? null : (int) $data->cutoffdate,
        ];
        foreach (['maxattempts', 'minpages', 'maxpages'] as $field) {
            $enabled = !empty($data->{'override' . $field});
            $record->{$field} = $enabled ? (int) $data->{$field} : null;
        }

        if ($overrideid) {
            $record->id = $overrideid;
            $DB->update_record('pagecheck_overrides', $record);
        } else {
            $DB->insert_record('pagecheck_overrides', $record);
        }

        redirect($baseurl, get_string('overridesaved', 'mod_pagecheck'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('overrides', 'mod_pagecheck'));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

$overrides = $DB->get_records('pagecheck_overrides', ['pagecheckid' => $pagecheck->id],
    'sortorder ASC, id ASC');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('overrides', 'mod_pagecheck'));

if (!$overrides) {
    echo $OUTPUT->notification(get_string('nooverrides', 'mod_pagecheck'),
        \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->head = [
        get_string('overridefor', 'mod_pagecheck'),
        get_string('duedate', 'mod_pagecheck'),
        get_string('cutoffdate', 'mod_pagecheck'),
        get_string('maxattempts', 'mod_pagecheck'),
        get_string('pages', 'mod_pagecheck'),
        get_string('actions'),
    ];
    $table->attributes['class'] = 'generaltable table';

    foreach ($overrides as $override) {
        if ($override->groupid) {
            $group = groups_get_group($override->groupid);
            $target = $group
                ? get_string('overridegrouplabel', 'mod_pagecheck',
                    format_string($group->name, true, ['context' => $context]))
                : get_string('overridemissingtarget', 'mod_pagecheck');
            $editmode = 'group';
        } else {
            $user = \core_user::get_user($override->userid);
            $target = $user
                ? get_string('overrideuserlabel', 'mod_pagecheck', fullname($user))
                : get_string('overridemissingtarget', 'mod_pagecheck');
            $editmode = 'user';
        }

        if ($override->minpages !== null && $override->maxpages !== null) {
            $pages = get_string('pagesbetween', 'mod_pagecheck', (object) [
                'min' => $override->minpages,
                'max' => $override->maxpages,
            ]);
        } else if ($override->minpages !== null) {
            $pages = get_string('pagesatleast', 'mod_pagecheck', $override->minpages);
        } else if ($override->maxpages !== null) {
            $pages = get_string('pagesatmost', 'mod_pagecheck', $override->maxpages);
        } else {
            $pages = '-';
        }

        $editurl = new moodle_url($baseurl, [
            'action' => 'edit',
            'mode' => $editmode,
            'overrideid' => $override->id,
        ]);
        $deleteurl = new moodle_url($baseurl, [
            'action' => 'delete',
            'overrideid' => $override->id,
            'sesskey' => sesskey(),
        ]);

        $table->data[] = [
            $target,
            $override->duedate === null ? '-' : userdate($override->duedate),
            $override->cutoffdate === null ? '-' : userdate($override->cutoffdate),
            $override->maxattempts === null ? '-' :
                ($override->maxattempts < 0
                    ? get_string('unlimitedattempts', 'mod_pagecheck')
                    : $override->maxattempts),
            $pages,
            html_writer::link($editurl, get_string('edit')) . ' ' .
                html_writer::link($deleteurl, get_string('delete')),
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->single_button(
    new moodle_url($baseurl, ['action' => 'add', 'mode' => 'group']),
    get_string('addgroupoverride', 'mod_pagecheck'),
    'get'
);
echo $OUTPUT->single_button(
    new moodle_url($baseurl, ['action' => 'add', 'mode' => 'user']),
    get_string('adduseroverride', 'mod_pagecheck'),
    'get'
);

echo $OUTPUT->footer();
