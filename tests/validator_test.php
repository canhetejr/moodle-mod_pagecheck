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
 * Tests for the submission validator.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck;

use mod_pagecheck\counter\count_result;
use mod_pagecheck\counter\page_size;
use mod_pagecheck\local\issue;
use mod_pagecheck\local\rules;
use mod_pagecheck\local\validator;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the submission validator.
 *
 * @covers \mod_pagecheck\local\validator
 * @covers \mod_pagecheck\local\rules
 * @covers \mod_pagecheck\local\issue
 */
class validator_test extends \advanced_testcase {

    /**
     * A counted file, described in one line.
     *
     * @param string $filename the file name
     * @param int|null $pages the page count, or null when it is unknown
     * @param array $extra any other property of the result to set
     * @return count_result
     */
    protected function file(string $filename, $pages, array $extra = []): count_result {
        $result = new count_result();
        $result->filename = $filename;
        $result->pages = $pages;
        $result->method = $pages === null
            ? count_result::METHOD_UNKNOWN : count_result::METHOD_FPDI;
        $result->filesize = 1024;
        $result->mimetype = 'application/pdf';

        foreach ($extra as $property => $value) {
            $result->{$property} = $value;
        }

        return $result;
    }

    /**
     * The restrictions of an activity, described in one line.
     *
     * @param array $settings the settings to override
     * @return rules
     */
    protected function rules(array $settings = []): rules {
        $rules = new rules();
        $rules->allowedextensions = ['pdf'];
        $rules->maxfiles = 3;

        foreach ($settings as $property => $value) {
            $rules->{$property} = $value;
        }

        return $rules;
    }

    /**
     * The codes of the issues found, so a test can name what it expects.
     *
     * @param issue[] $issues the issues
     * @return string[]
     */
    protected function codes(array $issues): array {
        return array_map(function(issue $issue) {
            return $issue->code;
        }, $issues);
    }

    /**
     * A submission inside the page range raises nothing.
     *
     * @return void
     */
    public function test_submission_within_the_page_range_is_accepted(): void {
        $issues = (new validator())->validate(
            [$this->file('report.pdf', 7)],
            $this->rules(['minpages' => 5, 'maxpages' => 10])
        );

        $this->assertSame([], $this->codes($issues));
    }

    /**
     * Too few and too many pages are both reported, and both block the submission.
     *
     * @return void
     */
    public function test_page_range_is_enforced(): void {
        $validator = new validator();
        $rules = $this->rules(['minpages' => 5, 'maxpages' => 10]);

        $short = $validator->validate([$this->file('report.pdf', 3)], $rules);
        $long = $validator->validate([$this->file('report.pdf', 30)], $rules);

        $this->assertSame(['toofewpages'], $this->codes($short));
        $this->assertTrue($short[0]->is_error());
        $this->assertSame(['toomanypages'], $this->codes($long));
        $this->assertTrue($long[0]->is_error());
    }

    /**
     * Every attached file counts towards the total.
     *
     * @return void
     */
    public function test_pages_are_totalled_across_files(): void {
        $rules = $this->rules(['minpages' => 5, 'maxpages' => 10, 'maxfiles' => 3]);
        $files = [$this->file('part1.pdf', 4), $this->file('part2.pdf', 4)];

        $issues = (new validator())->validate($files, $rules);

        $this->assertSame([], $this->codes($issues));
        $this->assertSame(8, validator::total_pages($files, $rules));
    }

    /**
     * Cover pages are discounted from each file before the range is checked.
     *
     * @return void
     */
    public function test_cover_pages_are_not_counted(): void {
        $rules = $this->rules(['minpages' => 5, 'maxpages' => 5, 'countcover' => 1]);

        $issues = (new validator())->validate([$this->file('report.pdf', 6)], $rules);

        $this->assertSame([], $this->codes($issues));
    }

    /**
     * "Warn only" downgrades the judgement about the content, and nothing else.
     *
     * @return void
     */
    public function test_warn_only_strictness(): void {
        $rules = $this->rules([
            'minpages' => 5,
            'maxfiles' => 1,
            'strictness' => rules::STRICTNESS_WARN,
        ]);

        $issues = (new validator())->validate(
            [$this->file('a.pdf', 2), $this->file('b.pdf', 1)],
            $rules
        );

        $bycode = [];
        foreach ($issues as $issue) {
            $bycode[$issue->code] = $issue;
        }

        // The page count becomes a warning.
        $this->assertArrayHasKey('toofewpages', $bycode);
        $this->assertFalse($bycode['toofewpages']->is_error());
        // How the submission is packaged does not.
        $this->assertArrayHasKey('toomanyfiles', $bycode);
        $this->assertTrue($bycode['toomanyfiles']->is_error());
    }

    /**
     * A file type that was not asked for is refused before it is opened.
     *
     * @return void
     */
    public function test_extension_is_enforced(): void {
        $issues = (new validator())->validate(
            [$this->file('report.docx', 7)],
            $this->rules(['allowedextensions' => ['pdf'], 'minpages' => 5])
        );

        $this->assertSame(['badextension'], $this->codes($issues));
    }

    /**
     * A file larger than the limit is refused.
     *
     * @return void
     */
    public function test_file_size_is_enforced(): void {
        $issues = (new validator())->validate(
            [$this->file('big.pdf', 7, ['filesize' => 5000000])],
            $this->rules(['maxbytes' => 1000000])
        );

        $this->assertSame(['toolarge'], $this->codes($issues));
    }

    /**
     * A password protected file is refused when the activity says so, and tolerated otherwise.
     *
     * @return void
     */
    public function test_encrypted_files(): void {
        $file = $this->file('locked.pdf', 7, ['encrypted' => true]);

        $strict = (new validator())->validate([$file], $this->rules(['rejectencrypted' => true]));
        $lenient = (new validator())->validate([$file], $this->rules(['rejectencrypted' => false]));

        $this->assertSame(['encrypted'], $this->codes($strict));
        $this->assertSame([], $this->codes($lenient));
    }

    /**
     * What happens to a file nobody could count is the teacher's decision.
     *
     * @return void
     */
    public function test_unknown_page_count_policies(): void {
        $validator = new validator();
        $file = $this->file('report.docx', null);
        $base = ['allowedextensions' => ['docx'], 'minpages' => 5];

        $warn = $validator->validate([$file],
            $this->rules($base + ['unknownpolicy' => rules::UNKNOWN_WARN]));
        $accept = $validator->validate([$file],
            $this->rules($base + ['unknownpolicy' => rules::UNKNOWN_ACCEPT]));
        $reject = $validator->validate([$file],
            $this->rules($base + ['unknownpolicy' => rules::UNKNOWN_REJECT]));

        $this->assertSame(['unknownpagecount'], $this->codes($warn));
        $this->assertFalse($warn[0]->is_error());
        $this->assertSame([], $this->codes($accept));
        $this->assertSame(['unknownpagecount'], $this->codes($reject));
        $this->assertTrue($reject[0]->is_error());
    }

    /**
     * An uncountable file makes the total unknown, so the range is not checked against a guess.
     *
     * @return void
     */
    public function test_unknown_page_count_does_not_fail_the_range(): void {
        $rules = $this->rules([
            'allowedextensions' => ['pdf', 'docx'],
            'minpages' => 5,
            'maxfiles' => 2,
            'unknownpolicy' => rules::UNKNOWN_ACCEPT,
        ]);

        $issues = (new validator())->validate(
            [$this->file('a.pdf', 2), $this->file('b.docx', null)],
            $rules
        );

        $this->assertSame([], $this->codes($issues));
        $this->assertNull(validator::total_pages([$this->file('b.docx', null)], $rules));
    }

    /**
     * A scan is refused when the activity requires selectable text.
     *
     * @return void
     */
    public function test_text_layer_requirement(): void {
        $issues = (new validator())->validate(
            [$this->file('scan.pdf', 7, ['hastext' => false])],
            $this->rules(['requiretextlayer' => true])
        );

        $this->assertSame(['notextlayer'], $this->codes($issues));
    }

    /**
     * Blank pages are reported once they pass the tolerance, and always as a warning.
     *
     * @return void
     */
    public function test_blank_pages_are_a_warning(): void {
        $validator = new validator();
        $file = $this->file('padded.pdf', 10, ['blankpages' => 3]);

        $tolerated = $validator->validate([$file],
            $this->rules(['rejectblankpages' => true, 'blankpagetolerance' => 3]));
        $reported = $validator->validate([$file],
            $this->rules(['rejectblankpages' => true, 'blankpagetolerance' => 1]));

        $this->assertSame([], $this->codes($tolerated));
        $this->assertSame(['blankpages'], $this->codes($reported));
        $this->assertFalse($reported[0]->is_error());
    }

    /**
     * A submission with nothing attached is refused.
     *
     * @return void
     */
    public function test_empty_submission(): void {
        $issues = (new validator())->validate([], $this->rules());

        $this->assertSame(['nofiles'], $this->codes($issues));
    }

    /**
     * Dates decide whether the activity is open, late or closed.
     *
     * @return void
     */
    public function test_dates(): void {
        $validator = new validator();
        $now = time();
        $file = [$this->file('report.pdf', 7)];

        $early = $validator->validate($file,
            $this->rules(['allowsubmissionsfromdate' => $now + DAYSECS]), ['time' => $now]);
        $late = $validator->validate($file,
            $this->rules(['duedate' => $now - DAYSECS]), ['time' => $now]);
        $closed = $validator->validate($file,
            $this->rules(['duedate' => $now - DAYSECS, 'cutoffdate' => $now - HOURSECS]),
            ['time' => $now]);
        $blocked = $validator->validate($file,
            $this->rules(['duedate' => $now - DAYSECS, 'blockafterdue' => true]), ['time' => $now]);

        $this->assertSame(['notopenyet'], $this->codes($early));
        $this->assertTrue($early[0]->is_error());

        $this->assertSame(['late'], $this->codes($late));
        $this->assertFalse($late[0]->is_error());

        $this->assertSame(['submissionsclosed'], $this->codes($closed));
        $this->assertSame(['submissionsclosed'], $this->codes($blocked));
    }

    /**
     * A student who has used every attempt cannot send another one.
     *
     * @return void
     */
    public function test_attempt_allowance(): void {
        $validator = new validator();
        $file = [$this->file('report.pdf', 7)];
        $rules = $this->rules(['maxattempts' => 2]);

        $spare = $validator->validate($file, $rules, ['attemptsused' => 1]);
        $spent = $validator->validate($file, $rules, ['attemptsused' => 2]);

        $this->assertSame([], $this->codes($spare));
        $this->assertSame(['noattemptsleft'], $this->codes($spent));
    }

    /**
     * A required paper size is enforced, and only when one was asked for.
     *
     * @return void
     */
    public function test_paper_size_is_enforced(): void {
        $validator = new validator();
        $letter = $this->file('report.pdf', 5, ['pagesize' => 'letter']);

        $required = $validator->validate([$letter], $this->rules(['pagesize' => 'a4']));
        $relaxed = $validator->validate([$letter], $this->rules(['pagesize' => page_size::ANY]));
        $matching = $validator->validate([$this->file('report.pdf', 5, ['pagesize' => 'a4'])],
            $this->rules(['pagesize' => 'a4']));

        $this->assertSame(['badpagesize'], $this->codes($required));
        $this->assertSame([], $this->codes($relaxed));
        $this->assertSame([], $this->codes($matching));
    }

    /**
     * A file whose paper size could not be read is not accused of having the wrong one.
     *
     * @return void
     */
    public function test_unknown_paper_size_is_not_an_accusation(): void {
        $issues = (new validator())->validate(
            [$this->file('report.docx', 5)],
            $this->rules(['pagesize' => 'a4', 'allowedextensions' => ['docx']])
        );

        $this->assertSame([], $this->codes($issues));
    }

    /**
     * Counting each file separately judges the files, not their sum.
     *
     * @return void
     */
    public function test_per_file_counting(): void {
        $validator = new validator();
        $files = [$this->file('a.pdf', 7), $this->file('b.pdf', 2)];
        $base = ['minpages' => 5, 'maxpages' => 10];

        $perfile = $validator->validate($files,
            $this->rules($base + ['countmode' => rules::COUNT_PER_FILE]));
        $total = $validator->validate($files,
            $this->rules($base + ['countmode' => rules::COUNT_TOTAL]));

        // One short file is reported once, and named.
        $this->assertSame(['toofewpages'], $this->codes($perfile));
        $this->assertSame('b.pdf', $perfile[0]->filename);
        // Added together the same two files are nine pages, which is inside the range.
        $this->assertSame([], $this->codes($total));
    }

    /**
     * The file name pattern reads as a person expects a file name pattern to read.
     *
     * @return void
     */
    public function test_file_name_pattern(): void {
        $validator = new validator();
        $rules = $this->rules(['filenamepattern' => 'TCC_*.pdf']);

        $this->assertSame([], $this->codes($validator->validate(
            [$this->file('TCC_Ana.pdf', 3)], $rules)));
        $this->assertSame(['badfilename'], $this->codes($validator->validate(
            [$this->file('trabalho.pdf', 3)], $rules)));
        // The dot is a dot, not "any character".
        $this->assertSame(['badfilename'], $this->codes($validator->validate(
            [$this->file('TCC_AnaXpdf', 3)], $this->rules([
                'filenamepattern' => 'TCC_*.pdf',
                'allowedextensions' => [],
            ]))));
    }

    /**
     * The same document attached twice is caught by its contents, not by its name.
     *
     * @return void
     */
    public function test_duplicate_files(): void {
        $files = [
            $this->file('report.pdf', 3, ['contenthash' => 'samehash']),
            $this->file('report-copy.pdf', 3, ['contenthash' => 'samehash']),
        ];

        $strict = (new validator())->validate($files, $this->rules([
            'rejectduplicates' => true,
            'maxfiles' => 2,
        ]));
        $lenient = (new validator())->validate($files, $this->rules([
            'rejectduplicates' => false,
            'maxfiles' => 2,
        ]));

        $this->assertSame(['duplicatefile'], $this->codes($strict));
        $this->assertSame([], $this->codes($lenient));
    }

    /**
     * A minimum number of files is enforced alongside the maximum.
     *
     * @return void
     */
    public function test_minimum_files(): void {
        $validator = new validator();
        $rules = $this->rules(['minfiles' => 2]);

        $short = $validator->validate([$this->file('a.pdf', 3)], $rules);
        $enough = $validator->validate([$this->file('a.pdf', 3), $this->file('b.pdf', 3)], $rules);

        $this->assertSame(['toofewfiles'], $this->codes($short));
        $this->assertSame([], $this->codes($enough));
    }

    /**
     * The extension list is read the same way whichever shape the teacher typed it in.
     *
     * @return void
     */
    public function test_extension_parsing(): void {
        $this->assertSame(['pdf', 'docx'], rules::parse_extensions('.pdf, .docx'));
        $this->assertSame(['pdf', 'docx'], rules::parse_extensions('PDF,DOCX'));
        $this->assertSame(['pdf'], rules::parse_extensions('.pdf,.pdf, '));
        $this->assertSame([], rules::parse_extensions(''));
    }
}
