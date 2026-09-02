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
 * Restore steps for mod_pagecheck.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Reads back what backup_pagecheck_activity_structure_step wrote.
 */
class restore_pagecheck_activity_structure_step extends restore_activity_structure_step {

    /**
     * The elements this step knows how to restore.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('pagecheck', '/activity/pagecheck');
        $paths[] = new restore_path_element('pagecheck_override',
            '/activity/pagecheck/overrides/override');

        if ($userinfo) {
            $paths[] = new restore_path_element('pagecheck_submission',
                '/activity/pagecheck/submissions/submission');
            $paths[] = new restore_path_element('pagecheck_countedfile',
                '/activity/pagecheck/submissions/submission/countedfiles/countedfile');
            $paths[] = new restore_path_element('pagecheck_grade',
                '/activity/pagecheck/grades/grade');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the activity instance.
     *
     * @param array $data the element data
     * @return void
     */
    protected function process_pagecheck($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        foreach (['allowsubmissionsfromdate', 'duedate', 'cutoffdate'] as $field) {
            $data->{$field} = $this->apply_date_offset($data->{$field});
        }

        $newitemid = $DB->insert_record('pagecheck', $data);
        $this->apply_activity_instance($newitemid);
        $this->set_mapping('pagecheck', $oldid, $newitemid);
    }

    /**
     * Restore one override.
     *
     * @param array $data the element data
     * @return void
     */
    protected function process_pagecheck_override($data) {
        global $DB;

        $data = (object) $data;
        $data->pagecheckid = $this->get_new_parentid('pagecheck');

        if ($data->groupid) {
            $data->groupid = (int) $this->get_mappingid('group', $data->groupid);
            if (!$data->groupid) {
                // The group did not come along, so the override has nothing to apply to.
                return;
            }
        }
        if ($data->userid) {
            $data->userid = (int) $this->get_mappingid('user', $data->userid);
            if (!$data->userid) {
                return;
            }
        }

        foreach (['allowsubmissionsfromdate', 'duedate', 'cutoffdate'] as $field) {
            if ($data->{$field} !== null) {
                $data->{$field} = $this->apply_date_offset($data->{$field});
            }
        }

        $DB->insert_record('pagecheck_overrides', $data);
    }

    /**
     * Restore one attempt.
     *
     * @param array $data the element data
     * @return void
     */
    protected function process_pagecheck_submission($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->pagecheckid = $this->get_new_parentid('pagecheck');
        $data->userid = (int) $this->get_mappingid('user', $data->userid);
        $data->groupid = $data->groupid ? (int) $this->get_mappingid('group', $data->groupid) : 0;

        $newitemid = $DB->insert_record('pagecheck_submissions', $data);
        $this->set_mapping('pagecheck_submission', $oldid, $newitemid, true);
    }

    /**
     * Restore the cached counting result of one file.
     *
     * @param array $data the element data
     * @return void
     */
    protected function process_pagecheck_countedfile($data) {
        global $DB;

        $data = (object) $data;
        $data->submissionid = $this->get_new_parentid('pagecheck_submission');

        // The path name hash is derived from the context, which changes on restore. Clearing it
        // makes the next page load recount the file rather than trust a hash that no longer
        // points anywhere.
        $data->pathnamehash = '';

        $DB->insert_record('pagecheck_files', $data);
    }

    /**
     * Restore one grade.
     *
     * @param array $data the element data
     * @return void
     */
    protected function process_pagecheck_grade($data) {
        global $DB;

        $data = (object) $data;
        $data->pagecheckid = $this->get_new_parentid('pagecheck');
        $data->userid = (int) $this->get_mappingid('user', $data->userid);
        $data->grader = $data->grader ? (int) $this->get_mappingid('user', $data->grader) : 0;

        $DB->insert_record('pagecheck_grades', $data);
    }

    /**
     * Bring the files back once every record exists.
     *
     * @return void
     */
    protected function after_execute() {
        $this->add_related_files('mod_pagecheck', 'intro', null);
        $this->add_related_files('mod_pagecheck',
            \mod_pagecheck\local\submission_manager::FILEAREA, 'pagecheck_submission');
    }
}
