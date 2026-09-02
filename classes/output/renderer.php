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
use mod_pagecheck\local\issue;
use mod_pagecheck\local\rules;
use mod_pagecheck\local\submission_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Turns the state of a submission into the tables and notices the student reads.
 */
class renderer extends \plugin_renderer_base {

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
     * @return string HTML
     */
    public function submission_status(submission_manager $manager, $submission, rules $rules,
            array $results): string {
        $context = [
            'status' => $this->status_rows($submission, $rules),
            'restrictions' => $this->restriction_rows($rules),
            'files' => [],
            'hasfiles' => false,
        ];

        if ($submission) {
            foreach ($manager->get_files($submission) as $file) {
                $hash = $file->get_pathnamehash();
                $result = isset($results[$hash]) ? $results[$hash] : null;
                $url = \moodle_url::make_pluginfile_url(
                    $manager->get_context()->id,
                    'mod_pagecheck',
                    PAGECHECK_FILEAREA_SUBMISSION,
                    $submission->id,
                    $file->get_filepath(),
                    $file->get_filename()
                );
                $context['files'][] = [
                    'filename' => $file->get_filename(),
                    'url' => $url->out(false),
                    'pages' => $this->format_page_count($result, $rules),
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
     * The rows describing the state of the attempt.
     *
     * @param \stdClass|null $submission the attempt
     * @param rules $rules the effective restrictions
     * @return array
     */
    protected function status_rows($submission, rules $rules): array {
        $status = $submission ? $submission->status : submission_manager::STATUS_NEW;

        $rows = [[
            'label' => get_string('submissionstatus', 'mod_pagecheck'),
            'value' => get_string('status_' . $status, 'mod_pagecheck'),
        ]];

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
        if ($submission && $submission->totalpages !== null) {
            $rows[] = [
                'label' => get_string('totalpages', 'mod_pagecheck'),
                'value' => (int) $submission->totalpages,
            ];
        }

        return $rows;
    }

    /**
     * The rows describing the restrictions in force for this user.
     *
     * @param rules $rules the effective restrictions
     * @return array
     */
    protected function restriction_rows(rules $rules): array {
        $rows = [];

        if ($rules->minpages > 0 && $rules->maxpages > 0) {
            $pages = get_string('pagesbetween', 'mod_pagecheck', (object) [
                'min' => $rules->minpages,
                'max' => $rules->maxpages,
            ]);
        } else if ($rules->minpages > 0) {
            $pages = get_string('pagesatleast', 'mod_pagecheck', $rules->minpages);
        } else if ($rules->maxpages > 0) {
            $pages = get_string('pagesatmost', 'mod_pagecheck', $rules->maxpages);
        } else {
            $pages = get_string('pagesnolimit', 'mod_pagecheck');
        }
        $rows[] = ['label' => get_string('pages', 'mod_pagecheck'), 'value' => $pages];

        if ($rules->countcover > 0) {
            $rows[] = [
                'label' => get_string('countcover', 'mod_pagecheck'),
                'value' => $rules->countcover,
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
            'value' => $rules->maxfiles,
        ];
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
