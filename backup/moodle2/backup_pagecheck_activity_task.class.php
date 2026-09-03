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
 * Backup task for mod_pagecheck.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/pagecheck/backup/moodle2/backup_pagecheck_stepslib.php');

/**
 * Backs up one pagecheck activity.
 */
class backup_pagecheck_activity_task extends backup_activity_task {
    /**
     * This activity has no backup settings of its own.
     *
     * @return void
     */
    protected function define_my_settings() {
    }

    /**
     * Add the single step that writes the activity structure.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new backup_pagecheck_activity_structure_step('pagecheck_structure', 'pagecheck.xml'));
    }

    /**
     * Replace links to this activity with a placeholder.
     *
     * @param string $content the content to encode
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        $content = preg_replace(
            '/(' . $base . '\/mod\/pagecheck\/index.php\?id\=)([0-9]+)/',
            '$@PAGECHECKINDEX*$2@$',
            $content
        );
        $content = preg_replace(
            '/(' . $base . '\/mod\/pagecheck\/view.php\?id\=)([0-9]+)/',
            '$@PAGECHECKVIEWBYID*$2@$',
            $content
        );

        return $content;
    }
}
