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
 * Upgrade steps for local_ragflowdashboard.
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Apply the plugin upgrade steps.
 *
 * @param int $oldversion The currently installed version.
 * @return bool
 */
function xmldb_local_ragflowdashboard_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026080904) {
        // Add the per-feature debug capture table.
        $table = new xmldb_table('local_ragflowdashboard_debug');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('action', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('success', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('question', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('response', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        $table->add_index('component_timecreated', XMLDB_INDEX_NOTUNIQUE, ['component', 'timecreated']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026080904, 'local', 'ragflowdashboard');
    }

    if ($oldversion < 2026081900) {
        // The invalid empty-string DEFAULTs on the char NOT NULL columns (component, action,
        // errortype) were removed in install.xml. No DDL is applied here: Moodle already strips
        // such a default at install time (so existing columns carry none), and component/action
        // are part of indexes, which makes change_field_default() raise a ddldependencyerror.
        // Fresh installs use the corrected install.xml; nothing needs to change on an existing DB.
        upgrade_plugin_savepoint(true, 2026081900, 'local', 'ragflowdashboard');
    }

    if ($oldversion < 2026082002) {
        // Add the raw RAGflow API-call log table (populated only while the admin raw-API-log toggle is on).
        $table = new xmldb_table('local_ragflowdashboard_apilog');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('method', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('url', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('success', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('durationms', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('errordetail', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('request', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('response', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026082002, 'local', 'ragflowdashboard');
    }

    if ($oldversion < 2026082116) {
        // Chat token consumption (from the RAGflow completion 'usage' block) plus the provider instance, so
        // the Tokens tab can break usage down by plugin and provider instance. Counts accrue from here on.
        $table = new xmldb_table('local_ragflowdashboard_log');
        $fields = [
            new xmldb_field('providerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'itemcount'),
            new xmldb_field('tokensprompt', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'providerid'),
            new xmldb_field('tokenscompletion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'tokensprompt'),
            new xmldb_field('tokenstotal', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'tokenscompletion'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026082116, 'local', 'ragflowdashboard');
    }

    if ($oldversion < 2026082303) {
        // Index the raw API-call log's HTTP status column, which the API-calls tab filters on exactly.
        $table = new xmldb_table('local_ragflowdashboard_apilog');
        $index = new xmldb_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);
        if ($dbman->table_exists($table) && !$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_plugin_savepoint(true, 2026082303, 'local', 'ragflowdashboard');
    }

    return true;
}
