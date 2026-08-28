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
 * Unit tests for the dashboard's capture/read API (raw API-call paging + filters, usage capture).
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(api::class)]
final class api_test extends \advanced_testcase {
    /**
     * Insert a raw API-call log row with sensible defaults.
     *
     * @param array $row Field overrides.
     * @return void
     */
    private function apirow(array $row): void {
        global $DB;
        $DB->insert_record(api::TABLE_API, (object) array_merge([
            'timecreated' => 1000,
            'userid' => 0,
            'contextid' => 0,
            'method' => 'GET',
            'url' => 'https://ragflow.example/api/v1/datasets',
            'status' => 200,
            'success' => 1,
            'durationms' => 10,
            'errordetail' => '',
            'request' => '',
            'response' => 'ok',
        ], $row));
    }

    /**
     * apicalls(): pages the log newest-first and reports the total match count.
     *
     * @return void
     */
    public function test_apicalls_paging(): void {
        $this->resetAfterTest();
        for ($i = 0; $i < 25; $i++) {
            $this->apirow(['timecreated' => 1000 + $i]);
        }
        $page0 = api::apicalls(0, 10, []);
        $this->assertSame(25, $page0['total']);
        $this->assertCount(10, $page0['rows']);
        $this->assertSame(1024, (int) reset($page0['rows'])->timecreated, 'newest first');
        $this->assertCount(5, api::apicalls(2, 10, [])['rows'], 'last (third) page has the remainder');
    }

    /**
     * apicalls(): filters by exact HTTP status, free text (url/request/response/cause) and a date range.
     *
     * @return void
     */
    public function test_apicalls_filters(): void {
        $this->resetAfterTest();
        $this->apirow(['status' => 200, 'url' => 'https://ragflow.example/api/v1/chats', 'timecreated' => 1000]);
        $this->apirow(['status' => 404, 'url' => 'https://ragflow.example/api/v1/datasets', 'timecreated' => 2000]);
        $this->apirow(['status' => 500, 'url' => 'https://ragflow.example/api/v1/retrieval',
            'errordetail' => 'boom', 'timecreated' => 3000]);

        $this->assertSame(1, api::apicalls(0, 10, ['status' => 404])['total']);
        $this->assertSame(1, api::apicalls(0, 10, ['q' => 'retrieval'])['total'], 'text matches the URL');
        $this->assertSame(1, api::apicalls(0, 10, ['q' => 'boom'])['total'], 'text matches the cause');
        $this->assertSame(2, api::apicalls(0, 10, ['from' => 2000])['total'], 'from is inclusive');
        $this->assertSame(2, api::apicalls(0, 10, ['to' => 2000])['total'], 'to is inclusive');
        $this->assertSame(1, api::apicalls(0, 10, ['from' => 2000, 'to' => 2000])['total']);
    }

    /**
     * capture_usage(): stores the provider instance and the chat token counts alongside the metrics.
     *
     * @return void
     */
    public function test_capture_usage_stores_tokens_and_provider(): void {
        global $DB;
        $this->resetAfterTest();
        $context = \context_system::instance();
        api::capture_usage($context, 0, 'block_ragflowtutor', 'chat', true, '', 120, 3, 7, 40, 12, 52);

        $row = $DB->get_record(stats::TABLE, ['component' => 'block_ragflowtutor']);
        $this->assertEquals(7, $row->providerid);
        $this->assertEquals(40, $row->tokensprompt);
        $this->assertEquals(12, $row->tokenscompletion);
        $this->assertEquals(52, $row->tokenstotal);
    }
}
