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

namespace local_ragflowdashboard\task;

/**
 * Deletes usage log rows older than the configured retention period.
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_logs extends \core\task\scheduled_task {
    /**
     * The task name shown in the admin UI.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:purgelogs', 'local_ragflowdashboard');
    }

    /**
     * Delete log rows older than retentiondays (0 = keep forever).
     *
     * @return void
     */
    public function execute() {
        global $DB;
        $days = (int) get_config('local_ragflowdashboard', 'retentiondays');
        if ($days <= 0) {
            mtrace('RAGflow Dashboard: retention disabled (keeping all logs).');
            return;
        }
        $cutoff = time() - ($days * DAYSECS);
        $params = ['cutoff' => $cutoff];
        $count = $DB->count_records_select('local_ragflowdashboard_log', 'timecreated < :cutoff', $params);
        $DB->delete_records_select('local_ragflowdashboard_log', 'timecreated < :cutoff', $params);
        $debugcount = $DB->count_records_select('local_ragflowdashboard_debug', 'timecreated < :cutoff', $params);
        $DB->delete_records_select('local_ragflowdashboard_debug', 'timecreated < :cutoff', $params);
        $apicount = $DB->count_records_select('local_ragflowdashboard_apilog', 'timecreated < :cutoff', $params);
        $DB->delete_records_select('local_ragflowdashboard_apilog', 'timecreated < :cutoff', $params);
        mtrace("RAGflow Dashboard: purged {$count} log, {$debugcount} debug and {$apicount} API-log row(s) "
            . "older than {$days} day(s).");
    }
}
