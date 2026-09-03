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
 * Activity settings form for mod_pagecheck.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use mod_pagecheck\counter\page_size;
use mod_pagecheck\local\rules;

/**
 * The form a teacher fills in to configure the activity and every restriction it enforces.
 */
class mod_pagecheck_mod_form extends moodleform_mod {
    /**
     * Build the form.
     *
     * @return void
     */
    public function definition() {
        global $CFG, $COURSE;

        $mform = $this->_form;
        $config = get_config('mod_pagecheck');

        // General.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('pagecheckname', 'mod_pagecheck'), ['size' => '64']);
        $mform->setType('name', empty($CFG->formatstringstriptags) ? PARAM_CLEANHTML : PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // Availability.
        $mform->addElement('header', 'availability', get_string('availability', 'mod_pagecheck'));
        $mform->setExpanded('availability', true);

        $mform->addElement(
            'date_time_selector',
            'allowsubmissionsfromdate',
            get_string('allowsubmissionsfromdate', 'mod_pagecheck'),
            ['optional' => true]
        );
        $mform->addHelpButton('allowsubmissionsfromdate', 'allowsubmissionsfromdate', 'mod_pagecheck');

        $mform->addElement(
            'date_time_selector',
            'duedate',
            get_string('duedate', 'mod_pagecheck'),
            ['optional' => true]
        );
        $mform->addHelpButton('duedate', 'duedate', 'mod_pagecheck');

        $mform->addElement(
            'date_time_selector',
            'cutoffdate',
            get_string('cutoffdate', 'mod_pagecheck'),
            ['optional' => true]
        );
        $mform->addHelpButton('cutoffdate', 'cutoffdate', 'mod_pagecheck');

        $mform->addElement('advcheckbox', 'blockafterdue', get_string('blockafterdue', 'mod_pagecheck'));
        $mform->addHelpButton('blockafterdue', 'blockafterdue', 'mod_pagecheck');
        $mform->setDefault('blockafterdue', 0);

        $mform->addElement(
            'select',
            'maxattempts',
            get_string('maxattempts', 'mod_pagecheck'),
            $this->get_attempt_options()
        );
        $mform->addHelpButton('maxattempts', 'maxattempts', 'mod_pagecheck');
        $mform->setDefault('maxattempts', PAGECHECK_UNLIMITED_ATTEMPTS);

        $mform->addElement(
            'advcheckbox',
            'requiresubmissionstatement',
            get_string('requiresubmissionstatement', 'mod_pagecheck')
        );
        $mform->addHelpButton('requiresubmissionstatement', 'requiresubmissionstatement', 'mod_pagecheck');

        // Accepted files.
        $mform->addElement('header', 'filesettings', get_string('filesettings', 'mod_pagecheck'));
        $mform->setExpanded('filesettings', true);

        $mform->addElement('filetypes', 'allowedextensions', get_string('allowedextensions', 'mod_pagecheck'));
        $mform->addHelpButton('allowedextensions', 'allowedextensions', 'mod_pagecheck');
        $mform->setDefault(
            'allowedextensions',
            !empty($config->allowedextensions) ? $config->allowedextensions : '.pdf'
        );

        $mform->addElement(
            'select',
            'maxfiles',
            get_string('maxfiles', 'mod_pagecheck'),
            array_combine(range(1, 20), range(1, 20))
        );
        $mform->setDefault('maxfiles', 1);

        $minfiles = [0 => get_string('nominimum', 'mod_pagecheck')] + array_combine(range(1, 20), range(1, 20));
        $mform->addElement('select', 'minfiles', get_string('minfiles', 'mod_pagecheck'), $minfiles);
        $mform->addHelpButton('minfiles', 'minfiles', 'mod_pagecheck');
        $mform->setDefault('minfiles', 0);

        $mform->addElement(
            'text',
            'filenamepattern',
            get_string('filenamepattern', 'mod_pagecheck'),
            ['size' => 40]
        );
        $mform->setType('filenamepattern', PARAM_TEXT);
        $mform->addHelpButton('filenamepattern', 'filenamepattern', 'mod_pagecheck');
        $mform->setDefault('filenamepattern', '');

        $mform->addElement(
            'advcheckbox',
            'rejectduplicates',
            get_string('rejectduplicates', 'mod_pagecheck')
        );
        $mform->addHelpButton('rejectduplicates', 'rejectduplicates', 'mod_pagecheck');
        $mform->setDefault('rejectduplicates', 0);

        $choices = get_max_upload_sizes(
            $CFG->maxbytes,
            $COURSE->maxbytes,
            0,
            !empty($config->maxbytes) ? $config->maxbytes : 0
        );
        $choices[0] = get_string('courseuploadlimit') . ' (' . display_size($COURSE->maxbytes) . ')';
        $mform->addElement('select', 'maxbytes', get_string('maxbytes', 'mod_pagecheck'), $choices);
        $mform->addHelpButton('maxbytes', 'maxbytes', 'mod_pagecheck');
        $mform->setDefault('maxbytes', 0);

        // Page restrictions.
        $mform->addElement('header', 'pagesettings', get_string('pagesettings', 'mod_pagecheck'));
        $mform->setExpanded('pagesettings', true);

        $mform->addElement('text', 'minpages', get_string('minpages', 'mod_pagecheck'), ['size' => 5]);
        $mform->setType('minpages', PARAM_INT);
        $mform->setDefault('minpages', 0);
        $mform->addHelpButton('minpages', 'minpages', 'mod_pagecheck');

        $mform->addElement('text', 'maxpages', get_string('maxpages', 'mod_pagecheck'), ['size' => 5]);
        $mform->setType('maxpages', PARAM_INT);
        $mform->setDefault('maxpages', 0);
        $mform->addHelpButton('maxpages', 'maxpages', 'mod_pagecheck');

        $mform->addElement('text', 'countcover', get_string('countcover', 'mod_pagecheck'), ['size' => 5]);
        $mform->setType('countcover', PARAM_INT);
        $mform->setDefault('countcover', 0);
        $mform->addHelpButton('countcover', 'countcover', 'mod_pagecheck');

        $mform->addElement('select', 'countmode', get_string('countmode', 'mod_pagecheck'), [
            rules::COUNT_TOTAL => get_string('countmode_total', 'mod_pagecheck'),
            rules::COUNT_PER_FILE => get_string('countmode_perfile', 'mod_pagecheck'),
        ]);
        $mform->addHelpButton('countmode', 'countmode', 'mod_pagecheck');
        $mform->setDefault('countmode', rules::COUNT_TOTAL);

        $mform->addElement(
            'select',
            'pagesize',
            get_string('pagesize', 'mod_pagecheck'),
            page_size::get_menu()
        );
        $mform->addHelpButton('pagesize', 'pagesize', 'mod_pagecheck');
        $mform->setDefault(
            'pagesize',
            !empty($config->pagesize) ? $config->pagesize : page_size::ANY
        );

        $mform->addElement('select', 'strictness', get_string('strictness', 'mod_pagecheck'), [
            rules::STRICTNESS_BLOCK => get_string('strictness_block', 'mod_pagecheck'),
            rules::STRICTNESS_WARN => get_string('strictness_warn', 'mod_pagecheck'),
        ]);
        $mform->addHelpButton('strictness', 'strictness', 'mod_pagecheck');
        $mform->setDefault('strictness', rules::STRICTNESS_BLOCK);

        $mform->addElement('select', 'unknownpolicy', get_string('unknownpolicy', 'mod_pagecheck'), [
            rules::UNKNOWN_WARN => get_string('unknownpolicy_warn', 'mod_pagecheck'),
            rules::UNKNOWN_ACCEPT => get_string('unknownpolicy_accept', 'mod_pagecheck'),
            rules::UNKNOWN_REJECT => get_string('unknownpolicy_reject', 'mod_pagecheck'),
        ]);
        $mform->addHelpButton('unknownpolicy', 'unknownpolicy', 'mod_pagecheck');
        $mform->setDefault('unknownpolicy', rules::UNKNOWN_WARN);

        // Document checks.
        $mform->addElement('header', 'documentchecks', get_string('documentchecks', 'mod_pagecheck'));

        $mform->addElement('advcheckbox', 'rejectencrypted', get_string('rejectencrypted', 'mod_pagecheck'));
        $mform->addHelpButton('rejectencrypted', 'rejectencrypted', 'mod_pagecheck');
        $mform->setDefault('rejectencrypted', 1);

        $mform->addElement('advcheckbox', 'requiretextlayer', get_string('requiretextlayer', 'mod_pagecheck'));
        $mform->addHelpButton('requiretextlayer', 'requiretextlayer', 'mod_pagecheck');
        $mform->setDefault('requiretextlayer', 0);

        $mform->addElement('advcheckbox', 'rejectblankpages', get_string('rejectblankpages', 'mod_pagecheck'));
        $mform->addHelpButton('rejectblankpages', 'rejectblankpages', 'mod_pagecheck');
        $mform->setDefault('rejectblankpages', 0);

        $mform->addElement(
            'text',
            'blankpagetolerance',
            get_string('blankpagetolerance', 'mod_pagecheck'),
            ['size' => 5]
        );
        $mform->setType('blankpagetolerance', PARAM_INT);
        $mform->setDefault('blankpagetolerance', 0);
        $mform->hideIf('blankpagetolerance', 'rejectblankpages', 'notchecked');

        // Group submission.
        $mform->addElement('header', 'groupsettings', get_string('groupsettings', 'mod_pagecheck'));

        $mform->addElement('advcheckbox', 'teamsubmission', get_string('teamsubmission', 'mod_pagecheck'));
        $mform->addHelpButton('teamsubmission', 'teamsubmission', 'mod_pagecheck');
        $mform->setDefault('teamsubmission', 0);

        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * The choices offered for the number of attempts.
     *
     * @return array
     */
    protected function get_attempt_options(): array {
        $options = [PAGECHECK_UNLIMITED_ATTEMPTS => get_string('unlimitedattempts', 'mod_pagecheck')];
        for ($i = 1; $i <= 30; $i++) {
            $options[$i] = $i;
        }
        return $options;
    }

    /**
     * Add the completion rule offered by this activity.
     *
     * @return array the names of the elements added
     */
    public function add_completion_rules() {
        $mform = $this->_form;

        $mform->addElement(
            'checkbox',
            'completionsubmit',
            get_string('completionsubmit', 'mod_pagecheck')
        );
        $mform->addHelpButton('completionsubmit', 'completionsubmit', 'mod_pagecheck');

        return ['completionsubmit'];
    }

    /**
     * Whether the teacher enabled any of our completion rules.
     *
     * @param array $data the submitted form data
     * @return bool
     */
    public function completion_rule_enabled($data) {
        return !empty($data['completionsubmit']);
    }

    /**
     * Check the settings make sense together.
     *
     * @param array $data the submitted data
     * @param array $files the submitted files
     * @return array errors keyed by element name
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (
            !empty($data['allowsubmissionsfromdate']) && !empty($data['duedate'])
                && $data['duedate'] < $data['allowsubmissionsfromdate']
        ) {
            $errors['duedate'] = get_string('errorduebeforeopen', 'mod_pagecheck');
        }

        if (
            !empty($data['cutoffdate']) && !empty($data['duedate'])
                && $data['cutoffdate'] < $data['duedate']
        ) {
            $errors['cutoffdate'] = get_string('errorcutoffbeforedue', 'mod_pagecheck');
        }

        $minpages = (int) ($data['minpages'] ?? 0);
        $maxpages = (int) ($data['maxpages'] ?? 0);

        if ($minpages < 0) {
            $errors['minpages'] = get_string('errornegativepages', 'mod_pagecheck');
        }
        if ($maxpages < 0) {
            $errors['maxpages'] = get_string('errornegativepages', 'mod_pagecheck');
        }
        if ($minpages > 0 && $maxpages > 0 && $maxpages < $minpages) {
            $errors['maxpages'] = get_string('errormaxbelowmin', 'mod_pagecheck');
        }

        $countcover = (int) ($data['countcover'] ?? 0);
        if ($countcover > 0 && $maxpages > 0 && $countcover >= $maxpages) {
            $errors['countcover'] = get_string('errorcovertoolarge', 'mod_pagecheck');
        }

        $minfiles = (int) ($data['minfiles'] ?? 0);
        $maxfiles = (int) ($data['maxfiles'] ?? 1);
        if ($minfiles > $maxfiles) {
            $errors['minfiles'] = get_string('errorminfilesabovemax', 'mod_pagecheck');
        }

        $pattern = trim((string) ($data['filenamepattern'] ?? ''));
        if (
            $pattern !== '' && strpos($pattern, '*') === false && strpos($pattern, '?') === false
                && strpos($pattern, '.') === false
        ) {
            // A pattern with no wildcard and no extension only ever matches one exact name, which
            // is almost never what was meant.
            $errors['filenamepattern'] = get_string('errorpatternnowildcard', 'mod_pagecheck');
        }

        if (empty($data['allowedextensions'])) {
            $errors['allowedextensions'] = get_string('errornoextensions', 'mod_pagecheck');
        }

        return $errors;
    }
}
