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
 * Effective restrictions for one user of one activity.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolves the instance settings against the group and user overrides.
 *
 * A user override wins over every group override; among group overrides the one with the lowest
 * sort order wins, which is the same precedence the core assignment activity uses. A null column
 * in an override means "inherit", so a teacher can extend a deadline for one group without
 * touching its page limits.
 */
class rules {

    /** @var string Accept a file whose page count could not be determined. */
    const UNKNOWN_ACCEPT = 'accept';

    /** @var string Accept it but warn the student. */
    const UNKNOWN_WARN = 'warn';

    /** @var string Refuse it. */
    const UNKNOWN_REJECT = 'reject';

    /** @var string Broken restrictions only produce warnings. */
    const STRICTNESS_WARN = 'warn';

    /** @var string Broken restrictions block the submission. */
    const STRICTNESS_BLOCK = 'block';

    /** @var string The page range applies to every file added together. */
    const COUNT_TOTAL = 'total';

    /** @var string The page range applies to each file on its own. */
    const COUNT_PER_FILE = 'perfile';

    /** @var array The columns an override may replace. */
    const OVERRIDABLE = [
        'allowsubmissionsfromdate',
        'duedate',
        'cutoffdate',
        'maxattempts',
        'minpages',
        'maxpages',
    ];

    /** @var int Timestamp before which submissions are not open, 0 for no restriction. */
    public $allowsubmissionsfromdate = 0;

    /** @var int Due date, 0 for no restriction. */
    public $duedate = 0;

    /** @var int Hard cut off date, 0 for no restriction. */
    public $cutoffdate = 0;

    /** @var bool Whether submitting after the due date is refused outright. */
    public $blockafterdue = false;

    /** @var int Maximum number of attempts, -1 for unlimited. */
    public $maxattempts = -1;

    /** @var int Minimum number of pages, 0 for no minimum. */
    public $minpages = 0;

    /** @var int Maximum number of pages, 0 for no maximum. */
    public $maxpages = 0;

    /** @var int Leading pages not counted, for example a cover sheet. */
    public $countcover = 0;

    /** @var string[] Accepted extensions, lower case and without the leading dot. */
    public $allowedextensions = [];

    /** @var int Maximum size of a single file in bytes, 0 for the course limit. */
    public $maxbytes = 0;

    /** @var int Maximum number of files in one submission. */
    public $maxfiles = 1;

    /** @var bool Whether encrypted files are refused. */
    public $rejectencrypted = true;

    /** @var bool Whether a text layer is required, that is, no scan only PDF. */
    public $requiretextlayer = false;

    /** @var bool Whether blank pages are reported. */
    public $rejectblankpages = false;

    /** @var int How many blank pages are tolerated before reporting. */
    public $blankpagetolerance = 0;

    /** @var string What to do when the page count is unknown, one of the UNKNOWN_* constants. */
    public $unknownpolicy = self::UNKNOWN_WARN;

    /** @var string One of the STRICTNESS_* constants. */
    public $strictness = self::STRICTNESS_BLOCK;

    /** @var string Required paper size, a page_size key, or page_size::ANY. */
    public $pagesize = \mod_pagecheck\counter\page_size::ANY;

    /** @var string Whether the page range applies to the whole submission or to each file. */
    public $countmode = self::COUNT_TOTAL;

    /** @var string Pattern every file name must match, with * and ? as wildcards. */
    public $filenamepattern = '';

    /** @var bool Whether the same file attached twice is refused. */
    public $rejectduplicates = false;

    /** @var int Minimum number of files, 0 for no minimum. */
    public $minfiles = 0;

    /** @var bool Whether the student has to tick the submission statement. */
    public $requiresubmissionstatement = false;

    /**
     * Build the effective rules for a user.
     *
     * @param \stdClass $pagecheck the activity instance record
     * @param int $userid the user the rules apply to
     * @return rules
     */
    public static function for_user(\stdClass $pagecheck, int $userid): rules {
        global $DB;

        $rules = self::from_instance($pagecheck);

        $overrides = $DB->get_records_select(
            'pagecheck_overrides',
            'pagecheckid = :pagecheckid AND (userid = :userid OR (userid = 0 AND groupid > 0))',
            ['pagecheckid' => $pagecheck->id, 'userid' => $userid],
            'sortorder ASC, id ASC'
        );
        if (!$overrides) {
            return $rules;
        }

        $groupids = self::get_user_group_ids($pagecheck, $userid);

        // Lowest sort order first, so the first value we see for a column is the one that wins.
        $applied = [];
        foreach ($overrides as $override) {
            if ($override->userid == 0 && !in_array((int) $override->groupid, $groupids, true)) {
                continue;
            }
            foreach (self::OVERRIDABLE as $column) {
                if ($override->{$column} !== null && !isset($applied[$column])) {
                    $applied[$column] = (int) $override->{$column};
                }
            }
        }
        // A user override beats every group override, whatever their sort order.
        foreach ($overrides as $override) {
            if ($override->userid != $userid) {
                continue;
            }
            foreach (self::OVERRIDABLE as $column) {
                if ($override->{$column} !== null) {
                    $applied[$column] = (int) $override->{$column};
                }
            }
        }

        foreach ($applied as $column => $value) {
            $rules->{$column} = $value;
        }

        return $rules;
    }

    /**
     * Build the rules from the instance settings alone, ignoring every override.
     *
     * @param \stdClass $pagecheck the activity instance record
     * @return rules
     */
    public static function from_instance(\stdClass $pagecheck): rules {
        $rules = new self();

        $rules->allowsubmissionsfromdate = (int) $pagecheck->allowsubmissionsfromdate;
        $rules->duedate = (int) $pagecheck->duedate;
        $rules->cutoffdate = (int) $pagecheck->cutoffdate;
        $rules->blockafterdue = !empty($pagecheck->blockafterdue);
        $rules->maxattempts = (int) $pagecheck->maxattempts;
        $rules->minpages = (int) $pagecheck->minpages;
        $rules->maxpages = (int) $pagecheck->maxpages;
        $rules->countcover = (int) $pagecheck->countcover;
        $rules->allowedextensions = self::parse_extensions($pagecheck->allowedextensions);
        $rules->maxbytes = (int) $pagecheck->maxbytes;
        $rules->maxfiles = (int) $pagecheck->maxfiles;
        $rules->rejectencrypted = !empty($pagecheck->rejectencrypted);
        $rules->requiretextlayer = !empty($pagecheck->requiretextlayer);
        $rules->rejectblankpages = !empty($pagecheck->rejectblankpages);
        $rules->blankpagetolerance = (int) $pagecheck->blankpagetolerance;
        $rules->unknownpolicy = (string) $pagecheck->unknownpolicy;
        $rules->strictness = (string) $pagecheck->strictness;
        $rules->requiresubmissionstatement = !empty($pagecheck->requiresubmissionstatement);
        $rules->pagesize = isset($pagecheck->pagesize)
            ? (string) $pagecheck->pagesize : \mod_pagecheck\counter\page_size::ANY;
        $rules->countmode = isset($pagecheck->countmode)
            ? (string) $pagecheck->countmode : self::COUNT_TOTAL;
        $rules->filenamepattern = isset($pagecheck->filenamepattern)
            ? trim((string) $pagecheck->filenamepattern) : '';
        $rules->rejectduplicates = !empty($pagecheck->rejectduplicates);
        $rules->minfiles = isset($pagecheck->minfiles) ? (int) $pagecheck->minfiles : 0;

        return $rules;
    }

    /**
     * The group ids of a user, restricted to the activity grouping when one is set.
     *
     * @param \stdClass $pagecheck the activity instance record
     * @param int $userid the user
     * @return int[]
     */
    protected static function get_user_group_ids(\stdClass $pagecheck, int $userid): array {
        $cm = get_coursemodule_from_instance('pagecheck', $pagecheck->id, $pagecheck->course, false, IGNORE_MISSING);
        $groupingid = $cm ? (int) $cm->groupingid : 0;
        $groups = groups_get_all_groups($pagecheck->course, $userid, $groupingid, 'g.id');
        return array_map('intval', array_keys($groups));
    }

    /**
     * Normalise the comma separated extension list stored in the instance.
     *
     * @param string|null $extensions the stored value, for example ".pdf, .docx"
     * @return string[] lower case extensions without the leading dot
     */
    public static function parse_extensions($extensions): array {
        $parsed = [];
        foreach (explode(',', (string) $extensions) as $extension) {
            $extension = strtolower(trim($extension));
            $extension = ltrim($extension, '.');
            if ($extension !== '') {
                $parsed[$extension] = $extension;
            }
        }
        return array_values($parsed);
    }

    /**
     * Whether a file name is acceptable.
     *
     * The pattern is written the way a person expects a file name pattern to work, with * and ?
     * as wildcards, rather than as a regular expression: a teacher writing "TCC_*.pdf" should not
     * have to know what a dot means to a regex engine.
     *
     * @param string $filename the name to test
     * @return bool true when there is no pattern, or when the name matches it
     */
    public function filename_matches(string $filename): bool {
        if ($this->filenamepattern === '') {
            return true;
        }

        $pattern = preg_quote($this->filenamepattern, '/');
        $pattern = str_replace(['\\*', '\\?'], ['.*', '.'], $pattern);

        return (bool) preg_match('/^' . $pattern . '$/iu', $filename);
    }

    /**
     * Whether a moment in time is past the due date.
     *
     * @param int|null $time the moment to test, defaults to now
     * @return bool
     */
    public function is_late(?int $time = null): bool {
        $time = $time === null ? time() : $time;
        return $this->duedate > 0 && $time > $this->duedate;
    }

    /**
     * Whether submissions are closed for good at the given moment.
     *
     * @param int|null $time the moment to test, defaults to now
     * @return bool
     */
    public function is_closed(?int $time = null): bool {
        $time = $time === null ? time() : $time;
        if ($this->cutoffdate > 0 && $time > $this->cutoffdate) {
            return true;
        }
        return $this->blockafterdue && $this->is_late($time);
    }

    /**
     * Whether submissions have not opened yet at the given moment.
     *
     * @param int|null $time the moment to test, defaults to now
     * @return bool
     */
    public function is_not_open_yet(?int $time = null): bool {
        $time = $time === null ? time() : $time;
        return $this->allowsubmissionsfromdate > 0 && $time < $this->allowsubmissionsfromdate;
    }
}
