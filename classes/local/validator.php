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
 * Applies the activity restrictions to a set of counted files.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\local;

use mod_pagecheck\counter\count_result;
use mod_pagecheck\counter\page_size;

/**
 * The single place where "is this submission acceptable" is decided.
 *
 * The activity page, the submission form and the JavaScript running in the browser all present
 * the output of this class, so a student never sees one verdict on screen and a different one
 * when they press submit. The browser only ever anticipates the answer: nothing is accepted
 * without this validator having run on the server.
 */
class validator {
    /**
     * Issues that the "warn only" strictness downgrades to warnings.
     *
     * The restrictions about how the file is packaged, when it was sent and how many attempts
     * were used are not negotiable, because they mirror what the rest of Moodle enforces anyway.
     * What "warn only" relaxes is the judgement this plugin makes about the content of the file.
     *
     * @var string[]
     */
    const SOFT_CODES = [
        'toofewpages',
        'toomanypages',
        'unknownpagecount',
        'notextlayer',
        'blankpages',
        'encrypted',
        'badpagesize',
    ];

    /**
     * Validate a submission.
     *
     * @param count_result[] $results one result per submitted file
     * @param rules $rules the effective restrictions for this user
     * @param array $context 'time' (defaults to now), 'attemptsused', and 'forsubmission' when
     *                        the answer decides whether work may be sent, rather than describing
     *                        work that was already sent
     * @return issue[]
     */
    public function validate(array $results, rules $rules, array $context = []): array {
        $time = isset($context['time']) ? (int) $context['time'] : time();
        $attemptsused = isset($context['attemptsused']) ? (int) $context['attemptsused'] : 0;
        $forsubmission = !empty($context['forsubmission']);

        $issues = [];
        $issues = array_merge(
            $issues,
            $this->check_timing($rules, $time, $attemptsused, $forsubmission)
        );
        $issues = array_merge($issues, $this->check_files($results, $rules));
        $issues = array_merge($issues, $this->check_total_pages($results, $rules));

        if ($rules->strictness === rules::STRICTNESS_WARN) {
            foreach ($issues as $issue) {
                if (in_array($issue->code, self::SOFT_CODES, true)) {
                    $issue->level = issue::LEVEL_WARNING;
                }
            }
        }

        return $issues;
    }

    /**
     * Check the dates and the attempt allowance.
     *
     * @param rules $rules the effective restrictions
     * @param int $time the moment the submission is being made
     * @param int $attemptsused how many attempts the user has already submitted
     * @param bool $forsubmission whether this answer decides if work may be sent
     * @return issue[]
     */
    protected function check_timing(
        rules $rules,
        int $time,
        int $attemptsused,
        bool $forsubmission = false
    ): array {
        $issues = [];

        if ($rules->is_not_open_yet($time)) {
            $issues[] = new issue(
                'notopenyet',
                issue::LEVEL_ERROR,
                userdate($rules->allowsubmissionsfromdate)
            );
        } else if ($rules->is_closed($time)) {
            $issues[] = new issue(
                'submissionsclosed',
                issue::LEVEL_ERROR,
                userdate($rules->cutoffdate > 0 ? $rules->cutoffdate : $rules->duedate)
            );
        } else if ($rules->is_late($time)) {
            $issues[] = new issue('late', issue::LEVEL_WARNING, userdate($rules->duedate));
        }

        // Having used every attempt is only a problem for someone trying to send another one.
        // Telling a student who already handed in that they are out of attempts turns a finished
        // submission into a screenful of red for no reason.
        if ($forsubmission && $rules->maxattempts >= 0 && $attemptsused >= $rules->maxattempts) {
            $issues[] = new issue('noattemptsleft', issue::LEVEL_ERROR, $rules->maxattempts);
        }

        return $issues;
    }

    /**
     * Check each file on its own.
     *
     * @param count_result[] $results one result per submitted file
     * @param rules $rules the effective restrictions
     * @return issue[]
     */
    protected function check_files(array $results, rules $rules): array {
        $issues = [];

        if (!$results) {
            return [new issue('nofiles', issue::LEVEL_ERROR)];
        }

        if ($rules->minfiles > 0 && count($results) < $rules->minfiles) {
            $issues[] = new issue('toofewfiles', issue::LEVEL_ERROR, (object) [
                'count' => count($results),
                'min' => $rules->minfiles,
            ]);
        }

        if ($rules->rejectduplicates) {
            $issues = array_merge($issues, $this->check_duplicates($results));
        }

        if (count($results) > $rules->maxfiles) {
            $issues[] = new issue('toomanyfiles', issue::LEVEL_ERROR, (object) [
                'count' => count($results),
                'max' => $rules->maxfiles,
            ]);
        }

        foreach ($results as $result) {
            $filename = $result->filename;
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if ($rules->allowedextensions && !in_array($extension, $rules->allowedextensions, true)) {
                $issues[] = new issue('badextension', issue::LEVEL_ERROR, (object) [
                    'extension' => $extension === '' ? '?' : $extension,
                    'allowed' => implode(', ', $rules->allowedextensions),
                ], $filename);
                // No point inspecting the inside of a file the activity does not accept.
                continue;
            }

            if ($rules->maxbytes > 0 && $result->filesize > $rules->maxbytes) {
                $issues[] = new issue('toolarge', issue::LEVEL_ERROR, (object) [
                    'size' => display_size($result->filesize),
                    'max' => display_size($rules->maxbytes),
                ], $filename);
            }

            if ($result->encrypted && $rules->rejectencrypted) {
                $issues[] = new issue('encrypted', issue::LEVEL_ERROR, null, $filename);
            }

            if ($result->error !== null && $result->error !== 'errorencrypted') {
                $issues[] = new issue(
                    'unreadable',
                    issue::LEVEL_ERROR,
                    get_string($result->error, 'mod_pagecheck'),
                    $filename
                );
                continue;
            }

            if (!$result->has_page_count()) {
                $level = $rules->unknownpolicy === rules::UNKNOWN_REJECT
                    ? issue::LEVEL_ERROR : issue::LEVEL_WARNING;
                if ($rules->unknownpolicy !== rules::UNKNOWN_ACCEPT) {
                    $issues[] = new issue('unknownpagecount', $level, null, $filename);
                }
            }

            if (!$rules->filename_matches($filename)) {
                $issues[] = new issue(
                    'badfilename',
                    issue::LEVEL_ERROR,
                    $rules->filenamepattern,
                    $filename
                );
            }

            if (
                $rules->pagesize !== page_size::ANY && $result->pagesize !== null
                    && $result->pagesize !== $rules->pagesize
            ) {
                $issues[] = new issue('badpagesize', issue::LEVEL_ERROR, (object) [
                    'found' => page_size::get_name($result->pagesize),
                    'expected' => page_size::get_name($rules->pagesize),
                ], $filename);
            }

            if ($rules->countmode === rules::COUNT_PER_FILE && $result->has_page_count()) {
                $issues = array_merge($issues, $this->check_one_file_pages($result, $rules));
            }

            if ($rules->requiretextlayer && $result->hastext === false) {
                $issues[] = new issue('notextlayer', issue::LEVEL_ERROR, null, $filename);
            }

            if (
                $rules->rejectblankpages && $result->blankpages !== null
                    && $result->blankpages > $rules->blankpagetolerance
            ) {
                $issues[] = new issue('blankpages', issue::LEVEL_WARNING, (object) [
                    'count' => $result->blankpages,
                    'tolerance' => $rules->blankpagetolerance,
                ], $filename);
            }
        }

        return $issues;
    }

    /**
     * Find files attached more than once.
     *
     * Two files with the same content hash are byte for byte the same document, whatever they
     * were named, which is nearly always a student attaching the same work twice by mistake.
     *
     * @param count_result[] $results one result per submitted file
     * @return issue[]
     */
    protected function check_duplicates(array $results): array {
        $seen = [];
        $issues = [];

        foreach ($results as $result) {
            if ($result->contenthash === '') {
                continue;
            }
            if (isset($seen[$result->contenthash])) {
                $issues[] = new issue(
                    'duplicatefile',
                    issue::LEVEL_ERROR,
                    $seen[$result->contenthash],
                    $result->filename
                );
                continue;
            }
            $seen[$result->contenthash] = $result->filename;
        }

        return $issues;
    }

    /**
     * Check one file against the page range, for activities that count each file separately.
     *
     * @param count_result $result the counted file
     * @param rules $rules the effective restrictions
     * @return issue[]
     */
    protected function check_one_file_pages(count_result $result, rules $rules): array {
        $pages = max(0, $result->pages - $rules->countcover);
        $issues = [];

        if ($rules->minpages > 0 && $pages < $rules->minpages) {
            $issues[] = new issue('toofewpages', issue::LEVEL_ERROR, (object) [
                'count' => $pages,
                'min' => $rules->minpages,
            ], $result->filename);
        }
        if ($rules->maxpages > 0 && $pages > $rules->maxpages) {
            $issues[] = new issue('toomanypages', issue::LEVEL_ERROR, (object) [
                'count' => $pages,
                'max' => $rules->maxpages,
            ], $result->filename);
        }

        return $issues;
    }

    /**
     * Check the page count of the submission as a whole.
     *
     * @param count_result[] $results one result per submitted file
     * @param rules $rules the effective restrictions
     * @return issue[]
     */
    protected function check_total_pages(array $results, rules $rules): array {
        if ($rules->minpages <= 0 && $rules->maxpages <= 0) {
            return [];
        }
        if ($rules->countmode === rules::COUNT_PER_FILE) {
            // Each file was already checked on its own, so checking the total too would report
            // the same problem twice with two different numbers.
            return [];
        }

        $total = self::total_pages($results, $rules);
        if ($total === null) {
            // At least one file could not be counted, so the total is unknown. The per file
            // "unknown page count" issue already told the student about it.
            return [];
        }

        $issues = [];
        if ($rules->minpages > 0 && $total < $rules->minpages) {
            $issues[] = new issue('toofewpages', issue::LEVEL_ERROR, (object) [
                'count' => $total,
                'min' => $rules->minpages,
            ]);
        }
        if ($rules->maxpages > 0 && $total > $rules->maxpages) {
            $issues[] = new issue('toomanypages', issue::LEVEL_ERROR, (object) [
                'count' => $total,
                'max' => $rules->maxpages,
            ]);
        }
        return $issues;
    }

    /**
     * The number of pages a submission counts as, once cover pages are discounted.
     *
     * @param count_result[] $results one result per submitted file
     * @param rules $rules the effective restrictions
     * @return int|null the total, or null when any file could not be counted
     */
    public static function total_pages(array $results, rules $rules) {
        $total = 0;
        foreach ($results as $result) {
            if (!$result->has_page_count()) {
                return null;
            }
            $total += max(0, $result->pages - $rules->countcover);
        }
        return $total;
    }
}
