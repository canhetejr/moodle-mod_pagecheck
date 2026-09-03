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
 * English strings for mod_pagecheck.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Submission with page check';
$string['modulename'] = 'Submission with page check';
$string['modulenameplural'] = 'Submissions with page check';
$string['modulename_help'] = 'The page check activity collects files from students the way the assignment activity does, and additionally counts the pages of what they send.

The teacher sets a range of pages, the accepted file types and a set of document checks. A student who attaches a file that breaks one of those rules is told immediately, on the upload screen, instead of finding out after the deadline.

The page count is exact for PDF. For .docx and .pptx it is read from the properties the editor stored in the file, which can be missing or out of date, so the activity can be set to accept PDF only when the count has to be reliable.';
$string['pagecheckname'] = 'Activity name';
$string['pluginadministration'] = 'Page check administration';

// Capabilities.
$string['pagecheck:addinstance'] = 'Add a new page check activity';
$string['pagecheck:view'] = 'View a page check activity';
$string['pagecheck:submit'] = 'Submit files to a page check activity';
$string['pagecheck:viewallsubmissions'] = 'View every submission';
$string['pagecheck:grade'] = 'Grade submissions';
$string['pagecheck:manageoverrides'] = 'Manage group and user overrides';
$string['pagecheck:submitwithissues'] = 'Submit files that break the restrictions';

// Settings form: availability.
$string['availability'] = 'Availability';
$string['allowsubmissionsfromdate'] = 'Allow submissions from';
$string['allowsubmissionsfromdate_help'] = 'Students cannot send anything before this date. The activity and its restrictions are still visible to them.';
$string['duedate'] = 'Due date';
$string['duedate_help'] = 'Submissions sent after this date are marked as late. They are still accepted unless "Refuse late submissions" is turned on or a cut off date has passed.';
$string['cutoffdate'] = 'Cut off date';
$string['cutoffdate_help'] = 'After this date nothing is accepted, whatever the due date says.';
$string['blockafterdue'] = 'Refuse late submissions';
$string['blockafterdue_help'] = 'When this is on, the due date itself closes the activity. Leave it off to keep accepting work while marking it as late.';
$string['maxattempts'] = 'Maximum attempts';
$string['maxattempts_help'] = 'How many times a student may send work for grading. Saving a draft does not use up an attempt.';
$string['unlimitedattempts'] = 'Unlimited';
$string['requiresubmissionstatement'] = 'Require students to accept the submission statement';
$string['requiresubmissionstatement_help'] = 'Students have to confirm the work is their own before their files are saved.';
$string['submissionstatement'] = 'This submission is my own work, except where I have acknowledged the work of others.';

// Settings form: files.
$string['filesettings'] = 'Accepted files';
$string['allowedextensions'] = 'Accepted file types';
$string['allowedextensions_help'] = 'Only these types can be attached. Pages are counted exactly for PDF and read from the stored document properties for .docx and .pptx; any other type is accepted but cannot be counted, and is then treated according to the "Unknown page count" setting.';
$string['allowedextensions_desc'] = 'The file types new activities accept by default.';
$string['maxfiles'] = 'Maximum number of files';
$string['maxbytes'] = 'Maximum file size';
$string['maxbytes_help'] = 'The largest a single attached file may be. The course upload limit still applies on top of this.';
$string['anyfiletype'] = 'Any file type';

// Settings form: pages.
$string['pagesettings'] = 'Page restrictions';
$string['minpages'] = 'Minimum pages';
$string['minpages_help'] = 'The submission must have at least this many pages, counting every attached file together. Zero means no minimum.';
$string['maxpages'] = 'Maximum pages';
$string['maxpages_help'] = 'The submission may have at most this many pages, counting every attached file together. Zero means no maximum.';
$string['countcover'] = 'Pages not counted';
$string['countcover_help'] = 'How many pages at the start of each file to leave out of the count, for a cover sheet or a title page.';
$string['strictness'] = 'When a restriction is broken';
$string['strictness_help'] = 'Whether a submission that breaks a page restriction is refused or merely flagged. Deadlines, file types, file sizes and the attempt allowance are always enforced, whichever option is chosen here.';
$string['strictness_block'] = 'Refuse the submission';
$string['strictness_warn'] = 'Accept it and warn the student';
$string['unknownpolicy'] = 'Unknown page count';
$string['unknownpolicy_help'] = 'What to do with a file whose pages cannot be counted, for example a .docx saved by a tool that records no page count.';
$string['unknownpolicy_warn'] = 'Accept it and warn the student';
$string['unknownpolicy_accept'] = 'Accept it silently';
$string['unknownpolicy_reject'] = 'Refuse it';

// Settings form: document checks.
$string['documentchecks'] = 'Document checks';
$string['rejectencrypted'] = 'Refuse password protected files';
$string['rejectencrypted_help'] = 'A file that is encrypted cannot be read, and often cannot be opened by the marker either.';
$string['requiretextlayer'] = 'Require selectable text';
$string['requiretextlayer_help'] = 'Refuses a PDF that is only a picture of a page, such as a photograph or a scan that has not been through character recognition. The check looks for text drawing instructions in the document, so a PDF whose text was converted to outlines can be flagged even though it looks fine on screen.';
$string['rejectblankpages'] = 'Report blank pages';
$string['rejectblankpages_help'] = 'Warns when a document contains pages that draw nothing at all. This is a warning rather than a refusal, because a page that only holds an unusual graphic can look blank to the check.';
$string['blankpagetolerance'] = 'Blank pages tolerated';

// Settings form: groups and completion.
$string['groupsettings'] = 'Group submission';
$string['teamsubmission'] = 'Students submit as a group';
$string['teamsubmission_help'] = 'One submission is shared by the whole group. Any member can attach files and any member can send the work for grading.';
$string['completionsubmit'] = 'Student must send work for grading';
$string['completionsubmit_help'] = 'The activity counts as complete once the student has sent an attempt for grading.';
$string['completionsubmit_desc'] = 'Send work for grading';

// Activity page.
$string['submissionstatus'] = 'Submission status';
$string['restrictions'] = 'Restrictions';
$string['submittedfiles'] = 'Submitted files';
$string['submissionfiles'] = 'Files';
$string['submissionfiles_help'] = 'Attach the files for this activity. They are checked against the restrictions above as soon as you pick them, and again when they are saved.';
$string['file'] = 'File';
$string['pages'] = 'Pages';
$string['size'] = 'Size';
$string['countmethod'] = 'Counted from';
$string['totalpages'] = 'Pages in total';
$string['timesubmitted'] = 'Sent for grading';
$string['issues'] = 'Checks';
$string['addsubmission'] = 'Add submission';
$string['editsubmission'] = 'Edit submission';
$string['submitforgrading'] = 'Send for grading';
$string['confirmsubmission'] = 'Once you send this work for grading you will not be able to change it. Are you sure?';
$string['alreadysubmitted'] = 'This work has already been sent for grading.';
$string['newattempt'] = 'Start a new attempt';
$string['confirmnewattempt'] = 'The work you already sent stays on record, and the new attempt starts empty. Do you want to continue?';
$string['errornonewattempt'] = 'You cannot start another attempt in this activity.';
$string['submissionsent'] = 'Your work has been sent for grading.';
$string['submissionrefused'] = 'This submission was not accepted.';
$string['filessaved'] = 'Your files have been saved. They have not been sent for grading yet.';
$string['checkingfile'] = 'Checking the file...';
$string['checkonserver'] = 'this file will be checked when it is uploaded.';

$string['status_new'] = 'Nothing submitted yet';
$string['status_draft'] = 'Draft, not sent for grading';
$string['status_submitted'] = 'Sent for grading';
$string['status_reopened'] = 'Reopened for another attempt';

$string['pagesbetween'] = 'Between {$a->min} and {$a->max}';
$string['pagesatleast'] = 'At least {$a}';
$string['pagesatmost'] = 'At most {$a}';
$string['pagesnolimit'] = 'No limit';
$string['pagesunknown'] = 'Could not be counted';
$string['pagescounted'] = '{$a->counted} counted, of {$a->total}';

$string['method_unknown'] = 'Not counted';
$string['method_fpdi'] = 'PDF page tree';
$string['method_raw'] = 'PDF structure';
$string['method_gs'] = 'Ghostscript';
$string['method_ooxml'] = 'Document properties';
$string['method_image'] = 'One page per image';

// Issues.
$string['issuewithfile'] = '{$a->filename}: {$a->message}';
$string['issue_nofiles'] = 'No file has been attached.';
$string['issue_toofewpages'] = 'the submission has {$a->count} pages, but at least {$a->min} are required.';
$string['issue_toomanypages'] = 'the submission has {$a->count} pages, but at most {$a->max} are allowed.';
$string['issue_badextension'] = 'files of type .{$a->extension} are not accepted here. Accepted types: {$a->allowed}.';
$string['issue_toolarge'] = 'the file is {$a->size}, and the limit is {$a->max}.';
$string['issue_toomanyfiles'] = '{$a->count} files were attached, and at most {$a->max} are allowed.';
$string['issue_encrypted'] = 'the file is password protected, so it cannot be read.';
$string['issue_unreadable'] = 'the file could not be read ({$a}).';
$string['issue_unknownpagecount'] = 'the number of pages in this file could not be determined. Save it as a PDF if the page count has to be checked.';
$string['issue_notextlayer'] = 'this document holds no selectable text. A scan or a photograph is not accepted here.';
$string['issue_blankpages'] = '{$a->count} blank pages were found, and {$a->tolerance} are tolerated.';
$string['issue_late'] = 'this submission is late. The due date was {$a}.';
$string['issue_notopenyet'] = 'this activity does not accept submissions until {$a}.';
$string['issue_submissionsclosed'] = 'this activity stopped accepting submissions on {$a}.';
$string['issue_noattemptsleft'] = 'you have used all {$a} attempts.';

// Errors raised while reading a file.
$string['errorfileunreadable'] = 'the file could not be opened';
$string['errorfiletoolarge'] = 'the file is too large to inspect';
$string['errornotapdf'] = 'this is not a PDF file';
$string['errorunreadablepdf'] = 'the PDF structure is damaged';
$string['errornozip'] = 'this server cannot open Office documents';
$string['errorunreadableooxml'] = 'the Office document could not be opened';
$string['errorencrypted'] = 'the file is password protected';
$string['errorunsupportedformat'] = 'pages cannot be counted for this file type';

// Form validation.
$string['errorduebeforeopen'] = 'The due date must come after the date submissions open.';
$string['errorcutoffbeforedue'] = 'The cut off date must come after the due date.';
$string['errornegativepages'] = 'A number of pages cannot be negative.';
$string['errormaxbelowmin'] = 'The maximum number of pages must be at least the minimum.';
$string['errorcovertoolarge'] = 'The pages left out of the count must be fewer than the maximum.';
$string['errornoextensions'] = 'At least one file type has to be accepted.';
$string['errorstatementrequired'] = 'You have to accept the submission statement.';
$string['errorcannotedit'] = 'You cannot change this submission any more.';
$string['errornothingtosubmit'] = 'There is nothing to send for grading yet.';
$string['errornotargets'] = 'There is nobody to create an override for.';

// Teacher pages.
$string['viewsubmissions'] = 'View submissions';
$string['summarysubmitted'] = '{$a->submitted} of {$a->participants} participants have sent work for grading.';
$string['nosubmissions'] = 'Nothing to show.';
$string['notenrolled'] = 'Not enrolled';
$string['noinstances'] = 'There are no page check activities in this course.';
$string['savegrades'] = 'Save grades';
$string['gradessaved'] = '{$a} grades saved.';
$string['gradefor'] = 'Grade for {$a}';
$string['exportcsv'] = 'Download as CSV';
$string['filterall'] = 'Everyone';
$string['filtersubmitted'] = 'Sent for grading';
$string['filternotsubmitted'] = 'Not sent for grading';
$string['filterwithissues'] = 'With failed checks';
$string['deleteallsubmissions'] = 'Delete every submission';

// Overrides.
$string['overrides'] = 'Overrides';
$string['nooverrides'] = 'No overrides have been set.';
$string['addgroupoverride'] = 'Add group override';
$string['adduseroverride'] = 'Add user override';
$string['overridegroup'] = 'Group';
$string['overrideuser'] = 'Student';
$string['overridefor'] = 'Applies to';
$string['overridegrouplabel'] = 'Group: {$a}';
$string['overrideuserlabel'] = 'Student: {$a}';
$string['overridemissingtarget'] = 'The group or student no longer exists';
$string['overridethis'] = 'Override';
$string['overridesaved'] = 'The override has been saved.';
$string['overridedeleted'] = 'The override has been deleted.';

// Calendar.
$string['calendaropen'] = '{$a} opens';
$string['calendardue'] = '{$a} is due';

// Events.
$string['eventsubmissioncreated'] = 'Submission files saved';
$string['eventsubmissionsubmitted'] = 'Submission sent for grading';
$string['eventsubmissionrejected'] = 'Submission refused';

// Site settings.
$string['useghostscript'] = 'Use Ghostscript as a last resort';
$string['useghostscript_desc'] = 'When a PDF cannot be read by either of the built in parsers, ask Ghostscript to count its pages. This uses the path configured in the "Path to ghostscript" system setting and is off by default.';

// Privacy.
$string['privacy:submissionpath'] = 'Submissions';
$string['privacy:gradepath'] = 'Grade';
$string['privacy:metadata:submissions'] = 'The attempts a student made in this activity.';
$string['privacy:metadata:submissions:userid'] = 'The student the attempt belongs to.';
$string['privacy:metadata:submissions:groupid'] = 'The group the attempt belongs to, for group submissions.';
$string['privacy:metadata:submissions:attemptnumber'] = 'Which attempt this is.';
$string['privacy:metadata:submissions:status'] = 'Whether the attempt is a draft or has been sent for grading.';
$string['privacy:metadata:submissions:totalpages'] = 'How many pages the attempt was counted as.';
$string['privacy:metadata:submissions:timecreated'] = 'When the attempt was started.';
$string['privacy:metadata:submissions:timemodified'] = 'When the attempt was last changed.';
$string['privacy:metadata:submissions:timesubmitted'] = 'When the attempt was sent for grading.';
$string['privacy:metadata:files'] = 'What was found in each submitted file.';
$string['privacy:metadata:files:filename'] = 'The name of the file.';
$string['privacy:metadata:files:filesize'] = 'The size of the file.';
$string['privacy:metadata:files:pagecount'] = 'How many pages the file was counted as.';
$string['privacy:metadata:files:status'] = 'Whether the file passed the checks.';
$string['privacy:metadata:grades'] = 'The grades given in this activity.';
$string['privacy:metadata:grades:userid'] = 'The student who was graded.';
$string['privacy:metadata:grades:grade'] = 'The grade.';
$string['privacy:metadata:grades:feedback'] = 'The comment the teacher wrote for the student.';
$string['privacy:metadata:grades:grader'] = 'The teacher who gave the grade.';
$string['privacy:metadata:grades:timemodified'] = 'When the grade was last changed.';
$string['privacy:metadata:overrides'] = 'The restrictions changed for individual students.';
$string['privacy:metadata:overrides:userid'] = 'The student the override applies to.';
$string['privacy:metadata:overrides:duedate'] = 'The due date that replaces the activity one.';
$string['privacy:metadata:overrides:cutoffdate'] = 'The cut off date that replaces the activity one.';
$string['privacy:metadata:overrides:maxattempts'] = 'The attempt allowance that replaces the activity one.';
$string['privacy:metadata:filepurpose'] = 'The files a student attached to a submission.';

// Paper size.
$string['pagesize'] = 'Required paper size';
$string['pagesize_help'] = 'Refuses a PDF whose pages are not the size the work is meant to be on. The size is read from the page itself, so a document exported to the wrong paper is caught here rather than at the printer. It cannot be checked for .docx and .pptx.';
$string['pagesize_any'] = 'Any size';
$string['pagesize_a4'] = 'A4';
$string['pagesize_a3'] = 'A3';
$string['pagesize_a5'] = 'A5';
$string['pagesize_letter'] = 'Letter';
$string['pagesize_legal'] = 'Legal';
$string['pagesize_mixed'] = 'Mixed sizes';
$string['pagesize_unknown'] = 'Unrecognised';

// Counting mode and further file restrictions.
$string['countmode'] = 'Apply the page range to';
$string['countmode_help'] = 'Whether the minimum and maximum pages describe the submission as a whole, or each attached file on its own. With one file allowed the two are the same thing.';
$string['countmode_total'] = 'The whole submission';
$string['countmode_perfile'] = 'Each file on its own';
$string['minfiles'] = 'Minimum number of files';
$string['minfiles_help'] = 'How many files the student has to attach before the work can be sent for grading.';
$string['nominimum'] = 'No minimum';
$string['filenamepattern'] = 'Required file name';
$string['filenamepattern_help'] = 'A pattern every attached file name has to match, useful when work is collected by name. Use * for any run of characters and ? for a single one, for example <code>TCC_*.pdf</code>. Leave empty to accept any name.';
$string['rejectduplicates'] = 'Refuse the same file attached twice';
$string['rejectduplicates_help'] = 'Compares the contents of the attached files, so the same document sent under two names is still caught.';

// Issues for the new checks.
$string['issue_badpagesize'] = 'the pages are {$a->found}, and {$a->expected} is required.';
$string['issue_badfilename'] = 'the file name does not match the required pattern ({$a}).';
$string['issue_toofewfiles'] = '{$a->count} files were attached, and at least {$a->min} are required.';
$string['issue_duplicatefile'] = 'this is the same file as {$a}, attached twice.';

$string['errorminfilesabovemax'] = 'The minimum number of files cannot be above the maximum.';
$string['errorpatternnowildcard'] = 'A pattern with no wildcard matches only one exact file name. Add * or ? , or an extension such as .pdf.';
$string['papersize'] = 'Paper size';

// The page count meter.
$string['nofilesyet'] = 'Nothing attached yet. Use the button below to add your work.';
$string['meterinrange'] = 'Within the required range.';
$string['meternorange'] = 'This activity sets no page limit.';
$string['metershort'] = '{$a} more pages are needed.';
$string['meterover'] = '{$a} pages over the limit.';
$string['metercannotcount'] = 'The pages of this submission could not be counted.';
$string['filesbetween'] = 'Between {$a->min} and {$a->max}';

// Grading.
$string['gradeheading'] = 'Grade';
$string['gradeverb'] = 'Grade';
$string['gradeoutof'] = 'Grade out of {$a}';
$string['gradeentry'] = 'Grade';
$string['gradeentry_help'] = 'Leave this empty to take the grade away again. The grade is sent to the gradebook as soon as it is saved.';
$string['feedback'] = 'Feedback for the student';
$string['feedback_help'] = 'The student reads this on the activity page, alongside their grade. It is also sent to the gradebook.';
$string['savechangesandnext'] = 'Save and go to the next student';
$string['gradesaved'] = 'The grade for {$a} has been saved.';
$string['gradedon'] = 'Last graded on {$a->date} by {$a->grader}.';
$string['gradedon_short'] = 'Graded on';
$string['graded'] = 'Graded';
$string['notgraded'] = 'Not graded';
$string['attempt'] = 'Attempt';
$string['attempthistory'] = 'Earlier attempts';
$string['studentxofy'] = 'Student {$a->position} of {$a->total}';
$string['nextstudent'] = 'Next student';
$string['previousstudent'] = 'Previous student';
$string['errorgradeoutofrange'] = 'The grade has to be a number between 0 and {$a}.';
$string['errorgradeinvalid'] = 'That is not one of the grades this activity offers.';
