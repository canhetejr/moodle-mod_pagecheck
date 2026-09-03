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
 * Tests for the page counters.
 *
 * @package    mod_pagecheck
 * @copyright  2026 TCC-M
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_pagecheck;

use mod_pagecheck\counter\count_result;
use mod_pagecheck\counter\counter_factory;
use mod_pagecheck\counter\ooxml_counter;
use mod_pagecheck\counter\pdf_counter;
use mod_pagecheck\counter\page_size;
use mod_pagecheck\counter\pdf_parser;
use mod_pagecheck\tests\fixtures\file_builder;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/pagecheck/tests/fixtures/file_builder.php');

/**
 * Tests for the page counters.
 *
 * @covers \mod_pagecheck\counter\pdf_parser
 * @covers \mod_pagecheck\counter\pdf_counter
 * @covers \mod_pagecheck\counter\ooxml_counter
 * @covers \mod_pagecheck\counter\counter_factory
 * @covers \mod_pagecheck\counter\page_size
 */
class counter_test extends \advanced_testcase {

    /** @var string A directory the test may write sample documents into. */
    protected $dir;

    /**
     * Give each test a directory of its own.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->dir = make_request_directory();
    }

    /**
     * The path of a sample document for the current test.
     *
     * @param string $name the file name
     * @return string
     */
    protected function path(string $name): string {
        return $this->dir . '/' . $name;
    }

    /**
     * Documents whose page count is known by construction.
     *
     * @return array
     */
    public function pdf_page_count_provider(): array {
        return [
            'single page' => [1, []],
            'three pages' => [3, []],
            'seven pages' => [7, []],
            'compressed streams' => [6, ['compress' => true]],
            'no text layer' => [2, ['text' => false]],
            'encrypted' => [4, ['encrypted' => true]],
        ];
    }

    /**
     * A PDF is counted correctly however it was written.
     *
     * @dataProvider pdf_page_count_provider
     * @param int $pages the number of pages the document was built with
     * @param array $options how the document was built
     * @return void
     */
    public function test_pdf_page_count(int $pages, array $options): void {
        $path = file_builder::pdf($this->path('sample.pdf'), $pages, $options);

        $result = (new pdf_counter())->count($path);

        $this->assertSame($pages, $result->pages);
        $this->assertNull($result->error);
        $this->assertNotSame(count_result::METHOD_UNKNOWN, $result->method);
    }

    /**
     * An encrypted PDF is recognised as such and still counted.
     *
     * FPDI refuses to open it, but the object structure of a PDF stays readable when only its
     * streams and strings are encrypted, so the raw parser can still find the page tree.
     *
     * @return void
     */
    public function test_encrypted_pdf_is_detected(): void {
        $path = file_builder::pdf($this->path('locked.pdf'), 4, ['encrypted' => true]);

        $result = (new pdf_counter())->count($path);

        $this->assertTrue($result->encrypted);
        $this->assertSame(4, $result->pages);
        $this->assertSame(count_result::METHOD_RAW, $result->method);
    }

    /**
     * A damaged upload is reported as unreadable rather than as zero pages.
     *
     * @return void
     */
    public function test_broken_pdf_reports_an_error(): void {
        $path = file_builder::broken_pdf($this->path('broken.pdf'));

        $result = (new pdf_counter())->count($path);

        $this->assertNull($result->pages);
        $this->assertFalse($result->has_page_count());
        $this->assertSame('errorunreadablepdf', $result->error);
    }

    /**
     * A file that is not a PDF at all is rejected before anything tries to parse it.
     *
     * @return void
     */
    public function test_non_pdf_is_rejected(): void {
        $path = $this->path('notes.txt');
        file_put_contents($path, 'These are my notes, not a document.');

        $result = (new pdf_counter())->count($path);

        $this->assertSame('errornotapdf', $result->error);
    }

    /**
     * The text layer check tells a typed document from one that only paints shapes.
     *
     * @return void
     */
    public function test_text_layer_detection(): void {
        $counter = new pdf_counter();

        $typed = $counter->count(
            file_builder::pdf($this->path('typed.pdf'), 2),
            ['analysetext' => true]
        );
        $painted = $counter->count(
            file_builder::pdf($this->path('painted.pdf'), 2, ['text' => false]),
            ['analysetext' => true]
        );

        $this->assertTrue($typed->hastext);
        $this->assertFalse($painted->hastext);
    }

    /**
     * Pages that paint nothing at all are counted as blank.
     *
     * @return void
     */
    public function test_blank_page_detection(): void {
        $path = file_builder::pdf($this->path('padded.pdf'), 5, ['blank' => 2]);

        $result = (new pdf_counter())->count($path, ['analysetext' => true]);

        $this->assertSame(5, $result->pages);
        $this->assertSame(2, $result->blankpages);
    }

    /**
     * Nothing is analysed inside an encrypted document, because nothing can be read.
     *
     * @return void
     */
    public function test_encrypted_pdf_is_not_analysed(): void {
        $path = file_builder::pdf($this->path('locked.pdf'), 3, ['encrypted' => true]);

        $result = (new pdf_counter())->count($path, ['analysetext' => true]);

        $this->assertNull($result->hastext);
        $this->assertNull($result->blankpages);
    }

    /**
     * A document hiding its objects in a compressed object stream is not guessed at.
     *
     * @return void
     */
    public function test_object_streams_are_not_analysed(): void {
        $path = file_builder::pdf($this->path('objstm.pdf'), 2);
        // Add the marker a document using object streams would carry.
        file_put_contents($path, file_get_contents($path) . "\n8 0 obj << /Type /ObjStm >> endobj\n");

        $parser = new pdf_parser($path);

        $this->assertTrue($parser->has_object_streams());
        $this->assertNull($parser->has_text_layer());
        $this->assertNull($parser->count_blank_pages());
    }

    /**
     * Office documents report the count their editor recorded.
     *
     * @return void
     */
    public function test_office_documents(): void {
        $counter = new ooxml_counter();

        $docx = $counter->count(file_builder::docx($this->path('a.docx'), 12), ['extension' => 'docx']);
        $pptx = $counter->count(file_builder::pptx($this->path('a.pptx'), 24), ['extension' => 'pptx']);

        $this->assertSame(12, $docx->pages);
        $this->assertSame(count_result::METHOD_OOXML, $docx->method);
        $this->assertSame(24, $pptx->pages);
    }

    /**
     * A document with no recorded page count reports null, never a guess.
     *
     * @return void
     */
    public function test_office_document_without_properties(): void {
        $path = file_builder::docx($this->path('bare.docx'), null);

        $result = (new ooxml_counter())->count($path, ['extension' => 'docx']);

        $this->assertNull($result->pages);
        $this->assertNull($result->error);
        $this->assertFalse($result->has_page_count());
    }

    /**
     * A password protected Office document is recognised by its container.
     *
     * @return void
     */
    public function test_encrypted_office_document(): void {
        $path = file_builder::encrypted_office($this->path('locked.docx'));

        $result = (new ooxml_counter())->count($path, ['extension' => 'docx']);

        $this->assertTrue($result->encrypted);
        $this->assertSame('errorencrypted', $result->error);
    }

    /**
     * Documents built on a known paper size, and what they should be called.
     *
     * @return array
     */
    public function paper_size_provider(): array {
        return [
            'a4' => ['a4', [595.276, 841.89]],
            'a4 landscape' => ['a4', [841.89, 595.276]],
            'letter' => ['letter', [612, 792]],
            'a3' => ['a3', [841.89, 1190.55]],
            'a5' => ['a5', [419.528, 595.276]],
            'legal' => ['legal', [612, 1008]],
            'nothing standard' => ['unknown', [500, 700]],
        ];
    }

    /**
     * The paper size is read from the page itself, whichever way round the page is.
     *
     * @dataProvider paper_size_provider
     * @param string $expected the name the size should be given
     * @param array $size the dimensions the document was built with
     * @return void
     */
    public function test_paper_size(string $expected, array $size): void {
        $path = file_builder::pdf($this->path('sized.pdf'), 2, ['size' => $size]);

        $result = (new pdf_counter())->count($path);

        $this->assertSame($expected, $result->pagesize);
    }

    /**
     * A document whose pages are not all the same size is reported as mixed, not as the first one.
     *
     * @return void
     */
    public function test_mixed_paper_sizes(): void {
        $path = file_builder::pdf($this->path('mixed.pdf'), 3, [
            'size' => [595.276, 841.89],
            'lastsize' => [612, 792],
        ]);

        $result = (new pdf_counter())->count($path);

        $this->assertSame(page_size::MIXED, $result->pagesize);
    }

    /**
     * A page a few points off nominal is still the size it was meant to be.
     *
     * @return void
     */
    public function test_paper_size_tolerance(): void {
        $this->assertSame('a4', page_size::classify(592.3, 839.0));
        $this->assertSame(page_size::UNKNOWN, page_size::classify(560.0, 800.0));
    }

    /**
     * Compressed content streams do not hide the paper size, which lives in the dictionaries.
     *
     * @return void
     */
    public function test_paper_size_with_compressed_streams(): void {
        $path = file_builder::pdf($this->path('flate.pdf'), 4, ['compress' => true]);

        $result = (new pdf_counter())->count($path);

        $this->assertSame('a4', $result->pagesize);
    }

    /**
     * The factory hands each file to a counter that understands it.
     *
     * @return void
     */
    public function test_factory_picks_the_right_counter(): void {
        $this->assertInstanceOf(pdf_counter::class,
            counter_factory::get_counter('application/pdf', 'pdf'));
        $this->assertInstanceOf(ooxml_counter::class, counter_factory::get_counter(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx'));
        $this->assertNull(counter_factory::get_counter('text/plain', 'txt'));
    }

    /**
     * A file type nobody can count is reported as such rather than silently ignored.
     *
     * @return void
     */
    public function test_unsupported_file_type(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $module = $this->getDataGenerator()->create_module('pagecheck', ['course' => $course->id]);
        $context = \context_module::instance($module->cmid);

        $file = get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'mod_pagecheck',
            'filearea' => \mod_pagecheck\local\submission_manager::FILEAREA,
            'itemid' => 1,
            'filepath' => '/',
            'filename' => 'notes.txt',
        ], 'Some notes.');

        $result = counter_factory::count_stored_file($file);

        $this->assertSame('errorunsupportedformat', $result->error);
        $this->assertSame('notes.txt', $result->filename);
    }
}
