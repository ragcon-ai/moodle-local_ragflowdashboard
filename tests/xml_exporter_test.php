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

namespace local_ragflowdashboard;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for the dashboard XML export date parsing.
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(xml_exporter::class)]
final class xml_exporter_test extends \advanced_testcase {
    /**
     * parse_date(): a YYYY-MM-DD string parses to the start (or end) of that day; anything else returns the
     * given default.
     *
     * @return void
     */
    public function test_parse_date(): void {
        // Start of day.
        $this->assertSame(
            make_timestamp(2026, 8, 15, 0, 0, 0),
            xml_exporter::parse_date('2026-08-15', 42)
        );
        // Surrounding whitespace is trimmed.
        $this->assertSame(
            make_timestamp(2026, 8, 15, 0, 0, 0),
            xml_exporter::parse_date('  2026-08-15  ', 42)
        );
        // End of day = start of day + 23:59:59 (same non-DST day).
        $start = xml_exporter::parse_date('2026-08-15', 0, false);
        $end = xml_exporter::parse_date('2026-08-15', 0, true);
        $this->assertSame(23 * 3600 + 59 * 60 + 59, $end - $start);
        // Invalid input returns the default.
        $this->assertSame(42, xml_exporter::parse_date('not-a-date', 42));
        $this->assertSame(42, xml_exporter::parse_date('', 42));
        $this->assertSame(7, xml_exporter::parse_date('2026/08/15', 7), 'only ISO YYYY-MM-DD is accepted');
    }

    /**
     * export(): produces a <ragflowusage> document with one <entry> per in-range log row (attributes for
     * component/success/errortype/… and the owning view), respecting the from/to range. All views are
     * exported — there is no per-view scoping.
     *
     * @return void
     */
    public function test_export(): void {
        global $DB;
        $this->resetAfterTest();
        $insert = function (array $row) use ($DB): void {
            $DB->insert_record(stats::TABLE, (object) array_merge([
                'timecreated' => 1000, 'component' => 'block_ragflowtutor', 'action' => 'chat', 'userid' => 0,
                'courseid' => 0, 'contextid' => 0, 'success' => 1, 'errortype' => '', 'latencyms' => 100,
                'itemcount' => 0,
            ], $row));
        };
        $insert(['timecreated' => 1500, 'component' => 'block_ragflowtutor', 'success' => 1]);
        $insert(['timecreated' => 1600, 'component' => 'aiplacement_ragflowhelpdesk', 'success' => 0, 'errortype' => 'network']);
        $insert(['timecreated' => 500]); // Outside [1000, 2000], so excluded.

        $doc = new \SimpleXMLElement(xml_exporter::export(1000, 2000));
        $this->assertSame('ragflowusage', $doc->getName());
        $this->assertCount(2, $doc->entry, 'only the two rows within the time range (all views exported)');

        // Every entry carries typed attributes and the owning view, independent of ordering.
        $bycomponent = [];
        foreach ($doc->entry as $entry) {
            $bycomponent[(string) $entry['component']] = $entry;
        }
        $this->assertArrayHasKey('block_ragflowtutor', $bycomponent);
        $this->assertArrayHasKey('aiplacement_ragflowhelpdesk', $bycomponent);
        $this->assertSame('1', (string) $bycomponent['block_ragflowtutor']['success']);
        $this->assertSame('0', (string) $bycomponent['aiplacement_ragflowhelpdesk']['success']);
        $this->assertSame('network', (string) $bycomponent['aiplacement_ragflowhelpdesk']['errortype']);
        $this->assertNotSame('', (string) $bycomponent['block_ragflowtutor']['view'], 'each entry is labelled with its view');
    }
}
