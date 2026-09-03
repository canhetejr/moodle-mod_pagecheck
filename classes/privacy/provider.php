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
 * Privacy provider for mod_pagecheck.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use mod_pagecheck\local\submission_manager;

/**
 * Describes and exports the personal data this activity stores.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data this plugin stores.
     *
     * @param collection $collection the collection to add to
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('pagecheck_submissions', [
            'userid' => 'privacy:metadata:submissions:userid',
            'groupid' => 'privacy:metadata:submissions:groupid',
            'attemptnumber' => 'privacy:metadata:submissions:attemptnumber',
            'status' => 'privacy:metadata:submissions:status',
            'totalpages' => 'privacy:metadata:submissions:totalpages',
            'timecreated' => 'privacy:metadata:submissions:timecreated',
            'timemodified' => 'privacy:metadata:submissions:timemodified',
            'timesubmitted' => 'privacy:metadata:submissions:timesubmitted',
        ], 'privacy:metadata:submissions');

        $collection->add_database_table('pagecheck_files', [
            'filename' => 'privacy:metadata:files:filename',
            'filesize' => 'privacy:metadata:files:filesize',
            'pagecount' => 'privacy:metadata:files:pagecount',
            'status' => 'privacy:metadata:files:status',
        ], 'privacy:metadata:files');

        $collection->add_database_table('pagecheck_grades', [
            'userid' => 'privacy:metadata:grades:userid',
            'grade' => 'privacy:metadata:grades:grade',
            'feedback' => 'privacy:metadata:grades:feedback',
            'grader' => 'privacy:metadata:grades:grader',
            'timemodified' => 'privacy:metadata:grades:timemodified',
        ], 'privacy:metadata:grades');

        $collection->add_database_table('pagecheck_overrides', [
            'userid' => 'privacy:metadata:overrides:userid',
            'duedate' => 'privacy:metadata:overrides:duedate',
            'cutoffdate' => 'privacy:metadata:overrides:cutoffdate',
            'maxattempts' => 'privacy:metadata:overrides:maxattempts',
        ], 'privacy:metadata:overrides');

        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:filepurpose');

        return $collection;
    }

    /**
     * The contexts holding data about a user.
     *
     * @param int $userid the user
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {pagecheck} p ON p.id = cm.instance
             LEFT JOIN {pagecheck_submissions} s ON s.pagecheckid = p.id AND s.userid = :userid1
             LEFT JOIN {pagecheck_grades} g ON g.pagecheckid = p.id AND g.userid = :userid2
             LEFT JOIN {pagecheck_overrides} o ON o.pagecheckid = p.id AND o.userid = :userid3
                 WHERE s.id IS NOT NULL OR g.id IS NOT NULL OR o.id IS NOT NULL";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'pagecheck',
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * The users holding data in a context.
     *
     * @param userlist $userlist the list to add to
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $params = ['cmid' => $context->instanceid, 'modname' => 'pagecheck'];
        $join = "JOIN {course_modules} cm ON cm.id = :cmid
                 JOIN {modules} m ON m.id = cm.module AND m.name = :modname";

        $userlist->add_from_sql(
            'userid',
            "SELECT s.userid FROM {pagecheck_submissions} s $join WHERE s.pagecheckid = cm.instance",
            $params
        );
        $userlist->add_from_sql(
            'userid',
            "SELECT g.userid FROM {pagecheck_grades} g $join WHERE g.pagecheckid = cm.instance",
            $params
        );
        $userlist->add_from_sql(
            'userid',
            "SELECT o.userid FROM {pagecheck_overrides} o $join
              WHERE o.pagecheckid = cm.instance AND o.userid > 0",
            $params
        );
    }

    /**
     * Export the data of the approved contexts.
     *
     * @param approved_contextlist $contextlist the contexts approved for export
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $cm = get_coursemodule_from_id('pagecheck', $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }

            $submissions = $DB->get_records(
                'pagecheck_submissions',
                ['pagecheckid' => $cm->instance, 'userid' => $user->id]
            );

            foreach ($submissions as $submission) {
                $subcontext = [
                    get_string('privacy:submissionpath', 'mod_pagecheck'),
                    $submission->attemptnumber + 1,
                ];

                $files = $DB->get_records('pagecheck_files', ['submissionid' => $submission->id]);
                $data = (object) [
                    'status' => $submission->status,
                    'totalpages' => $submission->totalpages,
                    'timecreated' => transform::datetime($submission->timecreated),
                    'timemodified' => transform::datetime($submission->timemodified),
                    'timesubmitted' => $submission->timesubmitted
                        ? transform::datetime($submission->timesubmitted) : null,
                    'files' => array_values(array_map(function ($file) {
                        return (object) [
                            'filename' => $file->filename,
                            'filesize' => $file->filesize,
                            'pagecount' => $file->pagecount,
                            'status' => $file->status,
                        ];
                    }, $files)),
                ];

                writer::with_context($context)->export_data($subcontext, $data);
                writer::with_context($context)->export_area_files(
                    $subcontext,
                    'mod_pagecheck',
                    submission_manager::FILEAREA,
                    $submission->id
                );
            }

            $grade = $DB->get_record(
                'pagecheck_grades',
                ['pagecheckid' => $cm->instance, 'userid' => $user->id]
            );
            if ($grade) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:gradepath', 'mod_pagecheck')],
                    (object) [
                        'grade' => $grade->grade,
                        'feedback' => $grade->feedback,
                        'timemodified' => transform::datetime($grade->timemodified),
                    ]
                );
            }
        }
    }

    /**
     * Delete every user's data in a context.
     *
     * @param \context $context the context to purge
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('pagecheck', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        get_file_storage()->delete_area_files(
            $context->id,
            'mod_pagecheck',
            submission_manager::FILEAREA
        );

        $submissionids = $DB->get_fieldset_select(
            'pagecheck_submissions',
            'id',
            'pagecheckid = ?',
            [$cm->instance]
        );
        if ($submissionids) {
            [$insql, $params] = $DB->get_in_or_equal($submissionids);
            $DB->delete_records_select('pagecheck_files', "submissionid $insql", $params);
        }
        $DB->delete_records('pagecheck_submissions', ['pagecheckid' => $cm->instance]);
        $DB->delete_records('pagecheck_grades', ['pagecheckid' => $cm->instance]);
        $DB->delete_records('pagecheck_overrides', ['pagecheckid' => $cm->instance]);
    }

    /**
     * Delete the data of one user across the approved contexts.
     *
     * @param approved_contextlist $contextlist the contexts approved for deletion
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        $userids = [$contextlist->get_user()->id];
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_users_in_context($context, $userids);
        }
    }

    /**
     * Delete the data of several users in one context.
     *
     * @param approved_userlist $userlist the users approved for deletion
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        self::delete_users_in_context($userlist->get_context(), $userlist->get_userids());
    }

    /**
     * Remove every trace of the given users from one activity.
     *
     * @param \context $context the module context
     * @param array $userids the users to remove
     * @return void
     */
    protected static function delete_users_in_context(\context $context, array $userids) {
        global $DB;

        if (!$context instanceof \context_module || !$userids) {
            return;
        }
        $cm = get_coursemodule_from_id('pagecheck', $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['pagecheckid'] = $cm->instance;

        $submissionids = $DB->get_fieldset_select(
            'pagecheck_submissions',
            'id',
            "pagecheckid = :pagecheckid AND userid $insql",
            $params
        );

        if ($submissionids) {
            $fs = get_file_storage();
            foreach ($submissionids as $submissionid) {
                $fs->delete_area_files(
                    $context->id,
                    'mod_pagecheck',
                    submission_manager::FILEAREA,
                    $submissionid
                );
            }
            [$fileinsql, $fileparams] = $DB->get_in_or_equal($submissionids);
            $DB->delete_records_select('pagecheck_files', "submissionid $fileinsql", $fileparams);
            $DB->delete_records_select(
                'pagecheck_submissions',
                "pagecheckid = :pagecheckid AND userid $insql",
                $params
            );
        }

        $DB->delete_records_select(
            'pagecheck_grades',
            "pagecheckid = :pagecheckid AND userid $insql",
            $params
        );
        $DB->delete_records_select(
            'pagecheck_overrides',
            "pagecheckid = :pagecheckid AND userid $insql",
            $params
        );
    }
}
