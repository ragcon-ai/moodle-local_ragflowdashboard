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

/**
 * Library callbacks for local_ragflowdashboard.
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Fragment callback: render one dashboard tab's content (used to reload a tab over AJAX for the
 * view/period filters and the API-call live view).
 *
 * @param array $args Fragment arguments: tab, view, days.
 * @return string The tab's HTML.
 */
function local_ragflowdashboard_output_fragment_view($args) {
    global $PAGE;

    // The dashboard is a site-wide report, so the capability must be checked against the system context –
    // not a caller-supplied context (a view grant elsewhere must not unlock the whole site's data).
    require_login();
    $context = \context_system::instance();
    require_capability('local/ragflowdashboard:view', $context);

    $tab = \local_ragflowdashboard\tabs::normalise(clean_param($args['tab'] ?? '', PARAM_ALPHA));
    $view = clean_param($args['view'] ?? 'all', PARAM_ALPHANUMEXT);
    $days = clean_param($args['days'] ?? 1, PARAM_INT);
    if ($days <= 0 || $days > 366) {
        $days = 1;
    }

    $renderer = $PAGE->get_renderer('local_ragflowdashboard');

    // A single Status area refresh (its own button) re-runs just that box's checks.
    $area = clean_param($args['area'] ?? '', PARAM_ALPHANUMEXT);
    if ($tab === \local_ragflowdashboard\tabs::STATUS && $area !== '') {
        return $renderer->render_status_area($area);
    }

    // API-calls tab: paging, per-page, filters and live-view flag (ignored by the other tabs).
    $extra = [
        'page' => clean_param($args['page'] ?? 0, PARAM_INT),
        'perpage' => clean_param($args['perpage'] ?? 20, PARAM_INT),
        'q' => clean_param($args['q'] ?? '', PARAM_RAW_TRIMMED),
        'status' => clean_param($args['status'] ?? 0, PARAM_INT),
        'fromdate' => clean_param($args['fromdate'] ?? '', PARAM_RAW_TRIMMED),
        'todate' => clean_param($args['todate'] ?? '', PARAM_RAW_TRIMMED),
        'live' => clean_param($args['live'] ?? 0, PARAM_BOOL),
    ];

    return $renderer->render_tab($tab, $view, $days, $extra);
}
