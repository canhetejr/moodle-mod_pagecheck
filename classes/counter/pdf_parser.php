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
 * Minimal, defensive PDF structure reader.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\counter;

/**
 * Reads just enough of the PDF file structure to answer three questions:
 * how many pages, is there a text layer, and which pages are blank.
 *
 * This is deliberately not a general purpose PDF parser. It walks the plain "N 0 obj ... endobj"
 * bodies of the file, which covers the overwhelming majority of documents produced by word
 * processors and by LaTeX. Documents that hide their objects inside compressed object streams
 * are detected and reported as unreadable, so that the caller can fall back to FPDI or refuse to
 * guess, rather than return a number that happens to be wrong.
 */
class pdf_parser {
    /** @var int Never load more than this many bytes into memory. */
    const MAX_BYTES = 104857600;

    /** @var int Refuse to inflate a single stream larger than this once decoded. */
    const MAX_STREAM_BYTES = 20971520;

    /** @var string Raw file content. */
    protected $content = '';

    /** @var array|null Map of "objectnumber" => raw object body, built lazily. */
    protected $objects = null;

    /**
     * Load a PDF file into memory.
     *
     * @param string $path absolute path to a readable file
     * @throws \moodle_exception when the file cannot be read or is too large
     */
    public function __construct(string $path) {
        $size = @filesize($path);
        if ($size === false) {
            throw new \moodle_exception('errorfileunreadable', 'mod_pagecheck');
        }
        if ($size > self::MAX_BYTES) {
            throw new \moodle_exception('errorfiletoolarge', 'mod_pagecheck');
        }
        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            throw new \moodle_exception('errorfileunreadable', 'mod_pagecheck');
        }
        if (strncmp($content, '%PDF-', 5) !== 0 && strpos(substr($content, 0, 1024), '%PDF-') === false) {
            throw new \moodle_exception('errornotapdf', 'mod_pagecheck');
        }
        $this->content = $content;
    }

    /**
     * Whether the document is encrypted or password protected.
     *
     * The /Encrypt entry of a trailer is always an indirect reference, which makes it safe to
     * look for without tripping over the word appearing inside page content.
     *
     * @return bool
     */
    public function is_encrypted(): bool {
        return (bool) preg_match('/\/Encrypt\s+\d+\s+\d+\s+R/', $this->content);
    }

    /**
     * Whether the document stores objects inside compressed object streams.
     *
     * When it does, the plain object scan below cannot see every object and its answers must not
     * be trusted.
     *
     * @return bool
     */
    public function has_object_streams(): bool {
        return (bool) preg_match('/\/Type\s*\/ObjStm\b/', $this->content);
    }

    /**
     * Count the pages of the document.
     *
     * @return int|null the number of pages, or null when it cannot be determined reliably
     */
    public function count_pages() {
        $objects = $this->get_objects();

        // Preferred source: the /Count of the root node of the page tree, that is, the only
        // /Pages node without a /Parent.
        $counts = [];
        foreach ($objects as $body) {
            if (!preg_match('/\/Type\s*\/Pages\b/', $body)) {
                continue;
            }
            if (!preg_match('/\/Count\s+(\d+)/', $body, $matches)) {
                continue;
            }
            $count = (int) $matches[1];
            if (!preg_match('/\/Parent\s+\d+\s+\d+\s+R/', $body)) {
                return $count;
            }
            $counts[] = $count;
        }
        if ($counts) {
            // Several page tree nodes but no obvious root: the largest count is the total.
            return max($counts);
        }

        // Last resort: count the leaves of the page tree directly.
        $pages = count($this->get_page_objects());
        return $pages > 0 ? $pages : null;
    }

    /**
     * Whether any page of the document carries a text layer.
     *
     * @return bool|null true or false, or null when the document could not be analysed
     */
    public function has_text_layer() {
        if ($this->has_object_streams()) {
            return null;
        }
        foreach ($this->get_page_objects() as $number => $body) {
            $content = $this->get_page_content($body);
            if ($content === null) {
                continue;
            }
            if ($this->content_has_text($content)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Count the pages that paint nothing at all.
     *
     * A page counts as blank when its content stream shows no text, draws no image and paints no
     * path. This is a heuristic: a page whose text has been converted to outlines looks blank to
     * it, which is why callers report the result as a warning rather than as an error.
     *
     * @return int|null number of blank pages, or null when the document could not be analysed
     */
    public function count_blank_pages() {
        if ($this->has_object_streams()) {
            return null;
        }
        $blank = 0;
        $analysed = 0;
        foreach ($this->get_page_objects() as $body) {
            $content = $this->get_page_content($body);
            if ($content === null) {
                continue;
            }
            $analysed++;
            if (
                !$this->content_has_text($content)
                    && !preg_match('/(^|[\s\]>])(Do|BI)[\s\/]/', $content)
                    && !preg_match('/(^|\s)(S|s|f|F|B|b|f\*|B\*|b\*)\s/', $content)
            ) {
                $blank++;
            }
        }
        return $analysed > 0 ? $blank : null;
    }

    /**
     * The dimensions of every page, in PostScript points.
     *
     * A page that does not carry its own /MediaBox inherits one from its parent node in the page
     * tree, which is how most writers store a document of uniform size, so the parent is followed
     * when the page itself is silent.
     *
     * @return array|null list of [width, height] pairs, or null when the document could not be read
     */
    public function get_page_sizes() {
        if ($this->has_object_streams()) {
            return null;
        }

        $objects = $this->get_objects();
        $sizes = [];

        foreach ($this->get_page_objects() as $body) {
            $box = $this->find_media_box($body, $objects, 0);
            if ($box !== null) {
                $sizes[] = $box;
            }
        }

        return $sizes ?: null;
    }

    /**
     * The media box of a page, following the page tree upwards when it is inherited.
     *
     * @param string $body raw body of the page or page tree object
     * @param array $objects every indexed object, to resolve the parent reference
     * @param int $depth how far up the tree we have walked, to stop a malformed loop
     * @return array|null [width, height], or null when there is no media box to be found
     */
    protected function find_media_box(string $body, array $objects, int $depth) {
        if (
            preg_match(
                '/\/MediaBox\s*\[\s*([\d.eE+-]+)\s+([\d.eE+-]+)\s+([\d.eE+-]+)\s+([\d.eE+-]+)\s*\]/',
                $body,
                $matches
            )
        ) {
            $width = abs((float) $matches[3] - (float) $matches[1]);
            $height = abs((float) $matches[4] - (float) $matches[2]);
            if ($width > 0 && $height > 0) {
                return [$width, $height];
            }
        }

        // A page tree can nest, but not deeply, and a damaged file must not send us round forever.
        if ($depth >= 8) {
            return null;
        }
        if (!preg_match('/\/Parent\s+(\d+)\s+\d+\s+R/', $body, $parent)) {
            return null;
        }
        $number = (int) $parent[1];
        if (!isset($objects[$number])) {
            return null;
        }

        return $this->find_media_box($objects[$number], $objects, $depth + 1);
    }

    /**
     * Whether a decoded content stream shows any text.
     *
     * @param string $content decoded page content stream
     * @return bool
     */
    protected function content_has_text(string $content): bool {
        return (bool) preg_match('/(^|[\s\)\]>])(Tj|TJ)(\s|$)/', $content);
    }

    /**
     * Index every plain "N 0 obj ... endobj" body in the file.
     *
     * @return array object number => raw body
     */
    protected function get_objects(): array {
        if ($this->objects !== null) {
            return $this->objects;
        }
        $this->objects = [];
        $count = preg_match_all('/(\d+)\s+(\d+)\s+obj\b/', $this->content, $matches, PREG_OFFSET_CAPTURE);
        if (!$count) {
            return $this->objects;
        }
        for ($i = 0; $i < $count; $i++) {
            $number = (int) $matches[1][$i][0];
            $start = $matches[0][$i][1] + strlen($matches[0][$i][0]);
            $end = strpos($this->content, 'endobj', $start);
            if ($end === false) {
                $end = ($i + 1 < $count) ? $matches[0][$i + 1][1] : strlen($this->content);
            }
            // A later definition of the same object number supersedes the earlier one.
            $this->objects[$number] = substr($this->content, $start, $end - $start);
        }
        return $this->objects;
    }

    /**
     * The objects that are leaves of the page tree.
     *
     * @return array object number => raw body
     */
    protected function get_page_objects(): array {
        $pages = [];
        foreach ($this->get_objects() as $number => $body) {
            // The word boundary keeps /Pages nodes out.
            if (preg_match('/\/Type\s*\/Page(?![sX])/', $body)) {
                $pages[$number] = $body;
            }
        }
        return $pages;
    }

    /**
     * Decoded content stream of a page, with every part concatenated.
     *
     * @param string $pagebody raw body of the page object
     * @return string|null the decoded content, or null when it could not be decoded
     */
    protected function get_page_content(string $pagebody) {
        if (!preg_match('/\/Contents\s*(\[[^\]]*\]|\d+\s+\d+\s+R)/', $pagebody, $matches)) {
            return null;
        }
        if (!preg_match_all('/(\d+)\s+\d+\s+R/', $matches[1], $refs)) {
            return null;
        }
        $objects = $this->get_objects();
        $content = '';
        foreach ($refs[1] as $number) {
            $number = (int) $number;
            if (!isset($objects[$number])) {
                continue;
            }
            $decoded = $this->decode_stream($objects[$number]);
            if ($decoded !== null) {
                $content .= $decoded . "\n";
            }
        }
        return $content === '' ? null : $content;
    }

    /**
     * Extract and inflate the stream of a raw object body.
     *
     * @param string $body raw object body
     * @return string|null decoded stream, or null when it is missing or uses an unsupported filter
     */
    protected function decode_stream(string $body) {
        $start = strpos($body, 'stream');
        if ($start === false) {
            return null;
        }
        $dictionary = substr($body, 0, $start);
        $start += strlen('stream');
        // The keyword is followed by CRLF or LF, never by CR alone.
        if (substr($body, $start, 2) === "\r\n") {
            $start += 2;
        } else if (substr($body, $start, 1) === "\n" || substr($body, $start, 1) === "\r") {
            $start += 1;
        }
        $end = strpos($body, 'endstream', $start);
        if ($end === false) {
            return null;
        }
        $stream = substr($body, $start, $end - $start);
        if (strlen($stream) > self::MAX_STREAM_BYTES) {
            return null;
        }

        if (strpos($dictionary, '/FlateDecode') !== false) {
            // A stream that is compressed and then encoded again is beyond what we parse.
            if (preg_match('/\/Filter\s*\[[^\]]*\/(ASCII85Decode|ASCIIHexDecode|LZWDecode)/', $dictionary)) {
                return null;
            }
            $inflated = @gzuncompress($stream);
            if ($inflated === false) {
                $inflated = @gzinflate($stream);
            }
            if ($inflated === false) {
                return null;
            }
            return $inflated;
        }
        if (preg_match('/\/Filter\s*(\/|\[)/', $dictionary)) {
            // Some other filter we do not implement.
            return null;
        }
        return $stream;
    }
}
