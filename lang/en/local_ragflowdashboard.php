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
 * English language strings for local_ragflowdashboard.
 *
 * @package    local_ragflowdashboard
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['anonymize'] = 'Anonymise log data';
$string['anonymize_desc'] = 'Store no user link with each log entry (user id 0). Aggregated statistics still work, but per-user analysis and the privacy export of individual usage do not.';
$string['apicall_apply'] = 'Apply';
$string['apicall_from'] = 'From';
$string['apicall_live'] = 'Live view';
$string['apicall_live_help'] = 'Automatically reload the list every few seconds.';
$string['apicall_next'] = 'Next';
$string['apicall_page'] = 'Page {$a->page} of {$a->pages}';
$string['apicall_perpage'] = 'Per page';
$string['apicall_prev'] = 'Previous';
$string['apicall_search'] = 'Search text';
$string['apicall_status'] = 'HTTP status';
$string['apicall_to'] = 'To';
$string['apicall_total'] = '{$a} matching calls';
$string['apilog'] = 'RAGflow API calls (raw)';
$string['apilog_cause'] = 'Cause';
$string['apilog_none'] = 'No API calls captured yet.';
$string['apilog_nostatus'] = 'no response';
$string['apilog_off'] = 'Raw API logging is off. Enable it in the settings below to capture calls here.';
$string['apilog_on'] = 'Raw API logging is ON — every RAGflow call is recorded below. Turn it off again when done.';
$string['apilog_request'] = 'Request';
$string['apilog_response'] = 'Response';
$string['apilog_url'] = 'URL';
$string['chart:bycomponent'] = 'Requests by feature';
$string['chart:bycourse'] = 'Top 10 courses';
$string['chart:byerrortype'] = 'Failures by type';
$string['chart:byrole'] = 'Requests by user group';
$string['chart:byuser'] = 'Top 10 users';
$string['chart:tokensbyinstance'] = 'Tokens by instance';
$string['chart:tokensbyplugin'] = 'Tokens by plugin';
$string['chart:tokensperday'] = 'Tokens per day';
$string['chart:usage'] = 'Requests per day';
$string['col:action'] = 'Action';
$string['col:component'] = 'Feature';
$string['col:errortype'] = 'Error type';
$string['col:latency'] = 'Latency (ms)';
$string['col:question'] = 'Question';
$string['col:response'] = 'Response';
$string['col:time'] = 'Time';
$string['course_none'] = 'Outside a course';
$string['debugapiraw'] = 'Raw RAGflow API call log';
$string['debugapiraw_desc'] = 'Log every RAGflow API call (URL, JSON request and raw response) into an admin-only table shown on this dashboard, with one collapsible row per call. The API key is never logged. Enable only for troubleshooting — it stores request and response content; turn it off again afterwards.';
$string['debugcaptures'] = 'Debug captures';
$string['debugfor'] = 'Debug: {$a}';
$string['debugheading'] = 'Per-feature debug mode';
$string['debugheading_desc'] = 'When enabled for a feature, the (bounded) request and response content is stored for troubleshooting. This captures user messages and answers, so enable it only temporarily and mind data protection.';
$string['detailmaxlen'] = 'Debug content limit (characters)';
$string['detailmaxlen_desc'] = 'Maximum number of characters stored per captured question and response.';
$string['errorlog'] = 'Recent errors';
$string['errors'] = 'Errors';
$string['errortype:embedding'] = 'Embedding error';
$string['errortype:embedding_contextwindow'] = 'Query too long for embedding model';
$string['errortype:http_4xx'] = 'RAGflow client error (4xx)';
$string['errortype:http_5xx'] = 'RAGflow server error (5xx)';
$string['errortype:network'] = 'Network / timeout';
$string['errortype:notconfigured'] = 'Not configured';
$string['errortype:ragflow'] = 'RAGflow error';
$string['errortype:ratelimited'] = 'Rate limited';
$string['errortype:session'] = 'Session error';
$string['errortype:unexpected'] = 'Unexpected response';
$string['errortype:unknown'] = 'Unknown';
$string['export'] = 'Export (all views)';
$string['export:allviews'] = 'All views';
$string['export:col:action'] = 'Action';
$string['export:col:component'] = 'Component';
$string['export:col:course'] = 'Course';
$string['export:col:errortype'] = 'Error type';
$string['export:col:itemcount'] = 'Items';
$string['export:col:latencyms'] = 'Latency (ms)';
$string['export:col:success'] = 'Success';
$string['export:col:time'] = 'Time';
$string['export:col:tokenscompletion'] = 'Completion tokens';
$string['export:col:tokensprompt'] = 'Prompt tokens';
$string['export:col:tokenstotal'] = 'Total tokens';
$string['export:col:user'] = 'User';
$string['export:col:view'] = 'View';
$string['export:kpi:failures'] = 'Failures';
$string['export:kpi:requests'] = 'Requests';
$string['export:kpi:successrate'] = 'Success rate';
$string['export:kpi:tokens'] = 'Total tokens';
$string['export:none'] = '—';
$string['export:norows'] = 'No data in this range.';
$string['export:otherview'] = 'Other';
$string['export:pdftitle'] = 'RAGflow usage report';
$string['export:pdftruncated'] = 'Only the first {$a} rows are shown — use CSV or XML for the full data.';
$string['exportbutton'] = 'Export';
$string['exportformat'] = 'Format';
$string['exportformat:csv'] = 'CSV';
$string['exportformat:pdf'] = 'PDF';
$string['exportformat:xml'] = 'XML';
$string['exportfrom'] = 'From';
$string['exportto'] = 'To';
$string['failed'] = 'Failed';
$string['kpi:avglatency'] = 'Average latency';
$string['kpi:failures'] = 'Failures';
$string['kpi:requests'] = 'Requests (last {$a} days)';
$string['kpi:successrate'] = 'Success rate';
$string['kpi:tokenscompletion'] = 'Completion tokens';
$string['kpi:tokensprompt'] = 'Prompt tokens';
$string['kpi:tokenstotal'] = 'Total tokens';
$string['nodata'] = 'No data yet.';
$string['noerrors'] = 'No errors in this period.';
$string['pluginname'] = 'RAGflow Dashboard';
$string['privacy:metadata:apilog'] = 'Optional raw RAGflow API-call log (request and response content), stored only while the raw-API-log toggle is enabled.';
$string['privacy:metadata:apilog:method'] = 'The HTTP method of the call.';
$string['privacy:metadata:apilog:request'] = 'The JSON request payload sent to RAGflow (bounded).';
$string['privacy:metadata:apilog:response'] = 'The raw response body from RAGflow (bounded).';
$string['privacy:metadata:apilog:status'] = 'The HTTP status code returned by RAGflow.';
$string['privacy:metadata:apilog:timecreated'] = 'When the call happened.';
$string['privacy:metadata:apilog:url'] = 'The RAGflow API endpoint URL called (no credentials).';
$string['privacy:metadata:apilog:userid'] = 'The user on whose behalf the call was made (unless anonymisation is enabled).';
$string['privacy:metadata:debug'] = 'Optional per-feature debug captures of request and response content, stored only while a feature\'s debug mode is enabled.';
$string['privacy:metadata:debug:action'] = 'The kind of request (chat or search).';
$string['privacy:metadata:debug:component'] = 'The feature the request came from.';
$string['privacy:metadata:debug:question'] = 'The user question or query (bounded).';
$string['privacy:metadata:debug:response'] = 'The answer, error or result summary (bounded).';
$string['privacy:metadata:debug:timecreated'] = 'When the request happened.';
$string['privacy:metadata:debug:userid'] = 'The user who made the request (unless anonymisation is enabled).';
$string['privacy:metadata:log'] = 'A log of RAGflow provider usage (chat and search requests) for statistics and troubleshooting.';
$string['privacy:metadata:log:action'] = 'The kind of request (chat or search).';
$string['privacy:metadata:log:component'] = 'The feature the request came from.';
$string['privacy:metadata:log:courseid'] = 'The course the request happened in (if any).';
$string['privacy:metadata:log:errortype'] = 'The error type if the request failed.';
$string['privacy:metadata:log:success'] = 'Whether the request succeeded.';
$string['privacy:metadata:log:timecreated'] = 'When the request happened.';
$string['privacy:metadata:log:userid'] = 'The user who made the request (unless anonymisation is enabled).';
$string['ragflowdashboard:view'] = 'View the RAGflow usage dashboard and logs';
$string['reload'] = 'Reload data';
$string['requests'] = 'Requests';
$string['retentiondays'] = 'Log retention (days)';
$string['retentiondays_desc'] = 'Delete log entries older than this many days. Set to 0 to keep them indefinitely.';
$string['role_anon'] = 'Anonymised';
$string['role_student'] = 'Students / users';
$string['role_trainer'] = 'Trainers';
$string['settings'] = 'RAGflow Dashboard settings';
$string['status_action'] = 'Action: {$a}';
$string['status_actions_heading'] = 'Provider actions';
$string['status_apicall'] = 'Check result (API call)';
$string['status_assistant_nokb'] = 'Assistant is not linked to a knowledge base';
$string['status_assistant_none'] = 'No assistant selected';
$string['status_assistant_notfound'] = 'Assistant not found in RAGflow';
$string['status_assistant_ok'] = 'Assistant "{$a}" OK';
$string['status_call_configonly'] = 'Local configuration check (no API call).';
$string['status_connection'] = 'RAGflow connection';
$string['status_connection_fail'] = 'Not reachable — check the base URL, API key and RAGflow service';
$string['status_connection_ok'] = 'Reachable — {$a->datasets} knowledge base(s), {$a->assistants} assistant(s)';
$string['status_context_gone'] = 'Unknown location';
$string['status_context_site'] = 'Site home';
$string['status_docs'] = '{$a} parsed document(s)';
$string['status_filter_placeholder'] = 'Filter by course or instance…';
$string['status_instance'] = 'Instance #{$a}';
$string['status_kb_count'] = '{$a} knowledge bases';
$string['status_kb_empty'] = 'Knowledge base "{$a}" has no parsed content yet';
$string['status_kb_nodocs'] = 'Knowledge base "{$a}" contains no documents yet';
$string['status_kb_none'] = 'No knowledge base selected';
$string['status_kb_notfound'] = 'Knowledge base not found in RAGflow';
$string['status_kb_ok'] = '"{$a->name}": {$a->docs} parsed document(s)';
$string['status_lastchecked'] = 'Checked at {$a}';
$string['status_link_chat'] = 'RAGflow chat app';
$string['status_link_course'] = 'Moodle course: {$a}';
$string['status_link_kb'] = 'RAGflow knowledge base';
$string['status_link_newwindow'] = '(opens in a new window)';
$string['status_link_settings'] = 'Settings';
$string['status_noinstances'] = 'No configured instances';
$string['status_placement'] = 'Placement (site-wide)';
$string['status_plugin_dashboard'] = 'RAGflow Dashboard (this plugin)';
$string['status_plugin_helpdesk'] = 'Helpdesk placement';
$string['status_plugin_installed'] = 'Installed';
$string['status_plugin_missing'] = 'Not installed';
$string['status_plugin_provider'] = 'RAGflow provider';
$string['status_plugin_search'] = 'Search block';
$string['status_plugin_tutor'] = 'Tutor block';
$string['status_plugins_heading'] = 'Suite plugins';
$string['status_provider'] = 'RAGflow provider instance';
$string['status_provider_heading'] = 'Provider & connection';
$string['status_provider_missing'] = 'Not configured — set the base URL and API key on the provider instance';
$string['status_provider_ok'] = 'Configured (base URL + API key set)';
$string['status_refresh'] = 'Refresh';
$string['status_section_instances'] = 'Plugin instances';
$string['status_section_system'] = 'System configuration';
$string['status_state_error'] = 'Error';
$string['status_state_info'] = 'Not configured';
$string['status_state_ok'] = 'OK';
$string['status_state_warn'] = 'Warning';
$string['subplugintype_rfdsource'] = 'RAGflow Dashboard source';
$string['subplugintype_rfdsource_plural'] = 'RAGflow Dashboard sources';
$string['successful'] = 'Successful';
$string['tab_apicalls'] = 'API calls';
$string['tab_errors'] = 'Errors';
$string['tab_export'] = 'Export';
$string['tab_status'] = 'Status';
$string['tab_tokens'] = 'Tokens';
$string['tab_usage'] = 'Usage';
$string['task:purgelogs'] = 'Purge old RAGflow usage logs';
$string['tokeninstanceunknown'] = 'Unknown instance';
$string['tokensinfo'] = 'Tokens are counted for chat only (search consumes none) and only for chats via the OpenAI-compatible RAGflow endpoint. Chats with session memory use the native RAGflow endpoint, which returns no token data, so they are not counted. Counting starts at installation (no history) and reflects the usage figures reported by RAGflow — provided without guarantee of completeness or accuracy.';
$string['view'] = 'View';
$string['viewall'] = 'All features';
$string['windowdays'] = 'Period (days)';
$string['windowtoday'] = 'Today';
