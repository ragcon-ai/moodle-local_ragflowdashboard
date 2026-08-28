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

namespace local_ragflowdashboard\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the RAGflow Dashboard usage log.
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the stored personal data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_ragflowdashboard_log', [
            'userid' => 'privacy:metadata:log:userid',
            'timecreated' => 'privacy:metadata:log:timecreated',
            'component' => 'privacy:metadata:log:component',
            'action' => 'privacy:metadata:log:action',
            'courseid' => 'privacy:metadata:log:courseid',
            'success' => 'privacy:metadata:log:success',
            'errortype' => 'privacy:metadata:log:errortype',
        ], 'privacy:metadata:log');
        $collection->add_database_table('local_ragflowdashboard_debug', [
            'userid' => 'privacy:metadata:debug:userid',
            'timecreated' => 'privacy:metadata:debug:timecreated',
            'component' => 'privacy:metadata:debug:component',
            'action' => 'privacy:metadata:debug:action',
            'question' => 'privacy:metadata:debug:question',
            'response' => 'privacy:metadata:debug:response',
        ], 'privacy:metadata:debug');
        $collection->add_database_table('local_ragflowdashboard_apilog', [
            'userid' => 'privacy:metadata:apilog:userid',
            'timecreated' => 'privacy:metadata:apilog:timecreated',
            'method' => 'privacy:metadata:apilog:method',
            'url' => 'privacy:metadata:apilog:url',
            'status' => 'privacy:metadata:apilog:status',
            'request' => 'privacy:metadata:apilog:request',
            'response' => 'privacy:metadata:apilog:response',
        ], 'privacy:metadata:apilog');
        return $collection;
    }

    /**
     * Contexts that hold data for the user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            'SELECT contextid FROM {local_ragflowdashboard_log} WHERE userid = :userid1
             UNION
             SELECT contextid FROM {local_ragflowdashboard_debug} WHERE userid = :userid2
             UNION
             SELECT contextid FROM {local_ragflowdashboard_apilog} WHERE userid = :userid3',
            ['userid1' => $userid, 'userid2' => $userid, 'userid3' => $userid]
        );
        return $contextlist;
    }

    /**
     * Users who have data in the given context.
     *
     * @param userlist $userlist
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $contextid = $userlist->get_context()->id;
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {local_ragflowdashboard_log} WHERE contextid = :contextid AND userid > 0',
            ['contextid' => $contextid]
        );
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {local_ragflowdashboard_debug} WHERE contextid = :contextid AND userid > 0',
            ['contextid' => $contextid]
        );
        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {local_ragflowdashboard_apilog} WHERE contextid = :contextid AND userid > 0',
            ['contextid' => $contextid]
        );
    }

    /**
     * Export the user's log rows, grouped by context.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $export = [];
            $logs = $DB->get_records(
                'local_ragflowdashboard_log',
                ['contextid' => $context->id, 'userid' => $userid],
                'timecreated ASC'
            );
            foreach ($logs as $row) {
                $export['logs'][] = [
                    'timecreated' => userdate($row->timecreated),
                    'component' => $row->component,
                    'action' => $row->action,
                    'success' => $row->success,
                    'errortype' => $row->errortype,
                ];
            }
            $debug = $DB->get_records(
                'local_ragflowdashboard_debug',
                ['contextid' => $context->id, 'userid' => $userid],
                'timecreated ASC'
            );
            foreach ($debug as $row) {
                $export['debug'][] = [
                    'timecreated' => userdate($row->timecreated),
                    'component' => $row->component,
                    'action' => $row->action,
                    'question' => $row->question,
                    'response' => $row->response,
                ];
            }
            $apicalls = $DB->get_records(
                'local_ragflowdashboard_apilog',
                ['contextid' => $context->id, 'userid' => $userid],
                'timecreated ASC'
            );
            foreach ($apicalls as $row) {
                $export['apicalls'][] = [
                    'timecreated' => userdate($row->timecreated),
                    'method' => $row->method,
                    'url' => $row->url,
                    'status' => $row->status,
                    'request' => $row->request,
                    'response' => $row->response,
                ];
            }
            if ($export) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_ragflowdashboard')],
                    (object) $export
                );
            }
        }
    }

    /**
     * Delete all users' data for a context.
     *
     * @param \context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        $DB->delete_records('local_ragflowdashboard_log', ['contextid' => $context->id]);
        $DB->delete_records('local_ragflowdashboard_debug', ['contextid' => $context->id]);
        $DB->delete_records('local_ragflowdashboard_apilog', ['contextid' => $context->id]);
    }

    /**
     * Delete a user's data across the approved contexts.
     *
     * @param approved_contextlist $contextlist
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $DB->delete_records('local_ragflowdashboard_log', ['contextid' => $context->id, 'userid' => $userid]);
            $DB->delete_records('local_ragflowdashboard_debug', ['contextid' => $context->id, 'userid' => $userid]);
            $DB->delete_records('local_ragflowdashboard_apilog', ['contextid' => $context->id, 'userid' => $userid]);
        }
    }

    /**
     * Delete data for a set of users in a context.
     *
     * @param approved_userlist $userlist
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $where = "contextid = :contextid AND userid {$insql}";
        $params = ['contextid' => $context->id] + $inparams;
        $DB->delete_records_select('local_ragflowdashboard_log', $where, $params);
        $DB->delete_records_select('local_ragflowdashboard_debug', $where, $params);
        $DB->delete_records_select('local_ragflowdashboard_apilog', $where, $params);
    }
}
