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
 * Test generator for mod_pagecheck.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Creates pagecheck activities for the tests.
 */
class mod_pagecheck_generator extends testing_module_generator {
    /**
     * Create an activity instance, filling in whatever the caller left out.
     *
     * @param array|stdClass|null $record the settings to use
     * @param array|null $options generator options
     * @return stdClass the new instance
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;

        $defaults = [
            'name' => 'Page check activity',
            'intro' => 'Attach your work.',
            'introformat' => FORMAT_HTML,
            'allowsubmissionsfromdate' => 0,
            'duedate' => 0,
            'cutoffdate' => 0,
            'blockafterdue' => 0,
            'maxattempts' => -1,
            'requiresubmissionstatement' => 0,
            'teamsubmission' => 0,
            'groupingid' => 0,
            'minpages' => 0,
            'maxpages' => 0,
            'countcover' => 0,
            'allowedextensions' => 'pdf',
            'maxbytes' => 0,
            'maxfiles' => 1,
            'rejectencrypted' => 1,
            'requiretextlayer' => 0,
            'rejectblankpages' => 0,
            'blankpagetolerance' => 0,
            'unknownpolicy' => 'warn',
            'pagesize' => 'any',
            'countmode' => 'total',
            'filenamepattern' => '',
            'rejectduplicates' => 0,
            'minfiles' => 0,
            'strictness' => 'block',
            'grade' => 100,
            'completionsubmit' => 0,
        ];

        foreach ($defaults as $field => $value) {
            if (!isset($record->{$field})) {
                $record->{$field} = $value;
            }
        }

        return parent::create_instance($record, (array) $options);
    }
}
