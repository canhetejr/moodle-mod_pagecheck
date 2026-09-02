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
 * Site wide settings for mod_pagecheck.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_configtext(
        'mod_pagecheck/allowedextensions',
        get_string('allowedextensions', 'mod_pagecheck'),
        get_string('allowedextensions_desc', 'mod_pagecheck'),
        '.pdf',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_pagecheck/useghostscript',
        get_string('useghostscript', 'mod_pagecheck'),
        get_string('useghostscript_desc', 'mod_pagecheck'),
        0
    ));
}
