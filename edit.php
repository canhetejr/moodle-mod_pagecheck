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
 * Attach or replace the files of an attempt.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/pagecheck/lib.php');
require_once($CFG->libdir . '/formslib.php');

use mod_pagecheck\form\edit_form;
use mod_pagecheck\local\submission_manager;

$id = required_param('id', PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'pagecheck');
$pagecheck = $DB->get_record('pagecheck', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('mod/pagecheck:submit', $context);

$manager = new submission_manager($cm, $pagecheck, $context);
$rules = $manager->get_rules($USER->id);
$viewurl = new moodle_url('/mod/pagecheck/view.php', ['id' => $cm->id]);

if (!$manager->can_edit($USER->id)) {
    redirect($viewurl, get_string('errorcannotedit', 'mod_pagecheck'), null,
        \core\output\notification::NOTIFY_ERROR);
}

$submission = $manager->get_submission($USER->id, true);
$fileoptions = $manager->get_filemanager_options($rules);

$draftitemid = file_get_submitted_draft_itemid('files');
file_prepare_draft_area($draftitemid, $context->id, 'mod_pagecheck',
    PAGECHECK_FILEAREA_SUBMISSION, $submission->id, $fileoptions);

$PAGE->set_url('/mod/pagecheck/edit.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($pagecheck->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$form = new edit_form(null, [
    'cmid' => $cm->id,
    'rules' => $rules,
    'fileoptions' => $fileoptions,
]);
$form->set_data(['files' => $draftitemid, 'id' => $cm->id]);

if ($form->is_cancelled()) {
    redirect($viewurl);
}

if ($data = $form->get_data()) {
    $manager->save_files($submission, (int) $data->files, $rules);

    $event = \mod_pagecheck\event\submission_created::create([
        'objectid' => $submission->id,
        'context' => $context,
    ]);
    $event->trigger();

    redirect($viewurl, get_string('filessaved', 'mod_pagecheck'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// The browser checks what it can before the upload finishes; the server checks everything again
// when the form is processed.
$PAGE->requires->js_call_amd('mod_pagecheck/validator', 'init', [[
    'minpages' => $rules->minpages,
    'maxpages' => $rules->maxpages,
    'countcover' => $rules->countcover,
    'allowedextensions' => $rules->allowedextensions,
    'maxbytes' => $rules->maxbytes > 0 ? $rules->maxbytes : (int) $fileoptions['maxbytes'],
    'maxfiles' => $rules->maxfiles,
    'strictness' => $rules->strictness,
    'countmode' => $rules->countmode,
    'filenamepattern' => $rules->filenamepattern,
    'pagesize' => $rules->pagesize,
    'pagesizelabels' => \mod_pagecheck\counter\page_size::get_menu()
        + ['mixed' => get_string('pagesize_mixed', 'mod_pagecheck')],
]]);

echo $OUTPUT->header();
if (!$PAGE->activityheader->is_title_allowed()) {
    echo $OUTPUT->heading(format_string($pagecheck->name));
}
$form->display();
echo $OUTPUT->footer();
