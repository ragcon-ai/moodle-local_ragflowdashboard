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

namespace local_ragflowdashboard\source;

/**
 * Base class for a dashboard "source" subplugin (rfdsource_*). A source owns one or more RAGflow
 * feature components (e.g. block_ragflowtutor) and contributes its own section to the dashboard. The
 * default section renders filtered KPIs, a per-day chart and a failures pie for the owned components;
 * a subplugin may override render_view() to add feature-specific analytics.
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /**
     * The frankenstyle names of the components this source owns (e.g. ['block_ragflowtutor']).
     *
     * @return string[]
     */
    abstract public function get_components(): array;

    /**
     * This subplugin's frankenstyle name, derived from its namespace (e.g. rfdsource_tutor).
     *
     * @return string
     */
    final public function get_frankenstyle(): string {
        return explode('\\', static::class)[0];
    }

    /**
     * Human-readable section title (the subplugin's own plugin name).
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('pluginname', $this->get_frankenstyle());
    }

    /**
     * Sort order among sections (lower first).
     *
     * @return int
     */
    public function get_sortorder(): int {
        return 100;
    }

    /**
     * Whether this source's feature consumes LLM tokens (chat), so it appears in the Tokens tab's view
     * filter. Search-only sources return false — retrieval uses no tokens.
     *
     * @return bool
     */
    public function has_token_usage(): bool {
        return true;
    }

    /**
     * Render this source's dashboard view (shown when it is selected in the dashboard dropdown). Default:
     * a filtered view (KPIs, charts, error log, debug captures) for the owned components. Override to add
     * feature-specific analytics.
     *
     * @param \local_ragflowdashboard\output\renderer $renderer
     * @param int $since Unix time lower bound.
     * @param int $days The reporting window in days (for labels).
     * @return string HTML
     */
    public function render_view(\local_ragflowdashboard\output\renderer $renderer, int $since, int $days): string {
        return $renderer->source_view($this, $since, $days);
    }

    /**
     * Health/connectivity checks for this source's configured instances, shown on the Status tab. Default:
     * none. Override to enumerate the feature's instances and validate each against RAGflow (assistant
     * valid + linked, knowledge base parsed) using the prefetched context.
     *
     * @param array $ctx {base, key, providerid, chats: [id => {name, kb}], datasets: [id => {name,
     *   chunk_count, document_count}]} – shared, already-fetched RAGflow data.
     * @return array List of {label, state, detail} check rows (see {@see \local_ragflowdashboard\status}).
     */
    public function status_checks(array $ctx): array {
        return [];
    }
}
