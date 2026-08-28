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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the retention/purge scheduled task: rows older than the retention window are deleted across all
 * three log tables, newer rows are kept, and a retention of 0 keeps everything (a data-loss safeguard).
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(purge_logs::class)]
final class purge_logs_test extends \advanced_testcase {
    /**
     * Insert one old + one recent row into each of the three log tables.
     *
     * @param int $old A timestamp before the cutoff.
     * @param int $recent A timestamp after the cutoff.
     * @return void
     */
    private function seed(int $old, int $recent): void {
        global $DB;
        foreach ([$old, $recent] as $ts) {
            $DB->insert_record('local_ragflowdashboard_log', (object) [
                'timecreated' => $ts, 'component' => 'block_ragflowtutor', 'action' => 'chat', 'errortype' => '',
            ]);
            $DB->insert_record('local_ragflowdashboard_debug', (object) [
                'timecreated' => $ts, 'component' => 'block_ragflowtutor', 'action' => 'chat',
            ]);
            $DB->insert_record('local_ragflowdashboard_apilog', (object) [
                'timecreated' => $ts, 'method' => 'GET', 'url' => 'https://r.example/api/v1/chats',
            ]);
        }
    }

    /**
     * With a 30-day retention, rows older than the cutoff are purged from all three tables and the recent
     * rows survive.
     *
     * @return void
     */
    public function test_execute_purges_old_rows(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('retentiondays', 30, 'local_ragflowdashboard');
        $this->seed(time() - 40 * DAYSECS, time() - 1 * DAYSECS);

        ob_start();
        (new purge_logs())->execute();
        ob_end_clean();

        foreach (['local_ragflowdashboard_log', 'local_ragflowdashboard_debug', 'local_ragflowdashboard_apilog'] as $t) {
            $this->assertSame(1, $DB->count_records($t), "only the recent row survives in {$t}");
        }
    }

    /**
     * A retention of 0 disables purging: nothing is deleted, however old.
     *
     * @return void
     */
    public function test_execute_retention_disabled_keeps_everything(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('retentiondays', 0, 'local_ragflowdashboard');
        $this->seed(time() - 400 * DAYSECS, time() - 1 * DAYSECS);

        ob_start();
        (new purge_logs())->execute();
        ob_end_clean();

        foreach (['local_ragflowdashboard_log', 'local_ragflowdashboard_debug', 'local_ragflowdashboard_apilog'] as $t) {
            $this->assertSame(2, $DB->count_records($t), "both rows kept in {$t} when retention is off");
        }
    }
}
