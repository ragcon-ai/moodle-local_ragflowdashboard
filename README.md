# RAGflow Dashboard (local_ragflowdashboard) #

An admin-only Moodle **local plugin** that visualises the usage and health of the **RAGflow AI provider**
(`aiprovider_ragflow`) and its features (Tutor, Helpdesk, Search). It is the operations layer above the
suite — a tabbed report showing what your RAGflow AI costs, proving it is working and showing how it is
used, without storing any message content. It is **free and open-source** and entirely **optional** — the
other plugins work fully without it.

## Features ##

Site administration → Reports → *RAGflow Dashboard* — a tabbed admin report; every chart uses Moodle's
built-in Chart API, with a matching, colour-coded data table:

* **Status** — health of the RAGflow provider and each configured feature instance (configured and
  reachable, assistant valid and linked, knowledge base parsed), with links to the relevant config page.
* **Usage** — requests, success rate, failures and average latency; requests per day, by feature, top
  users, by user group (trainers vs. students) and by course.
* **Tokens** — chat token consumption per day, by plugin and by provider instance (an indicator of cost,
  not a billing meter; search consumes none).
* **API calls** — the raw RAGflow API-call log with paging, filters and a live view; off by default and the
  API key is never logged.
* **Errors** — failures grouped by type, plus a recent-errors list.
* **Export** — download the current view for a date range as **CSV, XML or PDF**.

The usage log holds **metrics only — no message content** (unless an optional, admin-only per-feature debug
capture is switched on). An **anonymisation** option drops the user link, and a daily task purges entries
after a configurable retention period. **Admin-only.**

## Requirements ##

* **Moodle 5.0–5.2.**
* The **RAGflow AI provider** (`aiprovider_ragflow`) installed and enabled — the dashboard only visualises
  its usage events. This plugin declares a dependency on it.
* It is meaningful only alongside a working RAGflow setup (the provider configured with a reachable
  [RAGflow](https://ragflow.io/) instance, **0.25 or later**, that can be **self-hosted or hosted by
  RAGcon**). Without any RAGflow activity the reports are simply empty; the dashboard itself needs no
  external service or key of its own.

## Installation ##

1. Copy the plugin to `local/ragflowdashboard` in the Moodle tree (**Moodle 5.1+**: `public/local/ragflowdashboard`).
2. Complete the installation via *Site administration → Notifications* or `php admin/cli/upgrade.php`.

## Usage ##

Open it at *Site administration → Reports → RAGflow Dashboard*. Retention, anonymisation, the debug-capture
toggles and the raw API-call log are configured under *Site administration → Plugins → Local plugins →
RAGflow Dashboard settings*.

## Documentation ##

Full setup and usage documentation: <https://docs.ragcon.ai/moodle-ragflow/plugins/dashboard/>

## Privacy and GDPR ##

* Implements a **full Moodle Privacy API** provider (export + delete). It records usage and error entries
  that reference the acting user — **metrics only, no message content** — unless a feature's optional
  **debug capture** is switched on, in which case bounded request/response content is stored (and purged
  with the other logs).
* An **anonymisation** option stores no user link, and a scheduled retention task deletes old entries. The
  underlying AI requests are processed by RAGflow via the AI provider (see that plugin's *Privacy* section);
  RAGflow can be **self-hosted or hosted by RAGcon**.

## Issues & Contributing ##

* Issues and feature requests: <https://github.com/ragcon-ai/moodle-local_ragflowdashboard/issues>

  Please include your **RAGflow version**, **Moodle version**, **plugin version** and the **exact steps to
  reproduce**.
* Pull requests are welcome. The plugin stays **GPLv3**; by contributing you agree your changes are licensed
  under the same terms.

## Support ##

Professional support and web hosting for RAGflow + Moodle are available from **RAGcon GmbH** —
<https://www.ragcon.ai/en> (www.ragcon.ai).

## Community ##

* Moodle — <https://moodle.org>
* RAGflow — <https://ragflow.io>

## Changelog ##

### 0.7.0 ###

* **First public release (beta).** An admin-only usage-and-health report for the RAGflow suite: a tabbed
  dashboard (Status, Usage, Tokens, API calls, Errors, Export) built on Moodle's Chart API — metrics-only,
  content-free, with anonymisation and retention. Free and optional; the other plugins work without it.

## Acknowledgements ##

This plugin integrates two independent software projects:

* **Moodle** — software by Moodle Pty Ltd, released under the GNU GPL v3 or later
  (<https://github.com/moodle/moodle>). *The word Moodle and associated Moodle logos are trademarks or
  registered trademarks of Moodle Pty Ltd or its related affiliates.*
* **RAGflow** — open-source software by InfiniFlow Inc., released under the Apache License 2.0
  (<https://ragflow.io> · <https://github.com/infiniflow/ragflow>).

This plugin is an independent integration and is not affiliated with or endorsed by Moodle Pty Ltd or
InfiniFlow Inc.

## Development ##

This plugin is part of the Moodle RAGflow suite, developed with the help of a range of AI tools under the
professional supervision of the RAGcon GmbH team — pairing fast, AI-assisted development with human review,
automated testing and security checks before every release.

## License ##

Copyright 2026 RAGcon GmbH <info@ragcon.ai>

This program is free software: you can redistribute it and/or modify it under the terms of the GNU
General Public License as published by the Free Software Foundation, either version 3 of the License,
or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even
the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
Public License for more details.

The full licence text is in `LICENSE`, or at <https://www.gnu.org/licenses/gpl-3.0.html>.
