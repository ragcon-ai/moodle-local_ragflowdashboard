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

namespace local_ragflowdashboard\output;

use html_writer;
use local_ragflowdashboard\api;
use local_ragflowdashboard\source\base;
use local_ragflowdashboard\source_manager;
use local_ragflowdashboard\stats;
use local_ragflowdashboard\tabs;

/**
 * Renderer for the RAGflow Dashboard: KPI cards, charts (Moodle core Chart API → Chart.js), the error-log
 * table, and one section per installed source subplugin.
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /** @var string Chart colour for success/usage. */
    const CHART_GREEN = '#198754';
    /** @var string Chart colour for failures/errors. */
    const CHART_RED = '#dc3545';
    /** @var string[] Blue shades (well-separated) for the neutral usage charts (bars/pie slices). */
    const CHART_BLUES = ['#084594', '#2171b5', '#4292c6', '#6baed6', '#9ecae1'];
    /** @var string[] Red shades for multi-item error charts (pie slices). */
    const CHART_REDS = ['#dc3545', '#e35d6a', '#ea868f', '#f1aeb5', '#f8d7da'];

    /**
     * Cycle a shade list into $n colours (for the multi-item charts).
     *
     * @param int $n
     * @param string[] $shades
     * @return string[]
     */
    protected static function palette(int $n, array $shades): array {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = $shades[$i % count($shades)];
        }
        return $out;
    }

    /**
     * The dashboard controls bar: a "view" dropdown (All + one entry per source subplugin), a period
     * dropdown and a reload button. The AMD module reloads the view via a fragment when any of these
     * changes; without JS the selects submit the form and the page re-renders server-side.
     *
     * @param string $view The selected view key ('all' or a source frankenstyle name).
     * @param int $days The selected period in days.
     * @param string $tab The active tab key (kept across the no-JS GET fallback).
     * @return string HTML
     */
    public function controls(string $view, int $days, string $tab = ''): string {
        $viewoptions = ['all' => get_string('viewall', 'local_ragflowdashboard')];
        foreach (source_manager::instances() as $source) {
            // The Tokens tab only lists token-consuming (chat) sources — search has no tokens.
            if ($tab === tabs::TOKENS && !$source->has_token_usage()) {
                continue;
            }
            $viewoptions[$source->get_frankenstyle()] = $source->get_name();
        }
        $daysoptions = [
            1 => get_string('windowtoday', 'local_ragflowdashboard'),
            2 => 2,
            3 => 3,
            7 => 7,
            14 => 14,
            30 => 30,
            90 => 90,
        ];

        // Keep the active tab across the no-JS GET fallback.
        $controls = ($tab !== '') ? html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => $tab]) : '';

        $controls .= html_writer::tag(
            'label',
            get_string('view', 'local_ragflowdashboard') . ' '
                . html_writer::select(
                    $viewoptions,
                    'view',
                    $view,
                    false,
                    ['data-region' => 'rfd-view-select', 'class' => 'custom-select']
                ),
            ['class' => 'me-3']
        );
        $controls .= html_writer::tag(
            'label',
            get_string('windowdays', 'local_ragflowdashboard') . ' '
                . html_writer::select(
                    $daysoptions,
                    'days',
                    $days,
                    false,
                    ['data-region' => 'rfd-days-select', 'class' => 'custom-select']
                ),
            ['class' => 'me-3']
        );
        $controls .= html_writer::tag(
            'button',
            get_string('reload', 'local_ragflowdashboard'),
            ['type' => 'submit', 'data-action' => 'rfd-reload', 'class' => 'btn btn-primary']
        );

        // The <form> keeps a no-JS fallback (GET reload of index.php with view+days).
        return html_writer::tag('form', $controls, [
            'method' => 'get',
            'action' => new \moodle_url('/local/ragflowdashboard/index.php'),
            'class' => 'mb-4 d-flex align-items-end gap-2',
            'data-region' => 'rfd-controls',
        ]);
    }

    /**
     * Render a single dashboard view (shown in the fragment container): either the global overview ('all')
     * or one source subplugin's view. This is the content reloaded via AJAX when the dropdown changes.
     *
     * @param string $view 'all' or a source frankenstyle name.
     * @param int $days Reporting window in days.
     * @return string HTML
     */
    public function view(string $view, int $days): string {
        $since = self::since($days);
        if ($view !== 'all') {
            foreach (source_manager::instances() as $source) {
                if ($source->get_frankenstyle() === $view) {
                    return $source->render_view($this, $since, $days);
                }
            }
        }
        return $this->global_view($since, $days);
    }

    /**
     * Render one tab's content (also the fragment payload for in-tab AJAX reloads).
     *
     * @param string $tab A {@see tabs} key.
     * @param string $view 'all' or a source frankenstyle name (used by the filterable tabs).
     * @param int $days Reporting window in days.
     * @param array $extra Tab-specific request params (API-calls paging/filters, etc.).
     * @return string HTML
     */
    public function render_tab(string $tab, string $view, int $days, array $extra = []): string {
        $tab = tabs::normalise($tab);
        $out = tabs::is_filterable($tab) ? $this->controls($view, $days, $tab) : '';
        switch ($tab) {
            case tabs::USAGE:
                $out .= $this->tab_usage($view, $days);
                break;
            case tabs::TOKENS:
                $out .= $this->tab_tokens($view, $days);
                break;
            case tabs::APICALLS:
                $out .= $this->tab_apicalls($extra);
                break;
            case tabs::ERRORS:
                $out .= $this->tab_errors($view, $days);
                break;
            case tabs::EXPORT:
                $out .= $this->tab_export($view, $days);
                break;
            case tabs::STATUS:
            default:
                $out .= $this->tab_status();
                break;
        }
        return $out;
    }

    /**
     * The component filter for the selected view: [] for "All", else the source's components.
     *
     * @param string $view
     * @return string[]
     */
    protected function view_components(string $view): array {
        if ($view !== 'all') {
            foreach (source_manager::instances() as $source) {
                if ($source->get_frankenstyle() === $view) {
                    return $source->get_components();
                }
            }
        }
        return [];
    }

    /**
     * Usage tab: KPI cards, the per-day requests chart and (for "All") the requests-by-feature bar.
     *
     * @param string $view
     * @param int $days
     * @return string
     */
    protected function tab_usage(string $view, int $days): string {
        $since = self::since($days);
        $components = $this->view_components($view);
        $out = $this->box($this->kpis(stats::totals($since, $components), $days));
        // Requests per day above requests per feature (each in its own uniformly sized box).
        // Each section is a collapsible accordion, all closed by default. They share one group so only one
        // stays open at a time; the client remembers which across view/period reloads (data-rfd-key).
        $out .= $this->usage_chart($since, $components, false, 'usage');
        if ($view === 'all') {
            $out .= $this->component_chart($since, false, 'component');
        }
        // Who and where: most active users, coarse user groups (trainers vs students), busiest courses.
        $out .= $this->top_users_chart($since, $components, false, 'byuser');
        $out .= $this->role_chart($since, $components, false, 'byrole');
        $out .= $this->course_chart($since, $components, false, 'bycourse');
        return $out;
    }

    /**
     * Tokens tab: total-token KPIs, tokens per day, and the breakdown by plugin and by provider instance.
     * Chat only – search consumes no LLM tokens. Counts accrue from the version that added token capture.
     *
     * @param string $view
     * @param int $days
     * @return string
     */
    protected function tab_tokens(string $view, int $days): string {
        $since = self::since($days);
        $components = $this->view_components($view);
        // Be transparent about what is (and is not) counted, and that the figures carry no guarantee.
        $out = html_writer::div(get_string('tokensinfo', 'local_ragflowdashboard'), 'alert alert-info');
        $out .= $this->box($this->token_kpis(stats::tokens_totals($since, $components)));
        // All sections closed by default and mutually exclusive (client remembers the open one), like Usage.
        $out .= $this->tokens_perday_chart($since, $components, false, 'tokensday');
        $out .= $this->tokens_component_chart($since, false, 'tokenscomp');
        $out .= $this->tokens_provider_chart($since, $components, false, 'tokensinst');
        return $out;
    }

    /**
     * Token KPI cards (total / prompt / completion).
     *
     * @param \stdClass $t {prompt, completion, total}
     * @return string
     */
    protected function token_kpis(\stdClass $t): string {
        $cards = [
            ['label' => get_string('kpi:tokenstotal', 'local_ragflowdashboard'), 'value' => number_format($t->total)],
            ['label' => get_string('kpi:tokensprompt', 'local_ragflowdashboard'), 'value' => number_format($t->prompt)],
            ['label' => get_string('kpi:tokenscompletion', 'local_ragflowdashboard'), 'value' => number_format($t->completion)],
        ];
        $html = html_writer::start_div('row mb-3');
        foreach ($cards as $card) {
            $body = html_writer::div($card['value'], 'h3 mb-0') . html_writer::div($card['label'], 'text-muted small');
            $inner = html_writer::div(html_writer::div($body, 'card-body'), 'card h-100');
            $html .= html_writer::div($inner, 'col-md-4 mb-2');
        }
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * Tokens-per-day line chart.
     *
     * @param int $since
     * @param array $components
     * @param bool $open
     * @param string $key
     * @return string
     */
    protected function tokens_perday_chart(int $since, array $components, bool $open, string $key): string {
        $perday = stats::tokens_per_day($since, $components);
        $label = get_string('chart:tokensperday', 'local_ragflowdashboard');
        $startbucket = (int) floor($since / DAYSECS);
        $endbucket = (int) floor(time() / DAYSECS);
        $labels = [];
        $vals = [];
        for ($b = $startbucket; $b <= $endbucket; $b++) {
            $labels[] = userdate($b * DAYSECS, get_string('strftimedateshort', 'langconfig'));
            $vals[] = $perday[$b] ?? 0;
        }
        $chart = new \core\chart_line();
        $chart->set_labels($labels);
        $series = new \core\chart_series($label, $vals);
        $series->set_color(self::CHART_BLUES[1]);
        $chart->add_series($series);
        return $this->chart_box($label, $this->output->render($chart), false, $open, $key, 'rfd-tokens');
    }

    /**
     * Tokens-by-plugin bar chart with the colour-coded data table.
     *
     * @param int $since
     * @param bool $open
     * @param string $key
     * @return string
     */
    protected function tokens_component_chart(int $since, bool $open, string $key): string {
        $data = stats::tokens_by_component($since);
        $label = get_string('chart:tokensbyplugin', 'local_ragflowdashboard');
        if (!$data) {
            return $this->chart_box(
                $label,
                html_writer::div(get_string('nodata', 'local_ragflowdashboard'), 'text-muted'),
                false,
                $open,
                $key,
                'rfd-tokens'
            );
        }
        $labels = [];
        foreach (array_keys($data) as $component) {
            $labels[] = $this->component_label($component);
        }
        $colors = self::palette(count($data), self::CHART_BLUES);
        $chart = new \core\chart_bar();
        $chart->set_labels($labels);
        $series = new \core\chart_series($label, array_values($data));
        $series->set_colors($colors);
        $chart->add_series($series);
        $body = $this->output->render($chart) . $this->chart_data_table($labels, array_values($data), $colors);
        return $this->chart_box($label, $body, false, $open, $key, 'rfd-tokens');
    }

    /**
     * Tokens-by-provider-instance bar chart with the colour-coded data table.
     *
     * @param int $since
     * @param array $components
     * @param bool $open
     * @param string $key
     * @return string
     */
    protected function tokens_provider_chart(int $since, array $components, bool $open, string $key): string {
        global $DB;
        $data = stats::tokens_by_provider($since, $components);
        $label = get_string('chart:tokensbyinstance', 'local_ragflowdashboard');
        if (!$data) {
            return $this->chart_box(
                $label,
                html_writer::div(get_string('nodata', 'local_ragflowdashboard'), 'text-muted'),
                false,
                $open,
                $key,
                'rfd-tokens'
            );
        }
        $ids = [];
        foreach (array_keys($data) as $pid) {
            if ($pid > 0) {
                $ids[] = $pid;
            }
        }
        $provs = $ids ? $DB->get_records_list('ai_providers', 'id', $ids, '', 'id, name') : [];
        $labels = [];
        foreach (array_keys($data) as $pid) {
            $labels[] = ($pid > 0 && isset($provs[$pid]))
                ? format_string($provs[$pid]->name)
                : get_string('tokeninstanceunknown', 'local_ragflowdashboard');
        }
        $colors = self::palette(count($data), self::CHART_BLUES);
        $chart = new \core\chart_bar();
        $chart->set_horizontal(true);
        $chart->set_labels($labels);
        $series = new \core\chart_series($label, array_values($data));
        $series->set_colors($colors);
        $chart->add_series($series);
        $body = $this->output->render($chart) . $this->chart_data_table($labels, array_values($data), $colors);
        return $this->chart_box($label, $body, false, $open, $key, 'rfd-tokens');
    }

    /**
     * Errors tab: failures-by-type chart and the recent-errors log for the current view.
     *
     * @param string $view
     * @param int $days
     * @return string
     */
    protected function tab_errors(string $view, int $days): string {
        $since = self::since($days);
        $components = $this->view_components($view);
        $out = $this->error_chart($since, $components, true);
        $out .= $this->box($this->error_log(stats::recent_errors(50, $components)));
        return $out;
    }

    /**
     * API calls tab: the raw RAGflow API-call log with a filter bar (HTTP status / text / date range),
     * a per-page selector (10/20/50), paging and an optional live (auto-reload) view, plus any debug
     * captures. All state round-trips through the fragment so JS and the no-JS GET fallback stay in sync.
     *
     * @param array $extra {page:int, perpage:int, q:string, status:int, fromdate:string, todate:string, live:bool}
     * @return string
     */
    protected function tab_apicalls(array $extra): string {
        $allowed = [10, 20, 50];
        $perpage = (int) ($extra['perpage'] ?? 20);
        if (!in_array($perpage, $allowed, true)) {
            $perpage = 20;
        }
        $page = max(0, (int) ($extra['page'] ?? 0));
        $q = trim((string) ($extra['q'] ?? ''));
        $status = max(0, (int) ($extra['status'] ?? 0));
        $fromdate = (string) ($extra['fromdate'] ?? '');
        $todate = (string) ($extra['todate'] ?? '');
        $live = !empty($extra['live']);
        $filters = [
            'q' => $q,
            'status' => $status,
            'from' => self::date_to_ts($fromdate, false),
            'to' => self::date_to_ts($todate, true),
        ];

        $result = api::apicalls($page, $perpage, $filters);
        $total = (int) $result['total'];
        $pages = ($perpage > 0) ? (int) ceil($total / $perpage) : 1;
        // A shrinking result set (or a stale page number) can leave the page past the end — clamp and refetch.
        if ($pages > 0 && $page > $pages - 1) {
            $page = $pages - 1;
            $result = api::apicalls($page, $perpage, $filters);
        }

        $body = $this->apicall_controls($perpage, $page, $q, $status, $fromdate, $todate, $live, $total);
        $body .= $this->apicall_log($result['rows']);
        $body .= $this->apicall_paging($page, $pages);
        $out = $this->box($body);

        $debug = api::recent_debug(20, []);
        if ($debug) {
            $out .= $this->box($this->debug_table($debug));
        }
        return $out;
    }

    /**
     * A local date (YYYY-MM-DD) to a unix timestamp bound; empty/invalid input yields 0 (unbounded).
     *
     * @param string $date
     * @param bool $endofday End of the day (23:59:59) rather than its start.
     * @return int
     */
    protected static function date_to_ts(string $date, bool $endofday): int {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return 0;
        }
        $ts = strtotime($date . ($endofday ? ' 23:59:59' : ' 00:00:00'));
        return $ts ?: 0;
    }

    /**
     * The API-calls filter bar: search text, HTTP status, date range, per-page selector, live-view toggle
     * and a match count. Also carries the current page as a hidden field so paging round-trips.
     *
     * @param int $perpage
     * @param int $page
     * @param string $q
     * @param int $status
     * @param string $fromdate
     * @param string $todate
     * @param bool $live
     * @param int $total
     * @return string
     */
    protected function apicall_controls(
        int $perpage,
        int $page,
        string $q,
        int $status,
        string $fromdate,
        string $todate,
        bool $live,
        int $total
    ): string {
        $field = function (string $labelkey, string $input): string {
            $label = get_string($labelkey, 'local_ragflowdashboard');
            return html_writer::tag('label', $label . ' ' . $input, ['class' => 'me-3']);
        };

        $controls = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'tab', 'value' => tabs::APICALLS]);
        $controls .= html_writer::empty_tag('input', [
            'type' => 'hidden', 'name' => 'page', 'value' => $page, 'data-region' => 'rfd-ac-page',
        ]);
        $controls .= $field('apicall_search', html_writer::empty_tag('input', [
            'type' => 'search', 'name' => 'q', 'value' => $q,
            'class' => 'form-control d-inline-block w-auto', 'data-region' => 'rfd-ac-q',
        ]));
        $controls .= $field('apicall_status', html_writer::empty_tag('input', [
            'type' => 'number', 'name' => 'status', 'value' => ($status ?: ''), 'min' => 0,
            'placeholder' => 'e.g. 200', 'class' => 'form-control d-inline-block w-auto', 'data-region' => 'rfd-ac-status',
        ]));
        $controls .= $field('apicall_from', html_writer::empty_tag('input', [
            'type' => 'date', 'name' => 'fromdate', 'value' => $fromdate,
            'class' => 'form-control d-inline-block w-auto', 'data-region' => 'rfd-ac-from',
        ]));
        $controls .= $field('apicall_to', html_writer::empty_tag('input', [
            'type' => 'date', 'name' => 'todate', 'value' => $todate,
            'class' => 'form-control d-inline-block w-auto', 'data-region' => 'rfd-ac-to',
        ]));
        $controls .= $field('apicall_perpage', html_writer::select(
            [10 => 10, 20 => 20, 50 => 50],
            'perpage',
            $perpage,
            false,
            ['data-region' => 'rfd-ac-perpage', 'class' => 'custom-select']
        ));

        $controls .= html_writer::tag('button', get_string('apicall_apply', 'local_ragflowdashboard'), [
            'type' => 'submit', 'class' => 'btn btn-primary',
        ]);

        // The live-view toggle sits on its own line below the button (w-100 forces a wrap in the flex row),
        // so enabling it does not push the button aside.
        $livebox = html_writer::empty_tag('input', [
            'type' => 'checkbox', 'name' => 'live', 'value' => 1, 'class' => 'me-1',
            'data-region' => 'rfd-ac-live',
        ] + ($live ? ['checked' => 'checked'] : []));
        $livelabel = html_writer::tag('label', $livebox . get_string('apicall_live', 'local_ragflowdashboard'), [
            'class' => 'mb-0', 'title' => get_string('apicall_live_help', 'local_ragflowdashboard'),
        ]);
        $controls .= html_writer::div($livelabel, 'w-100 mt-2');

        $form = html_writer::tag('form', $controls, [
            'method' => 'get',
            'action' => (new \moodle_url('/local/ragflowdashboard/index.php'))->out(false),
            'class' => 'mb-3 d-flex flex-wrap align-items-end gap-2',
            'data-region' => 'rfd-apicalls-filter',
        ]);
        $form .= html_writer::div(
            get_string('apicall_total', 'local_ragflowdashboard', $total),
            'text-muted small mb-2'
        );
        return $form;
    }

    /**
     * Previous/next paging controls for the API-calls list. Page links carry data-page so JS can reload the
     * fragment; the surrounding form GET is the no-JS fallback.
     *
     * @param int $page Zero-based current page.
     * @param int $pages Total page count.
     * @return string
     */
    protected function apicall_paging(int $page, int $pages): string {
        if ($pages <= 1) {
            return '';
        }
        $btn = function (string $labelkey, int $target, bool $disabled): string {
            $attrs = [
                'type' => 'button',
                'class' => 'btn btn-outline-secondary btn-sm' . ($disabled ? ' disabled' : ''),
                'data-action' => 'rfd-ac-page',
                'data-page' => $target,
            ];
            if ($disabled) {
                $attrs['disabled'] = 'disabled';
            }
            return html_writer::tag('button', get_string($labelkey, 'local_ragflowdashboard'), $attrs);
        };
        $label = (object) ['page' => $page + 1, 'pages' => $pages];
        $inner = $btn('apicall_prev', $page - 1, $page <= 0)
            . html_writer::span(get_string('apicall_page', 'local_ragflowdashboard', $label), 'mx-2')
            . $btn('apicall_next', $page + 1, $page >= $pages - 1);
        return html_writer::div($inner, 'd-flex align-items-center mt-2');
    }

    /**
     * Export tab: the log export form (scoped to the current view/period).
     *
     * @param string $view
     * @param int $days
     * @return string
     */
    protected function tab_export(string $view, int $days): string {
        return $this->box($this->export_form($view, $days));
    }

    /**
     * Status tab: provider + per-instance health/connectivity. Health checks are added in a later step.
     *
     * @return string
     */
    protected function tab_status(): string {
        $report = \local_ragflowdashboard\status::report();

        // Section 1 — System configuration: three boxes side by side (stacked on narrow screens). The
        // suite-install box needs no API and shows even when RAGflow is unreachable.
        $cols = html_writer::div($this->status_box($report['plugins']), 'col-12 col-xl-4');
        $cols .= html_writer::div($this->status_box($report['provider']), 'col-12 col-xl-4');
        if ($report['reachable'] && !empty($report['actions']['checks'])) {
            $cols .= html_writer::div($this->status_box($report['actions']), 'col-12 col-xl-4');
        }
        $out = $this->status_section(
            'sysconfig',
            get_string('status_section_system', 'local_ragflowdashboard'),
            html_writer::div($cols, 'row rfd-status-sysgrid')
        );

        // Section 2 — Plugin instances (Helpdesk / Search / Tutor); these need RAGflow reachable.
        $body = '';
        if ($report['reachable']) {
            foreach ($report['sources'] as $section) {
                $body .= $this->status_box($section);
            }
        }
        if ($body === '') {
            $body = html_writer::div(
                get_string(
                    $report['reachable'] ? 'status_noinstances' : 'status_connection_fail',
                    'local_ragflowdashboard'
                ),
                'text-muted'
            );
        }
        $out .= $this->status_section(
            'instances',
            get_string('status_section_instances', 'local_ragflowdashboard'),
            $body
        );
        return $out;
    }

    /**
     * A collapsible top-level Status section (System configuration / Plugin instances), open by default.
     *
     * @param string $id
     * @param string $title
     * @param string $body
     * @return string
     */
    protected function status_section(string $id, string $title, string $body): string {
        $summary = html_writer::tag('summary', html_writer::tag('h3', s($title)));
        return html_writer::tag('details', $summary . html_writer::div($body, 'rfd-status-section__body'), [
            'class' => 'rfd-accordion rfd-status-section',
            'data-region' => 'rfd-status-section',
            'data-section' => $id,
            'open' => 'open',
        ]);
    }

    /**
     * A Status area as a shaded, separated box (refreshable independently).
     *
     * @param array $section {area, name, checks}
     * @return string
     */
    protected function status_box(array $section): string {
        return html_writer::div(
            $this->status_box_inner($section),
            'rfd-status-box',
            ['data-region' => 'rfd-status-area', 'data-area' => $section['area']]
        );
    }

    /**
     * The inner content of a Status box (heading + last-checked time + refresh button + checks). Also the
     * fragment payload when a single box is refreshed.
     *
     * @param array $section {area, name, checks}
     * @return string
     */
    public function status_box_inner(array $section): string {
        $checked = userdate(time(), get_string('strftimetime', 'langconfig'));
        $time = html_writer::span(
            get_string('status_lastchecked', 'local_ragflowdashboard', $checked),
            'rfd-status-box__time'
        );
        $refresh = html_writer::tag('button', get_string('status_refresh', 'local_ragflowdashboard'), [
            'type' => 'button',
            'class' => 'btn btn-sm btn-outline-secondary',
            'data-action' => 'rfd-status-refresh',
            'data-area' => $section['area'],
        ]);
        $head = html_writer::div(
            html_writer::tag('h4', s($section['name'])) . $time . $refresh,
            'rfd-status-box__head'
        );
        // Source areas carry rich per-instance rows (course – instance title + links); system areas
        // (plugins/provider/actions) are simple check rows without the API-call proof line.
        $checks = $section['checks'];
        $body = (!empty($checks) && isset($checks[0]['instancename']))
            ? $this->instance_list($section['area'], $checks)
            : $this->status_checklist($checks);
        return $head . $body;
    }

    /**
     * Fragment entry point: re-run and re-render a single Status area (its refresh button).
     *
     * @param string $area
     * @return string
     */
    public function render_status_area(string $area): string {
        return $this->status_box_inner(\local_ragflowdashboard\status::area($area));
    }

    /**
     * Render a list of status checks as coloured badge rows.
     *
     * @param array $checks Each {label, state, detail}.
     * @return string
     */
    protected function status_checklist(array $checks): string {
        if (empty($checks)) {
            return html_writer::div(get_string('status_noinstances', 'local_ragflowdashboard'), 'text-muted');
        }
        $rows = '';
        $settingsurl = '';
        foreach ($checks as $c) {
            // The name is plain text (never a hyperlink); a separate marked link is added once below.
            $row = $this->status_badge($c['state']) . ' ' . html_writer::tag('strong', s($c['label']));
            // No API-call proof line here; only surface a message when the check is not OK.
            if ($c['state'] !== \local_ragflowdashboard\status::OK && $c['detail'] !== '') {
                $row .= html_writer::div(s($c['detail']), 'small text-muted mt-1');
            }
            $rows .= html_writer::tag('li', $row, ['class' => 'mb-2']);
            if (!empty($c['url']) && $settingsurl === '') {
                $settingsurl = $c['url'];
            }
        }
        $out = html_writer::tag('ul', $rows, ['class' => 'list-unstyled rfd-status']);
        if ($settingsurl !== '') {
            // One clearly-labelled settings link for the whole box (internal — no new-window marker).
            $out .= html_writer::div(
                html_writer::link(
                    $settingsurl,
                    get_string('status_link_settings', 'local_ragflowdashboard'),
                    ['class' => 'rfd-instance-link']
                ),
                'rfd-status-settings mt-1'
            );
        }
        return $out;
    }

    /**
     * A coloured status badge for a check state.
     *
     * @param string $state
     * @return string
     */
    protected function status_badge(string $state): string {
        $map = [
            \local_ragflowdashboard\status::OK => 'success',
            \local_ragflowdashboard\status::INFO => 'info',
            \local_ragflowdashboard\status::WARN => 'warning',
            \local_ragflowdashboard\status::ERROR => 'danger',
        ];
        return html_writer::span(
            get_string('status_state_' . $state, 'local_ragflowdashboard'),
            'badge bg-' . ($map[$state] ?? 'secondary')
        );
    }

    /**
     * An external link marked as opening in a new window (↗ + a screen-reader hint + title).
     *
     * @param string $url
     * @param string $text Link text (already escaped where needed).
     * @return string
     */
    protected function ext_link(string $url, string $text): string {
        $newwindow = get_string('status_link_newwindow', 'local_ragflowdashboard');
        $mark = html_writer::span('&#x2197;', 'rfd-ext-icon', ['aria-hidden' => 'true'])
            . html_writer::span($newwindow, 'visually-hidden');
        return html_writer::link($url, $text . ' ' . $mark, [
            'class' => 'rfd-instance-link rfd-ext',
            'target' => '_blank',
            'rel' => 'noopener',
            'title' => $newwindow,
        ]);
    }

    /**
     * Render a source's plugin instances as accordion rows, with a client-side filter box for the
     * many-instance sources (Tutor / Search).
     *
     * @param string $area The source frankenstyle (e.g. rfdsource_tutor).
     * @param array $checks Instance checks (each carries coursename/instancename/kblinks/chaturl).
     * @return string
     */
    protected function instance_list(string $area, array $checks): string {
        $showcall = has_capability('aiprovider/ragflow:viewerrordetails', \context_system::instance());
        $out = '';
        if (in_array($area, ['rfdsource_tutor', 'rfdsource_search'], true) && count($checks) > 1) {
            $out .= html_writer::empty_tag('input', [
                'type' => 'search',
                'class' => 'form-control form-control-sm rfd-instance-filter mb-2',
                'placeholder' => get_string('status_filter_placeholder', 'local_ragflowdashboard'),
                'aria-label' => get_string('status_filter_placeholder', 'local_ragflowdashboard'),
                'data-action' => 'rfd-instance-filter',
            ]);
        }
        $rows = '';
        foreach ($checks as $c) {
            $rows .= $this->instance_row($c, $showcall);
        }
        return $out . html_writer::tag('ul', $rows, [
            'class' => 'list-unstyled rfd-status rfd-instances',
            'data-region' => 'rfd-instance-list',
        ]);
    }

    /**
     * One plugin-instance accordion row: badge + "course – instance" title; the body has the Moodle-course
     * link plus RAGflow knowledge-base / chat-app buttons, the detail, and (privileged) the API call.
     *
     * @param array $c The instance check.
     * @param bool $showcall Whether to show the API-call line.
     * @return string
     */
    protected function instance_row(array $c, bool $showcall): string {
        $coursename = (string) ($c['coursename'] ?? '');
        $instancename = (string) ($c['instancename'] ?? $c['label']);
        // Escape once at output: the course name via format_string (multilang), the RAGflow instance name
        // via s(). Both come raw from the data model, so nothing is double-escaped.
        $titlehtml = ($coursename !== '' ? format_string($coursename) . ' – ' : '') . s($instancename);
        $summary = html_writer::tag(
            'summary',
            $this->status_badge($c['state']) . ' ' . html_writer::tag('strong', $titlehtml)
        );

        $links = [];
        if (!empty($c['courseurl']) && $coursename !== '') {
            // Internal link — no new-window marker.
            $links[] = html_writer::link(
                $c['courseurl'],
                get_string('status_link_course', 'local_ragflowdashboard', s($coursename)),
                ['class' => 'rfd-instance-link']
            );
        }
        $kblinks = $c['kblinks'] ?? [];
        $multi = count($kblinks) > 1;
        foreach ($kblinks as $kb) {
            $text = get_string('status_link_kb', 'local_ragflowdashboard') . ($multi ? ' — ' . s($kb['name']) : '');
            $links[] = $this->ext_link($kb['url'], $text);
        }
        if (!empty($c['chaturl'])) {
            $links[] = $this->ext_link($c['chaturl'], get_string('status_link_chat', 'local_ragflowdashboard'));
        }
        $items = '';
        foreach ($links as $l) {
            $items .= html_writer::tag('li', $l);
        }
        $body = $items !== ''
            ? html_writer::tag('ul', $items, ['class' => 'rfd-instance-links list-unstyled mb-2'])
            : '';
        if ($c['detail'] !== '') {
            $body .= html_writer::div(s($c['detail']));
        }
        if (!empty($c['call']) && $showcall) {
            $body .= html_writer::div(
                html_writer::tag('strong', get_string('status_apicall', 'local_ragflowdashboard') . ': ')
                    . html_writer::tag('code', s($c['call'])),
                'small text-muted mt-1'
            );
        }
        return html_writer::tag(
            'li',
            html_writer::tag('details', $summary . $body, ['class' => 'rfd-accordion rfd-check']),
            ['class' => 'mb-2', 'data-filter' => \core_text::strtolower($coursename . ' ' . $instancename)]
        );
    }

    /**
     * The global ("All") view: KPIs, per-day chart, requests-by-feature bar, failures pie, error log and
     * the export form for the current view.
     *
     * @param int $since
     * @param int $days
     * @return string
     */
    protected function global_view(int $since, int $days): string {
        $out = html_writer::tag('h3', get_string('viewall', 'local_ragflowdashboard'), ['class' => 'mt-2']);
        $out .= $this->kpis(stats::totals($since), $days);
        $out .= $this->usage_chart($since);
        $out .= html_writer::start_div('row');
        $out .= html_writer::div($this->component_chart($since), 'col-md-6');
        $out .= html_writer::div($this->error_chart($since), 'col-md-6');
        $out .= html_writer::end_div();
        $out .= $this->error_log(stats::recent_errors(50));
        $out .= $this->apicall_log(\local_ragflowdashboard\api::recent_apicalls(50));
        $out .= $this->export_form('all', $days);
        return $out;
    }

    /**
     * The default per-source view: filtered KPIs, per-day chart, failures pie, recent errors, debug
     * captures and the export form scoped to the source's components.
     *
     * @param base $source
     * @param int $since
     * @param int $days
     * @return string
     */
    public function source_view(base $source, int $since, int $days): string {
        $components = $source->get_components();
        $out = html_writer::tag('h3', $source->get_name(), ['class' => 'mt-2']);
        $out .= $this->kpis(stats::totals($since, $components), $days);
        $out .= html_writer::start_div('row');
        $out .= html_writer::div($this->usage_chart($since, $components), 'col-md-7');
        $out .= html_writer::div($this->error_chart($since, $components), 'col-md-5');
        $out .= html_writer::end_div();
        $out .= $this->error_log(stats::recent_errors(20, $components));
        $debug = \local_ragflowdashboard\api::recent_debug(10, $components);
        if ($debug) {
            $out .= $this->debug_table($debug);
        }
        $out .= $this->export_form($source->get_frankenstyle(), $days);
        return $out;
    }

    /**
     * Recent debug captures (request/response content) for a source. Only populated while the feature's
     * debug mode is enabled; admin-only.
     *
     * @param array $rows
     * @return string
     */
    protected function debug_table(array $rows): string {
        $html = html_writer::tag('h5', get_string('debugcaptures', 'local_ragflowdashboard'), ['class' => 'mt-3']);
        $table = new \html_table();
        $table->head = [
            get_string('col:time', 'local_ragflowdashboard'),
            get_string('col:action', 'local_ragflowdashboard'),
            get_string('col:question', 'local_ragflowdashboard'),
            get_string('col:response', 'local_ragflowdashboard'),
        ];
        $table->attributes['class'] = 'generaltable';
        foreach ($rows as $row) {
            $table->data[] = [
                userdate($row->timecreated),
                s($row->action),
                shorten_text(s((string) $row->question), 160),
                shorten_text(s((string) $row->response), 200),
            ];
        }
        return $html . html_writer::table($table);
    }

    /**
     * Raw RAGflow API-call log: one collapsible row per call (a native <details> "accordion"). The summary
     * line shows time, method, HTTP status, URL and duration; expanding shows the full request and response.
     * Only populated while the admin raw-API-log toggle is on; admin-only (the whole dashboard is).
     *
     * @param array $rows apilog records, most recent first.
     * @return string
     */
    protected function apicall_log(array $rows): string {
        $enabled = (bool) get_config('local_ragflowdashboard', 'debug_apiraw');
        if (!$enabled && empty($rows)) {
            return '';
        }
        $html = html_writer::tag('h4', get_string('apilog', 'local_ragflowdashboard'), ['class' => 'mt-4']);
        $html .= html_writer::tag(
            'p',
            get_string($enabled ? 'apilog_on' : 'apilog_off', 'local_ragflowdashboard'),
            ['class' => 'text-muted small']
        );
        if (empty($rows)) {
            return $html . html_writer::tag('p', get_string('apilog_none', 'local_ragflowdashboard'));
        }
        foreach ($rows as $r) {
            $status = (int) $r->status;
            $ok = ($status >= 200 && $status < 300);
            $statuslabel = $status ? ('HTTP ' . $status) : get_string('apilog_nostatus', 'local_ragflowdashboard');
            $summary = html_writer::span(userdate($r->timecreated), 'me-2 text-muted')
                . html_writer::span(s((string) $r->method), 'me-2 fw-bold')
                . html_writer::span(s($statuslabel), 'badge me-2 ' . ($ok ? 'bg-success' : 'bg-danger'))
                . html_writer::span(s(shorten_text((string) $r->url, 80)), 'me-2')
                . html_writer::span((int) $r->durationms . ' ms', 'text-muted');
            $body = '';
            if (!empty($r->errordetail)) {
                $body .= html_writer::tag(
                    'p',
                    html_writer::tag(
                        'strong',
                        get_string('apilog_cause', 'local_ragflowdashboard') . ': '
                    ) . s((string) $r->errordetail),
                    ['class' => 'text-danger']
                );
            }
            $body .= html_writer::tag(
                'div',
                html_writer::tag('strong', get_string('apilog_url', 'local_ragflowdashboard') . ': ')
                . html_writer::tag('code', s((string) $r->url)),
                ['class' => 'mb-2']
            );
            $body .= html_writer::tag('strong', get_string('apilog_request', 'local_ragflowdashboard'));
            $body .= html_writer::tag('pre', s((string) $r->request), ['class' => 'ragflow-apicall__pre']);
            $body .= html_writer::tag('strong', get_string('apilog_response', 'local_ragflowdashboard'));
            $body .= html_writer::tag('pre', s((string) $r->response), ['class' => 'ragflow-apicall__pre']);
            $html .= html_writer::tag(
                'details',
                html_writer::tag('summary', $summary) . html_writer::div($body, 'ragflow-apicall__body p-2'),
                ['class' => 'ragflow-apicall']
            );
        }
        return $html;
    }

    /**
     * The date-range XML export form for the current view. The export is scoped to the same view (the
     * hidden 'view' field) and defaults to the current reporting window.
     *
     * @param string $view 'all' or a source frankenstyle name.
     * @param int $days Reporting window in days (sets the default date range).
     * @return string
     */
    protected function export_form(string $view, int $days): string {
        $from = date('Y-m-d', time() - $days * DAYSECS);
        $to = date('Y-m-d', time());
        // The export always covers all views, so no view scope is submitted.
        $inputs = html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()])
            . html_writer::tag(
                'label',
                get_string('exportfrom', 'local_ragflowdashboard')
                . ' ' . html_writer::empty_tag('input', ['type' => 'date', 'name' => 'from', 'value' => $from]),
                ['class' => 'me-2']
            )
            . html_writer::tag(
                'label',
                get_string('exportto', 'local_ragflowdashboard')
                . ' ' . html_writer::empty_tag('input', ['type' => 'date', 'name' => 'to', 'value' => $to]),
                ['class' => 'me-2']
            )
            . html_writer::tag(
                'label',
                get_string('exportformat', 'local_ragflowdashboard') . ' ' . html_writer::select(
                    [
                        'csv' => get_string('exportformat:csv', 'local_ragflowdashboard'),
                        'xml' => get_string('exportformat:xml', 'local_ragflowdashboard'),
                        'pdf' => get_string('exportformat:pdf', 'local_ragflowdashboard'),
                    ],
                    'format',
                    'csv',
                    false,
                    ['class' => 'form-select form-select-sm d-inline-block w-auto']
                ),
                ['class' => 'me-2']
            )
            . html_writer::tag(
                'button',
                get_string('exportbutton', 'local_ragflowdashboard'),
                ['type' => 'submit', 'class' => 'btn btn-secondary btn-sm']
            );
        return html_writer::tag('h4', get_string('export', 'local_ragflowdashboard'), ['class' => 'mt-4'])
            . html_writer::tag(
                'form',
                $inputs,
                ['method' => 'get', 'action' => new \moodle_url('/local/ragflowdashboard/export.php'),
                 'class' => 'mb-3 d-flex align-items-end gap-2']
            );
    }

    /**
     * KPI summary cards.
     *
     * @param \stdClass $totals
     * @param int $days
     * @return string
     */
    protected function kpis(\stdClass $totals, int $days): string {
        $rate = $totals->total > 0 ? round(100 * $totals->successful / $totals->total, 1) : 0;
        $cards = [
            ['label' => get_string('kpi:requests', 'local_ragflowdashboard', $days), 'value' => $totals->total],
            ['label' => get_string('kpi:successrate', 'local_ragflowdashboard'), 'value' => $rate . '%'],
            ['label' => get_string('kpi:failures', 'local_ragflowdashboard'), 'value' => $totals->failed],
            ['label' => get_string('kpi:avglatency', 'local_ragflowdashboard'), 'value' => $totals->avglatency . ' ms'],
        ];
        $html = html_writer::start_div('row mb-3');
        foreach ($cards as $card) {
            $body = html_writer::div($card['value'], 'h3 mb-0')
                . html_writer::div($card['label'], 'text-muted small');
            $inner = html_writer::div(html_writer::div($body, 'card-body'), 'card h-100');
            $html .= html_writer::div($inner, 'col-md-3 mb-2');
        }
        $html .= html_writer::end_div();
        return $html;
    }

    /**
     * Requests-per-day line chart (successful vs failed).
     *
     * @param int $since
     * @param array $components Optional component filter.
     * @param bool $open Render this section expanded (accordion open).
     * @param string $key Accordion key (data-rfd-key) for the client-side "remember open" state.
     * @return string
     */
    protected function usage_chart(int $since, array $components = [], bool $open = false, string $key = ''): string {
        $perday = stats::per_day($since, $components);
        $startbucket = (int) floor($since / DAYSECS);
        $endbucket = (int) floor(time() / DAYSECS);
        $labels = [];
        $successvals = [];
        $failedvals = [];
        for ($b = $startbucket; $b <= $endbucket; $b++) {
            $labels[] = userdate($b * DAYSECS, get_string('strftimedateshort', 'langconfig'));
            $successvals[] = isset($perday[$b]) ? $perday[$b]->success : 0;
            $failedvals[] = isset($perday[$b]) ? $perday[$b]->failed : 0;
        }
        $chart = new \core\chart_line();
        $chart->set_labels($labels);
        $ok = new \core\chart_series(get_string('successful', 'local_ragflowdashboard'), $successvals);
        $ok->set_color(self::CHART_GREEN);
        $chart->add_series($ok);
        $bad = new \core\chart_series(get_string('failed', 'local_ragflowdashboard'), $failedvals);
        $bad->set_color(self::CHART_RED);
        $chart->add_series($bad);
        return $this->chart_box(
            get_string('chart:usage', 'local_ragflowdashboard'),
            $this->output->render($chart),
            false,
            $open,
            $key,
            'rfd-usage'
        );
    }

    /**
     * Wrap a chart (or a "no data" message) in a uniformly sized box.
     *
     * @param string $heading
     * @param string $body Chart HTML or a message.
     * @param bool $pie Render at the smaller pie/doughnut size.
     * @param bool $open Render the section expanded (accordion open).
     * @param string $key Accordion key (data-rfd-key) for the client-side "remember open" state.
     * @param string $group Accordion group name; sections sharing it are mutually exclusive (one open).
     * @return string
     */
    protected function chart_box(
        string $heading,
        string $body,
        bool $pie = false,
        bool $open = false,
        string $key = '',
        string $group = ''
    ): string {
        $inner = html_writer::div($body, 'rfd-chart' . ($pie ? ' rfd-chart--pie' : ''));
        $attrs = ['class' => 'rfd-chart-wrap rfd-accordion'];
        if ($open) {
            $attrs['open'] = 'open';
        }
        if ($group !== '') {
            // Same group name → the browser keeps only one section open at a time (native exclusive accordion).
            $attrs['name'] = $group;
        }
        if ($key !== '') {
            $attrs['data-rfd-key'] = $key;
        }
        return html_writer::tag('details', html_writer::tag('summary', $heading) . $inner, $attrs);
    }

    /**
     * Wrap a content section in the shared shaded "area" box (matches the Status tab look).
     *
     * @param string $html
     * @return string
     */
    protected function box(string $html): string {
        return html_writer::div($html, 'rfd-box');
    }

    /**
     * A colour-coded data table under a categorical chart: each row carries a swatch (circle) in the chart's
     * colour for that item, so the table and the chart read together. Replaces Chart.js's plain auto table.
     *
     * @param string[] $labels
     * @param int[] $values Aligned with $labels.
     * @param string[] $colors Aligned with $labels.
     * @return string
     */
    protected function chart_data_table(array $labels, array $values, array $colors): string {
        $labels = array_values($labels);
        $values = array_values($values);
        $rows = '';
        foreach ($labels as $i => $lab) {
            $swatch = html_writer::span('', 'rfd-swatch', ['style' => 'background-color:' . ($colors[$i] ?? '#adb5bd') . ';']);
            $rows .= html_writer::tag(
                'tr',
                html_writer::tag('td', $swatch . ' ' . s($lab)) . html_writer::tag('td', (int) ($values[$i] ?? 0)),
                ['class' => 'r' . ($i % 2)]
            );
        }
        return html_writer::tag('table', $rows, ['class' => 'generaltable rfd-chart-data']);
    }

    /**
     * Requests-by-component bar chart (global overview only).
     *
     * @param int $since
     * @param bool $open Render this section expanded (accordion open).
     * @param string $key Accordion key (data-rfd-key) for the client-side "remember open" state.
     * @return string
     */
    protected function component_chart(int $since, bool $open = false, string $key = ''): string {
        $data = stats::by_component($since);
        $label = get_string('chart:bycomponent', 'local_ragflowdashboard');
        if (!$data) {
            return $this->chart_box(
                $label,
                html_writer::div(get_string('nodata', 'local_ragflowdashboard'), 'text-muted'),
                false,
                $open,
                $key,
                'rfd-usage'
            );
        }
        $labels = [];
        foreach (array_keys($data) as $component) {
            $labels[] = $this->component_label($component);
        }
        $chart = new \core\chart_bar();
        $chart->set_labels($labels);
        $colors = self::palette(count($data), self::CHART_BLUES);
        $series = new \core\chart_series(get_string('requests', 'local_ragflowdashboard'), array_values($data));
        $series->set_colors($colors);
        $chart->add_series($series);
        $body = $this->output->render($chart) . $this->chart_data_table($labels, array_values($data), $colors);
        return $this->chart_box($label, $body, false, $open, $key, 'rfd-usage');
    }

    /**
     * The most active users (horizontal bar). Empty when logging is anonymised.
     *
     * @param int $since
     * @param array $components
     * @param bool $open Render this section expanded (accordion open).
     * @param string $key Accordion key (data-rfd-key) for the client-side "remember open" state.
     * @return string
     */
    protected function top_users_chart(int $since, array $components = [], bool $open = false, string $key = ''): string {
        global $DB;
        $data = stats::top_users($since, 10, $components);
        $label = get_string('chart:byuser', 'local_ragflowdashboard');
        if (!$data) {
            return $this->chart_box(
                $label,
                html_writer::div(get_string('nodata', 'local_ragflowdashboard'), 'text-muted'),
                false,
                $open,
                $key,
                'rfd-usage'
            );
        }
        $users = $DB->get_records_list('user', 'id', array_keys($data));
        $labels = [];
        foreach (array_keys($data) as $uid) {
            $labels[] = isset($users[$uid]) ? fullname($users[$uid]) : ('#' . $uid);
        }
        $chart = new \core\chart_bar();
        $chart->set_horizontal(true);
        $chart->set_labels($labels);
        $colors = self::palette(count($data), self::CHART_BLUES);
        $series = new \core\chart_series(get_string('requests', 'local_ragflowdashboard'), array_values($data));
        $series->set_colors($colors);
        $chart->add_series($series);
        $body = $this->output->render($chart) . $this->chart_data_table($labels, array_values($data), $colors);
        return $this->chart_box($label, $body, false, $open, $key, 'rfd-usage');
    }

    /**
     * Requests grouped by coarse user group (trainers vs students/users vs anonymised) as a pie.
     *
     * @param int $since
     * @param array $components
     * @param bool $open Render this section expanded (accordion open).
     * @param string $key Accordion key (data-rfd-key) for the client-side "remember open" state.
     * @return string
     */
    protected function role_chart(int $since, array $components = [], bool $open = false, string $key = ''): string {
        $data = stats::by_role($since, $components);
        $label = get_string('chart:byrole', 'local_ragflowdashboard');
        if (!$data) {
            return $this->chart_box(
                $label,
                html_writer::div(get_string('nodata', 'local_ragflowdashboard'), 'text-muted'),
                true,
                $open,
                $key,
                'rfd-usage'
            );
        }
        $map = ['trainer' => 'role_trainer', 'student' => 'role_student', 'anon' => 'role_anon'];
        $labels = [];
        foreach (array_keys($data) as $key) {
            $labels[] = get_string($map[$key] ?? 'role_student', 'local_ragflowdashboard');
        }
        $chart = new \core\chart_pie();
        $chart->set_labels($labels);
        $colors = self::palette(count($data), self::CHART_BLUES);
        $series = new \core\chart_series(get_string('requests', 'local_ragflowdashboard'), array_values($data));
        $series->set_colors($colors);
        $chart->add_series($series);
        $body = $this->output->render($chart) . $this->chart_data_table($labels, array_values($data), $colors);
        return $this->chart_box($label, $body, true, $open, $key, 'rfd-usage');
    }

    /**
     * Requests grouped by course (top 10, horizontal bar). Course id 0 is "outside a course".
     *
     * @param int $since
     * @param array $components
     * @param bool $open Render this section expanded (accordion open).
     * @param string $key Accordion key (data-rfd-key) for the client-side "remember open" state.
     * @return string
     */
    protected function course_chart(int $since, array $components = [], bool $open = false, string $key = ''): string {
        global $DB;
        $data = stats::by_course($since, 10, $components);
        $label = get_string('chart:bycourse', 'local_ragflowdashboard');
        if (!$data) {
            return $this->chart_box(
                $label,
                html_writer::div(get_string('nodata', 'local_ragflowdashboard'), 'text-muted'),
                false,
                $open,
                $key,
                'rfd-usage'
            );
        }
        $ids = [];
        foreach (array_keys($data) as $cid) {
            if ($cid > 0) {
                $ids[] = $cid;
            }
        }
        $courses = $ids ? $DB->get_records_list('course', 'id', $ids, '', 'id, fullname, shortname') : [];
        $labels = [];
        foreach (array_keys($data) as $cid) {
            if ($cid === 0) {
                $labels[] = get_string('course_none', 'local_ragflowdashboard');
            } else if (isset($courses[$cid])) {
                $labels[] = format_string($courses[$cid]->fullname);
            } else {
                $labels[] = '#' . $cid;
            }
        }
        $chart = new \core\chart_bar();
        $chart->set_horizontal(true);
        $chart->set_labels($labels);
        $colors = self::palette(count($data), self::CHART_BLUES);
        $series = new \core\chart_series(get_string('requests', 'local_ragflowdashboard'), array_values($data));
        $series->set_colors($colors);
        $chart->add_series($series);
        $body = $this->output->render($chart) . $this->chart_data_table($labels, array_values($data), $colors);
        return $this->chart_box($label, $body, false, $open, $key, 'rfd-usage');
    }

    /**
     * Errors-by-type pie chart.
     *
     * @param int $since
     * @param array $components Optional component filter.
     * @param bool $open Render this section expanded (accordion open).
     * @param string $key Accordion key (data-rfd-key) for the client-side "remember open" state.
     * @return string
     */
    protected function error_chart(int $since, array $components = [], bool $open = false, string $key = ''): string {
        $data = stats::by_errortype($since, $components);
        $label = get_string('chart:byerrortype', 'local_ragflowdashboard');
        if (!$data) {
            return $this->chart_box(
                $label,
                html_writer::div(get_string('noerrors', 'local_ragflowdashboard'), 'text-muted'),
                true,
                $open
            );
        }
        $elabels = array_map([$this, 'errortype_label'], array_keys($data));
        $colors = self::palette(count($data), self::CHART_REDS);
        $chart = new \core\chart_pie();
        $chart->set_labels($elabels);
        $series = new \core\chart_series(get_string('errors', 'local_ragflowdashboard'), array_values($data));
        $series->set_colors($colors);
        $chart->add_series($series);
        $body = $this->output->render($chart) . $this->chart_data_table($elabels, array_values($data), $colors);
        return $this->chart_box($label, $body, true, $open);
    }

    /**
     * The recent-errors table.
     *
     * @param array $rows
     * @return string
     */
    protected function error_log(array $rows): string {
        $html = html_writer::tag('h4', get_string('errorlog', 'local_ragflowdashboard'), ['class' => 'mt-4']);
        if (!$rows) {
            return $html . html_writer::div(get_string('noerrors', 'local_ragflowdashboard'), 'text-muted');
        }
        // Each failure is a collapsible row (same look as the Status tab): a red error-type badge, the
        // component and the time in the summary; the request's metrics in the expanded detail.
        $items = '';
        foreach ($rows as $row) {
            $badge = html_writer::span(s($this->errortype_label($row->errortype)), 'badge bg-danger');
            $summary = html_writer::tag(
                'summary',
                $badge . ' ' . html_writer::tag('strong', s($this->component_label($row->component)))
                    . ' ' . html_writer::span(userdate($row->timecreated), 'text-muted small')
            );
            $detail = get_string('col:action', 'local_ragflowdashboard') . ': ' . s($row->action)
                . ' · ' . get_string('col:latency', 'local_ragflowdashboard') . ': ' . (int) $row->latencyms . ' ms';
            $items .= html_writer::tag(
                'li',
                html_writer::tag('details', $summary . html_writer::div($detail, 'small'), ['class' => 'rfd-accordion rfd-check']),
                ['class' => 'mb-2']
            );
        }
        return $html . html_writer::tag('ul', $items, ['class' => 'list-unstyled rfd-status']);
    }

    /**
     * A human label for a component (its plugin name if installed, else the raw frankenstyle name).
     *
     * @param string $component
     * @return string
     */
    protected function component_label(string $component): string {
        if ($component !== '' && get_string_manager()->string_exists('pluginname', $component)) {
            return get_string('pluginname', $component);
        }
        return $component;
    }

    /**
     * A human label for an error type (a plugin string if defined, else the raw key).
     *
     * @param string $errortype
     * @return string
     */
    public function errortype_label(string $errortype): string {
        $key = ($errortype !== '') ? $errortype : 'unknown';
        if (get_string_manager()->string_exists('errortype:' . $key, 'local_ragflowdashboard')) {
            return get_string('errortype:' . $key, 'local_ragflowdashboard');
        }
        return $key;
    }

    /**
     * Window start time for a number of days.
     *
     * @param int $days
     * @return int
     */
    protected static function since(int $days): int {
        return time() - ($days * DAYSECS);
    }
}
