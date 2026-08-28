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
 * Optional integration entry point the RAGflow provider calls (guarded by class_exists) to record
 * per-feature **debug content** – the request/response of a chat or search – but only while that
 * component's debug mode is enabled by an admin. Content is bounded and stored in a dashboard-owned,
 * admin-only table (never via events, so it does not reach the standard log store).
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /** @var string The usage-metrics table. */
    const TABLE_LOG = 'local_ragflowdashboard_log';

    /** @var string The debug capture table. */
    const TABLE = 'local_ragflowdashboard_debug';

    /** @var string The raw RAGflow API-call log table. */
    const TABLE_API = 'local_ragflowdashboard_apilog';

    /**
     * Record one usage/error occurrence (metrics only – never content). Called directly by the provider so
     * the dashboard's usage data is independent of the per-plugin "log to Moodle" setting. Best-effort.
     *
     * @param \context $context The action context.
     * @param int $userid The acting user (dropped to 0 when anonymisation is on).
     * @param string $component Calling component (e.g. block_ragflowtutor).
     * @param string $action Short action name (chat|search).
     * @param bool $success Whether the action succeeded.
     * @param string $errortype Coarse error type ('' on success).
     * @param int $latencyms Duration in milliseconds.
     * @param int $itemcount Item count (sources/results).
     * @param int $providerid RAGflow provider instance id (0 if unknown).
     * @param int $tokensprompt Prompt tokens (chat only).
     * @param int $tokenscompletion Completion tokens (chat only).
     * @param int $tokenstotal Total tokens (chat only).
     * @return void
     */
    public static function capture_usage(
        \context $context,
        int $userid,
        string $component,
        string $action,
        bool $success,
        string $errortype,
        int $latencyms,
        int $itemcount,
        int $providerid = 0,
        int $tokensprompt = 0,
        int $tokenscompletion = 0,
        int $tokenstotal = 0
    ): void {
        global $DB;
        try {
            // Anonymise: when enabled, drop the user linkage entirely (privacy over per-user analytics).
            if (get_config('local_ragflowdashboard', 'anonymize')) {
                $userid = 0;
            }
            $coursecontext = $context->get_course_context(false);
            $DB->insert_record(self::TABLE_LOG, (object) [
                'timecreated' => time(),
                'component' => \core_text::substr($component, 0, 100),
                'action' => \core_text::substr($action, 0, 20),
                'userid' => $userid,
                'courseid' => $coursecontext ? (int) $coursecontext->instanceid : 0,
                'contextid' => (int) $context->id,
                'success' => $success ? 1 : 0,
                'errortype' => \core_text::substr($errortype, 0, 40),
                'latencyms' => $latencyms,
                'itemcount' => $itemcount,
                'providerid' => $providerid,
                'tokensprompt' => $tokensprompt,
                'tokenscompletion' => $tokenscompletion,
                'tokenstotal' => $tokenstotal,
            ]);
        } catch (\Throwable $e) {
            debugging('local_ragflowdashboard: failed to store usage: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Record a debug capture if the component's debug mode is on. No-op otherwise.
     *
     * @param string $component Frankenstyle component (e.g. block_ragflowtutor).
     * @param string $action chat|search.
     * @param bool $success
     * @param int $userid
     * @param \context $context
     * @param string $question The user question/query.
     * @param string $response The answer, error cause, or result summary.
     * @return void
     */
    public static function debug_capture(
        string $component,
        string $action,
        bool $success,
        int $userid,
        \context $context,
        string $question,
        string $response
    ): void {
        global $DB;
        if (!get_config('local_ragflowdashboard', 'debug_' . $component)) {
            return;
        }
        $max = (int) get_config('local_ragflowdashboard', 'detailmaxlen');
        if ($max <= 0) {
            $max = 2000;
        }
        if (get_config('local_ragflowdashboard', 'anonymize')) {
            $userid = 0;
        }
        $courseid = 0;
        $coursecontext = $context->get_course_context(false);
        if ($coursecontext) {
            $courseid = (int) $coursecontext->instanceid;
        }
        $DB->insert_record(self::TABLE, (object) [
            'timecreated' => time(),
            'component' => $component,
            'action' => $action,
            'userid' => $userid,
            'courseid' => $courseid,
            'contextid' => (int) $context->id,
            'success' => $success ? 1 : 0,
            'question' => \core_text::substr($question, 0, $max),
            'response' => \core_text::substr($response, 0, $max),
        ]);
    }

    /**
     * The most recent debug captures for a set of components.
     *
     * @param int $limit
     * @param array $components
     * @return array debug records (most recent first)
     */
    public static function recent_debug(int $limit, array $components): array {
        global $DB;
        if (empty($components)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($components, SQL_PARAMS_NAMED, 'comp');
        return $DB->get_records_select(self::TABLE, 'component ' . $insql, $params, 'timecreated DESC', '*', 0, $limit);
    }

    /**
     * Record a raw RAGflow API call if raw API logging is switched on (global admin toggle `debug_apiraw`).
     * No-op otherwise. The API key is never passed in (it lives only in the request's Authorization header),
     * so it is never stored. Request/response are bounded by `detailmaxlen`; the user is dropped when the
     * anonymise option is on.
     *
     * @param string $method HTTP method (e.g. POST).
     * @param string $url Request URL (no credentials).
     * @param string $request The JSON request payload.
     * @param int $status HTTP status (0 if the request never completed).
     * @param string $response The raw response body.
     * @param bool $success Whether the call yielded a usable result.
     * @param int $durationms Request duration in milliseconds.
     * @param string $errordetail The technical failure cause (empty on success).
     * @return void
     */
    public static function capture_apicall(
        string $method,
        string $url,
        string $request,
        int $status,
        string $response,
        bool $success,
        int $durationms,
        string $errordetail = ''
    ): void {
        global $DB, $USER;
        if (!get_config('local_ragflowdashboard', 'debug_apiraw')) {
            return;
        }
        $max = (int) get_config('local_ragflowdashboard', 'detailmaxlen');
        if ($max <= 0) {
            $max = 2000;
        }
        $userid = (int) $USER->id;
        if (get_config('local_ragflowdashboard', 'anonymize')) {
            $userid = 0;
        }
        $contextid = ($userid > 0)
            ? \context_user::instance($userid)->id
            : \context_system::instance()->id;
        $DB->insert_record(self::TABLE_API, (object) [
            'timecreated' => time(),
            'userid' => $userid,
            'contextid' => $contextid,
            'method' => \core_text::substr($method, 0, 10),
            'url' => \core_text::substr($url, 0, 255),
            'status' => $status,
            'success' => $success ? 1 : 0,
            'durationms' => $durationms,
            'errordetail' => \core_text::substr($errordetail, 0, 255),
            'request' => \core_text::substr($request, 0, $max),
            'response' => \core_text::substr($response, 0, $max),
        ]);
    }

    /**
     * The most recent raw API-call log rows (most recent first).
     *
     * @param int $limit
     * @return array
     */
    public static function recent_apicalls(int $limit): array {
        global $DB;
        return $DB->get_records(self::TABLE_API, null, 'timecreated DESC', '*', 0, $limit);
    }

    /**
     * A filtered, paged page of raw API-call log rows (most recent first) plus the total match count.
     *
     * @param int $page Zero-based page number.
     * @param int $perpage Rows per page.
     * @param array $filters {q: string (text in url/request/response/cause), status: int (exact HTTP
     *   status, 0 = any), from: int, to: int (unix time bounds, 0 = unbounded)}.
     * @return array {rows: array, total: int}
     */
    public static function apicalls(int $page, int $perpage, array $filters): array {
        global $DB;
        $where = [];
        $params = [];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $DB->sql_like_escape($q) . '%';
            $where[] = '(' . $DB->sql_like('url', ':q1', false) . ' OR ' . $DB->sql_like('request', ':q2', false)
                . ' OR ' . $DB->sql_like('response', ':q3', false) . ' OR ' . $DB->sql_like('errordetail', ':q4', false) . ')';
            $params['q1'] = $params['q2'] = $params['q3'] = $params['q4'] = $like;
        }
        $status = (int) ($filters['status'] ?? 0);
        if ($status > 0) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }
        $from = (int) ($filters['from'] ?? 0);
        if ($from > 0) {
            $where[] = 'timecreated >= :from';
            $params['from'] = $from;
        }
        $to = (int) ($filters['to'] ?? 0);
        if ($to > 0) {
            $where[] = 'timecreated <= :to';
            $params['to'] = $to;
        }
        $select = $where ? implode(' AND ', $where) : '';
        $total = $DB->count_records_select(self::TABLE_API, $select, $params);
        $rows = $DB->get_records_select(
            self::TABLE_API,
            $select,
            $params,
            'timecreated DESC',
            '*',
            $page * $perpage,
            $perpage
        );
        return ['rows' => $rows, 'total' => $total];
    }
}
