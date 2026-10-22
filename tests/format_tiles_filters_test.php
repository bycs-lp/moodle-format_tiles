<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Outcome filter related unit tests for format tiles
 *
 * @package    format_tiles
 * @copyright  2025 BYCS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_tiles;

use format_tiles\local\filters;
use format_tiles\local\format_option;

/**
 * Tests that outcome names are escaped at the source and safe to output as HTML.
 *
 * @copyright  2025 BYCS
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class format_tiles_filters_test extends \advanced_testcase {
    /**
     * Create a course in tiles format with one outcome.
     *
     * @param string $outcomename full name of the outcome
     * @param int $displayfilterbar value of the displayfilterbar course option
     * @return array course record and outcome record
     */
    private function create_course_with_outcome(string $outcomename, int $displayfilterbar): array {
        global $CFG, $DB;
        $CFG->enableoutcomes = 1;
        $generator = $this->getDataGenerator();
        // The edit form options of the format are cached statically, including the allowed values of displayfilterbar.
        // The outcome values are missing there if an earlier test ran while outcomes were disabled, so write the option directly.
        \core_courseformat\base::reset_course_cache();
        $course = $generator->create_course(['format' => 'tiles']);
        $DB->set_field(
            'course_format_options',
            'value',
            $displayfilterbar,
            ['courseid' => $course->id, 'format' => 'tiles', 'sectionid' => 0, 'name' => 'displayfilterbar']
        );
        rebuild_course_cache($course->id, true);
        \core_courseformat\base::reset_course_cache($course->id);
        $scale = $generator->create_scale(['name' => 'Outcome scale', 'scale' => 'Low,High']);
        $outcome = $generator->create_grade_outcome([
            'courseid' => $course->id,
            'shortname' => 'outcome1',
            'fullname' => $outcomename,
            'scaleid' => $scale->id,
        ]);
        return [$course, $outcome];
    }

    /**
     * Outcome names as entered by a teacher and the expected escaped result.
     *
     * @return array
     */
    public static function outcome_name_provider(): array {
        return [
            'markup is stripped' => ['<img src=x onerror=alert(1)>Outcome', 'Outcome'],
            'ampersand is escaped once' => ['Lesekompetenz & Präsentation', 'Lesekompetenz &amp; Präsentation'],
            'escaped markup stays as it is' => ['&lt;b&gt;', '&lt;b&gt;'],
            'escaped ampersand stays as it is' => ['A &amp; B', 'A &amp; B'],
        ];
    }

    /**
     * Outcome names must be escaped by get_course_outcomes().
     *
     * @covers \format_tiles\local\filters::get_course_outcomes
     * @dataProvider outcome_name_provider
     * @param string $name outcome name as stored
     * @param string $expected expected value returned for the name
     * @return void
     */
    public function test_get_course_outcomes_escapes_html(string $name, string $expected): void {
        global $CFG;
        $this->resetAfterTest(true);
        $CFG->formatstringstriptags = 1;
        [$course, $outcome] = $this->create_course_with_outcome($name, format_option::FILTER_OUTCOMES_ONLY);

        $outcomes = filters::get_course_outcomes($course->id);

        $this->assertSame([$outcome->id => $expected], $outcomes);
        $this->assertStringNotContainsString('onerror', $outcomes[$outcome->id]);
    }

    /**
     * Even without stripping tags no event handler attribute may survive.
     *
     * @covers \format_tiles\local\filters::get_course_outcomes
     * @return void
     */
    public function test_get_course_outcomes_without_striptags(): void {
        global $CFG;
        $this->resetAfterTest(true);
        $CFG->formatstringstriptags = 0;
        [$course, $outcome] = $this->create_course_with_outcome(
            '<img src=x onerror=alert(1)>Outcome',
            format_option::FILTER_OUTCOMES_ONLY
        );

        $outcomes = filters::get_course_outcomes($course->id);

        $this->assertStringNotContainsString('onerror', $outcomes[$outcome->id]);
    }

    /**
     * Multilang names must be resolved, not shown as markup.
     *
     * @covers \format_tiles\local\filters::get_course_outcomes
     * @return void
     */
    public function test_get_course_outcomes_multilang(): void {
        global $CFG;
        $this->resetAfterTest(true);
        require_once($CFG->libdir . '/filterlib.php');
        $CFG->filterall = true;
        $CFG->stringfilters = 'multilang';
        filter_set_global_state('multilang', TEXTFILTER_ON);
        \filter_manager::reset_caches();
        $name = '<span lang="de" class="multilang">Deutsch</span><span lang="en" class="multilang">English</span>';
        [$course, $outcome] = $this->create_course_with_outcome($name, format_option::FILTER_OUTCOMES_ONLY);

        $outcomes = filters::get_course_outcomes($course->id);

        $this->assertSame([$outcome->id => 'English'], $outcomes);
    }

    /**
     * Without outcomes enabled nothing is returned.
     *
     * @covers \format_tiles\local\filters::get_course_outcomes
     * @return void
     */
    public function test_get_course_outcomes_disabled(): void {
        global $CFG;
        $this->resetAfterTest(true);
        [$course] = $this->create_course_with_outcome('Outcome', format_option::FILTER_OUTCOMES_ONLY);
        $CFG->enableoutcomes = 0;

        $this->assertSame([], filters::get_course_outcomes($course->id));
    }

    /**
     * Enabled outcomes but none defined for the course.
     *
     * @covers \format_tiles\local\filters::get_course_outcomes
     * @return void
     */
    public function test_get_course_outcomes_none(): void {
        global $CFG;
        $this->resetAfterTest(true);
        $CFG->enableoutcomes = 1;
        $course = $this->getDataGenerator()->create_course(['format' => 'tiles']);

        $this->assertSame([], filters::get_course_outcomes($course->id));
    }

    /**
     * The edit form validation calls get_course_outcomes() with course id 0 when a course is created.
     * Guards against context_course::instance(0) throwing an exception.
     *
     * @covers \format_tiles\local\filters::get_course_outcomes
     * @return void
     */
    public function test_get_course_outcomes_courseid_zero(): void {
        global $CFG;
        $this->resetAfterTest(true);
        $CFG->enableoutcomes = 1;

        $this->assertSame([], filters::get_course_outcomes(0));
    }

    /**
     * The filter button must show the escaped name once, without double escaping.
     *
     * @covers \format_tiles\local\filters::get_filter_outcome_buttons_data
     * @return void
     */
    public function test_filter_button_data_and_template(): void {
        global $CFG, $OUTPUT;
        $this->resetAfterTest(true);
        $CFG->formatstringstriptags = 1;
        [$course, $outcome] = $this->create_course_with_outcome(
            '<img src=x onerror=alert(1)>Lesekompetenz & Präsentation',
            format_option::FILTER_OUTCOMES_ONLY
        );
        $tiles = [['tileid' => 1, 'tileoutcomeid' => $outcome->id]];

        $outcomes = filters::get_course_outcomes($course->id);
        $buttons = filters::get_filter_outcome_buttons_data($tiles, $outcomes);
        $this->assertCount(1, $buttons);
        $html = $OUTPUT->render_from_template('format_tiles/filterbutton', $buttons[0]);

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringContainsString('Lesekompetenz &amp; Präsentation', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    /**
     * The outcome select of the tile edit form must contain the escaped names.
     *
     * @covers \format_tiles\local\filters::get_course_outcomes
     * @return void
     */
    public function test_section_edit_form_select_escaped(): void {
        global $CFG;
        $this->resetAfterTest(true);
        $CFG->formatstringstriptags = 1;
        [$course, $outcome] = $this->create_course_with_outcome(
            '<img src=x onerror=alert(1)>Outcome',
            format_option::FILTER_OUTCOMES_ONLY
        );

        \core_courseformat\base::reset_course_cache($course->id);
        $format = course_get_format($course->id);
        $options = $format->section_format_options(true);

        $this->assertArrayHasKey('tileoutcomeid', $options);
        $selectoptions = $options['tileoutcomeid']['element_attributes'][0];
        $this->assertSame([$outcome->id => 'Outcome', 0 => get_string('none', 'format_tiles')], $selectoptions);
    }
}
