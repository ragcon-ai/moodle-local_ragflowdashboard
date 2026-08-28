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
 * Read-only aggregation queries over the usage log for the dashboard. All grouping uses portable integer
 * arithmetic (FLOOR(timecreated/86400)) so it works across database engines. Every method accepts an
 * optional list of components to scope a per-source section to the components it owns.
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stats {
    /** @var string The log table. */
    const TABLE = 'local_ragflowdashboard_log';

    /**
     * Build the "component IN (...)" clause and params for an optional component filter.
     *
     * @param array $components Frankenstyle component names (empty = no filter).
     * @return array [string $sqlfragment (leading " AND ..." or ""), array $params]
     */
    protected static function component_filter(array $components): array {
        global $DB;
        if (empty($components)) {
            return ['', []];
        }
        [$insql, $params] = $DB->get_in_or_equal($components, SQL_PARAMS_NAMED, 'comp');
        return [' AND component ' . $insql, $params];
    }

    /**
     * Headline totals since a timestamp.
     *
     * @param int $since Unix time lower bound.
     * @param array $components Optional component filter.
     * @return \stdClass {total, successful, failed, avglatency}
     */
    public static function totals(int $since, array $components = []): \stdClass {
        global $DB;
        [$compsql, $params] = self::component_filter($components);
        $params['since'] = $since;
        $row = $DB->get_record_sql(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) AS successful,
                    AVG(latencyms) AS avglatency
               FROM {' . self::TABLE . '}
              WHERE timecreated >= :since' . $compsql,
            $params
        );
        $total = (int) ($row->total ?? 0);
        $successful = (int) ($row->successful ?? 0);
        return (object) [
            'total' => $total,
            'successful' => $successful,
            'failed' => $total - $successful,
            'avglatency' => (int) round((float) ($row->avglatency ?? 0)),
        ];
    }

    /**
     * Per-day success/failure counts since a timestamp, keyed by day bucket (FLOOR(time/86400)).
     *
     * @param int $since
     * @param array $components Optional component filter.
     * @return array [daybucket => {success:int, failed:int}]
     */
    public static function per_day(int $since, array $components = []): array {
        global $DB;
        [$compsql, $params] = self::component_filter($components);
        $params['since'] = $since;
        $out = [];
        $rs = $DB->get_recordset_sql(
            'SELECT FLOOR(timecreated / 86400) AS daybucket, success, COUNT(*) AS cnt
               FROM {' . self::TABLE . '}
              WHERE timecreated >= :since' . $compsql . '
           GROUP BY FLOOR(timecreated / 86400), success
           ORDER BY daybucket ASC',
            $params
        );
        foreach ($rs as $row) {
            $bucket = (int) $row->daybucket;
            if (!isset($out[$bucket])) {
                $out[$bucket] = (object) ['success' => 0, 'failed' => 0];
            }
            if ((int) $row->success === 1) {
                $out[$bucket]->success = (int) $row->cnt;
            } else {
                $out[$bucket]->failed = (int) $row->cnt;
            }
        }
        $rs->close();
        return $out;
    }

    /**
     * Request counts grouped by calling component since a timestamp.
     *
     * @param int $since
     * @return array [component => count]
     */
    public static function by_component(int $since): array {
        global $DB;
        $rows = $DB->get_records_sql(
            'SELECT component, COUNT(*) AS cnt
               FROM {' . self::TABLE . '}
              WHERE timecreated >= :since
           GROUP BY component
           ORDER BY cnt DESC',
            ['since' => $since]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row->component] = (int) $row->cnt;
        }
        return $out;
    }

    /**
     * Failure counts grouped by error type since a timestamp.
     *
     * @param int $since
     * @param array $components Optional component filter.
     * @return array [errortype => count]
     */
    public static function by_errortype(int $since, array $components = []): array {
        global $DB;
        [$compsql, $params] = self::component_filter($components);
        $params['since'] = $since;
        $rows = $DB->get_records_sql(
            'SELECT errortype, COUNT(*) AS cnt
               FROM {' . self::TABLE . '}
              WHERE success = 0 AND timecreated >= :since' . $compsql . '
           GROUP BY errortype
           ORDER BY cnt DESC',
            $params
        );
        $out = [];
        foreach ($rows as $row) {
            $key = ($row->errortype !== '') ? $row->errortype : 'unknown';
            $out[$key] = (int) $row->cnt;
        }
        return $out;
    }

    /**
     * The most recent failures for the error-log table.
     *
     * @param int $limit
     * @param array $components Optional component filter.
     * @return array log records (most recent first)
     */
    public static function recent_errors(int $limit = 50, array $components = []): array {
        global $DB;
        [$compsql, $params] = self::component_filter($components);
        $params['success'] = 0;
        return $DB->get_records_select(
            self::TABLE,
            'success = :success' . $compsql,
            $params,
            'timecreated DESC',
            '*',
            0,
            $limit
        );
    }

    /**
     * The most active users (by request count) since a timestamp. Anonymised rows (userid 0) are excluded,
     * so this is empty when logging is anonymised.
     *
     * @param int $since
     * @param int $limit
     * @param array $components Optional component filter.
     * @return array [userid => count], most active first
     */
    public static function top_users(int $since, int $limit, array $components = []): array {
        global $DB;
        [$compsql, $params] = self::component_filter($components);
        $params['since'] = $since;
        $rows = $DB->get_records_sql(
            'SELECT userid, COUNT(*) AS cnt
               FROM {' . self::TABLE . '}
              WHERE userid > 0 AND timecreated >= :since' . $compsql . '
           GROUP BY userid
           ORDER BY cnt DESC',
            $params,
            0,
            $limit
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->userid] = (int) $row->cnt;
        }
        return $out;
    }

    /**
     * Request counts grouped into coarse user groups since a timestamp: trainers (users holding a
     * teaching/management role anywhere), students/users (everyone else) and anonymised (userid 0).
     * Empty buckets are dropped.
     *
     * @param int $since
     * @param array $components Optional component filter.
     * @return array [group key (trainer|student|anon) => count]
     */
    public static function by_role(int $since, array $components = []): array {
        global $DB;
        [$compsql, $params] = self::component_filter($components);
        $params['since'] = $since;
        $rows = $DB->get_records_sql(
            'SELECT userid, COUNT(*) AS cnt
               FROM {' . self::TABLE . '}
              WHERE timecreated >= :since' . $compsql . '
           GROUP BY userid',
            $params
        );
        $trainers = self::trainer_userids();
        $out = ['trainer' => 0, 'student' => 0, 'anon' => 0];
        foreach ($rows as $row) {
            $uid = (int) $row->userid;
            $cnt = (int) $row->cnt;
            if ($uid === 0) {
                $out['anon'] += $cnt;
            } else if (isset($trainers[$uid])) {
                $out['trainer'] += $cnt;
            } else {
                $out['student'] += $cnt;
            }
        }
        return array_filter($out, function ($v) {
            return $v > 0;
        });
    }

    /**
     * The set of user ids holding a teaching/management role in any context ("trainers").
     *
     * @return array [userid => true]
     */
    protected static function trainer_userids(): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal(
            ['editingteacher', 'teacher', 'manager', 'coursecreator'],
            SQL_PARAMS_NAMED,
            'sn'
        );
        $rows = $DB->get_records_sql(
            'SELECT DISTINCT ra.userid
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
              WHERE r.shortname ' . $insql,
            $params
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->userid] = true;
        }
        return $out;
    }

    /**
     * Request counts grouped by course since a timestamp (top N). Course id 0 means "outside a course".
     *
     * @param int $since
     * @param int $limit
     * @param array $components Optional component filter.
     * @return array [courseid => count], busiest first
     */
    public static function by_course(int $since, int $limit, array $components = []): array {
        global $DB;
        [$compsql, $params] = self::component_filter($components);
        $params['since'] = $since;
        $rows = $DB->get_records_sql(
            'SELECT courseid, COUNT(*) AS cnt
               FROM {' . self::TABLE . '}
              WHERE timecreated >= :since' . $compsql . '
           GROUP BY courseid
           ORDER BY cnt DESC',
            $params,
            0,
            $limit
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->courseid] = (int) $row->cnt;
        }
        return $out;
    }

    /**
     * Summed chat token consumption since a timestamp.
     *
     * @param int $since
     * @param array $components Optional component filter.
     * @return \stdClass {prompt, completion, total}
     */
    public static function tokens_totals(int $since, array $components = []): \stdClass {
        global $DB;
        [$compsql, $params] = self::component_filter($components);
        $params['since'] = $since;
        $row = $DB->get_record_sql(
            'SELECT COALESCE(SUM(tokensprompt), 0) AS prompt,
                    COALESCE(SUM(tokenscompletion), 0) AS completion,
                    COALESCE(SUM(tokenstotal), 0) AS total
               FROM {' . self::TABLE . '}
              WHERE timecreated >= :since' . $compsql,
            $params
        );
        return (object) [
            'prompt' => (int) ($row->prompt ?? 0),
            'completion' => (int) ($row->completion ?? 0),
            'total' => (int) ($row->total ?? 0),
        ];
    }

    /**
     * Total chat tokens per day since a timestamp.
     *
     * @param int $since
     * @param array $components Optional component filter.
     * @return array [daybucket => total tokens]
     */
    public static function tokens_per_day(int $since, array $components = []): array {
        global $DB;
        [$compsql, $params] = self::component_filter($components);
        $params['since'] = $since;
        $rows = $DB->get_records_sql(
            'SELECT FLOOR(timecreated / 86400) AS daybucket, COALESCE(SUM(tokenstotal), 0) AS tokens
               FROM {' . self::TABLE . '}
              WHERE timecreated >= :since' . $compsql . '
           GROUP BY FLOOR(timecreated / 86400)',
            $params
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->daybucket] = (int) $row->tokens;
        }
        return $out;
    }

    /**
     * Chat tokens grouped by calling component (only components that consumed tokens).
     *
     * @param int $since
     * @return array [component => total tokens]
     */
    public static function tokens_by_component(int $since): array {
        global $DB;
        $rows = $DB->get_records_sql(
            'SELECT component, COALESCE(SUM(tokenstotal), 0) AS tokens
               FROM {' . self::TABLE . '}
              WHERE timecreated >= :since AND tokenstotal > 0
           GROUP BY component
           ORDER BY tokens DESC',
            ['since' => $since]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row->component] = (int) $row->tokens;
        }
        return $out;
    }

    /**
     * Chat tokens grouped by provider instance since a timestamp.
     *
     * @param int $since
     * @param array $components Optional component filter.
     * @return array [providerid => total tokens]
     */
    public static function tokens_by_provider(int $since, array $components = []): array {
        global $DB;
        [$compsql, $params] = self::component_filter($components);
        $params['since'] = $since;
        $rows = $DB->get_records_sql(
            'SELECT providerid, COALESCE(SUM(tokenstotal), 0) AS tokens
               FROM {' . self::TABLE . '}
              WHERE timecreated >= :since AND tokenstotal > 0' . $compsql . '
           GROUP BY providerid
           ORDER BY tokens DESC',
            $params
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->providerid] = (int) $row->tokens;
        }
        return $out;
    }
}
