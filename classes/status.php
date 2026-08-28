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

use aiprovider_ragflow\helper;
use aiprovider_ragflow\local\health\checker;
use aiprovider_ragflow\local\health\reference_status;

/**
 * Builds the dashboard's Status report: is the RAGflow provider configured and reachable, and is each
 * configured instance (Tutor/Search/Helpdesk) correctly linked (assistant valid + bound, knowledge base
 * parsed)? Instance checks are contributed **modularly** by each source subplugin's {@see source\base}.
 * Chats and datasets are fetched once and shared, so a page with many instances stays cheap.
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status {
    /** @var string A passing check. */
    const OK = 'ok';
    /** @var string A "not set up yet" notice – informational, not a fault (e.g. no assistant chosen). */
    const INFO = 'info';
    /** @var string A non-fatal warning. */
    const WARN = 'warn';
    /** @var string A failing check. */
    const ERROR = 'error';

    /**
     * Assemble the full report.
     *
     * @return array {reachable: bool, provider: array[], sources: array[]} – each check is
     *   {label, state, detail}; each source is {name, checks}.
     */
    public static function report(): array {
        $pr = self::provider_report();
        $sources = [];
        if ($pr['reachable']) {
            foreach (source_manager::instances() as $source) {
                $checks = $source->status_checks($pr['ctx']);
                if (!empty($checks)) {
                    $sources[] = ['area' => $source->get_frankenstyle(), 'name' => $source->get_name(), 'checks' => $checks];
                }
            }
        }
        return [
            'reachable' => $pr['reachable'],
            'plugins' => self::plugins_report(),
            'provider' => ['area' => 'provider', 'name' => self::provider_name(), 'checks' => $pr['checks']],
            'actions' => $pr['reachable']
                ? self::actions_report($pr['ctx'])
                : ['area' => 'actions', 'name' => get_string('status_actions_heading', 'local_ragflowdashboard'), 'checks' => []],
            'sources' => $sources,
        ];
    }

    /**
     * Re-run and return a single Status area (used by its "refresh" button).
     *
     * @param string $area 'provider' or a source frankenstyle name.
     * @return array {area, name, checks}
     */
    public static function area(string $area): array {
        // A refresh is the authoritative "check now": drop the shared reference-verdict cache so every other
        // surface (block panels, forms) re-reads live data too, not a stale ≤60s verdict.
        checker::purge();
        if ($area === 'plugins') {
            return self::plugins_report();
        }
        $pr = self::provider_report();
        if ($area === 'provider') {
            return ['area' => 'provider', 'name' => self::provider_name(), 'checks' => $pr['checks']];
        }
        if ($area === 'actions') {
            return $pr['reachable']
                ? self::actions_report($pr['ctx'])
                : ['area' => 'actions', 'name' => get_string('status_actions_heading', 'local_ragflowdashboard'),
                    'checks' => [self::check(
                        get_string('status_actions_heading', 'local_ragflowdashboard'),
                        self::ERROR,
                        get_string('status_connection_fail', 'local_ragflowdashboard')
                    )]];
        }
        foreach (source_manager::instances() as $source) {
            if ($source->get_frankenstyle() === $area) {
                $checks = $pr['reachable']
                    ? $source->status_checks($pr['ctx'])
                    : [self::check(
                        $source->get_name(),
                        self::ERROR,
                        get_string('status_connection_fail', 'local_ragflowdashboard')
                    )];
                return ['area' => $area, 'name' => $source->get_name(), 'checks' => $checks];
            }
        }
        return ['area' => $area, 'name' => $area, 'checks' => []];
    }

    /**
     * The provider connectivity box + the shared RAGflow context (chats/datasets) fetched once for reuse.
     *
     * @return array {checks, reachable, ctx}
     */
    public static function provider_report(): array {
        [$providerid, $base, $key] = self::provider_credentials();
        $configured = ($base !== '' && $key !== '');
        $settingsurl = self::provider_settings_url();
        $checks = [
            self::check(
                get_string('status_provider', 'local_ragflowdashboard'),
                $configured ? self::OK : self::INFO,
                get_string($configured ? 'status_provider_ok' : 'status_provider_missing', 'local_ragflowdashboard'),
                $settingsurl,
                get_string('status_call_configonly', 'local_ragflowdashboard')
            ),
        ];
        $reachable = false;
        $ctx = ['base' => $base, 'key' => $key, 'providerid' => $providerid, 'chats' => [], 'datasets' => []];
        if ($configured) {
            $reachable = helper::ping($base, $key);
            $ctx['chats'] = $reachable ? helper::get_chats_detailed($base, $key) : [];
            $ctx['datasets'] = $reachable ? helper::get_datasets_detailed($base, $key) : [];
            // The concrete proof: report the live dataset/assistant counts the probe actually returned.
            $detail = $reachable
                ? get_string(
                    'status_connection_ok',
                    'local_ragflowdashboard',
                    (object) ['datasets' => count($ctx['datasets']), 'assistants' => count($ctx['chats'])]
                )
                : get_string('status_connection_fail', 'local_ragflowdashboard');
            $call = 'GET ' . $base . '/api/v1/datasets · GET ' . $base . '/api/v1/chats';
            $checks[] = self::check(
                get_string('status_connection', 'local_ragflowdashboard'),
                $reachable ? self::OK : self::ERROR,
                $detail,
                $settingsurl,
                $call
            );
        }
        return ['checks' => $checks, 'reachable' => $reachable, 'ctx' => $ctx];
    }

    /**
     * The provider box heading.
     *
     * @return string
     */
    private static function provider_name(): string {
        return get_string('status_provider_heading', 'local_ragflowdashboard');
    }

    /**
     * Build a check row.
     *
     * @param string $label
     * @param string $state OK|WARN|ERROR
     * @param string $detail
     * @param string $url Optional link for the label (its Moodle config page).
     * @param string $call Optional concrete API call shown as proof in the expanded detail.
     * @param array $extra Optional extra fields for a plugin-instance row (coursename, courseurl,
     *   instancename, kblinks [[name,url],...], chaturl) merged into the returned array.
     * @return array
     */
    public static function check(
        string $label,
        string $state,
        string $detail = '',
        string $url = '',
        string $call = '',
        array $extra = []
    ): array {
        return ['label' => $label, 'state' => $state, 'detail' => $detail, 'url' => $url, 'call' => $call] + $extra;
    }

    /**
     * Resolve the Moodle course a block instance lives in, as [name, url]. Blocks placed outside a course
     * (site home, Dashboard, a user context) resolve to a generic site label with the context's own URL.
     *
     * @param int $contextid The block instance's parent context id.
     * @return array [name => string, url => string]
     */
    public static function course_of_context(int $contextid): array {
        $context = \context::instance_by_id($contextid, IGNORE_MISSING);
        if (!$context) {
            return ['name' => get_string('status_context_gone', 'local_ragflowdashboard'), 'url' => ''];
        }
        $coursecontext = $context->get_course_context(false);
        if ($coursecontext && (int) $coursecontext->instanceid !== SITEID) {
            $course = get_course((int) $coursecontext->instanceid);
            // Return the RAW fullname; the renderer applies format_string()/s() once at output time (so
            // names never get double-escaped and the client-side filter matches the raw text).
            return [
                'name' => (string) $course->fullname,
                'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            ];
        }
        return [
            'name' => get_string('status_context_site', 'local_ragflowdashboard'),
            'url' => $context->get_url()->out(false),
        ];
    }

    /**
     * Deep link into the RAGflow web UI for a knowledge base / dataset (opens its files view).
     *
     * @param string $base The provider base URL (already trailing-slash-trimmed).
     * @param string $kbid The dataset id.
     * @return string
     */
    public static function ragflow_kb_url(string $base, string $kbid): string {
        return $base . '/dataset/files/' . rawurlencode($kbid);
    }

    /**
     * Deep link into the RAGflow web UI for a chat assistant (opens its chat app).
     *
     * @param string $base The provider base URL (already trailing-slash-trimmed).
     * @param string $chatid The chat assistant id.
     * @return string
     */
    public static function ragflow_chat_url(string $base, string $chatid): string {
        return $base . '/chat/' . rawurlencode($chatid);
    }

    /**
     * URL of the RAGflow AI provider settings page (where base URL + API key are configured).
     *
     * @return string
     */
    public static function provider_settings_url(): string {
        return (new \moodle_url('/admin/settings.php', ['section' => 'aiprovider']))->out(false);
    }

    /**
     * The natural page URL for a context (used to link a block instance back to where it lives), or '' if
     * the context no longer exists.
     *
     * @param int $contextid
     * @return string
     */
    public static function instance_url(int $contextid): string {
        $context = \context::instance_by_id($contextid, IGNORE_MISSING);
        return $context ? $context->get_url()->out(false) : '';
    }

    /**
     * The dashboard traffic-light colour for a central {@see reference_status} state: not-configured is a
     * blue notice (nothing set up yet – not a fault), degraded and unverified are amber (usable / not a
     * config fault), and missing (a configured reference that no longer exists) is red.
     *
     * @param string $state A {@see reference_status} state constant.
     * @return string self::OK|INFO|WARN|ERROR
     */
    public static function color_for(string $state): string {
        return match ($state) {
            reference_status::NOT_CONFIGURED => self::INFO,
            reference_status::MISSING => self::ERROR,
            reference_status::DEGRADED, reference_status::UNVERIFIED => self::WARN,
            default => self::OK,
        };
    }

    /**
     * Assess a chat assistant referenced by an instance. The verdict (missing / degraded / ok / …) comes
     * from the single central classifier {@see checker::classify_assistant()} on the prefetched list, so the
     * dashboard never re-implements "is this usable?"; only the wording of the detail is dashboard-local.
     *
     * @param array $ctx The report context (chats keyed by id => {name, kb}).
     * @param string $chatid The configured assistant id ('' if none).
     * @return array [state, detail]
     */
    public static function assistant_state(array $ctx, string $chatid): array {
        $st = checker::classify_assistant((array) $ctx['chats'], '', '', $chatid);
        $detail = match ($st->reason) {
            'not_configured' => get_string('status_assistant_none', 'local_ragflowdashboard'),
            'assistant_not_found' => get_string('status_assistant_notfound', 'local_ragflowdashboard'),
            'kb_not_bound' => get_string('status_assistant_nokb', 'local_ragflowdashboard'),
            default => get_string('status_assistant_ok', 'local_ragflowdashboard', $st->label),
        };
        return [self::color_for($st->state), $detail];
    }

    /**
     * Assess a knowledge base referenced by an instance, using the prefetched datasets.
     *
     * @param array $ctx The report context (datasets keyed by id => {name, chunk_count, document_count}).
     * @param string $datasetid The configured dataset id.
     * @return array [state, detail]
     */
    public static function dataset_state(array $ctx, string $datasetid): array {
        $st = checker::classify_kb((array) $ctx['datasets'], '', '', $datasetid);
        $ds = $ctx['datasets'][$datasetid] ?? null;
        $detail = match ($st->reason) {
            'not_configured' => get_string('status_kb_none', 'local_ragflowdashboard'),
            'kb_not_found' => get_string('status_kb_notfound', 'local_ragflowdashboard'),
            'kb_empty' => get_string('status_kb_nodocs', 'local_ragflowdashboard', $st->label),
            'kb_not_parsed' => get_string('status_kb_empty', 'local_ragflowdashboard', $st->label),
            default => get_string(
                'status_kb_ok',
                'local_ragflowdashboard',
                (object) ['name' => $st->label, 'docs' => (int) ($ds->document_count ?? 0)]
            ),
        };
        return [self::color_for($st->state), $detail];
    }

    /**
     * The more severe of two states (ERROR > WARN > INFO > OK). INFO ("not set up yet") ranks above OK so an
     * area with an unconfigured item surfaces a blue notice, but below WARN/ERROR so a real fault dominates.
     *
     * @param string $a
     * @param string $b
     * @return string
     */
    public static function worst(string $a, string $b): string {
        $rank = [self::OK => 0, self::INFO => 1, self::WARN => 2, self::ERROR => 3];
        return ($rank[$b] ?? 0) > ($rank[$a] ?? 0) ? $b : $a;
    }

    /**
     * The enabled RAGflow provider instance record, or null if none is enabled.
     *
     * @return \stdClass|null
     */
    private static function provider_record(): ?\stdClass {
        global $DB;
        $rec = $DB->get_record_select(
            'ai_providers',
            'provider = :p AND enabled = 1',
            ['p' => 'aiprovider_ragflow\\provider'],
            '*',
            IGNORE_MULTIPLE
        );
        return $rec ?: null;
    }

    /**
     * Credentials of the enabled RAGflow provider instance.
     *
     * @return array [providerid, baseurl, apikey]
     */
    private static function provider_credentials(): array {
        $rec = self::provider_record();
        if (!$rec) {
            return [0, '', ''];
        }
        $conf = json_decode($rec->config, true) ?: [];
        return [(int) $rec->id, rtrim((string) ($conf['baseurl'] ?? ''), '/'), (string) ($conf['apikey'] ?? '')];
    }

    /**
     * The Provider-actions Status area: for each enabled core_ai action the RAGflow provider handles
     * (generate/summarise/explain text), the health of the assistant it is configured to use. This closes
     * the gap where an action could reference a deleted assistant without ever showing in the Status tab.
     *
     * @param array $ctx The report context (prefetched chats), as built by {@see provider_report()}.
     * @return array {area, name, checks}
     */
    public static function actions_report(array $ctx): array {
        $rec = self::provider_record();
        $actionconfig = $rec ? (json_decode((string) $rec->actionconfig, true) ?: []) : [];
        $settingsurl = self::provider_settings_url();
        $checks = [];
        foreach ($actionconfig as $actionclass => $cfg) {
            if (empty($cfg['enabled'])) {
                continue;
            }
            $chatid = trim((string) ($cfg['settings']['chatid'] ?? ''));
            [$state, $detail] = self::assistant_state($ctx, $chatid);
            $short = strrchr((string) $actionclass, '\\');
            $short = $short === false ? (string) $actionclass : substr($short, 1);
            $checks[] = self::check(
                get_string('status_action', 'local_ragflowdashboard', $short),
                $state,
                $detail,
                $settingsurl,
                get_string('status_call_configonly', 'local_ragflowdashboard')
            );
        }
        return [
            'area' => 'actions',
            'name' => get_string('status_actions_heading', 'local_ragflowdashboard'),
            'checks' => $checks,
        ];
    }

    /**
     * The Suite-plugins Status area: which of the RAGflow suite plugins are installed (green) or absent
     * (red). The dashboard depends only on the provider and degrades gracefully with any subset of the
     * feature plugins, so this is purely informational — no API call, shown even when RAGflow is unreachable.
     *
     * @return array {area, name, checks}
     */
    public static function plugins_report(): array {
        $suite = [
            'aiprovider_ragflow' => 'status_plugin_provider',
            'block_ragflowtutor' => 'status_plugin_tutor',
            'block_ragflowsearch' => 'status_plugin_search',
            'aiplacement_ragflowhelpdesk' => 'status_plugin_helpdesk',
            'local_ragflowdashboard' => 'status_plugin_dashboard',
        ];
        $checks = [];
        foreach ($suite as $component => $labelkey) {
            $installed = \core_component::get_component_directory($component) !== null;
            $checks[] = self::check(
                get_string($labelkey, 'local_ragflowdashboard'),
                $installed ? self::OK : self::ERROR,
                get_string(
                    $installed ? 'status_plugin_installed' : 'status_plugin_missing',
                    'local_ragflowdashboard'
                )
            );
        }
        return [
            'area' => 'plugins',
            'name' => get_string('status_plugins_heading', 'local_ragflowdashboard'),
            'checks' => $checks,
        ];
    }
}
