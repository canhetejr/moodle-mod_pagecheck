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
 * Creates, analyses and stores the submissions of one activity.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\local;

use mod_pagecheck\counter\count_result;
use mod_pagecheck\counter\counter_factory;

defined('MOODLE_INTERNAL') || die();

/**
 * Everything that happens between a student choosing a file and the teacher seeing the result.
 *
 * Counting a file is the expensive part of this plugin, so every result is cached in the
 * pagecheck_files table and only recomputed when the content of the file actually changed.
 */
class submission_manager {

    /** @var string File area holding the files a student submitted. */
    const FILEAREA = 'submission';

    /** @var string No attempt has been started. */
    const STATUS_NEW = 'new';

    /** @var string Files have been saved but not sent for grading. */
    const STATUS_DRAFT = 'draft';

    /** @var string Sent for grading. */
    const STATUS_SUBMITTED = 'submitted';

    /** @var string Sent back to the student for another attempt. */
    const STATUS_REOPENED = 'reopened';

    /** @var \stdClass|\cm_info The course module. */
    protected $cm;

    /** @var \stdClass The activity instance record. */
    protected $pagecheck;

    /** @var \context_module The module context. */
    protected $context;

    /**
     * Build a manager for one activity.
     *
     * @param \stdClass|\cm_info $cm the course module
     * @param \stdClass $pagecheck the activity instance record
     * @param \context_module $context the module context
     */
    public function __construct($cm, \stdClass $pagecheck, \context_module $context) {
        $this->cm = $cm;
        $this->pagecheck = $pagecheck;
        $this->context = $context;
    }

    /**
     * The activity instance record.
     *
     * @return \stdClass
     */
    public function get_instance(): \stdClass {
        return $this->pagecheck;
    }

    /**
     * The module context.
     *
     * @return \context_module
     */
    public function get_context(): \context_module {
        return $this->context;
    }

    /**
     * The effective restrictions for a user.
     *
     * @param int $userid the user
     * @return rules
     */
    public function get_rules(int $userid): rules {
        return rules::for_user($this->pagecheck, $userid);
    }

    /**
     * The group a user submits as, or 0 when they submit on their own.
     *
     * @param int $userid the user
     * @return int
     */
    public function get_group_id(int $userid): int {
        if (empty($this->pagecheck->teamsubmission)) {
            return 0;
        }
        $groupingid = isset($this->cm->groupingid) ? (int) $this->cm->groupingid : 0;
        $groups = groups_get_all_groups($this->pagecheck->course, $userid, $groupingid, 'g.id');
        if (!$groups) {
            // A student who belongs to no group submits alone, into the "default" group 0.
            return 0;
        }
        $group = reset($groups);
        return (int) $group->id;
    }

    /**
     * The most recent attempt of a user, optionally creating one.
     *
     * @param int $userid the user
     * @param bool $create whether to create the attempt when there is none
     * @return \stdClass|null the submission record
     */
    public function get_submission(int $userid, bool $create = false) {
        global $DB;

        $groupid = $this->get_group_id($userid);
        $params = ['pagecheckid' => $this->pagecheck->id, 'groupid' => $groupid];
        if ($groupid > 0) {
            // A group submission belongs to the group, whoever of its members touched it last.
            $where = 'pagecheckid = :pagecheckid AND groupid = :groupid AND latest = 1';
        } else {
            $where = 'pagecheckid = :pagecheckid AND groupid = 0 AND userid = :userid AND latest = 1';
            $params['userid'] = $userid;
        }

        $submission = $DB->get_record_select('pagecheck_submissions', $where, $params);
        if ($submission) {
            return $submission;
        }
        if (!$create) {
            return null;
        }

        return $this->create_attempt($userid, $groupid, 0);
    }

    /**
     * Create an attempt record.
     *
     * @param int $userid the user the attempt belongs to
     * @param int $groupid the group the attempt belongs to, or 0
     * @param int $attemptnumber the zero based attempt number
     * @return \stdClass the new submission record
     */
    protected function create_attempt(int $userid, int $groupid, int $attemptnumber): \stdClass {
        global $DB;

        $now = time();
        $submission = (object) [
            'pagecheckid' => $this->pagecheck->id,
            'userid' => $groupid > 0 ? 0 : $userid,
            'groupid' => $groupid,
            'attemptnumber' => $attemptnumber,
            'status' => self::STATUS_NEW,
            'totalpages' => null,
            'latest' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
            'timesubmitted' => 0,
        ];
        $submission->id = $DB->insert_record('pagecheck_submissions', $submission);

        return $submission;
    }

    /**
     * Every attempt a user has made, oldest first.
     *
     * @param int $userid the user
     * @return \stdClass[] the submission records
     */
    public function get_attempts(int $userid): array {
        global $DB;

        $groupid = $this->get_group_id($userid);
        if ($groupid > 0) {
            $where = 'pagecheckid = :pagecheckid AND groupid = :groupid';
            $params = ['pagecheckid' => $this->pagecheck->id, 'groupid' => $groupid];
        } else {
            $where = 'pagecheckid = :pagecheckid AND groupid = 0 AND userid = :userid';
            $params = ['pagecheckid' => $this->pagecheck->id, 'userid' => $userid];
        }

        return $DB->get_records_select('pagecheck_submissions', $where, $params, 'attemptnumber ASC');
    }

    /**
     * How many attempts a user has already sent for grading.
     *
     * @param int $userid the user
     * @return int
     */
    public function get_attempts_used(int $userid): int {
        global $DB;

        $groupid = $this->get_group_id($userid);
        if ($groupid > 0) {
            $where = 'pagecheckid = :pagecheckid AND groupid = :groupid AND timesubmitted > 0';
            $params = ['pagecheckid' => $this->pagecheck->id, 'groupid' => $groupid];
        } else {
            $where = 'pagecheckid = :pagecheckid AND groupid = 0 AND userid = :userid AND timesubmitted > 0';
            $params = ['pagecheckid' => $this->pagecheck->id, 'userid' => $userid];
        }

        return $DB->count_records_select('pagecheck_submissions', $where, $params);
    }

    /**
     * The files attached to an attempt.
     *
     * @param \stdClass $submission the submission record
     * @return \stored_file[]
     */
    public function get_files(\stdClass $submission): array {
        $files = get_file_storage()->get_area_files(
            $this->context->id,
            'mod_pagecheck',
            self::FILEAREA,
            $submission->id,
            'filename',
            false
        );
        return array_values($files);
    }

    /**
     * Count the pages of every file of an attempt, reusing cached results where possible.
     *
     * @param \stdClass $submission the submission record
     * @param rules $rules the effective restrictions, which decide how deeply to look
     * @param bool $force whether to recount even when a cached result is available
     * @return count_result[] keyed by path name hash
     */
    public function analyse(\stdClass $submission, rules $rules, bool $force = false): array {
        global $DB;

        $analysetext = $rules->requiretextlayer || $rules->rejectblankpages;
        $options = [
            'analysetext' => $analysetext,
            'usegs' => (bool) get_config('mod_pagecheck', 'useghostscript'),
        ];

        $cached = $DB->get_records('pagecheck_files', ['submissionid' => $submission->id], '', '*');
        $byhash = [];
        foreach ($cached as $record) {
            $byhash[$record->pathnamehash] = $record;
        }

        $results = [];
        $seen = [];

        foreach ($this->get_files($submission) as $file) {
            $hash = $file->get_pathnamehash();
            $seen[$hash] = true;
            $record = isset($byhash[$hash]) ? $byhash[$hash] : null;

            // A null paper size means the row was written before this plugin knew how to read
            // one, not that the format has none: that case is stored as an empty string. Such a
            // row is recounted once, so an existing submission heals itself on the next visit.
            if (!$force && $record && $record->contenthash === $file->get_contenthash()
                    && $record->pagesize !== null
                    && (!$analysetext || $record->hastext !== null)) {
                $results[$hash] = $this->result_from_record($record);
                continue;
            }

            $result = counter_factory::count_stored_file($file, $options);
            $results[$hash] = $result;
            $this->store_result($submission, $file, $result, $record);
        }

        // Forget the files that are no longer part of the attempt.
        foreach ($byhash as $hash => $record) {
            if (empty($seen[$hash])) {
                $DB->delete_records('pagecheck_files', ['id' => $record->id]);
            }
        }

        $total = validator::total_pages($results, $rules);
        $stored = $submission->totalpages === null ? null : (int) $submission->totalpages;
        if ($stored !== $total) {
            $submission->totalpages = $total;
            $DB->set_field('pagecheck_submissions', 'totalpages', $total, ['id' => $submission->id]);
        }

        return $results;
    }

    /**
     * Rebuild a count result from its cached row.
     *
     * @param \stdClass $record a pagecheck_files row
     * @return count_result
     */
    protected function result_from_record(\stdClass $record): count_result {
        $result = new count_result();
        $result->pages = $record->pagecount === null ? null : (int) $record->pagecount;
        $result->method = $record->countmethod;
        $result->pagesize = ($record->pagesize === null || $record->pagesize === '')
            ? null : $record->pagesize;
        $result->contenthash = (string) $record->contenthash;
        $result->encrypted = (bool) $record->encrypted;
        $result->hastext = $record->hastext === null ? null : (bool) $record->hastext;
        $result->blankpages = $record->blankpages === null ? null : (int) $record->blankpages;
        $result->filename = $record->filename;
        $result->filesize = (int) $record->filesize;
        $result->mimetype = $record->mimetype;

        $issues = json_decode((string) $record->issues, true);
        if (is_array($issues) && isset($issues['error'])) {
            $result->error = $issues['error'];
        }

        return $result;
    }

    /**
     * Store the outcome of counting one file.
     *
     * @param \stdClass $submission the submission record
     * @param \stored_file $file the file that was counted
     * @param count_result $result what the counter found
     * @param \stdClass|null $existing the row to update, when there is one
     * @return void
     */
    protected function store_result(\stdClass $submission, \stored_file $file,
            count_result $result, $existing = null) {
        global $DB;

        $record = (object) [
            'submissionid' => $submission->id,
            'pathnamehash' => $file->get_pathnamehash(),
            'contenthash' => $file->get_contenthash(),
            'filename' => $file->get_filename(),
            'mimetype' => (string) $file->get_mimetype(),
            'filesize' => (int) $file->get_filesize(),
            'pagecount' => $result->pages,
            'countmethod' => $result->method,
            // Empty means "looked at, and this format has no paper size"; null is reserved for
            // rows an older version of the plugin wrote.
            'pagesize' => $result->pagesize === null ? '' : $result->pagesize,
            'hastext' => $result->hastext === null ? null : (int) $result->hastext,
            'blankpages' => $result->blankpages,
            'encrypted' => (int) $result->encrypted,
            'status' => $result->error === null ? 'ok' : 'error',
            'issues' => $result->error === null ? null : json_encode(['error' => $result->error]),
            'timemodified' => time(),
        ];

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('pagecheck_files', $record);
        } else {
            $DB->insert_record('pagecheck_files', $record);
        }
    }

    /**
     * Validate the current state of a user's attempt.
     *
     * @param int $userid the user
     * @param \stdClass|null $submission the attempt, looked up when not given
     * @param array $context extra validation context, see validator::validate()
     * @return issue[]
     */
    public function validate(int $userid, $submission = null, array $context = []): array {
        $rules = $this->get_rules($userid);
        $submission = $submission ?: $this->get_submission($userid);

        $results = $submission ? $this->analyse($submission, $rules) : [];
        $context += ['attemptsused' => $this->get_attempts_used($userid)];

        return (new validator())->validate($results, $rules, $context);
    }

    /**
     * Move the files a student picked into the submission file area.
     *
     * @param \stdClass $submission the submission record
     * @param int $draftitemid the draft area holding the chosen files
     * @param rules $rules the effective restrictions
     * @return void
     */
    public function save_files(\stdClass $submission, int $draftitemid, rules $rules) {
        global $DB;

        file_save_draft_area_files(
            $draftitemid,
            $this->context->id,
            'mod_pagecheck',
            self::FILEAREA,
            $submission->id,
            $this->get_filemanager_options($rules)
        );

        if ($submission->status === self::STATUS_NEW) {
            $submission->status = self::STATUS_DRAFT;
        }
        $submission->timemodified = time();
        $DB->update_record('pagecheck_submissions', $submission);
    }

    /**
     * The options the file manager element is built with.
     *
     * @param rules $rules the effective restrictions
     * @return array
     */
    public function get_filemanager_options(rules $rules): array {
        $maxbytes = $rules->maxbytes > 0 ? $rules->maxbytes : $this->get_course_max_bytes();

        return [
            'subdirs' => 0,
            'maxbytes' => $maxbytes,
            'maxfiles' => $rules->maxfiles,
            'accepted_types' => $rules->allowedextensions
                ? array_map(function($extension) {
                    return '.' . $extension;
                }, $rules->allowedextensions)
                : '*',
        ];
    }

    /**
     * The upload limit of the course this activity belongs to.
     *
     * @return int
     */
    protected function get_course_max_bytes(): int {
        global $CFG, $DB;

        $maxbytes = $DB->get_field('course', 'maxbytes', ['id' => $this->pagecheck->course]);
        if ($maxbytes === false || (int) $maxbytes === 0) {
            return (int) $CFG->maxbytes;
        }
        return (int) $maxbytes;
    }

    /**
     * Mark an attempt as sent for grading.
     *
     * @param \stdClass $submission the submission record
     * @return void
     */
    public function submit_for_grading(\stdClass $submission) {
        global $DB;

        $submission->status = self::STATUS_SUBMITTED;
        $submission->timesubmitted = time();
        $submission->timemodified = $submission->timesubmitted;
        $DB->update_record('pagecheck_submissions', $submission);
    }

    /**
     * Start a further attempt for a user, keeping the previous one on record.
     *
     * @param int $userid the user
     * @return \stdClass the new submission record
     */
    public function add_new_attempt(int $userid): \stdClass {
        global $DB;

        $current = $this->get_submission($userid, true);
        $DB->set_field('pagecheck_submissions', 'latest', 0, ['id' => $current->id]);

        $submission = $this->create_attempt($userid, (int) $current->groupid, (int) $current->attemptnumber + 1);
        $submission->status = self::STATUS_REOPENED;
        $DB->set_field('pagecheck_submissions', 'status', self::STATUS_REOPENED, ['id' => $submission->id]);

        return $submission;
    }

    /**
     * Whether a user may start a further attempt.
     *
     * Without this, a maximum attempts setting greater than one would be unreachable: a student
     * whose work has been sent for grading can no longer edit it, so there has to be a way to
     * begin the next attempt.
     *
     * @param int $userid the user
     * @param \stdClass|null $submission the attempt, looked up when not given
     * @return bool
     */
    public function can_start_new_attempt(int $userid, $submission = null): bool {
        $rules = $this->get_rules($userid);
        if ($rules->is_not_open_yet() || $rules->is_closed()) {
            return false;
        }

        $submission = $submission ?: $this->get_submission($userid);
        if (!$submission || $submission->status !== self::STATUS_SUBMITTED) {
            return false;
        }

        if ($rules->maxattempts < 0) {
            return true;
        }
        return $this->get_attempts_used($userid) < $rules->maxattempts;
    }

    /**
     * Whether a user may still change their submission.
     *
     * @param int $userid the user
     * @param \stdClass|null $submission the attempt, looked up when not given
     * @return bool
     */
    public function can_edit(int $userid, $submission = null): bool {
        $rules = $this->get_rules($userid);
        if ($rules->is_not_open_yet() || $rules->is_closed()) {
            return false;
        }
        $submission = $submission ?: $this->get_submission($userid);
        if ($submission && $submission->status === self::STATUS_SUBMITTED) {
            return false;
        }
        if ($rules->maxattempts >= 0 && $this->get_attempts_used($userid) >= $rules->maxattempts) {
            return false;
        }
        return true;
    }
}
