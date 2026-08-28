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
 * Builds the usage-log export in CSV (via \core\dataformat) and PDF (a KPI summary + a chat/usage table).
 * XML is produced by {@see xml_exporter}. All formats draw the same metrics rows from the usage log
 * ({@see stats::TABLE}); no message content is exported (that lives only in the opt-in debug capture).
 *
 * The export always covers ALL views: every row is labelled with the view (source subplugin) that owns its
 * component and the rows are grouped by view, one view after another, in a single file (a "View" column in
 * CSV/XML, one section per view in the PDF). The acting user is already 0 in the log when anonymisation is
 * on, so name resolution needs no extra gate: userid 0 renders as a dash.
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter {
    /** @var int Maximum rows embedded in a PDF (CSV/XML are unbounded). */
    const PDF_MAX_ROWS = 2000;

    /**
     * The export columns, in order, as key => header label. The first column is the view a row belongs to.
     *
     * @return array
     */
    protected static function columns(): array {
        $s = function (string $k): string {
            return get_string('export:col:' . $k, 'local_ragflowdashboard');
        };
        return [
            'view' => $s('view'),
            'time' => $s('time'),
            'component' => $s('component'),
            'action' => $s('action'),
            'success' => $s('success'),
            'errortype' => $s('errortype'),
            'latencyms' => $s('latencyms'),
            'itemcount' => $s('itemcount'),
            'user' => $s('user'),
            'course' => $s('course'),
            'tokensprompt' => $s('tokensprompt'),
            'tokenscompletion' => $s('tokenscompletion'),
            'tokenstotal' => $s('tokenstotal'),
        ];
    }

    /**
     * The view map: component => view name, plus the ordered list of view names (source order, then the
     * catch-all "Other" for any component not owned by a source).
     *
     * @return array [array $componenttoview, string[] $orderednames]
     */
    public static function view_map(): array {
        $map = [];
        $order = [];
        foreach (source_manager::instances() as $source) {
            $name = $source->get_name();
            $order[] = $name;
            foreach ($source->get_components() as $comp) {
                $map[$comp] = $name;
            }
        }
        $order[] = get_string('export:otherview', 'local_ragflowdashboard');
        return [$map, $order];
    }

    /**
     * All usage-log rows for a date range (inclusive), formatted for display, keyed by column and grouped
     * by view (source order, then "Other"); within a view they stay in chronological order.
     *
     * @param int $from Start timestamp.
     * @param int $to End timestamp.
     * @return array[] Each row keyed by the column keys of {@see columns()}.
     */
    public static function rows(int $from, int $to): array {
        global $DB;
        [$map, $order] = self::view_map();
        $other = get_string('export:otherview', 'local_ragflowdashboard');

        $records = $DB->get_records_select(
            stats::TABLE,
            'timecreated >= :from AND timecreated <= :to',
            ['from' => $from, 'to' => $to],
            'timecreated ASC'
        );

        $userids = [];
        $courseids = [];
        foreach ($records as $r) {
            if ((int) $r->userid > 0) {
                $userids[(int) $r->userid] = 1;
            }
            if ((int) $r->courseid > 0) {
                $courseids[(int) $r->courseid] = 1;
            }
        }
        $usernames = self::user_names(array_keys($userids));
        $coursenames = self::course_names(array_keys($courseids));

        $dash = get_string('export:none', 'local_ragflowdashboard');
        $yes = get_string('yes');
        $no = get_string('no');
        $timefmt = get_string('strftimedatetimeshort', 'langconfig');

        // Bucket by view so we can emit the views one after another in a defined order.
        $buckets = [];
        foreach ($records as $r) {
            $uid = (int) $r->userid;
            $cid = (int) $r->courseid;
            $view = $map[$r->component] ?? $other;
            $buckets[$view][] = [
                'view' => $view,
                'time' => userdate((int) $r->timecreated, $timefmt),
                'component' => (string) $r->component,
                'action' => (string) $r->action,
                'success' => $r->success ? $yes : $no,
                'errortype' => (string) $r->errortype,
                'latencyms' => (int) $r->latencyms,
                'itemcount' => (int) $r->itemcount,
                'user' => ($uid > 0 && isset($usernames[$uid])) ? $usernames[$uid] : $dash,
                'course' => ($cid > 0 && isset($coursenames[$cid])) ? $coursenames[$cid] : $dash,
                'tokensprompt' => (int) $r->tokensprompt,
                'tokenscompletion' => (int) $r->tokenscompletion,
                'tokenstotal' => (int) $r->tokenstotal,
            ];
        }

        $rows = [];
        foreach ($order as $viewname) {
            if (!empty($buckets[$viewname])) {
                foreach ($buckets[$viewname] as $row) {
                    $rows[] = $row;
                }
            }
        }
        return $rows;
    }

    /**
     * Aggregate KPIs for a date range (used in the PDF header).
     *
     * @param int $from
     * @param int $to
     * @return \stdClass {total, successful, failed, tokens}
     */
    public static function kpis(int $from, int $to): \stdClass {
        global $DB;
        $row = $DB->get_record_sql(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) AS successful,
                    SUM(tokenstotal) AS tokens
               FROM {' . stats::TABLE . '}
              WHERE timecreated >= :from AND timecreated <= :to',
            ['from' => $from, 'to' => $to]
        );
        $total = (int) ($row->total ?? 0);
        $successful = (int) ($row->successful ?? 0);
        return (object) [
            'total' => $total,
            'successful' => $successful,
            'failed' => $total - $successful,
            'tokens' => (int) ($row->tokens ?? 0),
        ];
    }

    /**
     * Stream the log as a downloadable spreadsheet via Moodle's dataformat writer (CSV/Excel/ODS).
     * This sends output and terminates the request.
     *
     * @param string $format A dataformat plugin name (e.g. 'csv').
     * @param int $from
     * @param int $to
     * @param string $filenamebase Download file name without extension.
     * @return void
     */
    public static function download_spreadsheet(string $format, int $from, int $to, string $filenamebase): void {
        $columns = self::columns();
        $rows = self::csv_safe(self::rows($from, $to));
        \core\dataformat::download_data($filenamebase, $format, $columns, new \ArrayIterator($rows));
    }

    /**
     * Neutralise spreadsheet formula injection: a cell whose first character is one of = + - @ (or a
     * leading tab/CR) is treated as a formula by Excel/LibreOffice/Sheets, so a user- or teacher-supplied
     * value (e.g. a full name or course short name) could execute on open. Prefix such string cells with a
     * single quote so they render as literal text. Numeric cells are left untouched.
     *
     * @param array[] $rows Rows keyed by column.
     * @return array[] The same rows with dangerous string cells escaped.
     */
    public static function csv_safe(array $rows): array {
        $dangerous = ['=', '+', '-', '@', "\t", "\r"];
        foreach ($rows as &$row) {
            foreach ($row as $key => $value) {
                if (is_string($value) && $value !== '' && in_array($value[0], $dangerous, true)) {
                    $row[$key] = "'" . $value;
                }
            }
        }
        unset($row);
        return $rows;
    }

    /**
     * Build and stream a PDF report: a KPI summary, then one section per view (chat/usage table). Sends
     * output and terminates.
     *
     * @param int $from
     * @param int $to
     * @param string $filenamebase Download file name without extension.
     * @return void
     */
    public static function download_pdf(int $from, int $to, string $filenamebase): void {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');

        $kpis = self::kpis($from, $to);
        $allrows = self::rows($from, $to);
        $truncated = count($allrows) > self::PDF_MAX_ROWS;
        if ($truncated) {
            $allrows = array_slice($allrows, 0, self::PDF_MAX_ROWS);
        }

        // Table columns are all columns except the view (the view is the section heading).
        $columns = self::columns();
        unset($columns['view']);

        $pdf = new \pdf('L');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetTitle(get_string('export:pdftitle', 'local_ragflowdashboard'));
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();

        $range = userdate($from, get_string('strftimedaydate', 'langconfig'))
            . ' – ' . userdate($to, get_string('strftimedaydate', 'langconfig'));
        $rate = $kpis->total > 0 ? round(100 * $kpis->successful / $kpis->total, 1) : 0;

        $html = '<h2>' . s(get_string('export:pdftitle', 'local_ragflowdashboard')) . '</h2>'
            . '<p><strong>' . s(get_string('export:allviews', 'local_ragflowdashboard')) . '</strong>'
            . ' &nbsp; <strong>' . s($range) . '</strong></p>'
            . '<p>'
            . s(get_string('export:kpi:requests', 'local_ragflowdashboard')) . ': <strong>' . $kpis->total . '</strong> &nbsp; '
            . s(get_string('export:kpi:successrate', 'local_ragflowdashboard')) . ': <strong>' . $rate . '%</strong> &nbsp; '
            . s(get_string('export:kpi:failures', 'local_ragflowdashboard')) . ': <strong>' . $kpis->failed . '</strong> &nbsp; '
            . s(get_string('export:kpi:tokens', 'local_ragflowdashboard')) . ': <strong>' . $kpis->tokens . '</strong>'
            . '</p>';

        $th = '';
        foreach ($columns as $label) {
            $th .= '<th>' . s($label) . '</th>';
        }

        // Group the (already view-ordered) rows into sections, one heading per view.
        $sections = [];
        foreach ($allrows as $row) {
            $sections[$row['view']][] = $row;
        }
        if (empty($sections)) {
            $html .= '<p><em>' . s(get_string('export:norows', 'local_ragflowdashboard')) . '</em></p>';
        }
        foreach ($sections as $viewname => $viewrows) {
            $trs = '';
            foreach ($viewrows as $row) {
                $tds = '';
                foreach (array_keys($columns) as $key) {
                    $tds .= '<td>' . s((string) $row[$key]) . '</td>';
                }
                $trs .= '<tr>' . $tds . '</tr>';
            }
            $html .= '<h3>' . s($viewname) . ' (' . count($viewrows) . ')</h3>'
                . '<table border="1" cellpadding="2"><thead><tr>' . $th . '</tr></thead><tbody>' . $trs . '</tbody></table>';
        }
        if ($truncated) {
            $html .= '<p><em>' . s(get_string('export:pdftruncated', 'local_ragflowdashboard', self::PDF_MAX_ROWS))
                . '</em></p>';
        }

        $pdf->SetFont('freesans', '', 7);
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output($filenamebase . '.pdf', 'D');
    }

    /**
     * Resolve user ids to full names (batched).
     *
     * @param array $ids
     * @return array [userid => fullname]
     */
    protected static function user_names(array $ids): array {
        global $DB;
        if (empty($ids)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'u');
        $select = 'id, ' . implode(', ', \core_user\fields::get_name_fields());
        $recs = $DB->get_records_select('user', 'id ' . $insql, $params, '', $select);
        $out = [];
        foreach ($recs as $u) {
            $out[(int) $u->id] = fullname($u);
        }
        return $out;
    }

    /**
     * Resolve course ids to short names (batched).
     *
     * @param array $ids
     * @return array [courseid => shortname]
     */
    protected static function course_names(array $ids): array {
        global $DB;
        if (empty($ids)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'c');
        $recs = $DB->get_records_select('course', 'id ' . $insql, $params, '', 'id, shortname');
        $ctx = \context_system::instance();
        $out = [];
        foreach ($recs as $c) {
            $out[(int) $c->id] = format_string($c->shortname, true, ['context' => $ctx]);
        }
        return $out;
    }
}
