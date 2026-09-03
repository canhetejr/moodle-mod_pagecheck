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
 * Backup steps for mod_pagecheck.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines what a backup of one pagecheck activity contains.
 */
class backup_pagecheck_activity_structure_step extends backup_activity_structure_step {
    /**
     * Build the backup structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('userinfo');

        $pagecheck = new backup_nested_element('pagecheck', ['id'], [
            'name', 'intro', 'introformat', 'timecreated', 'timemodified',
            'allowsubmissionsfromdate', 'duedate', 'cutoffdate', 'blockafterdue',
            'maxattempts', 'requiresubmissionstatement', 'teamsubmission', 'groupingid',
            'minpages', 'maxpages', 'countcover', 'allowedextensions', 'maxbytes', 'maxfiles',
            'rejectencrypted', 'requiretextlayer', 'rejectblankpages', 'blankpagetolerance',
            'unknownpolicy', 'strictness', 'grade', 'completionsubmit',
        ]);

        $overrides = new backup_nested_element('overrides');
        $override = new backup_nested_element('override', ['id'], [
            'groupid', 'userid', 'sortorder', 'allowsubmissionsfromdate', 'duedate',
            'cutoffdate', 'maxattempts', 'minpages', 'maxpages',
        ]);

        $submissions = new backup_nested_element('submissions');
        $submission = new backup_nested_element('submission', ['id'], [
            'userid', 'groupid', 'attemptnumber', 'status', 'totalpages', 'latest',
            'timecreated', 'timemodified', 'timesubmitted',
        ]);

        $files = new backup_nested_element('countedfiles');
        $file = new backup_nested_element('countedfile', ['id'], [
            'pathnamehash', 'contenthash', 'filename', 'mimetype', 'filesize', 'pagecount',
            'countmethod', 'hastext', 'blankpages', 'encrypted', 'status', 'issues',
            'timemodified',
        ]);

        $grades = new backup_nested_element('grades');
        $grade = new backup_nested_element('grade', ['id'], [
            'userid', 'grade', 'grader', 'feedback', 'feedbackformat', 'timemodified',
        ]);

        $pagecheck->add_child($overrides);
        $overrides->add_child($override);
        $pagecheck->add_child($submissions);
        $submissions->add_child($submission);
        $submission->add_child($files);
        $files->add_child($file);
        $pagecheck->add_child($grades);
        $grades->add_child($grade);

        $pagecheck->set_source_table('pagecheck', ['id' => backup::VAR_ACTIVITYID]);

        if ($userinfo) {
            $override->set_source_table('pagecheck_overrides', ['pagecheckid' => backup::VAR_PARENTID]);
            $submission->set_source_table('pagecheck_submissions', ['pagecheckid' => backup::VAR_PARENTID]);
            $file->set_source_table('pagecheck_files', ['submissionid' => backup::VAR_PARENTID]);
            $grade->set_source_table('pagecheck_grades', ['pagecheckid' => backup::VAR_PARENTID]);
        } else {
            // Overrides that apply to a whole group survive a backup without user data.
            $override->set_source_sql(
                'SELECT * FROM {pagecheck_overrides} WHERE pagecheckid = ? AND userid = 0',
                [backup::VAR_PARENTID]
            );
        }

        $override->annotate_ids('user', 'userid');
        $override->annotate_ids('group', 'groupid');
        $submission->annotate_ids('user', 'userid');
        $submission->annotate_ids('group', 'groupid');
        $grade->annotate_ids('user', 'userid');
        $grade->annotate_ids('user', 'grader');

        $pagecheck->annotate_files('mod_pagecheck', 'intro', null);
        $submission->annotate_files(
            'mod_pagecheck',
            \mod_pagecheck\local\submission_manager::FILEAREA,
            'id'
        );

        return $this->prepare_activity_structure($pagecheck);
    }
}
