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

namespace rfdsource_tutor;

/**
 * Dashboard source for the RAGflow Tutor feature (block_ragflowtutor).
 *
 * @package    rfdsource_tutor
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
        return ['block_ragflowtutor'];
    }

    /**
     * Section sort order.
     *
     * @return int
     */
    public function get_sortorder(): int {
        return 10;
    }

    /**
     * One health check per Tutor block instance: its assistant is valid + linked, and its Moodle-managed
     * knowledge base (if any) has parsed content.
     *
     * @param array $ctx Shared RAGflow context (chats, datasets).
     * @return array
     */
    public function status_checks(array $ctx): array {
        global $DB;
        $st = '\local_ragflowdashboard\status';
        $base = (string) $ctx['base'];
        $checks = [];
        $rs = $DB->get_recordset('block_instances', ['blockname' => 'ragflowtutor'], 'id');
        foreach ($rs as $bi) {
            $conf = $bi->configdata !== ''
                ? @unserialize(base64_decode($bi->configdata), ['allowed_classes' => ['stdClass']]) : null;
            $chatid = is_object($conf) ? trim((string) ($conf->chatid ?? '')) : '';
            $kbid = is_object($conf) ? trim((string) ($conf->kbid ?? '')) : '';
            [$astate, $detail] = $st::assistant_state($ctx, $chatid);
            $state = $astate;
            if ($kbid !== '') {
                [$kstate, $kdetail] = $st::dataset_state($ctx, $kbid);
                $state = $st::worst($astate, $kstate);
                // The badge + title already convey the assistant, so the detail is just the KB status —
                // a plain doc count when parsed — unless the assistant itself is the problem.
                if ($astate === $st::OK) {
                    $ds = $ctx['datasets'][$kbid] ?? null;
                    $detail = ($kstate === $st::OK && $ds)
                        ? get_string('status_docs', 'local_ragflowdashboard', (int) $ds->document_count)
                        : $kdetail;
                }
            }
            $endpoints = ['GET ' . $base . '/api/v1/chats'];
            if ($kbid !== '') {
                $endpoints[] = 'GET ' . $base . '/api/v1/datasets';
            }
            // Instance identity = the RAGflow assistant name, scoped to the course the block lives in.
            $assistant = isset($ctx['chats'][$chatid]) ? (string) $ctx['chats'][$chatid]->name : '';
            $instancename = $assistant !== ''
                ? $assistant
                : get_string('status_instance', 'local_ragflowdashboard', $bi->id);
            $course = $st::course_of_context((int) $bi->parentcontextid);
            $kblinks = [];
            if ($kbid !== '') {
                $kbname = isset($ctx['datasets'][$kbid]) ? (string) $ctx['datasets'][$kbid]->name : $instancename;
                $kblinks[] = ['name' => $kbname, 'url' => $st::ragflow_kb_url($base, $kbid)];
            }
            $checks[] = $st::check(
                $course['name'] . ' – ' . $instancename,
                $state,
                $detail,
                '',
                implode(' · ', $endpoints),
                [
                    'coursename' => $course['name'],
                    'courseurl' => $course['url'],
                    'instancename' => $instancename,
                    'kblinks' => $kblinks,
                    'chaturl' => $chatid !== '' ? $st::ragflow_chat_url($base, $chatid) : '',
                ]
            );
        }
        $rs->close();
        return $checks;
    }
}
