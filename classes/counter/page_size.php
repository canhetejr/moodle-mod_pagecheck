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
 * Recognises the standard paper sizes.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\counter;

/**
 * Turns the dimensions of a PDF page into the name of a paper size.
 *
 * Academic work is normally required on A4, and a document exported with the wrong paper size
 * looks right on screen and wrong on paper, which is exactly the kind of mistake a student only
 * discovers after handing it in.
 */
class page_size {
    /** @var string The size could not be worked out. */
    const UNKNOWN = 'unknown';

    /** @var string Pages of more than one size. */
    const MIXED = 'mixed';

    /** @var string Any size is accepted. */
    const ANY = 'any';

    /** @var float How far from the nominal size a page may be, in points. */
    const TOLERANCE = 5.0;

    /**
     * The sizes we recognise, as [short edge, long edge] in PostScript points.
     *
     * @return array name => [short, long]
     */
    public static function known_sizes(): array {
        return [
            'a4' => [595.276, 841.89],
            'a3' => [841.89, 1190.55],
            'a5' => [419.528, 595.276],
            'letter' => [612.0, 792.0],
            'legal' => [612.0, 1008.0],
        ];
    }

    /**
     * Name the paper size of one page.
     *
     * Orientation is ignored: a landscape A4 page is still A4, so the dimensions are compared
     * shortest edge first.
     *
     * @param float $width width of the page in points
     * @param float $height height of the page in points
     * @return string a key of known_sizes(), or UNKNOWN
     */
    public static function classify(float $width, float $height): string {
        $short = min($width, $height);
        $long = max($width, $height);

        foreach (self::known_sizes() as $name => $size) {
            if (abs($short - $size[0]) <= self::TOLERANCE && abs($long - $size[1]) <= self::TOLERANCE) {
                return $name;
            }
        }

        return self::UNKNOWN;
    }

    /**
     * Name the paper size of a whole document.
     *
     * @param array $pages list of [width, height] pairs, one per page
     * @return string a key of known_sizes(), MIXED, or UNKNOWN
     */
    public static function classify_document(array $pages): string {
        if (!$pages) {
            return self::UNKNOWN;
        }

        $names = [];
        foreach ($pages as $page) {
            $names[self::classify((float) $page[0], (float) $page[1])] = true;
        }

        if (count($names) > 1) {
            return self::MIXED;
        }

        return (string) array_key_first($names);
    }

    /**
     * The sizes a teacher can require, ready for a form select.
     *
     * @return array value => translated label
     */
    public static function get_menu(): array {
        $menu = [self::ANY => get_string('pagesize_any', 'mod_pagecheck')];
        foreach (array_keys(self::known_sizes()) as $name) {
            $menu[$name] = get_string('pagesize_' . $name, 'mod_pagecheck');
        }
        return $menu;
    }

    /**
     * The translated name of a size.
     *
     * @param string $name a key of known_sizes(), MIXED or UNKNOWN
     * @return string
     */
    public static function get_name(string $name): string {
        $known = array_keys(self::known_sizes());
        if (in_array($name, $known, true) || in_array($name, [self::ANY, self::MIXED], true)) {
            return get_string('pagesize_' . $name, 'mod_pagecheck');
        }
        return get_string('pagesize_unknown', 'mod_pagecheck');
    }
}
