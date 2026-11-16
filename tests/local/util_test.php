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

namespace format_tiles\local;

/**
 * Tests for util.
 *
 * @package    format_tiles
 * @copyright  2026 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(util::class)]
final class util_test extends \advanced_testcase {
    /**
     * The name of get_course_mod_info() is safe in the modal template.
     */
    public function test_get_course_mod_info_name_is_escaped(): void {
        global $OUTPUT;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['format' => 'tiles', 'numsections' => 1]);
        $cm = $this->getDataGenerator()->create_module(
            'page',
            ['course' => $course->id, 'section' => 1, 'name' => 'Slides" onmouseover="window.__x=1']
        );
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));

        $name = util::get_course_mod_info($course->id, $cm->cmid)->name;
        $html = $OUTPUT->render_from_template(
            'format_tiles/embed_file_modal_body',
            ['id' => $cm->cmid, 'cmid' => $cm->cmid, 'activityname' => $name]
        );

        $this->assertStringContainsString('title="Slides&quot; onmouseover=&quot;window.__x=1"', $html);
        $this->assertStringNotContainsString('onmouseover="', $html);
    }
}
