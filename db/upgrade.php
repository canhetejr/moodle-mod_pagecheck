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
 * Upgrade steps for mod_pagecheck.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute the mod_pagecheck upgrade steps from the given old version.
 *
 * @param int $oldversion the currently installed version
 * @return bool
 */
function xmldb_pagecheck_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026090400) {

        // Five further restrictions a teacher can set, and the paper size we detect per file.
        $table = new xmldb_table('pagecheck');

        $fields = [
            new xmldb_field('pagesize', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'any', 'strictness'),
            new xmldb_field('countmode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'total', 'pagesize'),
            new xmldb_field('filenamepattern', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '', 'countmode'),
            new xmldb_field('rejectduplicates', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'filenamepattern'),
            new xmldb_field('minfiles', XMLDB_TYPE_INTEGER, '5', null, XMLDB_NOTNULL, null, '0', 'rejectduplicates'),
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        $table = new xmldb_table('pagecheck_files');
        $field = new xmldb_field('pagesize', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'countmethod');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026090400, 'pagecheck');
    }

    return true;
}
