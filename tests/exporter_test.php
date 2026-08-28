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
 * Unit tests for the dashboard exporter's CSV formula-injection guard.
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(exporter::class)]
final class exporter_test extends \advanced_testcase {
    /**
     * csv_safe(): a string cell whose first character is a spreadsheet formula trigger (= + - @, tab, CR)
     * is prefixed with a single quote so it renders as literal text; safe strings and numeric cells are
     * left unchanged.
     *
     * @return void
     */
    public function test_csv_safe(): void {
        $rows = [
            ['user' => '=cmd|calc', 'course' => 'Maths', 'tokenstotal' => 10, 'component' => '+danger'],
            ['user' => 'Ada Lovelace', 'course' => '-1', 'tokenstotal' => 0, 'component' => '@x'],
            ['user' => "\t=SUM(A1)", 'course' => '', 'tokenstotal' => 5, 'component' => 'block_ragflowtutor'],
        ];
        $safe = exporter::csv_safe($rows);

        // Dangerous leading characters are neutralised.
        $this->assertSame("'=cmd|calc", $safe[0]['user']);
        $this->assertSame("'+danger", $safe[0]['component']);
        $this->assertSame("'-1", $safe[1]['course']);
        $this->assertSame("'@x", $safe[1]['component']);
        $this->assertSame("'\t=SUM(A1)", $safe[2]['user']);

        // Safe strings and numeric / empty cells are untouched.
        $this->assertSame('Maths', $safe[0]['course']);
        $this->assertSame('Ada Lovelace', $safe[1]['user']);
        $this->assertSame('block_ragflowtutor', $safe[2]['component']);
        $this->assertSame('', $safe[2]['course']);
        $this->assertSame(10, $safe[0]['tokenstotal'], 'numeric cells are left as-is');
        $this->assertSame(0, $safe[1]['tokenstotal']);
    }
}
