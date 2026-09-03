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
     * The SELECT list for reading a participant.
     *
     * \core_user\fields::get_sql() decides on its own whether to lead with a comma, and that
     * choice is what the caller passes in. Normalising it in one place means no caller has to know
     * the convention, and gluing the id column on can never run two column names together.
     *
     * @return string
     */
    public static function user_fields_sql(): string {
        $fields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $fields = ltrim(trim($fields), ',');

        return $fields === '' ? 'u.id' : 'u.id, ' . $fields;
    }

    /**
     * Everyone the report should account for.
     *
     * This is the enrolled participants plus anyone who actually submitted, because the two are
     * not the same set. A site administrator is not enrolled, and a student can be unenrolled
     * after handing work in; in both cases the work is still there, and a teacher who opens this
     * page must not be told there is nothing to show. Users who turn up only through their
     * submission are flagged so the teacher can see why they are listed.
     *
     * @param \context_module $context the module context
     * @param int $pagecheckid the activity instance id
     * @param int $groupid the group being viewed, or 0 for all of them
     * @return \stdClass[] user records keyed by user id
     */
    public static function get_participants(
        \context_module $context,
        int $pagecheckid,
        int $groupid = 0
    ): array {
        global $DB;

        $fields = self::user_fields_sql();

        $participants = get_enrolled_users(
            $context,
            'mod/pagecheck:submit',
            $groupid,
            $fields,
            'u.lastname, u.firstname'
        );

        $submitters = $DB->get_records_sql(
            "SELECT DISTINCT $fields
               FROM {user} u
               JOIN {pagecheck_submissions} s ON s.userid = u.id
              WHERE s.pagecheckid = :pagecheckid AND u.deleted = 0",
            ['pagecheckid' => $pagecheckid]
        );

        $added = false;
        foreach ($submitters as $user) {
            if (isset($participants[$user->id])) {
                continue;
            }
            if ($groupid > 0 && !groups_is_member($groupid, $user->id)) {
                continue;
            }
            $user->pagechecknotenrolled = true;
            $participants[$user->id] = $user;
            $added = true;
        }

        if ($added) {
            // Enrolled users arrive sorted already; re-sort once the extras are in.
            foreach ($participants as $user) {
                $user->pagechecksortname = $user->lastname . ' ' . $user->firstname;
            }
            \core_collator::asort_objects_by_property($participants, 'pagechecksortname');
            foreach ($participants as $user) {
                unset($user->pagechecksortname);
            }
        }

        return $participants;
    }

    /**
     * Build one row per participant.
     *
     * @param array $participants the users to report on
     * @param submission_manager $manager the manager of this activity
     * @param string $filter one of the FILTER_* constants
     * @return \stdClass[] rows with user, submission, status, pages, issues and grade
     */
    public static function build_rows(
        array $participants,
        submission_manager $manager,
        string $filter = self::FILTER_ALL
    ): array {
        global $DB;

        $grades = $DB->get_records_menu(
            'pagecheck_grades',
            ['pagecheckid' => $manager->get_instance()->id],
            '',
            'userid, grade'
        );

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
