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
 * Result of counting the pages of a single submitted file.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\counter;

/**
 * Value object describing what we managed to learn about a file.
 *
 * Every "unknown" is represented by null rather than by a zero, so that callers can tell
 * "this file has no pages" apart from "we could not work out how many pages this file has".
 */
class count_result {
    /** @var string The page count could not be determined at all. */
    const METHOD_UNKNOWN = 'unknown';

    /** @var string Counted with the FPDI parser bundled with Moodle. */
    const METHOD_FPDI = 'fpdi';

    /** @var string Counted by scanning the raw PDF structure. */
    const METHOD_RAW = 'raw';

    /** @var string Counted with Ghostscript. */
    const METHOD_GS = 'gs';

    /** @var string Read from the Office Open XML document properties. */
    const METHOD_OOXML = 'ooxml';

    /** @var string One page per image file. */
    const METHOD_IMAGE = 'image';

    /** @var int|null Number of pages, or null when it could not be determined. */
    public $pages = null;

    /** @var string How the page count was obtained. One of the METHOD_* constants. */
    public $method = self::METHOD_UNKNOWN;

    /** @var bool Whether the file is encrypted or password protected. */
    public $encrypted = false;

    /** @var bool|null Whether the file carries a text layer, or null when not analysed. */
    public $hastext = null;

    /** @var int|null Number of blank pages, or null when not analysed. */
    public $blankpages = null;

    /** @var string|null Paper size of the document, a page_size constant, or null when unknown. */
    public $pagesize = null;

    /** @var string Content hash of the file, used to spot the same file attached twice. */
    public $contenthash = '';

    /** @var string|null Error code (a language string key) when the file could not be read. */
    public $error = null;

    /** @var string Name of the file this result describes. */
    public $filename = '';

    /** @var int Size of the file in bytes. */
    public $filesize = 0;

    /** @var string Mime type of the file. */
    public $mimetype = '';

    /**
     * Build a result representing a file we could not read at all.
     *
     * @param string $error error code, a language string key in mod_pagecheck
     * @return count_result
     */
    public static function failure(string $error): count_result {
        $result = new self();
        $result->error = $error;
        return $result;
    }

    /**
     * Whether a usable page count is available.
     *
     * @return bool
     */
    public function has_page_count(): bool {
        return $this->pages !== null;
    }
}
