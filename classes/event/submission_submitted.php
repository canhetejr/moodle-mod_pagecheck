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
 * The submission sent for grading event.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\event;

/**
 * Fired when a student sends an attempt for grading.
 */
class submission_submitted extends \core\event\base {
    /**
     * Describe the event.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'pagecheck_submissions';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * The name shown in the log report.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsubmissionsubmitted', 'mod_pagecheck');
    }

    /**
     * The description shown in the log report.
     *
     * @return string
     */
    public function get_description() {
        $pages = isset($this->other['totalpages']) ? $this->other['totalpages'] : 'unknown';
        return "The user with id '{$this->userid}' submitted the attempt with id '{$this->objectid}' " .
            "totalling '{$pages}' pages in the pagecheck activity with course module id " .
            "'{$this->contextinstanceid}'.";
    }

    /**
     * Where the log report links to.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/pagecheck/view.php', ['id' => $this->contextinstanceid]);
    }
}
