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
 * The dashboard's top-level tabs. A small registry so the report page, the fragment reload and any future
 * tab share one ordered, validated definition. Each tab's content is assembled by the renderer.
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tabs {
    /** @var string Health / connectivity of the provider and each configured instance. */
    const STATUS = 'status';
    /** @var string Usage KPIs and charts. */
    const USAGE = 'usage';
    /** @var string Chat token consumption (by plugin and provider instance). */
    const TOKENS = 'tokens';
    /** @var string Raw RAGflow API-call log (+ debug captures). */
    const APICALLS = 'apicalls';
    /** @var string Failures by type and the recent-errors log. */
    const ERRORS = 'errors';
    /** @var string Log export and retention/purge. */
    const EXPORT = 'export';

    /**
     * The tabs in display order.
     *
     * @return string[]
     */
    public static function all(): array {
        return [self::STATUS, self::USAGE, self::TOKENS, self::APICALLS, self::ERRORS, self::EXPORT];
    }

    /**
     * Coerce an arbitrary value to a valid tab key (defaults to Status).
     *
     * @param string $key
     * @return string
     */
    public static function normalise(string $key): string {
        return in_array($key, self::all(), true) ? $key : self::STATUS;
    }

    /**
     * The translated label for a tab.
     *
     * @param string $key
     * @return string
     */
    public static function label(string $key): string {
        return get_string('tab_' . $key, 'local_ragflowdashboard');
    }

    /**
     * Whether a tab keeps the view/period filter controls (Usage and Errors are filterable per feature).
     *
     * @param string $key
     * @return bool
     */
    public static function is_filterable(string $key): bool {
        return in_array($key, [self::USAGE, self::TOKENS, self::ERRORS], true);
    }
}
