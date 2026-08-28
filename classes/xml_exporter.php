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

/**
 * Builds an XML document of usage-log rows in a date range, for the dashboard export.
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xml_exporter {
    /**
     * Return the whole usage log between two timestamps (inclusive) as an XML string. Every entry carries
     * the view (source subplugin) that owns its component, so all views are covered in one document,
     * grouped by view (source order, then "Other") and chronological within a view.
     *
     * @param int $from Unix time lower bound.
     * @param int $to Unix time upper bound.
     * @return string XML
     */
    public static function export(int $from, int $to): string {
        global $DB;
        [$map, $order] = exporter::view_map();
        $other = get_string('export:otherview', 'local_ragflowdashboard');
        $vieworder = array_flip($order);

        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->setIndent(true);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('ragflowusage');
        $writer->writeAttribute('from', date('c', $from));
        $writer->writeAttribute('to', date('c', $to));

        $rows = $DB->get_records_select(
            stats::TABLE,
            'timecreated >= :from AND timecreated <= :to',
            ['from' => $from, 'to' => $to],
            'timecreated ASC'
        );
        $bucketed = [];
        foreach ($rows as $row) {
            $view = $map[$row->component] ?? $other;
            $bucketed[$vieworder[$view] ?? PHP_INT_MAX][] = $row;
        }
        ksort($bucketed);
        foreach ($bucketed as $group) {
            foreach ($group as $row) {
                $writer->startElement('entry');
                $writer->writeAttribute('id', (string) $row->id);
                $writer->writeAttribute('view', $map[$row->component] ?? $other);
                $writer->writeAttribute('time', date('c', (int) $row->timecreated));
                $writer->writeAttribute('component', (string) $row->component);
                $writer->writeAttribute('action', (string) $row->action);
                $writer->writeAttribute('success', $row->success ? '1' : '0');
                $writer->writeAttribute('errortype', (string) $row->errortype);
                $writer->writeAttribute('latencyms', (string) (int) $row->latencyms);
                $writer->writeAttribute('itemcount', (string) (int) $row->itemcount);
                $writer->writeAttribute('userid', (string) (int) $row->userid);
                $writer->writeAttribute('courseid', (string) (int) $row->courseid);
                $writer->endElement();
            }
        }

        $writer->endElement();
        $writer->endDocument();
        return $writer->outputMemory();
    }

    /**
     * Convert a yyyy-mm-dd string to a timestamp, or a default when empty/invalid.
     *
     * @param string $date yyyy-mm-dd
     * @param int $default Fallback timestamp.
     * @param bool $endofday Use the end of the day (23:59:59) instead of the start.
     * @return int
     */
    public static function parse_date(string $date, int $default, bool $endofday = false): int {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($date), $m)) {
            $hour = $endofday ? 23 : 0;
            $min = $endofday ? 59 : 0;
            $sec = $endofday ? 59 : 0;
            return make_timestamp((int) $m[1], (int) $m[2], (int) $m[3], $hour, $min, $sec);
        }
        return $default;
    }
}
