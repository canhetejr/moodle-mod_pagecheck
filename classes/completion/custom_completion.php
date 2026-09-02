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
 * Custom completion rules for mod_pagecheck.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\completion;

use core_completion\activity_custom_completion;

defined('MOODLE_INTERNAL') || die();

/**
 * Reports whether the student has sent an attempt for grading.
 */
class custom_completion extends activity_custom_completion {

    /**
     * Whether a given rule is complete for the current user.
     *
     * @param string $rule the rule name
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $submitted = $DB->record_exists_select(
            'pagecheck_submissions',
            'pagecheckid = :pagecheckid AND userid = :userid AND timesubmitted > 0',
            ['pagecheckid' => $this->cm->instance, 'userid' => $this->userid]
        );

        return $submitted ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * The rules this activity defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionsubmit'];
    }

    /**
     * How each rule is described in the activity settings and on the course page.
     *
     * @return array rule name => description
     */
    public function get_custom_rule_descriptions(): array {
        return ['completionsubmit' => get_string('completionsubmit_desc', 'mod_pagecheck')];
    }

    /**
     * The order the completion conditions are shown in.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return ['completionview', 'completionsubmit', 'completionusegrade'];
    }
}
