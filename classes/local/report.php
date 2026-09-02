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
 * Assembles the rows of the submissions report.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Turns a list of participants into what the teacher sees, and into what a CSV export contains.
 *
 * Both outputs come from the same rows, so the file a teacher downloads always says the same
 * thing as the screen they downloaded it from.
 */
class report {

    /** @var string Every participant. */
    const FILTER_ALL = 'all';

    /** @var string Only those who sent work for grading. */
    const FILTER_SUBMITTED = 'submitted';

    /** @var string Only those who did not. */
    const FILTER_NOTSUBMITTED = 'notsubmitted';

    /** @var string Only those whose submission failed a check. */
    const FILTER_WITHISSUES = 'withissues';

    /**
     * Build one row per participant.
     *
     * @param array $participants the users to report on
     * @param submission_manager $manager the manager of this activity
     * @param string $filter one of the FILTER_* constants
     * @return \stdClass[] rows with user, submission, status, pages, issues and grade
     */
    public static function build_rows(array $participants, submission_manager $manager,
            string $filter = self::FILTER_ALL): array {
        global $DB;

        $grades = $DB->get_records_menu('pagecheck_grades',
            ['pagecheckid' => $manager->get_instance()->id], '', 'userid, grade');

        $rows = [];
        foreach ($participants as $user) {
            $userid = (int) $user->id;
            $submission = $manager->get_submission($userid);
            $issues = [];
            $pages = null;

            if ($submission) {
                $issues = $manager->validate($userid, $submission);
                $pages = $submission->totalpages;
            }

            $status = $submission ? $submission->status : submission_manager::STATUS_NEW;

            if (!self::passes_filter($filter, $status, $issues)) {
                continue;
            }

            $rows[] = (object) [
                'user' => $user,
                'submission' => $submission,
                'status' => $status,
                'pages' => $pages,
                'issues' => $issues,
                'grade' => isset($grades[$userid]) ? $grades[$userid] : null,
            ];
        }

        return $rows;
    }

    /**
     * Whether a row survives the chosen filter.
     *
     * @param string $filter one of the FILTER_* constants
     * @param string $status the submission status of the row
     * @param issue[] $issues the issues found with the submission
     * @return bool
     */
    protected static function passes_filter(string $filter, string $status, array $issues): bool {
        switch ($filter) {
            case self::FILTER_SUBMITTED:
                return $status === submission_manager::STATUS_SUBMITTED;
            case self::FILTER_NOTSUBMITTED:
                return $status !== submission_manager::STATUS_SUBMITTED;
            case self::FILTER_WITHISSUES:
                return !empty($issues);
            default:
                return true;
        }
    }

    /**
     * The filters a teacher can choose between.
     *
     * @return array filter constant => translated label
     */
    public static function get_filter_options(): array {
        return [
            self::FILTER_ALL => get_string('filterall', 'mod_pagecheck'),
            self::FILTER_SUBMITTED => get_string('filtersubmitted', 'mod_pagecheck'),
            self::FILTER_NOTSUBMITTED => get_string('filternotsubmitted', 'mod_pagecheck'),
            self::FILTER_WITHISSUES => get_string('filterwithissues', 'mod_pagecheck'),
        ];
    }
}
