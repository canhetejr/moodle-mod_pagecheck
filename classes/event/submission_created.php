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
 * The submission created event.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Fired when a student saves files for the first time in an attempt.
 */
class submission_created extends \core\event\base {

    /**
     * Describe the event.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'pagecheck_submissions';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * The name shown in the log report.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsubmissioncreated', 'mod_pagecheck');
    }

    /**
     * The description shown in the log report.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' saved files in the submission with id " .
            "'{$this->objectid}' of the pagecheck activity with course module id '{$this->contextinstanceid}'.";
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
