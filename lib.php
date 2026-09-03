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
 * Library of functions and constants for mod_pagecheck.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');

/** @var string File area holding the files a student submitted. */
define('PAGECHECK_FILEAREA_SUBMISSION', \mod_pagecheck\local\submission_manager::FILEAREA);

/** @var int Value of maxattempts meaning "as many as they like". */
define('PAGECHECK_UNLIMITED_ATTEMPTS', -1);

/**
 * Declare which optional Moodle features this activity implements.
 *
 * @param string $feature one of the FEATURE_* constants
 * @return mixed true, false, a string, or null for features we do not know about
 */
function pagecheck_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GRADE_OUTCOMES:
        case FEATURE_ADVANCED_GRADING:
            return false;
        case FEATURE_MOD_PURPOSE:
            // Introduced in Moodle 4.0; the constant is always defined on the versions we support.
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Create a new activity instance.
 *
 * @param stdClass $data the form data
 * @param mod_pagecheck_mod_form|null $mform the form itself
 * @return int the id of the new instance
 */
function pagecheck_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    pagecheck_prepare_instance_data($data);

    $data->id = $DB->insert_record('pagecheck', $data);

    pagecheck_grade_item_update($data);
    pagecheck_update_calendar_events($data);

    return $data->id;
}

/**
 * Update an existing activity instance.
 *
 * @param stdClass $data the form data
 * @param mod_pagecheck_mod_form|null $mform the form itself
 * @return bool
 */
function pagecheck_update_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;
    pagecheck_prepare_instance_data($data);

    $DB->update_record('pagecheck', $data);

    pagecheck_grade_item_update($data);
    pagecheck_update_grades($data);
    pagecheck_update_calendar_events($data);

    return true;
}

/**
 * Normalise the settings coming from the activity form before they are stored.
 *
 * @param stdClass $data the form data, modified in place
 * @return void
 */
function pagecheck_prepare_instance_data($data) {
    // Store extensions with their leading dot, which is the shape the filetypes form element
    // reads back. Everything else goes through rules::parse_extensions(), which strips it again.
    $extensions = \mod_pagecheck\local\rules::parse_extensions($data->allowedextensions ?? '');
    $data->allowedextensions = $extensions ? '.' . implode(',.', $extensions) : '';

    // An empty maximum is stored as zero, which every check reads as "no limit".
    foreach (['minpages', 'maxpages', 'countcover', 'maxbytes', 'blankpagetolerance'] as $field) {
        $data->{$field} = isset($data->{$field}) ? max(0, (int) $data->{$field}) : 0;
    }
    $data->maxfiles = isset($data->maxfiles) ? max(1, (int) $data->maxfiles) : 1;
    $data->minfiles = isset($data->minfiles) ? max(0, min((int) $data->minfiles, $data->maxfiles)) : 0;
    $data->filenamepattern = isset($data->filenamepattern) ? trim((string) $data->filenamepattern) : '';
}

/**
 * Delete an activity instance and everything attached to it.
 *
 * @param int $id the instance id
 * @return bool
 */
function pagecheck_delete_instance($id) {
    global $DB;

    $pagecheck = $DB->get_record('pagecheck', ['id' => $id]);
    if (!$pagecheck) {
        return false;
    }

    $cm = get_coursemodule_from_instance('pagecheck', $id, $pagecheck->course, false, IGNORE_MISSING);
    if ($cm) {
        $context = context_module::instance($cm->id);
        get_file_storage()->delete_area_files($context->id, 'mod_pagecheck', PAGECHECK_FILEAREA_SUBMISSION);
    }

    $submissionids = $DB->get_fieldset_select('pagecheck_submissions', 'id', 'pagecheckid = ?', [$id]);
    if ($submissionids) {
        list($insql, $params) = $DB->get_in_or_equal($submissionids);
        $DB->delete_records_select('pagecheck_files', "submissionid $insql", $params);
    }
    $DB->delete_records('pagecheck_submissions', ['pagecheckid' => $id]);
    $DB->delete_records('pagecheck_grades', ['pagecheckid' => $id]);
    $DB->delete_records('pagecheck_overrides', ['pagecheckid' => $id]);
    $DB->delete_records('event', ['modulename' => 'pagecheck', 'instance' => $id]);
    $DB->delete_records('pagecheck', ['id' => $id]);

    pagecheck_grade_item_delete($pagecheck);

    return true;
}

/**
 * Create or update the gradebook item for an instance.
 *
 * @param stdClass $pagecheck the instance record
 * @param mixed $grades grades to send to the gradebook, or 'reset'
 * @return int a GRADE_UPDATE_* constant
 */
function pagecheck_grade_item_update($pagecheck, $grades = null) {
    $item = ['itemname' => clean_param($pagecheck->name, PARAM_NOTAGS)];
    $grade = (int) $pagecheck->grade;

    if ($grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax'] = $grade;
        $item['grademin'] = 0;
    } else if ($grade < 0) {
        // A negative value is how the grade form reports a scale.
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid'] = -$grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $item['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/pagecheck', $pagecheck->course, 'mod', 'pagecheck',
        $pagecheck->id, 0, $grades, $item);
}

/**
 * Remove the gradebook item of an instance.
 *
 * @param stdClass $pagecheck the instance record
 * @return int a GRADE_UPDATE_* constant
 */
function pagecheck_grade_item_delete($pagecheck) {
    return grade_update('mod/pagecheck', $pagecheck->course, 'mod', 'pagecheck',
        $pagecheck->id, 0, null, ['deleted' => 1]);
}

/**
 * Push grades into the gradebook.
 *
 * @param stdClass $pagecheck the instance record
 * @param int $userid a single user, or 0 for all of them
 * @param bool $nullifnone whether a user without a grade gets a null grade
 * @return void
 */
function pagecheck_update_grades($pagecheck, $userid = 0, $nullifnone = true) {
    $grades = pagecheck_get_user_grades($pagecheck, $userid);
    if ($grades) {
        pagecheck_grade_item_update($pagecheck, $grades);
    } else if ($userid && $nullifnone) {
        pagecheck_grade_item_update($pagecheck, (object) ['userid' => $userid, 'rawgrade' => null]);
    } else {
        pagecheck_grade_item_update($pagecheck);
    }
}

/**
 * Read the grades of an instance in the shape the gradebook expects.
 *
 * @param stdClass $pagecheck the instance record
 * @param int $userid a single user, or 0 for all of them
 * @return array userid => grade object
 */
function pagecheck_get_user_grades($pagecheck, $userid = 0) {
    global $DB;

    $params = ['pagecheckid' => $pagecheck->id];
    $where = 'pagecheckid = :pagecheckid AND (grade IS NOT NULL OR ' .
        $DB->sql_isnotempty('pagecheck_grades', 'feedback', true, true) . ')';
    if ($userid) {
        $where .= ' AND userid = :userid';
        $params['userid'] = $userid;
    }

    $grades = [];
    $records = $DB->get_records_select('pagecheck_grades', $where, $params);
    foreach ($records as $record) {
        $grades[$record->userid] = (object) [
            'userid' => $record->userid,
            'rawgrade' => $record->grade,
            'feedback' => $record->feedback,
            'feedbackformat' => $record->feedbackformat,
            'usermodified' => $record->grader,
            'dategraded' => $record->timemodified,
        ];
    }
    return $grades;
}

/**
 * Extra information the course page and the completion API need about an instance.
 *
 * @param stdClass $coursemodule the course module record
 * @return cached_cm_info|null
 */
function pagecheck_get_coursemodule_info($coursemodule) {
    global $DB;

    $fields = 'id, name, intro, introformat, completionsubmit, allowsubmissionsfromdate, duedate';
    $pagecheck = $DB->get_record('pagecheck', ['id' => $coursemodule->instance], $fields);
    if (!$pagecheck) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $pagecheck->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('pagecheck', $pagecheck, $coursemodule->id, false);
    }
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionsubmit'] = (int) $pagecheck->completionsubmit;
    }

    return $info;
}

/**
 * Keep the calendar in step with the activity dates.
 *
 * @param stdClass $pagecheck the instance record
 * @return void
 */
function pagecheck_update_calendar_events($pagecheck) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/calendar/lib.php');

    $events = [
        'open' => (int) $pagecheck->allowsubmissionsfromdate,
        'due' => (int) $pagecheck->duedate,
    ];

    foreach ($events as $type => $timestart) {
        $eventname = $type === 'open' ? 'calendaropen' : 'calendardue';
        $existing = $DB->get_record('event', [
            'modulename' => 'pagecheck',
            'instance' => $pagecheck->id,
            'eventtype' => $type,
        ]);

        if ($timestart <= 0) {
            if ($existing) {
                $event = calendar_event::load($existing->id);
                $event->delete();
            }
            continue;
        }

        $data = new stdClass();
        $data->name = get_string($eventname, 'mod_pagecheck', $pagecheck->name);
        $data->description = '';
        $data->format = FORMAT_HTML;
        $data->courseid = $pagecheck->course;
        $data->groupid = 0;
        $data->userid = 0;
        $data->modulename = 'pagecheck';
        $data->instance = $pagecheck->id;
        $data->eventtype = $type;
        $data->type = CALENDAR_EVENT_TYPE_ACTION;
        $data->timestart = $timestart;
        $data->timesort = $timestart;
        $data->timeduration = 0;
        $data->visible = 1;

        if ($existing) {
            $data->id = $existing->id;
            $event = calendar_event::load($existing->id);
            $event->update($data, false);
        } else {
            calendar_event::create($data, false);
        }
    }
}

/**
 * What the calendar and the timeline offer a user to do about an event.
 *
 * @param calendar_event $event the event being shown
 * @param \core_calendar\action_factory $factory the factory that builds the action
 * @param int $userid the user the event is shown to, 0 for the current user
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_pagecheck_core_calendar_provide_event_action(calendar_event $event,
        \core_calendar\action_factory $factory, $userid = 0) {
    global $DB, $USER;

    $userid = $userid ?: $USER->id;

    $modinfo = get_fast_modinfo($event->courseid, $userid);
    if (empty($modinfo->instances['pagecheck'][$event->instance])) {
        return null;
    }
    $cm = $modinfo->instances['pagecheck'][$event->instance];
    if (!$cm->uservisible) {
        return null;
    }

    $context = context_module::instance($cm->id);
    if (!has_capability('mod/pagecheck:submit', $context, $userid)) {
        // A teacher has nothing to submit, so the event stays informational for them.
        return null;
    }

    $submitted = $DB->record_exists_select(
        'pagecheck_submissions',
        'pagecheckid = :pagecheckid AND userid = :userid AND timesubmitted > 0',
        ['pagecheckid' => $event->instance, 'userid' => $userid]
    );

    return $factory->create_instance(
        get_string($submitted ? 'viewsubmissions' : 'addsubmission', 'mod_pagecheck'),
        new moodle_url('/mod/pagecheck/view.php', ['id' => $cm->id]),
        1,
        !$submitted
    );
}

/**
 * Serve the files a student submitted.
 *
 * @param stdClass $course the course
 * @param stdClass $cm the course module
 * @param context $context the module context
 * @param string $filearea the file area
 * @param array $args the remaining path arguments, the first being the item id
 * @param bool $forcedownload whether to force a download
 * @param array $options additional options
 * @return bool false when the file could not be served
 */
function pagecheck_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE || $filearea !== PAGECHECK_FILEAREA_SUBMISSION) {
        return false;
    }

    require_login($course, false, $cm);
    require_capability('mod/pagecheck:view', $context);

    $itemid = (int) array_shift($args);
    $submission = $DB->get_record('pagecheck_submissions', ['id' => $itemid]);
    if (!$submission || $submission->pagecheckid != $cm->instance) {
        return false;
    }

    if (!has_capability('mod/pagecheck:viewallsubmissions', $context)) {
        $owner = $submission->userid == $USER->id;
        if (!$owner && $submission->groupid > 0) {
            $owner = groups_is_member($submission->groupid, $USER->id);
        }
        if (!$owner) {
            return false;
        }
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $file = get_file_storage()->get_file($context->id, 'mod_pagecheck',
        $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Add the teacher links to the activity administration menu.
 *
 * @param settings_navigation $settings the settings navigation
 * @param navigation_node $node the activity node
 * @return void
 */
function pagecheck_extend_settings_navigation($settings, $node) {
    $cm = $settings->get_page()->cm;
    if (!$cm) {
        return;
    }
    $context = $cm->context;

    if (has_capability('mod/pagecheck:viewallsubmissions', $context)) {
        $node->add(
            get_string('viewsubmissions', 'mod_pagecheck'),
            new moodle_url('/mod/pagecheck/submissions.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'pagechecksubmissions'
        );
    }

    if (has_capability('mod/pagecheck:manageoverrides', $context)) {
        $node->add(
            get_string('overrides', 'mod_pagecheck'),
            new moodle_url('/mod/pagecheck/overrides.php', ['id' => $cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'pagecheckoverrides'
        );
    }
}

/**
 * Remove user data when a course is reset.
 *
 * @param stdClass $data the reset form data
 * @return array the outcome, in the shape the reset report expects
 */
function pagecheck_reset_userdata($data) {
    global $DB;

    $status = [];
    if (empty($data->reset_pagecheck_submissions)) {
        return $status;
    }

    $pagechecks = $DB->get_records('pagecheck', ['course' => $data->courseid]);
    $fs = get_file_storage();

    foreach ($pagechecks as $pagecheck) {
        $cm = get_coursemodule_from_instance('pagecheck', $pagecheck->id, $data->courseid, false, IGNORE_MISSING);
        if ($cm) {
            $context = context_module::instance($cm->id);
            $fs->delete_area_files($context->id, 'mod_pagecheck', PAGECHECK_FILEAREA_SUBMISSION);
        }
        $submissionids = $DB->get_fieldset_select('pagecheck_submissions', 'id', 'pagecheckid = ?', [$pagecheck->id]);
        if ($submissionids) {
            list($insql, $params) = $DB->get_in_or_equal($submissionids);
            $DB->delete_records_select('pagecheck_files', "submissionid $insql", $params);
        }
        $DB->delete_records('pagecheck_submissions', ['pagecheckid' => $pagecheck->id]);
        $DB->delete_records('pagecheck_grades', ['pagecheckid' => $pagecheck->id]);
        pagecheck_grade_item_update($pagecheck, 'reset');
    }

    $status[] = [
        'component' => get_string('modulenameplural', 'mod_pagecheck'),
        'item' => get_string('deleteallsubmissions', 'mod_pagecheck'),
        'error' => false,
    ];

    return $status;
}

/**
 * Add the reset options to the course reset form.
 *
 * @param MoodleQuickForm $mform the reset form
 * @return void
 */
function pagecheck_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'pagecheckheader', get_string('modulenameplural', 'mod_pagecheck'));
    $mform->addElement('advcheckbox', 'reset_pagecheck_submissions',
        get_string('deleteallsubmissions', 'mod_pagecheck'));
}
