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
 * RAGflow Dashboard admin report: a tabbed view of status, usage, API calls, errors and export. Within a
 * tab the view (All + one entry per source subplugin) and period filters reload the tab over AJAX.
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_ragflowdashboard\tabs;

$tab = tabs::normalise(optional_param('tab', tabs::STATUS, PARAM_ALPHA));
$view = optional_param('view', 'all', PARAM_ALPHANUMEXT);
$days = optional_param('days', 1, PARAM_INT);
if ($days <= 0 || $days > 366) {
    $days = 1;
}

// API-calls tab: paging, per-page, filters (search text / HTTP status / date range) and the live-view flag.
// Harmless for the other tabs (render_tab ignores them there); validated/clamped inside the renderer.
$extra = [
    'page' => optional_param('page', 0, PARAM_INT),
    'perpage' => optional_param('perpage', 20, PARAM_INT),
    'q' => optional_param('q', '', PARAM_RAW_TRIMMED),
    'status' => optional_param('status', 0, PARAM_INT),
    'fromdate' => optional_param('fromdate', '', PARAM_RAW_TRIMMED),
    'todate' => optional_param('todate', '', PARAM_RAW_TRIMMED),
    'live' => optional_param('live', 0, PARAM_BOOL),
];

// The admin_externalpage_setup() call enforces the page's capability (local/ragflowdashboard:view) and
// sets up the admin page layout/context.
admin_externalpage_setup('local_ragflowdashboard');

$context = context_system::instance();
$PAGE->set_url(new moodle_url('/local/ragflowdashboard/index.php', ['tab' => $tab, 'view' => $view, 'days' => $days]));

/** @var \local_ragflowdashboard\output\renderer $renderer */
$renderer = $PAGE->get_renderer('local_ragflowdashboard');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_ragflowdashboard'));

// Top-level tab bar.
$taburls = [];
foreach (tabs::all() as $key) {
    $taburls[] = new tabobject(
        $key,
        new moodle_url('/local/ragflowdashboard/index.php', ['tab' => $key, 'view' => $view, 'days' => $days]),
        tabs::label($key)
    );
}
echo $OUTPUT->tabtree($taburls, $tab);

// Active tab content, in a fragment container so in-tab filters (view/period) and the API-call live view
// can reload just this region over AJAX.
echo html_writer::div(
    $renderer->render_tab($tab, $view, $days, $extra),
    '',
    ['data-region' => 'rfd-view', 'data-tab' => $tab]
);

$PAGE->requires->js_call_amd('local_ragflowdashboard/control', 'init', [$context->id, $tab]);

echo $OUTPUT->footer();
