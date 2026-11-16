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

namespace format_tiles;

/**
 * Tests that the templates escape activity and section names.
 *
 * @package    format_tiles
 * @copyright  2026 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class templates_test extends \advanced_testcase {
    /**
     * Data provider for test_template_escapes_name.
     *
     * @return array Datasets with template, context, expected and unexpected output.
     */
    public static function template_escapes_name_provider(): array {
        $malicious = 'Slides" onmouseover="window.__x=1';
        $escaped = 'Slides&quot; onmouseover=&quot;window.__x=1';
        return [
            'course_module_data_title' => [
                'template' => 'format_tiles/course_module',
                'context' => ['useSubtiles' => true, 'cmid' => 1, 'activitynamehtml' => $malicious],
                'expected' => 'data-title="' . $escaped . '"',
                'unexpected' => 'onmouseover="',
            ],
            'cmitem_data_title' => [
                'template' => 'format_tiles/local/content/section/cmitem',
                'context' => ['cmid' => 1, 'activitynamehtml' => $malicious],
                'expected' => 'data-title="' . $escaped . '"',
                'unexpected' => 'onmouseover="',
            ],
            'tileicon_alt' => [
                'template' => 'format_tiles/tileicon',
                'context' => ['icon' => ['url' => 'https://example.com/icon.svg', 'label' => '<img src=x onerror=alert(1)>']],
                'expected' => 'alt="&lt;img src=x onerror=alert(1)&gt;"',
                'unexpected' => '<img src=x',
            ],
            'multi_section_tile_aria_label' => [
                'template' => 'format_tiles/multi_section_tile',
                'context' => ['tileid' => 1, 'tilearialabel' => $malicious],
                'expected' => 'aria-label="' . $escaped . '"',
                'unexpected' => 'onmouseover="',
            ],
            'embed_file_modal_body_iframe_title' => [
                // The activity name is passed already HTML-escaped (see util::get_course_mod_info()).
                'template' => 'format_tiles/embed_file_modal_body',
                'context' => ['id' => 1, 'cmid' => 1, 'activityname' => $escaped],
                'expected' => 'title="' . $escaped . '"',
                'unexpected' => 'onmouseover="',
            ],
        ];
    }

    /**
     * Test that a template escapes a malicious name.
     *
     * @param string $template The template name.
     * @param array $context The template context.
     * @param string $expected String the output must contain.
     * @param string $unexpected String the output must not contain.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('template_escapes_name_provider')]
    public function test_template_escapes_name(string $template, array $context, string $expected, string $unexpected): void {
        global $OUTPUT;
        $html = $OUTPUT->render_from_template($template, $context);
        $this->assertStringContainsString($expected, $html);
        $this->assertStringNotContainsString($unexpected, $html);
    }
}
