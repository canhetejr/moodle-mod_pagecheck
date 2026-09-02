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
 * The form used to add or edit an override.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Change the dates, the attempt allowance or the page limits for one group or one student.
 *
 * Every value is optional: a field left switched off is inherited from the activity settings, so
 * a teacher can give one group a later deadline without repeating the rest of the configuration.
 */
class override_form extends \moodleform {

    /**
     * Build the form.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $targets = $this->_customdata['targets'];
        $mode = $this->_customdata['mode'];

        $mform->addElement('hidden', 'id', $this->_customdata['cmid']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'mode', $mode);
        $mform->setType('mode', PARAM_ALPHA);
        $mform->addElement('hidden', 'overrideid', $this->_customdata['overrideid']);
        $mform->setType('overrideid', PARAM_INT);

        $label = $mode === 'group'
            ? get_string('overridegroup', 'mod_pagecheck')
            : get_string('overrideuser', 'mod_pagecheck');
        $mform->addElement('select', 'target', $label, $targets);
        $mform->addRule('target', get_string('required'), 'required', null, 'client');

        $mform->addElement('date_time_selector', 'allowsubmissionsfromdate',
            get_string('allowsubmissionsfromdate', 'mod_pagecheck'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'duedate',
            get_string('duedate', 'mod_pagecheck'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'cutoffdate',
            get_string('cutoffdate', 'mod_pagecheck'), ['optional' => true]);

        $this->add_optional_number('maxattempts', get_string('maxattempts', 'mod_pagecheck'));
        $this->add_optional_number('minpages', get_string('minpages', 'mod_pagecheck'));
        $this->add_optional_number('maxpages', get_string('maxpages', 'mod_pagecheck'));

        $this->add_action_buttons();
    }

    /**
     * Add a number that is only sent when its "override" box is ticked.
     *
     * @param string $name the field name
     * @param string $label the field label
     * @return void
     */
    protected function add_optional_number(string $name, string $label) {
        $mform = $this->_form;

        $group = [
            $mform->createElement('advcheckbox', 'override' . $name, '',
                get_string('overridethis', 'mod_pagecheck')),
            $mform->createElement('text', $name, '', ['size' => 5]),
        ];
        $mform->addGroup($group, $name . 'group', $label, ' ', false);
        $mform->setType($name, PARAM_INT);
        $mform->hideIf($name, 'override' . $name, 'notchecked');
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

        if (!empty($data['cutoffdate']) && !empty($data['duedate'])
                && $data['cutoffdate'] < $data['duedate']) {
            $errors['cutoffdate'] = get_string('errorcutoffbeforedue', 'mod_pagecheck');
        }

        $min = !empty($data['overrideminpages']) ? (int) $data['minpages'] : 0;
        $max = !empty($data['overridemaxpages']) ? (int) $data['maxpages'] : 0;
        if ($min > 0 && $max > 0 && $max < $min) {
            $errors['maxpagesgroup'] = get_string('errormaxbelowmin', 'mod_pagecheck');
        }

        return $errors;
    }
}
