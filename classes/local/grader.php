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
 * Reads and writes the grades of one activity.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\local;

/**
 * Everything the grading screen and the submissions report need to know about grades.
 *
 * Both screens go through this class so a grade means the same thing in the list as it does on
 * the screen where it was awarded. That matters most for scales: an activity graded on a scale
 * stores the index of the scale item, and a number shown raw would be meaningless to a teacher.
 */
class grader {
    /** @var \stdClass The activity instance record. */
    protected $pagecheck;

    /** @var \context_module The module context. */
    protected $context;

    /** @var array|null The grade menu for this activity, built on first use. */
    protected $menu = null;

    /**
     * Build a grader for one activity.
     *
     * @param \stdClass $pagecheck the activity instance record
     * @param \context_module $context the module context
     */
    public function __construct(\stdClass $pagecheck, \context_module $context) {
        $this->pagecheck = $pagecheck;
        $this->context = $context;
    }

    /**
     * Whether this activity is graded on a scale rather than out of a number of points.
     *
     * A negative value in the grade column is how the Moodle grading form records a scale.
     *
     * @return bool
     */
    public function uses_scale(): bool {
        return (int) $this->pagecheck->grade < 0;
    }

    /**
     * Whether this activity is graded at all.
     *
     * @return bool
     */
    public function is_graded(): bool {
        return (int) $this->pagecheck->grade != 0;
    }

    /**
     * The highest grade that can be awarded, for an activity graded out of points.
     *
     * @return float
     */
    public function get_max_grade(): float {
        return $this->uses_scale() ? 0.0 : (float) $this->pagecheck->grade;
    }

    /**
     * The choices offered when grading, whether they are scale items or whole points.
     *
     * @return array grade value => label
     */
    public function get_grade_menu(): array {
        global $CFG;

        if ($this->menu === null) {
            require_once($CFG->libdir . '/gradelib.php');
            $this->menu = make_grades_menu((int) $this->pagecheck->grade);
        }

        return $this->menu;
    }

    /**
     * The grade record of one user.
     *
     * @param int $userid the user
     * @return \stdClass|null the record, or null when they have not been graded
     */
    public function get_grade(int $userid) {
        global $DB;

        $record = $DB->get_record('pagecheck_grades', [
            'pagecheckid' => $this->pagecheck->id,
            'userid' => $userid,
        ]);

        return $record ?: null;
    }

    /**
     * Every grade of this activity, keyed by user.
     *
     * @return \stdClass[] user id => grade record
     */
    public function get_all_grades(): array {
        global $DB;

        $records = $DB->get_records('pagecheck_grades', ['pagecheckid' => $this->pagecheck->id]);

        $bytuser = [];
        foreach ($records as $record) {
            $bytuser[(int) $record->userid] = $record;
        }

        return $bytuser;
    }

    /**
     * A grade as a person should read it.
     *
     * @param mixed $grade the stored grade, or null when there is none
     * @return string
     */
    public function format_grade($grade): string {
        if ($grade === null || $grade === '') {
            return get_string('notgraded', 'mod_pagecheck');
        }

        if ($this->uses_scale()) {
            $menu = $this->get_grade_menu();
            $key = (int) round((float) $grade);
            return isset($menu[$key]) ? (string) $menu[$key] : get_string('notgraded', 'mod_pagecheck');
        }

        return format_float((float) $grade, 2) . ' / ' . format_float($this->get_max_grade(), 2);
    }

    /**
     * Turn what a teacher typed into a grade that can be stored.
     *
     * @param mixed $value the submitted value
     * @return array [bool $valid, float|null $grade] the grade, or null to clear it
     */
    public function parse_grade($value): array {
        if ($value === null || trim((string) $value) === '') {
            // An empty box means "no grade yet", not "zero".
            return [true, null];
        }

        if ($this->uses_scale()) {
            $key = (int) $value;
            $menu = $this->get_grade_menu();
            return isset($menu[$key]) ? [true, (float) $key] : [false, null];
        }

        $grade = unformat_float((string) $value);
        if ($grade === false || $grade === null || !is_numeric($grade)) {
            return [false, null];
        }
        if ($grade < 0 || $grade > $this->get_max_grade()) {
            return [false, null];
        }

        return [true, (float) $grade];
    }

    /**
     * Award a grade and push it to the gradebook.
     *
     * @param int $userid the student being graded
     * @param float|null $grade the grade, or null to clear it
     * @param string $feedback the comment for the student
     * @param int $format the format of the comment
     * @param int $graderid the teacher awarding the grade
     * @return void
     */
    public function save(int $userid, $grade, string $feedback, int $format, int $graderid) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/pagecheck/lib.php');

        $record = $this->get_grade($userid);
        $now = time();

        if ($record) {
            $record->grade = $grade;
            $record->feedback = $feedback;
            $record->feedbackformat = $format;
            $record->grader = $graderid;
            $record->timemodified = $now;
            $DB->update_record('pagecheck_grades', $record);
        } else {
            $DB->insert_record('pagecheck_grades', (object) [
                'pagecheckid' => $this->pagecheck->id,
                'userid' => $userid,
                'grade' => $grade,
                'feedback' => $feedback,
                'feedbackformat' => $format,
                'grader' => $graderid,
                'timemodified' => $now,
            ]);
        }

        pagecheck_update_grades($this->pagecheck, $userid);
    }

    /**
     * Where a student sits in the list, and who comes before and after them.
     *
     * The order is the one the submissions report shows, so working through a class with the
     * next button visits everybody exactly once, in the order the teacher is looking at.
     *
     * @param array $participants user records keyed by user id, in display order
     * @param int $userid the student being graded
     * @return array with keys previous, next, position and total
     */
    public static function get_neighbours(array $participants, int $userid): array {
        $ids = array_map('intval', array_keys($participants));
        $index = array_search($userid, $ids, true);

        if ($index === false) {
            return ['previous' => null, 'next' => null, 'position' => 0, 'total' => count($ids)];
        }

        return [
            'previous' => $index > 0 ? $ids[$index - 1] : null,
            'next' => $index < count($ids) - 1 ? $ids[$index + 1] : null,
            'position' => $index + 1,
            'total' => count($ids),
        ];
    }
}
