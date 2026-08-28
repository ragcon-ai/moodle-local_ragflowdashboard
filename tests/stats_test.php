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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit tests for the dashboard usage-statistics aggregation.
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(stats::class)]
final class stats_test extends \advanced_testcase {
    /**
     * Insert a usage-log row with sensible defaults.
     *
     * @param array $row Field overrides.
     * @return void
     */
    private function log(array $row): void {
        global $DB;
        $DB->insert_record(stats::TABLE, (object) array_merge([
            'timecreated' => 1000,
            'component' => 'block_ragflowtutor',
            'action' => 'chat',
            'userid' => 0,
            'courseid' => 0,
            'contextid' => 0,
            'success' => 1,
            'errortype' => '',
            'latencyms' => 100,
            'itemcount' => 0,
        ], $row));
    }

    /**
     * totals(): counts rows since the lower bound, splits success/failure and averages the latency; an
     * optional component filter narrows the set.
     *
     * @return void
     */
    public function test_totals_with_since_and_component_filter(): void {
        $this->resetAfterTest();
        $tutor = 'block_ragflowtutor';
        $this->log(['timecreated' => 1000, 'component' => $tutor, 'success' => 1, 'latencyms' => 100]);
        $this->log(['timecreated' => 2000, 'component' => $tutor, 'success' => 0, 'errortype' => 'network', 'latencyms' => 300]);
        $this->log(['timecreated' => 3000, 'component' => 'aiplacement_ragflowhelpdesk', 'success' => 1, 'latencyms' => 200]);
        // Below the "since" bound, so excluded.
        $this->log(['timecreated' => 500, 'component' => $tutor, 'success' => 1, 'latencyms' => 999]);

        $all = stats::totals(1000);
        $this->assertSame(3, $all->total);
        $this->assertSame(2, $all->successful);
        $this->assertSame(1, $all->failed);
        $this->assertSame(200, $all->avglatency, 'round((100+300+200)/3)');

        $tutor = stats::totals(1000, ['block_ragflowtutor']);
        $this->assertSame(2, $tutor->total);
        $this->assertSame(1, $tutor->successful);
        $this->assertSame(1, $tutor->failed);
        $this->assertSame(200, $tutor->avglatency, 'round((100+300)/2)');
    }

    /**
     * by_errortype(): groups failed rows by error type (empty type -> "unknown"), ignoring successes.
     *
     * @return void
     */
    public function test_by_errortype_groups_failures(): void {
        $this->resetAfterTest();
        $this->log(['success' => 1, 'errortype' => '']);
        $this->log(['success' => 0, 'errortype' => 'network']);
        $this->log(['success' => 0, 'errortype' => 'network']);
        $this->log(['success' => 0, 'errortype' => 'http_5xx']);
        $this->log(['success' => 0, 'errortype' => '']);

        $out = stats::by_errortype(1000);
        $this->assertSame(2, $out['network']);
        $this->assertSame(1, $out['http_5xx']);
        $this->assertSame(1, $out['unknown']);
        $this->assertArrayNotHasKey('', $out);
    }

    /**
     * per_day(): buckets rows by day (floor(timecreated/86400)) with separate success/failure counts.
     *
     * @return void
     */
    public function test_per_day(): void {
        $this->resetAfterTest();
        $day10 = 10 * 86400;
        $day11 = 11 * 86400;
        $this->log(['timecreated' => $day10, 'success' => 1]);
        $this->log(['timecreated' => $day10 + 100, 'success' => 0]);
        $this->log(['timecreated' => $day11 + 50, 'success' => 1]);

        $out = stats::per_day(0);
        $this->assertSame(1, $out[10]->success);
        $this->assertSame(1, $out[10]->failed);
        $this->assertSame(1, $out[11]->success);
        $this->assertSame(0, $out[11]->failed);
    }

    /**
     * by_component(): counts rows per component, most frequent first.
     *
     * @return void
     */
    public function test_by_component(): void {
        $this->resetAfterTest();
        $this->log(['component' => 'block_ragflowtutor']);
        $this->log(['component' => 'block_ragflowtutor']);
        $this->log(['component' => 'aiplacement_ragflowhelpdesk']);

        $out = stats::by_component(0);
        $this->assertSame(2, $out['block_ragflowtutor']);
        $this->assertSame(1, $out['aiplacement_ragflowhelpdesk']);
        $this->assertSame('block_ragflowtutor', array_key_first($out), 'most frequent component first');
    }

    /**
     * recent_errors(): returns only failures, newest first, capped at the limit.
     *
     * @return void
     */
    public function test_recent_errors(): void {
        $this->resetAfterTest();
        $this->log(['success' => 1, 'timecreated' => 999]);            // Success row, so excluded.
        $this->log(['success' => 0, 'timecreated' => 100, 'errortype' => 'a']);
        $this->log(['success' => 0, 'timecreated' => 200, 'errortype' => 'b']);
        $this->log(['success' => 0, 'timecreated' => 300, 'errortype' => 'c']);

        $limited = array_values(stats::recent_errors(2));
        $this->assertCount(2, $limited);
        $this->assertSame('c', $limited[0]->errortype, 'newest failure first');
        $this->assertSame('b', $limited[1]->errortype);
        foreach ($limited as $row) {
            $this->assertSame(0, (int) $row->success);
        }
        $this->assertCount(3, stats::recent_errors(50), 'all three failures, success excluded');
    }

    /**
     * top_users(): ranks users by request count, excludes anonymised (userid 0) rows and honours the limit.
     *
     * @return void
     */
    public function test_top_users_ranks_and_excludes_anonymous(): void {
        $this->resetAfterTest();
        $u1 = $this->getDataGenerator()->create_user();
        $u2 = $this->getDataGenerator()->create_user();
        $this->log(['userid' => $u1->id]);
        $this->log(['userid' => $u1->id]);
        $this->log(['userid' => $u1->id]);
        $this->log(['userid' => $u2->id]);
        $this->log(['userid' => 0]); // Anonymised – excluded.

        $this->assertSame([$u1->id => 3, $u2->id => 1], stats::top_users(500, 10));
        $this->assertCount(1, stats::top_users(500, 1), 'limit caps the number of users');
    }

    /**
     * by_role(): buckets requests into trainers (a teaching/management role anywhere), students/users and
     * anonymised (userid 0); empty buckets are dropped.
     *
     * @return void
     */
    public function test_by_role_buckets_trainers_students_anon(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $trainer = $this->getDataGenerator()->create_user();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($trainer->id, $course->id, 'editingteacher');
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->log(['userid' => $trainer->id]);
        $this->log(['userid' => $trainer->id]);
        $this->log(['userid' => $student->id]);
        $this->log(['userid' => 0]);
        $this->log(['userid' => 0]);
        $this->log(['userid' => 0]);

        $roles = stats::by_role(500);
        $this->assertSame(2, $roles['trainer']);
        $this->assertSame(1, $roles['student']);
        $this->assertSame(3, $roles['anon']);
    }

    /**
     * by_course(): groups requests by course id (0 = outside a course), busiest first, capped at the limit.
     *
     * @return void
     */
    public function test_by_course_groups_and_limits(): void {
        $this->resetAfterTest();
        $c1 = $this->getDataGenerator()->create_course();
        $this->log(['courseid' => $c1->id]);
        $this->log(['courseid' => $c1->id]);
        $this->log(['courseid' => 0]); // Outside a course.

        $by = stats::by_course(500, 10);
        $this->assertSame(2, $by[$c1->id]);
        $this->assertSame(1, $by[0]);
        $this->assertCount(1, stats::by_course(500, 1), 'limit caps the number of courses');
    }

    /**
     * The token aggregations sum prompt/completion/total and break down by plugin and provider instance;
     * rows with zero tokens (e.g. search) are excluded from the by-plugin and by-provider breakdowns.
     *
     * @return void
     */
    public function test_token_aggregation(): void {
        $this->resetAfterTest();
        $this->log(['component' => 'block_ragflowtutor', 'providerid' => 1,
            'tokensprompt' => 10, 'tokenscompletion' => 5, 'tokenstotal' => 15]);
        $this->log(['component' => 'block_ragflowtutor', 'providerid' => 1,
            'tokensprompt' => 20, 'tokenscompletion' => 10, 'tokenstotal' => 30]);
        $this->log(['component' => 'aiplacement_ragflowhelpdesk', 'providerid' => 2,
            'tokensprompt' => 1, 'tokenscompletion' => 2, 'tokenstotal' => 3]);
        // A search row consumes no tokens: it must not appear in the by-plugin / by-provider breakdowns.
        $this->log(['component' => 'block_ragflowsearch', 'action' => 'search', 'providerid' => 1, 'tokenstotal' => 0]);

        $t = stats::tokens_totals(500);
        $this->assertSame(48, $t->total);
        $this->assertSame(31, $t->prompt);
        $this->assertSame(17, $t->completion);

        $bycomp = stats::tokens_by_component(500);
        $this->assertSame(45, $bycomp['block_ragflowtutor']);
        $this->assertSame(3, $bycomp['aiplacement_ragflowhelpdesk']);
        $this->assertArrayNotHasKey('block_ragflowsearch', $bycomp, 'zero-token rows are excluded');

        $byprov = stats::tokens_by_provider(500);
        $this->assertSame(45, $byprov[1]);
        $this->assertSame(3, $byprov[2]);
    }
}
