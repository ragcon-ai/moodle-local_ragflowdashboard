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

namespace rfdsource_helpdesk;

/**
 * Dashboard source for the RAGflow Helpdesk feature (aiplacement_ragflowhelpdesk).
 *
 * @package    rfdsource_helpdesk
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
        return ['aiplacement_ragflowhelpdesk'];
    }

    /**
     * Section sort order.
     *
     * @return int
     */
    public function get_sortorder(): int {
        return 20;
    }

    /**
     * The Helpdesk is a single site-wide placement: one check for its configured assistant.
     *
     * @param array $ctx Shared RAGflow context (chats).
     * @return array
     */
    public function status_checks(array $ctx): array {
        $st = '\local_ragflowdashboard\status';
        $base = (string) $ctx['base'];
        $chatid = trim((string) get_config('aiplacement_ragflowhelpdesk', 'chatid'));
        [$state, $detail] = $st::assistant_state($ctx, $chatid);
        // Site-wide placement: no course. Identity = the RAGflow assistant name.
        $assistant = isset($ctx['chats'][$chatid]) ? (string) $ctx['chats'][$chatid]->name : '';
        $instancename = $assistant !== '' ? $assistant : get_string('status_placement', 'local_ragflowdashboard');
        return [
            $st::check(
                $instancename,
                $state,
                $detail,
                '',
                'GET ' . $base . '/api/v1/chats',
                [
                    'coursename' => '',
                    'courseurl' => '',
                    'instancename' => $instancename,
                    'kblinks' => [],
                    'chaturl' => $chatid !== '' ? $st::ragflow_chat_url($base, $chatid) : '',
                ]
            ),
        ];
    }
}
