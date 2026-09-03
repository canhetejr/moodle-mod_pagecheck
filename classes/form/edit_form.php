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
 * The form a student uses to attach files.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\form;

use mod_pagecheck\local\rules;

/**
 * Attach files to an attempt.
 *
 * The restrictions are enforced twice: this form applies the ones the file manager understands
 * (how many files, how large, which extensions), and mod_pagecheck\local\validator applies all of
 * them, including the page count, once the files have been saved.
 */
class edit_form extends \moodleform {
    /**
     * Build the form.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        /** @var rules $rules */
        $rules = $this->_customdata['rules'];
        $options = $this->_customdata['fileoptions'];

        $mform->addElement('hidden', 'id', $this->_customdata['cmid']);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('html', \html_writer::div('', 'pagecheck-client-issues', [
            'id' => 'pagecheck-client-issues',
        ]));

        $mform->addElement(
            'filemanager',
            'files',
            get_string('submissionfiles', 'mod_pagecheck'),
            null,
            $options
        );
        $mform->addHelpButton('files', 'submissionfiles', 'mod_pagecheck');

        if ($rules->requiresubmissionstatement) {
            $mform->addElement(
                'checkbox',
                'submissionstatement',
                '',
                get_string('submissionstatement', 'mod_pagecheck')
            );
            $mform->addRule('submissionstatement', get_string('required'), 'required', null, 'client');
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Check the form before it is processed.
     *
     * @param array $data the submitted data
     * @param array $files the submitted files
     * @return array errors keyed by element name
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        /** @var rules $rules */
        $rules = $this->_customdata['rules'];
        if ($rules->requiresubmissionstatement && empty($data['submissionstatement'])) {
            $errors['submissionstatement'] = get_string('errorstatementrequired', 'mod_pagecheck');
        }

        return $errors;
    }
}
