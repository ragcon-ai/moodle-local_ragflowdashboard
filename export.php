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
 * XML export of the RAGflow usage log for a date range (admin-only).
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

require_login();
$context = context_system::instance();
require_capability('local/ragflowdashboard:view', $context);
require_sesskey();
// This is a download endpoint (no page render), but formatting helpers (format_string) fall back to
// $PAGE->context – set it explicitly so they don't emit a "context was not set" notice.
$PAGE->set_context($context);

$from = optional_param('from', '', PARAM_TEXT);
$to = optional_param('to', '', PARAM_TEXT);
$format = optional_param('format', 'csv', PARAM_ALPHA);
if (!in_array($format, ['csv', 'xml', 'pdf'], true)) {
    $format = 'csv';
}
$fromts = \local_ragflowdashboard\xml_exporter::parse_date($from, time() - 30 * DAYSECS, false);
$tots = \local_ragflowdashboard\xml_exporter::parse_date($to, time(), true);

// The export always covers all views (all source subplugins), grouped by view in one file.
$filenamebase = 'ragflow-usage-all-' . date('Ymd', $fromts) . '-' . date('Ymd', $tots);

// All formats export the same usage-log metrics (no message content); each streams and terminates.
if ($format === 'xml') {
    $xml = \local_ragflowdashboard\xml_exporter::export($fromts, $tots);
    send_file($xml, $filenamebase . '.xml', 0, 0, true, true, 'application/xml');
} else if ($format === 'pdf') {
    \local_ragflowdashboard\exporter::download_pdf($fromts, $tots, $filenamebase);
} else {
    \local_ragflowdashboard\exporter::download_spreadsheet('csv', $fromts, $tots, $filenamebase);
}
