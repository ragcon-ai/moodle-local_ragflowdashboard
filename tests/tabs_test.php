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
 * Tests the tab routing helper: an unknown key falls back to Status, and only the analytics tabs keep the
 * per-feature filter controls.
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(tabs::class)]
final class tabs_test extends \advanced_testcase {
    /**
     * all() lists the six tabs in display order, Status first.
     *
     * @return void
     */
    public function test_all(): void {
        $all = tabs::all();
        $this->assertSame(tabs::STATUS, $all[0]);
        $this->assertContains(tabs::EXPORT, $all);
        $this->assertCount(6, $all);
    }

    /**
     * normalise() passes a valid key through and coerces anything else to Status.
     *
     * @return void
     */
    public function test_normalise(): void {
        $this->assertSame(tabs::USAGE, tabs::normalise(tabs::USAGE));
        $this->assertSame(tabs::STATUS, tabs::normalise('bogus'));
        $this->assertSame(tabs::STATUS, tabs::normalise(''));
    }

    /**
     * is_filterable() is true only for the per-feature analytics tabs (Usage, Tokens, Errors).
     *
     * @return void
     */
    public function test_is_filterable(): void {
        $this->assertTrue(tabs::is_filterable(tabs::USAGE));
        $this->assertTrue(tabs::is_filterable(tabs::TOKENS));
        $this->assertTrue(tabs::is_filterable(tabs::ERRORS));
        $this->assertFalse(tabs::is_filterable(tabs::STATUS));
        $this->assertFalse(tabs::is_filterable(tabs::APICALLS));
        $this->assertFalse(tabs::is_filterable(tabs::EXPORT));
    }
}
