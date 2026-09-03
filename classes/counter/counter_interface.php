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
 * Contract shared by every page counter.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\counter;

/**
 * A page counter knows how to read one family of file formats.
 *
 * Counters work on a plain filesystem path rather than on a stored_file so that they can be
 * unit tested against fixture files without going through the Moodle file API.
 */
interface counter_interface {
    /**
     * Whether this counter can handle the given file.
     *
     * @param string $mimetype mime type of the file
     * @param string $extension lower case extension, without the leading dot
     * @return bool
     */
    public function supports(string $mimetype, string $extension): bool;

    /**
     * Count the pages of a file.
     *
     * Implementations must never throw: an unreadable file is reported through the error
     * property of the returned result. A malicious file must not be able to take the site down.
     *
     * @param string $path absolute path to a readable local file
     * @param array $options counter specific options, see each implementation
     * @return count_result
     */
    public function count(string $path, array $options = []): count_result;
}
