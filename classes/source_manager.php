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
 * Discovers the installed dashboard source subplugins (rfdsource_*).
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source_manager {
    /**
     * Instantiate every installed source subplugin, sorted by its sort order then name.
     *
     * @return \local_ragflowdashboard\source\base[]
     */
    public static function instances(): array {
        $out = [];
        foreach (\core_component::get_plugin_list('rfdsource') as $name => $unused) {
            $class = '\\rfdsource_' . $name . '\\source';
            if (class_exists($class)) {
                $out[] = new $class();
            }
        }
        usort($out, function (source\base $a, source\base $b) {
            return [$a->get_sortorder(), $a->get_name()] <=> [$b->get_sortorder(), $b->get_name()];
        });
        return $out;
    }
}
