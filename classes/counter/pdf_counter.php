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
 * Page counter for PDF files.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\counter;

defined('MOODLE_INTERNAL') || die();

/**
 * Counts the pages of a PDF, from the most reliable source to the most forgiving one.
 *
 * 1. FPDI, the parser Moodle already bundles for the annotate PDF feedback plugin. It resolves
 *    cross reference streams and compressed object streams properly, so it is the primary source.
 * 2. A raw scan of the file structure, used when FPDI refuses the file. This also covers
 *    encrypted documents, whose object structure stays readable even though FPDI will not open
 *    them.
 * 3. Ghostscript, only when the administrator has enabled it and $CFG->pathtogs is configured.
 */
class pdf_counter implements counter_interface {

    /**
     * Whether this counter can handle the given file.
     *
     * @param string $mimetype mime type of the file
     * @param string $extension lower case extension, without the leading dot
     * @return bool
     */
    public function supports(string $mimetype, string $extension): bool {
        return $mimetype === 'application/pdf' || $extension === 'pdf';
    }

    /**
     * Count the pages of a PDF file.
     *
     * @param string $path absolute path to a readable local file
     * @param array $options 'analysetext' to also look for a text layer and blank pages,
     *                       'usegs' to allow the Ghostscript fallback
     * @return count_result
     */
    public function count(string $path, array $options = []): count_result {
        $result = new count_result();
        $result->mimetype = 'application/pdf';

        try {
            $parser = new pdf_parser($path);
        } catch (\Throwable $e) {
            return count_result::failure($this->error_code($e));
        }

        try {
            $result->encrypted = $parser->is_encrypted();

            if (!$result->encrypted) {
                $pages = $this->count_with_fpdi($path);
                if ($pages !== null) {
                    $result->pages = $pages;
                    $result->method = count_result::METHOD_FPDI;
                }
            }

            if ($result->pages === null) {
                $pages = $parser->count_pages();
                if ($pages !== null) {
                    $result->pages = $pages;
                    $result->method = count_result::METHOD_RAW;
                }
            }

            if ($result->pages === null && !empty($options['usegs'])) {
                $pages = $this->count_with_ghostscript($path);
                if ($pages !== null) {
                    $result->pages = $pages;
                    $result->method = count_result::METHOD_GS;
                }
            }

            // The content of an encrypted document is unreadable, so there is nothing to analyse.
            if (!empty($options['analysetext']) && !$result->encrypted) {
                $result->hastext = $parser->has_text_layer();
                $result->blankpages = $parser->count_blank_pages();
            }
        } catch (\Throwable $e) {
            // A damaged file must never take a page down with it.
            if ($result->pages === null) {
                return count_result::failure('errorunreadablepdf');
            }
        }

        if ($result->pages === null && $result->error === null) {
            $result->error = 'errorunreadablepdf';
        }

        return $result;
    }

    /**
     * Count pages with the FPDI parser bundled with Moodle.
     *
     * @param string $path absolute path to the file
     * @return int|null page count, or null when FPDI could not read the file
     */
    protected function count_with_fpdi(string $path) {
        global $CFG;

        require_once($CFG->libdir . '/pdflib.php');

        if (!class_exists('\setasign\Fpdi\TcpdfFpdi')) {
            return null;
        }

        try {
            $pdf = new \setasign\Fpdi\TcpdfFpdi();
            $pages = $pdf->setSourceFile($path);
            return $pages > 0 ? (int) $pages : null;
        } catch (\Throwable $e) {
            return null;
        } finally {
            // TCPDF holds the whole document in memory; dropping the last reference to it here
            // means a batch of submissions does not accumulate one parsed PDF after another.
            unset($pdf);
        }
    }

    /**
     * Count pages by asking Ghostscript.
     *
     * @param string $path absolute path to the file
     * @return int|null page count, or null when Ghostscript is unavailable or failed
     */
    protected function count_with_ghostscript(string $path) {
        global $CFG;

        $gs = isset($CFG->pathtogs) ? trim((string) $CFG->pathtogs) : '';
        if ($gs === '' || !is_executable($gs)) {
            return null;
        }
        if (!function_exists('exec')) {
            return null;
        }

        $escaped = escapeshellarg($path);
        $command = escapeshellarg($gs)
            . ' -q -dNODISPLAY -dBATCH -dNOPAUSE'
            . ' --permit-file-read=' . $escaped
            . ' -c ' . escapeshellarg('(' . $path . ') (r) file runpdfbegin pdfpagecount = quit')
            . ' 2>/dev/null';

        $output = [];
        $status = 0;
        @exec($command, $output, $status);
        if ($status !== 0) {
            return null;
        }
        foreach ($output as $line) {
            $line = trim($line);
            if ($line !== '' && ctype_digit($line)) {
                return (int) $line;
            }
        }
        return null;
    }

    /**
     * Translate a parser exception into an error code understood by the validator.
     *
     * @param \Throwable $e the exception thrown while opening the file
     * @return string language string key
     */
    protected function error_code(\Throwable $e): string {
        $known = ['errorfileunreadable', 'errorfiletoolarge', 'errornotapdf'];
        if ($e instanceof \moodle_exception && in_array($e->errorcode, $known, true)) {
            return $e->errorcode;
        }
        return 'errorunreadablepdf';
    }
}
