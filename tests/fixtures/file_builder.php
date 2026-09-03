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
 * Builds the sample documents the counter tests run against.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\tests\fixtures;

/**
 * Writes small, valid documents on the fly.
 *
 * The alternative would be committing binary sample files that nobody can review in a diff.
 * Building them here keeps every byte the tests depend on visible in the source, and lets a test
 * ask for exactly the document it needs: seven pages, no text layer, a blank page in the middle.
 */
class file_builder {
    /**
     * Write a PDF with a valid cross reference table.
     *
     * @param string $path where to write the file
     * @param int $pages how many pages it should have
     * @param array $options 'text' (default true) to draw text on every page,
     *                       'blank' number of trailing pages that paint nothing,
     *                       'compress' to deflate the content streams, as real writers do,
     *                       'size' as [width, height] in points, A4 by default,
     *                       'lastsize' as [width, height] for the final page only, to build a
     *                       document of mixed paper sizes,
     *                       'encrypted' to reference an encryption dictionary in the trailer
     * @return string the path that was written
     */
    public static function pdf(string $path, int $pages, array $options = []): string {
        $withtext = !array_key_exists('text', $options) || $options['text'];
        $blank = isset($options['blank']) ? (int) $options['blank'] : 0;
        $compress = !empty($options['compress']);
        $size = isset($options['size']) ? $options['size'] : [595.276, 841.89];
        $lastsize = isset($options['lastsize']) ? $options['lastsize'] : null;
        $encrypted = !empty($options['encrypted']);

        // Object 1 is the catalogue, object 2 the page tree, then a page and a content stream
        // for each page in turn.
        $objects = [];
        $kids = [];
        for ($i = 0; $i < $pages; $i++) {
            $pageobj = 3 + ($i * 2);
            $kids[] = "$pageobj 0 R";
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pages . ' >>';

        for ($i = 0; $i < $pages; $i++) {
            $pageobj = 3 + ($i * 2);
            $contentobj = $pageobj + 1;

            $isblank = $i >= ($pages - $blank);
            if ($isblank) {
                $stream = "q Q\n";
            } else if ($withtext) {
                $stream = "BT /F1 12 Tf 72 720 Td (Page " . ($i + 1) . ") Tj ET\n";
            } else {
                // Paints a rectangle, so the page is not blank, but shows no text.
                $stream = "0 0 0 rg 72 72 100 100 re f\n";
            }

            $box = ($lastsize !== null && $i === $pages - 1) ? $lastsize : $size;
            $objects[$pageobj] = '<< /Type /Page /Parent 2 0 R '
                . '/MediaBox [0 0 ' . $box[0] . ' ' . $box[1] . '] '
                . '/Resources << /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >> '
                . '/Contents ' . $contentobj . ' 0 R >>';
            $filter = '';
            if ($compress) {
                $stream = gzcompress($stream);
                $filter = ' /Filter /FlateDecode';
            }
            $objects[$contentobj] = '<< /Length ' . strlen($stream) . $filter
                . " >>\nstream\n" . $stream . "\nendstream";
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefoffset = strlen($pdf);
        $maxobject = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxobject + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($number = 1; $number <= $maxobject; $number++) {
            $offset = isset($offsets[$number]) ? $offsets[$number] : 0;
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $trailer = '<< /Size ' . ($maxobject + 1) . ' /Root 1 0 R';
        if ($encrypted) {
            // Enough to make the file look encrypted to anything that reads the trailer.
            $trailer .= ' /Encrypt 999 0 R';
        }
        $trailer .= ' >>';

        $pdf .= "trailer\n" . $trailer . "\nstartxref\n" . $xrefoffset . "\n%%EOF\n";

        file_put_contents($path, $pdf);

        return $path;
    }

    /**
     * Write a file that starts like a PDF but holds nothing a parser can use.
     *
     * @param string $path where to write the file
     * @return string the path that was written
     */
    public static function broken_pdf(string $path): string {
        file_put_contents($path, "%PDF-1.4\nthis file was truncated by a bad upload\n");
        return $path;
    }

    /**
     * Write a minimal .docx.
     *
     * @param string $path where to write the file
     * @param int|null $pages the page count to record, or null to record none at all
     * @return string the path that was written
     */
    public static function docx(string $path, $pages): string {
        return self::ooxml(
            $path,
            'Pages',
            $pages,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
    }

    /**
     * Write a minimal .pptx.
     *
     * @param string $path where to write the file
     * @param int|null $slides the slide count to record, or null to record none at all
     * @return string the path that was written
     */
    public static function pptx(string $path, $slides): string {
        return self::ooxml(
            $path,
            'Slides',
            $slides,
            'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        );
    }

    /**
     * Write an Office Open XML package holding just the parts the counter reads.
     *
     * @param string $path where to write the file
     * @param string $element the statistics element, Pages or Slides
     * @param int|null $count the value to record, or null to omit the properties part
     * @param string $contenttype the mime type to declare
     * @return string the path that was written
     */
    protected static function ooxml(string $path, string $element, $count, string $contenttype): string {
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString(
            '[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="xml" ContentType="' . $contenttype . '"/></Types>'
        );

        if ($count !== null) {
            $zip->addFromString(
                'docProps/app.xml',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">' .
                '<' . $element . '>' . (int) $count . '</' . $element . '>' .
                '</Properties>'
            );
        }

        $zip->close();

        return $path;
    }

    /**
     * Write a file that looks like a password protected Office document.
     *
     * Office wraps an encrypted document in an OLE compound file, which is recognised by the
     * eight byte signature at its start.
     *
     * @param string $path where to write the file
     * @return string the path that was written
     */
    public static function encrypted_office(string $path): string {
        file_put_contents($path, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1" . str_repeat("\x00", 512));
        return $path;
    }
}
