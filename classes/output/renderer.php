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
 * Renderer for mod_pagecheck.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\output;

use mod_pagecheck\counter\count_result;
use mod_pagecheck\counter\page_size;
use mod_pagecheck\local\issue;
use mod_pagecheck\local\rules;
use mod_pagecheck\local\submission_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Turns the state of a submission into what the student reads.
 *
 * The page count leads every screen, because it is the one thing this activity does that the
 * assignment activity does not: a student should see whether their work is the right length
 * without having to read a table.
 */
class renderer extends \plugin_renderer_base {

    /** @var string Everything is within the rules. */
    const STATE_OK = 'ok';

    /** @var string Something is worth telling the student about. */
    const STATE_WARN = 'warn';

    /** @var string Something stops the submission. */
    const STATE_ERROR = 'error';

    /** @var string Nothing has been counted yet. */
    const STATE_UNKNOWN = 'unknown';

    /**
     * Render the list of problems found with a submission.
     *
     * @param issue[] $issues the problems
     * @return string HTML
     */
    public function issue_list(array $issues): string {
        if (!$issues) {
            return '';
        }

        $context = ['checking' => false, 'issues' => []];
        foreach ($issues as $issue) {
            $context['issues'][] = [
                'message' => $issue->get_full_message(),
                'iserror' => $issue->is_error(),
            ];
        }

        return $this->render_from_template('mod_pagecheck/issue_list', $context);
    }

    /**
     * Render the state of an attempt, the rules in force and the files that were counted.
     *
     * @param submission_manager $manager the manager of this activity
     * @param \stdClass|null $submission the attempt, or null when there is none yet
     * @param rules $rules the effective restrictions for this user
     * @param count_result[] $results the counting results, keyed by path name hash
     * @param issue[] $issues the problems found, which decide the colour of the page count
     * @return string HTML
     */
    public function submission_status(submission_manager $manager, $submission, rules $rules,
            array $results, array $issues = []): string {
        $status = $submission ? $submission->status : submission_manager::STATUS_NEW;

        $context = [
            'statuslabel' => get_string('status_' . $status, 'mod_pagecheck'),
            'statusstate' => $this->status_state($status, $issues),
            'meter' => $this->meter($submission, $rules, $results, $issues),
            'facts' => $this->fact_rows($submission, $rules),
            'restrictions' => $this->restriction_rows($rules),
            'files' => [],
            'hasfiles' => false,
            'emptyimage' => $this->image_url('nosubmission', 'mod_pagecheck')->out(false),
            'emptytext' => get_string('nofilesyet', 'mod_pagecheck'),
        ];

        $context['hasfacts'] = !empty($context['facts']);

        if ($submission) {
            foreach ($manager->get_files($submission) as $file) {
                $hash = $file->get_pathnamehash();
                $result = isset($results[$hash]) ? $results[$hash] : null;
                $url = \moodle_url::make_pluginfile_url(
                    $manager->get_context()->id,
                    'mod_pagecheck',
                    submission_manager::FILEAREA,
                    $submission->id,
                    $file->get_filepath(),
                    $file->get_filename()
                );
                $context['files'][] = [
                    'filename' => $file->get_filename(),
                    'url' => $url->out(false),
                    'pages' => $this->format_page_count($result, $rules),
                    'papersize' => $result !== null && $result->pagesize !== null
                        ? page_size::get_name($result->pagesize)
                        : '-',
                    'size' => display_size($file->get_filesize()),
                    'method' => $this->format_method($result),
                    'iserror' => $result !== null && $result->error !== null,
                ];
            }
            $context['hasfiles'] = !empty($context['files']);
        }

        return $this->render_from_template('mod_pagecheck/submission_status', $context);
    }

    /**
     * Which pill to draw beside the submission status.
     *
     * @param string $status the submission status
     * @param issue[] $issues the problems found
     * @return string one of the STATE_* constants
     */
    protected function status_state(string $status, array $issues): string {
        if (issue::has_errors($issues)) {
            return self::STATE_ERROR;
        }
        if ($issues) {
            return self::STATE_WARN;
        }
        return $status === submission_manager::STATUS_SUBMITTED ? self::STATE_OK : 'neutral';
    }

    /**
     * The page count set against the range that was asked for.
     *
     * @param \stdClass|null $submission the attempt
     * @param rules $rules the effective restrictions
     * @param count_result[] $results the counting results
     * @param issue[] $issues the problems found
     * @return array the meter context
     */
    protected function meter($submission, rules $rules, array $results, array $issues): array {
        // Counting each file separately makes a single total meaningless, so the meter steps
        // aside and lets the per file numbers in the table speak instead.
        $perfile = $rules->countmode === rules::COUNT_PER_FILE && count($results) > 1;

        if (!$submission || !$results || $perfile) {
            return ['show' => false];
        }

        $pages = \mod_pagecheck\local\validator::total_pages($results, $rules);
        if ($pages === null) {
            return [
                'show' => true,
                'state' => self::STATE_UNKNOWN,
                'value' => get_string('pagesunknown', 'mod_pagecheck'),
                'unit' => '',
                'hasrange' => false,
                'caption' => get_string('metercannotcount', 'mod_pagecheck'),
            ];
        }

        $min = $rules->minpages;
        $max = $rules->maxpages;
        $hasrange = $min > 0 || $max > 0;

        $meter = [
            'show' => true,
            'value' => (string) $pages,
            'unit' => get_string('pages', 'mod_pagecheck'),
            'hasrange' => $hasrange,
            'state' => self::STATE_OK,
            'caption' => get_string('meterinrange', 'mod_pagecheck'),
        ];

        if (!$hasrange) {
            $meter['state'] = 'neutral';
            $meter['caption'] = get_string('meternorange', 'mod_pagecheck');
            return $meter;
        }

        // The scale runs to a little past whichever is larger, the limit or what was handed in,
        // so a submission well over the maximum still has somewhere to be drawn.
        $ceiling = max($max > 0 ? $max : $min, $pages, 1);
        $scale = max($ceiling * 1.1, 1);

        $meter['percent'] = (int) round(min(100, ($pages / $scale) * 100));
        $meter['bandleft'] = (int) round(($min / $scale) * 100);
        $bandright = $max > 0 ? min(100, ($max / $scale) * 100) : 100;
        $meter['bandwidth'] = (int) round(max(0, $bandright - $meter['bandleft']));
        $meter['scalemin'] = '0';
        $meter['scalemax'] = (string) (int) ceil($scale);
        $meter['rangetext'] = $this->range_text($rules);

        if ($min > 0 && $pages < $min) {
            $meter['state'] = self::STATE_ERROR;
            $meter['caption'] = get_string('metershort', 'mod_pagecheck', $min - $pages);
        } else if ($max > 0 && $pages > $max) {
            $meter['state'] = self::STATE_ERROR;
            $meter['caption'] = get_string('meterover', 'mod_pagecheck', $pages - $max);
        } else if ($issues) {
            $meter['state'] = self::STATE_WARN;
        }

        // "Warn only" never paints a refusal the activity is not going to make.
        if ($meter['state'] === self::STATE_ERROR && !issue::has_errors($issues)) {
            $meter['state'] = self::STATE_WARN;
        }

        return $meter;
    }

    /**
     * The dates and other facts about the attempt.
     *
     * @param \stdClass|null $submission the attempt
     * @param rules $rules the effective restrictions
     * @return array
     */
    protected function fact_rows($submission, rules $rules): array {
        $rows = [];

        if ($rules->allowsubmissionsfromdate > 0) {
            $rows[] = [
                'label' => get_string('allowsubmissionsfromdate', 'mod_pagecheck'),
                'value' => userdate($rules->allowsubmissionsfromdate),
            ];
        }
        if ($rules->duedate > 0) {
            $rows[] = [
                'label' => get_string('duedate', 'mod_pagecheck'),
                'value' => userdate($rules->duedate),
            ];
        }
        if ($rules->cutoffdate > 0) {
            $rows[] = [
                'label' => get_string('cutoffdate', 'mod_pagecheck'),
                'value' => userdate($rules->cutoffdate),
            ];
        }
        if ($submission && $submission->timesubmitted > 0) {
            $rows[] = [
                'label' => get_string('timesubmitted', 'mod_pagecheck'),
                'value' => userdate($submission->timesubmitted),
            ];
        }

        return $rows;
    }

    /**
     * How the page range reads in words.
     *
     * @param rules $rules the effective restrictions
     * @return string
     */
    protected function range_text(rules $rules): string {
        if ($rules->minpages > 0 && $rules->maxpages > 0) {
            return get_string('pagesbetween', 'mod_pagecheck', (object) [
                'min' => $rules->minpages,
                'max' => $rules->maxpages,
            ]);
        }
        if ($rules->minpages > 0) {
            return get_string('pagesatleast', 'mod_pagecheck', $rules->minpages);
        }
        if ($rules->maxpages > 0) {
            return get_string('pagesatmost', 'mod_pagecheck', $rules->maxpages);
        }
        return get_string('pagesnolimit', 'mod_pagecheck');
    }

    /**
     * The rows describing the restrictions in force for this user.
     *
     * @param rules $rules the effective restrictions
     * @return array
     */
    protected function restriction_rows(rules $rules): array {
        $rows = [[
            'label' => get_string('pages', 'mod_pagecheck'),
            'value' => $this->range_text($rules),
        ]];

        if ($rules->countmode === rules::COUNT_PER_FILE) {
            $rows[] = [
                'label' => get_string('countmode', 'mod_pagecheck'),
                'value' => get_string('countmode_perfile', 'mod_pagecheck'),
            ];
        }

        if ($rules->countcover > 0) {
            $rows[] = [
                'label' => get_string('countcover', 'mod_pagecheck'),
                'value' => $rules->countcover,
            ];
        }

        if ($rules->pagesize !== page_size::ANY) {
            $rows[] = [
                'label' => get_string('papersize', 'mod_pagecheck'),
                'value' => page_size::get_name($rules->pagesize),
            ];
        }

        $rows[] = [
            'label' => get_string('allowedextensions', 'mod_pagecheck'),
            'value' => $rules->allowedextensions
                ? '.' . implode(', .', $rules->allowedextensions)
                : get_string('anyfiletype', 'mod_pagecheck'),
        ];

        $rows[] = [
            'label' => get_string('maxfiles', 'mod_pagecheck'),
            'value' => $rules->minfiles > 0
                ? get_string('filesbetween', 'mod_pagecheck',
                    (object) ['min' => $rules->minfiles, 'max' => $rules->maxfiles])
                : $rules->maxfiles,
        ];

        if ($rules->filenamepattern !== '') {
            $rows[] = [
                'label' => get_string('filenamepattern', 'mod_pagecheck'),
                'value' => $rules->filenamepattern,
            ];
        }

        if ($rules->maxbytes > 0) {
            $rows[] = [
                'label' => get_string('maxbytes', 'mod_pagecheck'),
                'value' => display_size($rules->maxbytes),
            ];
        }

        $rows[] = [
            'label' => get_string('maxattempts', 'mod_pagecheck'),
            'value' => $rules->maxattempts < 0
                ? get_string('unlimitedattempts', 'mod_pagecheck')
                : $rules->maxattempts,
        ];

        $checks = [];
        if ($rules->rejectencrypted) {
            $checks[] = get_string('rejectencrypted', 'mod_pagecheck');
        }
        if ($rules->requiretextlayer) {
            $checks[] = get_string('requiretextlayer', 'mod_pagecheck');
        }
        if ($rules->rejectblankpages) {
            $checks[] = get_string('rejectblankpages', 'mod_pagecheck');
        }
        if ($rules->rejectduplicates) {
            $checks[] = get_string('rejectduplicates', 'mod_pagecheck');
        }
        if ($checks) {
            $rows[] = [
                'label' => get_string('documentchecks', 'mod_pagecheck'),
                'value' => implode('; ', $checks),
            ];
        }

        return $rows;
    }

    /**
     * The page count of a file as the student should read it.
     *
     * @param count_result|null $result the counting result
     * @param rules $rules the effective restrictions
     * @return string
     */
    protected function format_page_count($result, rules $rules): string {
        if ($result === null) {
            return get_string('pagesunknown', 'mod_pagecheck');
        }
        if ($result->error !== null) {
            return get_string($result->error, 'mod_pagecheck');
        }
        if (!$result->has_page_count()) {
            return get_string('pagesunknown', 'mod_pagecheck');
        }
        if ($rules->countcover > 0) {
            return get_string('pagescounted', 'mod_pagecheck', (object) [
                'counted' => max(0, $result->pages - $rules->countcover),
                'total' => $result->pages,
            ]);
        }
        return (string) $result->pages;
    }

    /**
     * How a page count was obtained, in words.
     *
     * @param count_result|null $result the counting result
     * @return string
     */
    protected function format_method($result): string {
        $method = $result === null ? count_result::METHOD_UNKNOWN : $result->method;
        return get_string('method_' . $method, 'mod_pagecheck');
    }
}
