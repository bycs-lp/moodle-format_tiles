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

namespace format_tiles\output;

/**
 * Tests for course_output.
 *
 * @package    format_tiles
 * @copyright  2026 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(course_output::class)]
final class course_output_test extends \advanced_testcase {
    /**
     * Activity names are escaped in the rendered subtiles.
     */
    public function test_activity_names_are_escaped(): void {
        global $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(
            ['format' => 'tiles', 'numsections' => 1, 'courseusesubtiles' => 1],
            ['createsections' => true]
        );
        foreach (['Slides" onmouseover="window.__x=1', 'Slides & Übungen "A"'] as $name) {
            $this->getDataGenerator()->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => $name]);
        }
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        $renderer = $PAGE->get_renderer('format_tiles');

        $data = (new course_output($course, true, 1))->export_for_template($renderer);
        $html = $renderer->render_from_template('format_tiles/single_section_content', $data);

        $this->assertStringContainsString('data-title="Slides&amp;quot; onmouseover=&amp;quot;window.__x=1"', $html);
        $this->assertStringNotContainsString('data-title="Slides" onmouseover', $html);
        $this->assertStringNotContainsString('alt="Slides" onmouseover', $html);
        $this->assertStringContainsString('alt="Slides &amp; Übungen &quot;A&quot;"', $html);
    }
}
