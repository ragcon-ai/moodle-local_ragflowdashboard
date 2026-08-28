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

use aiprovider_ragflow\local\health\reference_status;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests that the dashboard Status report derives its verdicts from the single central reference checker
 * (traffic-light colours per state; the degraded distinction empty-vs-not-parsed) and lists suite install
 * state. The "is it usable?" logic itself is owned + unit-tested by aiprovider_ragflow\local\health\checker.
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(status::class)]
final class status_test extends \advanced_testcase {
    /**
     * degraded and unverified are amber; missing (a deleted reference) is red; not-configured is a blue
     * notice (nothing set up yet, not a fault); ok is green.
     *
     * @return void
     */
    public function test_color_for(): void {
        $this->assertSame(status::OK, status::color_for(reference_status::OK));
        $this->assertSame(status::WARN, status::color_for(reference_status::DEGRADED));
        $this->assertSame(status::WARN, status::color_for(reference_status::UNVERIFIED));
        $this->assertSame(status::ERROR, status::color_for(reference_status::MISSING));
        $this->assertSame(status::INFO, status::color_for(reference_status::NOT_CONFIGURED));
    }

    /**
     * An assistant present with a bound KB is green; absent from the loaded list is red (missing, never
     * conflated with unreachable); present but unbound is amber; none chosen yet is a blue notice.
     *
     * @return void
     */
    public function test_assistant_state(): void {
        $ctx = ['chats' => ['a1' => (object) ['name' => 'Onboarding', 'kb' => 2]]];
        $this->assertSame(status::OK, status::assistant_state($ctx, 'a1')[0]);
        $this->assertSame(status::ERROR, status::assistant_state($ctx, 'gone')[0]);
        $this->assertSame(status::INFO, status::assistant_state($ctx, '')[0]);

        $nokb = ['chats' => ['a2' => (object) ['name' => 'Plain', 'kb' => 0]]];
        $this->assertSame(status::WARN, status::assistant_state($nokb, 'a2')[0]);
    }

    /**
     * A KB with parsed content is green; with documents but 0 chunks it is "not parsed yet" (amber); with 0
     * documents it is "no documents" (amber, a distinct message); absent from the loaded list is red; none
     * chosen yet is a blue notice.
     *
     * @return void
     */
    public function test_dataset_state(): void {
        $ok = ['datasets' => ['d1' => (object) ['name' => 'KB', 'document_count' => 3, 'chunk_count' => 40]]];
        $this->assertSame(status::OK, status::dataset_state($ok, 'd1')[0]);

        $notparsed = ['datasets' => ['d2' => (object) ['name' => 'KB', 'document_count' => 3, 'chunk_count' => 0]]];
        [$state, $detail] = status::dataset_state($notparsed, 'd2');
        $this->assertSame(status::WARN, $state);
        $this->assertSame(get_string('status_kb_empty', 'local_ragflowdashboard', 'KB'), $detail);

        $nodocs = ['datasets' => ['d3' => (object) ['name' => 'KB', 'document_count' => 0, 'chunk_count' => 0]]];
        [$state, $detail] = status::dataset_state($nodocs, 'd3');
        $this->assertSame(status::WARN, $state);
        $this->assertSame(get_string('status_kb_nodocs', 'local_ragflowdashboard', 'KB'), $detail);

        $this->assertSame(status::ERROR, status::dataset_state($ok, 'gone')[0]);
        $this->assertSame(status::INFO, status::dataset_state($ok, '')[0]);
    }

    /**
     * The install-status area reports every suite plugin; in the test environment they are all installed, so
     * each row is green.
     *
     * @return void
     */
    public function test_plugins_report(): void {
        $report = status::plugins_report();
        $this->assertSame('plugins', $report['area']);

        // The report enumerates the five suite plugins in a fixed order.
        $suite = [
            'aiprovider_ragflow',
            'block_ragflowtutor',
            'block_ragflowsearch',
            'aiplacement_ragflowhelpdesk',
            'local_ragflowdashboard',
        ];
        $this->assertCount(count($suite), $report['checks']);

        // The dashboard works with any subset of the feature plugins, and moodle-plugin-ci installs only
        // the dashboard plus its provider dependency. Do not assume the optional siblings are present:
        // each check's state must match whether that component is actually installed in this run.
        foreach ($suite as $i => $component) {
            $installed = \core_component::get_component_directory($component) !== null;
            $this->assertSame(
                $installed ? status::OK : status::ERROR,
                $report['checks'][$i]['state'],
                $component
            );
        }

        // The provider (a hard dependency) and the dashboard itself are always installed in a test run.
        $this->assertSame(status::OK, $report['checks'][0]['state'], 'provider installed');
        $this->assertSame(status::OK, $report['checks'][4]['state'], 'dashboard installed');
    }

    /**
     * worst() returns the more severe of two states (ERROR > WARN > INFO > OK). It is the backbone that folds
     * an assistant + knowledge-base verdict into a single per-instance state; INFO ("not set up yet") ranks
     * above OK but below a real fault.
     *
     * @return void
     */
    public function test_worst(): void {
        $this->assertSame(status::ERROR, status::worst(status::OK, status::ERROR));
        $this->assertSame(status::ERROR, status::worst(status::ERROR, status::WARN));
        $this->assertSame(status::WARN, status::worst(status::WARN, status::OK));
        $this->assertSame(status::WARN, status::worst(status::OK, status::WARN));
        $this->assertSame(status::OK, status::worst(status::OK, status::OK));
        $this->assertSame(status::INFO, status::worst(status::OK, status::INFO));
        $this->assertSame(status::WARN, status::worst(status::INFO, status::WARN));
        $this->assertSame(status::ERROR, status::worst(status::INFO, status::ERROR));
    }

    /**
     * actions_report() lists each ENABLED core_ai action the provider handles with the health of its
     * configured assistant, and skips disabled actions. A valid assistant is green, a deleted one red.
     *
     * @return void
     */
    public function test_actions_report(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->insert_record('ai_providers', (object) [
            'name' => 'RAGflow',
            'provider' => 'aiprovider_ragflow\\provider',
            'enabled' => 1,
            'config' => json_encode(['baseurl' => 'https://r.example', 'apikey' => 'k']),
            'actionconfig' => json_encode([
                'core_ai\\aiactions\\generate_text' => ['enabled' => true, 'settings' => ['chatid' => 'good']],
                'core_ai\\aiactions\\summarise_text' => ['enabled' => true, 'settings' => ['chatid' => 'gone']],
                'core_ai\\aiactions\\explain_text' => ['enabled' => false, 'settings' => ['chatid' => 'good']],
            ]),
        ]);
        // Prefetched context: only the 'good' assistant exists (with a bound KB).
        $ctx = ['chats' => ['good' => (object) ['name' => 'Assistant', 'kb' => 1]]];

        $report = status::actions_report($ctx);

        $this->assertSame('actions', $report['area']);
        $this->assertCount(2, $report['checks'], 'the disabled action is skipped');
        $states = [];
        foreach ($report['checks'] as $c) {
            $states[] = $c['state'];
        }
        $this->assertContains(status::OK, $states, 'the action bound to the existing assistant is green');
        $this->assertContains(status::ERROR, $states, 'the action bound to a deleted assistant is red');
    }

    /**
     * ragflow_kb_url() / ragflow_chat_url() build the RAGflow web deep links from the (config) base URL.
     *
     * @return void
     */
    public function test_ragflow_urls(): void {
        $this->assertSame(
            'https://r.example/dataset/files/abc123',
            status::ragflow_kb_url('https://r.example', 'abc123')
        );
        $this->assertSame(
            'https://r.example/chat/def456',
            status::ragflow_chat_url('https://r.example', 'def456')
        );
    }

    /**
     * check() merges the optional plugin-instance extras (course, links, chat) into the returned row.
     *
     * @return void
     */
    public function test_check_extra(): void {
        $row = status::check('L', status::OK, 'd', '', 'GET x', [
            'coursename' => 'Course 1',
            'instancename' => 'Tutor',
            'kblinks' => [['name' => 'KB', 'url' => 'u']],
            'chaturl' => 'https://r.example/chat/x',
        ]);
        $this->assertSame('Course 1', $row['coursename']);
        $this->assertSame('Tutor', $row['instancename']);
        $this->assertSame('https://r.example/chat/x', $row['chaturl']);
        $this->assertSame('GET x', $row['call']);
    }

    /**
     * course_of_context() resolves a block's course (name + view URL), and falls back to a site label for a
     * context outside any course.
     *
     * @return void
     */
    public function test_course_of_context(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Onboarding Course']);
        $coursecontext = \context_course::instance($course->id);

        $resolved = status::course_of_context($coursecontext->id);
        $this->assertSame('Onboarding Course', $resolved['name']);
        $this->assertStringContainsString('/course/view.php?id=' . $course->id, $resolved['url']);

        $site = status::course_of_context(\context_system::instance()->id);
        $this->assertSame(get_string('status_context_site', 'local_ragflowdashboard'), $site['name']);
    }
}
