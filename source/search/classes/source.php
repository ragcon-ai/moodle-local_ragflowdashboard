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

namespace rfdsource_search;

/**
 * Dashboard source for the RAGflow Search feature (block_ragflowsearch).
 *
 * @package    rfdsource_search
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source extends \local_ragflowdashboard\source\base {
    /**
     * The component(s) this source owns.
     *
     * @return string[]
     */
    public function get_components(): array {
        return ['block_ragflowsearch'];
    }

    /**
     * Search is retrieval-only: it consumes no LLM tokens, so it is hidden from the Tokens tab's view filter.
     *
     * @return bool
     */
    public function has_token_usage(): bool {
        return false;
    }

    /**
     * Section sort order.
     *
     * @return int
     */
    public function get_sortorder(): int {
        return 30;
    }

    /**
     * One health check per Search block instance: each configured knowledge base exists and has parsed
     * content.
     *
     * @param array $ctx Shared RAGflow context (datasets).
     * @return array
     */
    public function status_checks(array $ctx): array {
        global $DB;
        $st = '\local_ragflowdashboard\status';
        $base = (string) $ctx['base'];
        $checks = [];
        $rs = $DB->get_recordset('block_instances', ['blockname' => 'ragflowsearch'], 'id');
        foreach ($rs as $bi) {
            $conf = $bi->configdata !== ''
                ? @unserialize(base64_decode($bi->configdata), ['allowed_classes' => ['stdClass']]) : null;
            $datasets = is_object($conf) ? array_values(array_filter(array_map('strval', (array) ($conf->datasets ?? [])))) : [];
            $kblinks = [];
            if (empty($datasets)) {
                $state = $st::INFO;
                $detail = get_string('status_kb_none', 'local_ragflowdashboard');
            } else {
                $state = $st::OK;
                $parts = [];
                foreach ($datasets as $dsid) {
                    [$dstate, $ddetail] = $st::dataset_state($ctx, $dsid);
                    $state = $st::worst($state, $dstate);
                    $parts[] = $ddetail;
                    $kbname = isset($ctx['datasets'][$dsid]) ? (string) $ctx['datasets'][$dsid]->name : $dsid;
                    $kblinks[] = ['name' => $kbname, 'url' => $st::ragflow_kb_url($base, $dsid)];
                }
                $detail = implode(' · ', $parts);
            }
            // Instance identity = the knowledge base name(s); Search has no chat assistant.
            if (count($kblinks) === 1) {
                $instancename = $kblinks[0]['name'];
            } else if (count($kblinks) > 1) {
                $instancename = get_string('status_kb_count', 'local_ragflowdashboard', count($kblinks));
            } else {
                $instancename = get_string('status_instance', 'local_ragflowdashboard', $bi->id);
            }
            $course = $st::course_of_context((int) $bi->parentcontextid);
            $checks[] = $st::check(
                $course['name'] . ' – ' . $instancename,
                $state,
                $detail,
                '',
                'GET ' . $base . '/api/v1/datasets',
                [
                    'coursename' => $course['name'],
                    'courseurl' => $course['url'],
                    'instancename' => $instancename,
                    'kblinks' => $kblinks,
                    'chaturl' => '',
                ]
            );
        }
        $rs->close();
        return $checks;
    }
}
