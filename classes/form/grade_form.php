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
 * The form a teacher grades one submission with.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\form;

use mod_pagecheck\local\grader;

/**
 * Award a grade and write a comment for one student.
 */
class grade_form extends \moodleform {
    /**
     * Build the form.
     *
     * @return void
     */
    public function definition() {
        $mform = $this->_form;

        /** @var grader $grader */
        $grader = $this->_customdata['grader'];

        foreach (['id', 'userid', 'page'] as $name) {
            $mform->addElement('hidden', $name, $this->_customdata[$name]);
            $mform->setType($name, PARAM_INT);
        }
        $mform->addElement('hidden', 'filter', $this->_customdata['filter']);
        $mform->setType('filter', PARAM_ALPHA);

        if ($grader->is_graded()) {
            if ($grader->uses_scale()) {
                $menu = [-1 => get_string('notgraded', 'mod_pagecheck')] + $grader->get_grade_menu();
                $mform->addElement('select', 'grade', get_string('gradelabel', 'mod_pagecheck'), $menu);
            } else {
                // A menu of a hundred and one entries would be worse than a box for a grade out
                // of a hundred, so points are typed.
                $mform->addElement('text', 'grade', get_string(
                    'gradeoutof',
                    'mod_pagecheck',
                    format_float($grader->get_max_grade(), 2)
                ), ['size' => 8]);
                $mform->setType('grade', PARAM_RAW_TRIMMED);
            }
            $mform->addHelpButton('grade', 'gradeentry', 'mod_pagecheck');
        }

        $mform->addElement(
            'editor',
            'feedback',
            get_string('feedback', 'mod_pagecheck'),
            null,
            $this->_customdata['editoroptions']
        );
        $mform->setType('feedback', PARAM_RAW);
        $mform->addHelpButton('feedback', 'feedback', 'mod_pagecheck');

        $buttons = [
            $mform->createElement('submit', 'savegrade', get_string('savechanges')),
        ];
        if (!empty($this->_customdata['hasnext'])) {
            $buttons[] = $mform->createElement(
                'submit',
                'savenext',
                get_string('savechangesandnext', 'mod_pagecheck')
            );
        }
        $buttons[] = $mform->createElement('cancel');

        $mform->addGroup($buttons, 'actions', '', ' ', false);
    }

    /**
     * Check the grade is one this activity can award.
     *
     * @param array $data the submitted data
     * @param array $files the submitted files
     * @return array errors keyed by element name
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        /** @var grader $grader */
        $grader = $this->_customdata['grader'];

        if (!$grader->is_graded() || !isset($data['grade'])) {
            return $errors;
        }

        // The scale menu offers "not graded" as -1, which is a deliberate choice, not a mistake.
        if ($grader->uses_scale() && (int) $data['grade'] === -1) {
            return $errors;
        }

        [$valid] = $grader->parse_grade($data['grade']);
        if (!$valid) {
            $errors['grade'] = $grader->uses_scale()
                ? get_string('errorgradeinvalid', 'mod_pagecheck')
                : get_string(
                    'errorgradeoutofrange',
                    'mod_pagecheck',
                    format_float($grader->get_max_grade(), 2)
                );
        }

        return $errors;
    }
}
