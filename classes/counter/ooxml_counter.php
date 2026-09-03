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
 * Page counter for Office Open XML documents.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\counter;

/**
 * Reads the page count of a .docx and the slide count of a .pptx.
 *
 * Word and PowerPoint record these numbers in docProps/app.xml when they save the file, so this
 * counter reads a stored value rather than laying the document out. Two consequences the caller
 * has to live with, and which the activity settings account for through the "unknown page count"
 * policy:
 *
 * - A file written by a tool that does not fill in docProps/app.xml (many converters, and Google
 *   Docs exports) carries no count at all, and this counter reports null rather than a guess.
 * - A count that is present was correct when the file was last saved by Word. It can drift if the
 *   document was edited afterwards by something else.
 *
 * A PDF is the only format where the page count can be established from the file itself, which is
 * why the activity can be configured to accept PDF only.
 */
class ooxml_counter implements counter_interface {
    /** @var string Where Word and PowerPoint keep the document statistics. */
    const PROPERTIES_PATH = 'docProps/app.xml';

    /** @var array Supported mime types mapped to the XML element holding the count. */
    const SUPPORTED = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Pages',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'Slides',
    ];

    /** @var array Supported extensions mapped to the XML element holding the count. */
    const EXTENSIONS = [
        'docx' => 'Pages',
        'pptx' => 'Slides',
    ];

    /**
     * Whether this counter can handle the given file.
     *
     * @param string $mimetype mime type of the file
     * @param string $extension lower case extension, without the leading dot
     * @return bool
     */
    public function supports(string $mimetype, string $extension): bool {
        return isset(self::SUPPORTED[$mimetype]) || isset(self::EXTENSIONS[$extension]);
    }

    /**
     * Read the recorded page or slide count.
     *
     * @param string $path absolute path to a readable local file
     * @param array $options 'extension' of the original file name, used to pick the XML element
     * @return count_result
     */
    public function count(string $path, array $options = []): count_result {
        $result = new count_result();

        $extension = isset($options['extension']) ? strtolower($options['extension']) : '';
        $element = isset(self::EXTENSIONS[$extension]) ? self::EXTENSIONS[$extension] : 'Pages';

        if ($this->is_encrypted_office_file($path)) {
            $result->encrypted = true;
            $result->error = 'errorencrypted';
            return $result;
        }

        if (!class_exists('ZipArchive')) {
            return count_result::failure('errornozip');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return count_result::failure('errorunreadableooxml');
        }

        try {
            $xml = $zip->getFromName(self::PROPERTIES_PATH);
        } catch (\Throwable $e) {
            $xml = false;
        } finally {
            $zip->close();
        }

        if ($xml === false || $xml === '') {
            // No document properties part: the count is genuinely unknown, not zero.
            $result->method = count_result::METHOD_UNKNOWN;
            return $result;
        }

        if (preg_match('/<' . $element . '>\s*(\d+)\s*<\/' . $element . '>/', $xml, $matches)) {
            $result->pages = (int) $matches[1];
            $result->method = count_result::METHOD_OOXML;
        }

        return $result;
    }

    /**
     * Whether the file is a password protected Office document.
     *
     * Office encrypts by wrapping the document in an OLE compound file, which starts with a fixed
     * signature and is not a ZIP archive at all.
     *
     * @param string $path absolute path to the file
     * @return bool
     */
    protected function is_encrypted_office_file(string $path): bool {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $signature = @fread($handle, 8);
        fclose($handle);
        return $signature === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
    }
}
