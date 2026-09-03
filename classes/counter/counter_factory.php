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
 * Picks the right counter for a file and runs it.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\counter;

/**
 * Entry point of the counting layer.
 */
class counter_factory {
    /**
     * Every counter, in the order they get asked whether they support a file.
     *
     * @return counter_interface[]
     */
    public static function get_counters(): array {
        return [
            new pdf_counter(),
            new ooxml_counter(),
        ];
    }

    /**
     * The counter that handles a given file, if any.
     *
     * @param string $mimetype mime type of the file
     * @param string $extension lower case extension, without the leading dot
     * @return counter_interface|null
     */
    public static function get_counter(string $mimetype, string $extension) {
        foreach (self::get_counters() as $counter) {
            if ($counter->supports($mimetype, $extension)) {
                return $counter;
            }
        }
        return null;
    }

    /**
     * Count the pages of a file held in the Moodle file storage.
     *
     * The file is copied to a request scoped temporary directory first, because the counters read
     * a plain path and the file may live in an object store rather than on local disk.
     *
     * @param \stored_file $file the submitted file
     * @param array $options options passed through to the counter
     * @return count_result
     */
    public static function count_stored_file(\stored_file $file, array $options = []): count_result {
        $filename = $file->get_filename();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mimetype = (string) $file->get_mimetype();

        $counter = self::get_counter($mimetype, $extension);
        if ($counter === null) {
            $result = count_result::failure('errorunsupportedformat');
            $result->filename = $filename;
            $result->filesize = (int) $file->get_filesize();
            $result->mimetype = $mimetype;
            $result->contenthash = (string) $file->get_contenthash();
            return $result;
        }

        $options['extension'] = $extension;

        $path = make_request_directory() . '/' . 'pagecheck.' . ($extension !== '' ? $extension : 'tmp');
        try {
            $file->copy_content_to($path);
            $result = $counter->count($path, $options);
        } catch (\Throwable $e) {
            $result = count_result::failure('errorfileunreadable');
        } finally {
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        $result->filename = $filename;
        $result->filesize = (int) $file->get_filesize();
        $result->mimetype = $mimetype;
        $result->contenthash = (string) $file->get_contenthash();

        return $result;
    }
}
