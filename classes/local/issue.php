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
 * A single problem found with a submission.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck\local;

/**
 * Value object describing one violated restriction, ready to be shown to the student.
 */
class issue {
    /** @var string The submission cannot be accepted. */
    const LEVEL_ERROR = 'error';

    /** @var string The submission is accepted but the student is told about it. */
    const LEVEL_WARNING = 'warning';

    /** @var string Language string key, without the "issue_" prefix. */
    public $code;

    /** @var string One of the LEVEL_* constants. */
    public $level;

    /** @var mixed Parameter for the language string. */
    public $a;

    /** @var string Name of the file this issue is about, empty when it concerns the submission. */
    public $filename;

    /**
     * Build an issue.
     *
     * @param string $code language string key, without the "issue_" prefix
     * @param string $level one of the LEVEL_* constants
     * @param mixed $a parameter for the language string
     * @param string $filename name of the offending file, if any
     */
    public function __construct(string $code, string $level, $a = null, string $filename = '') {
        $this->code = $code;
        $this->level = $level;
        $this->a = $a;
        $this->filename = $filename;
    }

    /**
     * Whether this issue blocks the submission.
     *
     * @return bool
     */
    public function is_error(): bool {
        return $this->level === self::LEVEL_ERROR;
    }

    /**
     * The translated message.
     *
     * @return string
     */
    public function get_message(): string {
        return get_string('issue_' . $this->code, 'mod_pagecheck', $this->a);
    }

    /**
     * The message prefixed with the file name, when the issue is about a specific file.
     *
     * @return string
     */
    public function get_full_message(): string {
        if ($this->filename === '') {
            return $this->get_message();
        }
        return get_string('issuewithfile', 'mod_pagecheck', (object) [
            'filename' => $this->filename,
            'message' => $this->get_message(),
        ]);
    }

    /**
     * Whether any issue in a list blocks the submission.
     *
     * @param issue[] $issues the issues to inspect
     * @return bool
     */
    public static function has_errors(array $issues): bool {
        foreach ($issues as $issue) {
            if ($issue->is_error()) {
                return true;
            }
        }
        return false;
    }
}
