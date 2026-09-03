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
 * Start a further attempt.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/pagecheck/lib.php');

use mod_pagecheck\local\submission_manager;

$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'pagecheck');
$pagecheck = $DB->get_record('pagecheck', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('mod/pagecheck:submit', $context);

$manager = new submission_manager($cm, $pagecheck, $context);
$viewurl = new moodle_url('/mod/pagecheck/view.php', ['id' => $cm->id]);

if (!$manager->can_start_new_attempt($USER->id)) {
    redirect(
        $viewurl,
        get_string('errornonewattempt', 'mod_pagecheck'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$PAGE->set_url('/mod/pagecheck/newattempt.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($pagecheck->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($confirm) {
    require_sesskey();

    $manager->add_new_attempt($USER->id);

    redirect(new moodle_url('/mod/pagecheck/edit.php', ['id' => $cm->id]));
}

$confirmurl = new moodle_url('/mod/pagecheck/newattempt.php', [
    'id' => $cm->id,
    'confirm' => 1,
    'sesskey' => sesskey(),
]);

echo $OUTPUT->header();
if (!$PAGE->activityheader->is_title_allowed()) {
    echo $OUTPUT->heading(format_string($pagecheck->name));
}
echo $OUTPUT->confirm(get_string('confirmnewattempt', 'mod_pagecheck'), $confirmurl, $viewurl);
echo $OUTPUT->footer();
