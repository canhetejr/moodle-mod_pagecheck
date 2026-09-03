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
 * Restore task for mod_pagecheck.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/pagecheck/backup/moodle2/restore_pagecheck_stepslib.php');

/**
 * Restores one pagecheck activity.
 */
class restore_pagecheck_activity_task extends restore_activity_task {
    /**
     * This activity has no restore settings of its own.
     *
     * @return void
     */
    protected function define_my_settings() {
    }

    /**
     * Add the single step that reads the activity structure.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new restore_pagecheck_activity_structure_step('pagecheck_structure', 'pagecheck.xml'));
    }

    /**
     * The file areas whose contents are decoded after the restore.
     *
     * @return array
     */
    public static function define_decode_contents() {
        return [new restore_decode_content('pagecheck', ['intro'], 'pagecheck')];
    }

    /**
     * How the placeholders written by encode_content_links are turned back into links.
     *
     * @return array
     */
    public static function define_decode_rules() {
        return [
            new restore_decode_rule('PAGECHECKVIEWBYID', '/mod/pagecheck/view.php?id=$1', 'course_module'),
            new restore_decode_rule('PAGECHECKINDEX', '/mod/pagecheck/index.php?id=$1', 'course'),
        ];
    }

    /**
     * The log entries this activity restores.
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        return [];
    }
}
