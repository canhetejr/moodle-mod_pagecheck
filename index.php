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
 * List every pagecheck activity of a course.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_login($course);

$context = context_course::instance($course->id);

$PAGE->set_url('/mod/pagecheck/index.php', ['id' => $course->id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_pagecheck'));

$instances = get_all_instances_in_course('pagecheck', $course);
if (!$instances) {
    notice(get_string('noinstances', 'mod_pagecheck'), new moodle_url('/course/view.php', ['id' => $course->id]));
}

$table = new html_table();
$table->head = [
    get_string('name'),
    get_string('duedate', 'mod_pagecheck'),
    get_string('pages', 'mod_pagecheck'),
];
$table->align = ['left', 'left', 'left'];

foreach ($instances as $instance) {
    $link = html_writer::link(
        new moodle_url('/mod/pagecheck/view.php', ['id' => $instance->coursemodule]),
        format_string($instance->name),
        ['class' => $instance->visible ? '' : 'dimmed']
    );

    if ($instance->minpages > 0 && $instance->maxpages > 0) {
        $pages = get_string('pagesbetween', 'mod_pagecheck', (object) [
            'min' => $instance->minpages,
            'max' => $instance->maxpages,
        ]);
    } else if ($instance->minpages > 0) {
        $pages = get_string('pagesatleast', 'mod_pagecheck', $instance->minpages);
    } else if ($instance->maxpages > 0) {
        $pages = get_string('pagesatmost', 'mod_pagecheck', $instance->maxpages);
    } else {
        $pages = get_string('pagesnolimit', 'mod_pagecheck');
    }

    $table->data[] = [
        $link,
        $instance->duedate > 0 ? userdate($instance->duedate) : '-',
        $pages,
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
