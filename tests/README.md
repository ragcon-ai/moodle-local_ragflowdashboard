# Tests – local_ragflowdashboard

**Plugin version:** `2026082500` (release `0.6.9`) — update this line whenever the tests or the plugin
version change.

PHPUnit tests for this plugin. They run automatically in the bundled **moodle-plugin-ci** GitHub Actions
workflow; to run them locally, use `vendor/bin/phpunit` from a configured Moodle root (see the
[Moodle PHPUnit docs](https://moodledev.io/general/development/tools/phpunit)).

This file records **what the tests verify**, in **execution order** (PHPUnit runs the methods top-to-bottom
as defined in each class). Keep it in sync when tests are added, reordered or changed.

## Coverage

### `stats_test.php` — usage-statistics aggregation (`\local_ragflowdashboard\stats`)

| # | Test | Verifies |
|---|---|---|
| 1 | `test_totals_with_since_and_component_filter` | `totals()` counts rows since the lower bound, splits success/failure, averages the latency, and narrows to the given component(s). |
| 2 | `test_by_errortype_groups_failures` | `by_errortype()` groups **failed** rows by error type (empty type → `unknown`) and ignores successes. |
| 3 | `test_per_day` | `per_day()` buckets rows by day (`floor(timecreated/86400)`) with separate success/failure counts. |
| 4 | `test_by_component` | `by_component()` counts rows per component, most frequent first. |
| 5 | `test_recent_errors` | `recent_errors()` returns only failures, newest first, capped at the limit. |
| 6 | `test_top_users_ranks_and_excludes_anonymous` | `top_users()` ranks users by request count, excludes anonymised (`userid 0`) rows and honours the limit. |
| 7 | `test_by_role_buckets_trainers_students_anon` | `by_role()` buckets requests into trainers (a teaching/management role anywhere), students/users and anonymised, dropping empty buckets. |
| 8 | `test_by_course_groups_and_limits` | `by_course()` groups by course id (`0` = outside a course), busiest first, capped at the limit. |
| 9 | `test_token_aggregation` | `tokens_totals()` / `tokens_by_component()` / `tokens_by_provider()` sum prompt/completion/total and break down by plugin and provider instance; zero-token rows (e.g. search) are excluded from the breakdowns. |

### `api_test.php` — capture/read API (`\local_ragflowdashboard\api`)

| # | Test | Verifies |
|---|---|---|
| 1 | `test_apicalls_paging` | `apicalls()` pages the raw API-call log newest-first and reports the total match count. |
| 2 | `test_apicalls_filters` | `apicalls()` filters by exact HTTP status, free text (url/request/response/cause) and an inclusive date range. |
| 3 | `test_capture_usage_stores_tokens_and_provider` | `capture_usage()` stores the provider instance id and the chat token counts (prompt/completion/total) alongside the metrics. |

### `xml_exporter_test.php` — export (`\local_ragflowdashboard\xml_exporter`)

| # | Test | Verifies |
|---|---|---|
| 1 | `test_parse_date` | `parse_date()` parses `YYYY-MM-DD` to the start (or, with `endofday`, the end) of that day, trims whitespace, and returns the given default for anything else (only ISO `YYYY-MM-DD` is accepted). |
| 2 | `test_export` | `export()` produces a `<ragflowusage>` document with one `<entry>` per in-range log row (typed attributes plus the owning `view`), respecting the from/to range; all views are exported (no per-view scoping). |

### `exporter_test.php` — export CSV safety (`\local_ragflowdashboard\exporter`)

| # | Test | Verifies |
|---|---|---|
| 1 | `test_csv_safe` | `csv_safe()` prefixes string cells whose first character is a spreadsheet formula trigger (`= + - @`, tab, CR) with a single quote (CSV formula-injection guard); safe strings and numeric/empty cells are left unchanged. |

### `status_test.php` — Status report via the central checker (`\local_ragflowdashboard\status`)

| # | Test | Verifies |
|---|---|---|
| 1 | `test_color_for` | the five reference states map to the traffic light: degraded/unverified → amber, missing (deleted reference) → red, not-configured → **blue notice**, ok → green. |
| 2 | `test_assistant_state` | assistant health is derived from the central classifier: bound → green, absent from the loaded list → red (missing), present-but-unbound → amber, none chosen yet → **blue notice**. |
| 3 | `test_dataset_state` | KB health: parsed → green; documents but 0 chunks → "not parsed yet" (amber); 0 documents → "no documents" (amber, distinct message); absent → red; none chosen yet → **blue notice**. |
| 4 | `test_plugins_report` | the install-status area reports all five suite plugins (green when installed in the test run). |
| 5 | `test_worst` | `worst()` returns the more severe of two states (ERROR > WARN > INFO > OK) — the fold used per instance; INFO ("not set up yet") ranks above OK but below a real fault. |
| 6 | `test_actions_report` | lists each **enabled** core_ai action with its assistant's health (valid → green, deleted → red) and skips disabled actions. |
| 7 | `test_ragflow_urls` | `ragflow_kb_url()` / `ragflow_chat_url()` build the RAGflow web deep links (`{base}/dataset/files/{id}`, `{base}/chat/{id}`) from the config base URL. |
| 8 | `test_check_extra` | `check()` merges the optional plugin-instance extras (course name/url, instance name, KB links, chat URL) into the returned row. |
| 9 | `test_course_of_context` | `course_of_context()` resolves a block's course (name + view URL); a context outside any course falls back to the site label. |

### `tabs_test.php` — tab routing (`\local_ragflowdashboard\tabs`)

| # | Test | Verifies |
|---|---|---|
| 1 | `test_all` | `all()` lists the six tabs in display order, Status first. |
| 2 | `test_normalise` | a valid key passes through; anything else coerces to Status. |
| 3 | `test_is_filterable` | only the per-feature analytics tabs (Usage, Tokens, Errors) keep the view/period filters. |

### `task/purge_logs_test.php` — retention/purge task (`\local_ragflowdashboard\task\purge_logs`)

| # | Test | Verifies |
|---|---|---|
| 1 | `test_execute_purges_old_rows` | with a retention window set, rows older than the cutoff are deleted from all three log tables and recent rows are kept. |
| 2 | `test_execute_retention_disabled_keeps_everything` | a retention of 0 disables purging — nothing is deleted, however old (data-loss safeguard). |

### `behat/dashboard_report.feature` — acceptance (`@local_ragflowdashboard @javascript`)

Run with **moodle-plugin-ci** (the bundled CI runs Behat automatically) or `vendor/bin/behat` from a
configured Moodle (see the [Moodle Behat docs](https://moodledev.io/general/development/tools/behat)).

| # | Scenario | Verifies |
|---|---|---|
| 1 | A site administrator can open the RAGflow usage dashboard | The report page (Site administration → Reports → RAGflow Dashboard) opens and renders (empty state, no RAGflow needed). |

## Deliberately not covered here (needs integration / a running RAGflow)

- The chart details / AJAX view fragment and the admin export page — only the report opening is smoke-tested
  via Behat.
- `observer` event handling and `api::debug_capture` / `capture_apicall` anonymisation branches (write
  paths driven by provider events).
- The per-instance source subplugins' `status_checks` (`rfdsource_{tutor,search,helpdesk}`) and the
  `provider_report`/`area` refresh path — exercised through the live Status tab rather than as units here.
