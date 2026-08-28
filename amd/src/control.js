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
 * RAGflow Dashboard control: reloads the active tab's content over a fragment when its in-tab controls
 * change — the Usage/Errors view+period filters, and the API-calls filter bar (search/status/date),
 * per-page selector, paging and live (auto-reload) view. Progressive enhancement over the no-JS GET form.
 *
 * @module     local_ragflowdashboard/control
 * @copyright  2026 RAGcon GmbH <info@ragcon.ai>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Fragment from 'core/fragment';
import Templates from 'core/templates';
import Notification from 'core/notification';

// How often the API-calls "live view" reloads the list, in milliseconds.
const LIVE_INTERVAL = 5000;

export const init = (contextid, tab) => {
    const container = document.querySelector('[data-region="rfd-view"]');
    if (!container) {
        return;
    }
    const activeTab = tab || container.dataset.tab || 'status';
    let liveTimer = null;
    // The Usage accordion sections are closed by default and mutually exclusive; remember which one the
    // user opened so it can be restored after a view/period reload (the fragment re-renders them closed).
    let usageOpenKey = '';

    const value = (region) => {
        const el = container.querySelector('[data-region="' + region + '"]');
        return el ? el.value : null;
    };

    // Collect every in-tab control's current value. Fields absent on the active tab are simply skipped, so
    // the same collector serves all tabs; the server ignores params that do not apply to a tab.
    const collectParams = () => {
        const params = {
            tab: activeTab,
            view: value('rfd-view-select') || 'all',
            days: value('rfd-days-select') || 30,
        };
        const apicalls = {
            page: 'rfd-ac-page',
            perpage: 'rfd-ac-perpage',
            q: 'rfd-ac-q',
            status: 'rfd-ac-status',
            fromdate: 'rfd-ac-from',
            todate: 'rfd-ac-to',
        };
        Object.keys(apicalls).forEach((key) => {
            const v = value(apicalls[key]);
            if (v !== null) {
                params[key] = v;
            }
        });
        const live = container.querySelector('[data-region="rfd-ac-live"]');
        if (live) {
            params.live = live.checked ? 1 : 0;
        }
        return params;
    };

    const load = () => {
        container.style.opacity = '0.5';
        Fragment.loadFragment('local_ragflowdashboard', 'view', contextid, collectParams())
            .then((html, js) => {
                Templates.replaceNodeContents(container, html, js);
                container.style.opacity = '';
                bind();
                restoreUsageOpen();
                return null;
            })
            .catch((error) => {
                container.style.opacity = '';
                Notification.exception(error);
            });
    };

    // Re-open the remembered Usage section after a reload (the native name-group closes the others).
    const restoreUsageOpen = () => {
        if (!usageOpenKey) {
            return;
        }
        const det = container.querySelector('details[data-rfd-key="' + usageOpenKey + '"]');
        if (det && !det.open) {
            det.open = true;
        }
    };

    // Filter changes should show the first page of the new result set, not a now-out-of-range page.
    const resetPageAndLoad = () => {
        const pageEl = container.querySelector('[data-region="rfd-ac-page"]');
        if (pageEl) {
            pageEl.value = 0;
        }
        load();
    };

    // Start/stop the live poll to match the checkbox; re-evaluated after every (re)bind so it survives reloads.
    const syncLive = () => {
        const box = container.querySelector('[data-region="rfd-ac-live"]');
        const on = box && box.checked;
        if (on && !liveTimer) {
            liveTimer = setInterval(load, LIVE_INTERVAL);
        } else if (!on && liveTimer) {
            clearInterval(liveTimer);
            liveTimer = null;
        }
    };

    // The controls live inside the reloaded container, so (re)bind them after each load.
    const bind = () => {
        const usageForm = container.querySelector('[data-region="rfd-controls"]');
        const viewSelect = container.querySelector('[data-region="rfd-view-select"]');
        const daysSelect = container.querySelector('[data-region="rfd-days-select"]');
        if (usageForm) {
            usageForm.addEventListener('submit', (e) => {
                e.preventDefault();
                load();
            });
        }
        if (viewSelect) {
            viewSelect.addEventListener('change', load);
        }
        if (daysSelect) {
            daysSelect.addEventListener('change', load);
        }

        const filterForm = container.querySelector('[data-region="rfd-apicalls-filter"]');
        if (filterForm) {
            filterForm.addEventListener('submit', (e) => {
                e.preventDefault();
                resetPageAndLoad();
            });
            // Any filter/per-page/live change reloads from page one.
            filterForm.addEventListener('change', resetPageAndLoad);
        }
        syncLive();
    };

    bind();

    // Charts sit inside <details> accordions; with Chart.js maintainAspectRatio:false a collapsed chart
    // renders at zero size. Nudge every responsive chart to re-measure when a section opens. The 'toggle'
    // event does not bubble, so listen in the capture phase; this survives fragment reloads.
    container.addEventListener('toggle', (e) => {
        const det = e.target;
        if (det && det.matches && det.matches('details[data-rfd-key]')) {
            if (det.open) {
                usageOpenKey = det.dataset.rfdKey;
            } else if (det.dataset.rfdKey === usageOpenKey) {
                usageOpenKey = '';
            }
        }
        window.dispatchEvent(new Event('resize'));
    }, true);

    // Status tab: the Tutor/Search instance filter. Client-side, instant — hide rows whose
    // "course instance" text does not contain the query. Delegated so it survives area refreshes.
    container.addEventListener('input', (e) => {
        const filter = e.target.closest('[data-action="rfd-instance-filter"]');
        if (!filter) {
            return;
        }
        const box = filter.closest('[data-region="rfd-status-area"]');
        const list = box && box.querySelector('[data-region="rfd-instance-list"]');
        if (!list) {
            return;
        }
        const q = filter.value.trim().toLowerCase();
        list.querySelectorAll('li').forEach((li) => {
            const hay = li.dataset.filter || '';
            li.style.display = (q === '' || hay.indexOf(q) !== -1) ? '' : 'none';
        });
    });

    // Delegated on the container so these keep working after its content is replaced.
    container.addEventListener('click', (e) => {
        // API-calls paging: set the target page and reload (no filter reset).
        const pager = e.target.closest('[data-action="rfd-ac-page"]');
        if (pager && !pager.disabled) {
            e.preventDefault();
            const pageEl = container.querySelector('[data-region="rfd-ac-page"]');
            if (pageEl) {
                pageEl.value = pager.dataset.page;
            }
            load();
            return;
        }

        // Status tab: each box's own "refresh" button re-runs just that area's checks.
        const btn = e.target.closest('[data-action="rfd-status-refresh"]');
        if (!btn) {
            return;
        }
        e.preventDefault();
        const box = btn.closest('[data-region="rfd-status-area"]');
        if (!box) {
            return;
        }
        box.style.opacity = '0.5';
        Fragment.loadFragment('local_ragflowdashboard', 'view', contextid, {tab: 'status', area: btn.dataset.area})
            .then((html, js) => {
                Templates.replaceNodeContents(box, html, js);
                box.style.opacity = '';
                return null;
            })
            .catch((error) => {
                box.style.opacity = '';
                Notification.exception(error);
            });
    });
};
