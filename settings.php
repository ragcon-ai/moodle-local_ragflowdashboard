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
 * Admin settings and the dashboard report link for the RAGflow Dashboard.
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// The dashboard page (Site administration → Reports). Added unconditionally; the page's own capability
// (local/ragflowdashboard:view, managers) gates who can open it.
$ADMIN->add('reports', new admin_externalpage(
    'local_ragflowdashboard',
    get_string('pluginname', 'local_ragflowdashboard'),
    new moodle_url('/local/ragflowdashboard/index.php'),
    'local/ragflowdashboard:view'
));

// Configuration (site admins only).
if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_ragflowdashboard_settings',
        get_string('settings', 'local_ragflowdashboard')
    );

    $settings->add(new admin_setting_configtext(
        'local_ragflowdashboard/retentiondays',
        get_string('retentiondays', 'local_ragflowdashboard'),
        get_string('retentiondays_desc', 'local_ragflowdashboard'),
        90,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_ragflowdashboard/anonymize',
        get_string('anonymize', 'local_ragflowdashboard'),
        get_string('anonymize_desc', 'local_ragflowdashboard'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_ragflowdashboard/detailmaxlen',
        get_string('detailmaxlen', 'local_ragflowdashboard'),
        get_string('detailmaxlen_desc', 'local_ragflowdashboard'),
        2000,
        PARAM_INT
    ));

    // Per-feature debug mode: a checkbox per component owned by an installed source subplugin. When on, the
    // provider captures the (bounded) request/response content for that feature into an admin-only table.
    $settings->add(new admin_setting_heading(
        'local_ragflowdashboard/debugheading',
        get_string('debugheading', 'local_ragflowdashboard'),
        get_string('debugheading_desc', 'local_ragflowdashboard')
    ));
    // Raw RAGflow API-call log: when on, the provider records every RAGflow HTTP call (URL + JSON request +
    // raw response) into an admin-only table shown on the dashboard. The API key is never logged.
    $settings->add(new admin_setting_configcheckbox(
        'local_ragflowdashboard/debug_apiraw',
        get_string('debugapiraw', 'local_ragflowdashboard'),
        get_string('debugapiraw_desc', 'local_ragflowdashboard'),
        0
    ));
    foreach (\local_ragflowdashboard\source_manager::instances() as $rfdsource) {
        foreach ($rfdsource->get_components() as $rfdcomponent) {
            $settings->add(new admin_setting_configcheckbox(
                'local_ragflowdashboard/debug_' . $rfdcomponent,
                get_string('debugfor', 'local_ragflowdashboard', $rfdsource->get_name()),
                '',
                0
            ));
        }
    }

    $ADMIN->add('localplugins', $settings);
}
