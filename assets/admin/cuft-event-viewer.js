/**
 * CUFT Testing Dashboard: Event Viewer and Test Database Events panels
 *
 * Lists the dataLayer events CUFTDataLayerMonitor captures, checked by
 * CUFTEventValidator, and loads or deletes the events the simulator stored.
 * Event data is written as text only.
 *
 * @package Choice_UFT
 * @since 3.30.1
 */

(function() {
    'use strict';

    const EMPTY_VIEWER = 'No events captured yet. Generate test data and simulate events to begin.';

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    function cell(child) {
        const td = el('td');
        td.appendChild(child);
        return td;
    }

    function details(data) {
        const box = el('details');
        box.appendChild(el('summary', '', 'View'));
        box.appendChild(el('pre', '', JSON.stringify(data, null, 2)));
        return box;
    }

    function onClick(id, handler) {
        const node = document.getElementById(id);
        if (node) {
            node.addEventListener('click', handler);
        }
    }

    class CUFTEventViewer {
        constructor(monitor, validator) {
            this.monitor = monitor;
            this.validator = validator;
            this.list = document.getElementById('cuft-event-list');
            this.dbContainer = document.getElementById('cuft-db-events-container');
        }

        bind() {
            this.monitor.onEvent(() => this.render());

            const filter = document.getElementById('cuft-filter-test-only');
            if (filter) {
                this.monitor.setFilterMode(filter.checked);
                filter.addEventListener('change', () => {
                    this.monitor.setFilterMode(filter.checked);
                    this.render();
                });
            }

            onClick('cuft-clear-events', () => {
                this.monitor.clearEvents();
                this.render();
            });
            onClick('cuft-refresh-events', () => this.render());
            onClick('cuft-load-db-events', () => this.loadDbEvents());
            onClick('cuft-delete-session-events', () => this.deleteSessionEvents());
            onClick('cuft-delete-all-events', () => this.deleteAllEvents());

            this.render();
        }

        render() {
            if (!this.list) {
                return;
            }
            const events = this.monitor.getFilteredEvents();
            if (!events.length) {
                const empty = el('td', 'no-events', EMPTY_VIEWER);
                empty.setAttribute('colspan', '4');
                const row = el('tr');
                row.appendChild(empty);
                this.list.replaceChildren(row);
                return;
            }
            this.list.replaceChildren(...events.map(event => this.row(event)));
        }

        row(event) {
            const check = this.validator ? this.validator.validate(event) : { valid: true, errors: [] };
            const payload = Object.assign({}, event);
            delete payload._cuft_captured_at;
            delete payload._cuft_index;

            const row = el('tr');
            row.appendChild(el('td', '', new Date(event._cuft_captured_at).toLocaleTimeString()));
            row.appendChild(el('td', '', String(event.event || '(no event name)')));
            row.appendChild(cell(el('span', 'event-status ' + (check.valid ? 'valid' : 'invalid'),
                check.valid ? 'Valid' : check.errors.join('; '))));
            row.appendChild(cell(details(payload)));
            return row;
        }

        async post(fields) {
            const config = window.cuftConfig || {};
            const body = new FormData();
            body.append('nonce', config.nonce);
            Object.keys(fields).forEach(key => body.append(key, fields[key]));

            const response = await fetch(config.ajaxUrl, {
                method: 'POST',
                body: body,
                credentials: 'same-origin'
            });
            const result = await response.json();
            if (!result.success) {
                throw new Error((result.data && result.data.message) || 'Request failed');
            }
            return result.data;
        }

        async loadDbEvents() {
            try {
                const data = await this.post({ action: 'cuft_get_test_events', limit: 50 });
                this.renderDbEvents((data && data.events) || []);
            } catch (error) {
                this.dbMessage('Could not load test events: ' + error.message);
            }
        }

        renderDbEvents(events) {
            if (!this.dbContainer) {
                return;
            }
            if (!events.length) {
                this.dbMessage('No test events are stored.');
                return;
            }

            const head = el('tr');
            ['Time', 'Event', 'Session', 'Details'].forEach(label => head.appendChild(el('th', '', label)));
            const thead = el('thead');
            thead.appendChild(head);

            const tbody = el('tbody');
            events.forEach(event => {
                const row = el('tr');
                row.appendChild(el('td', '', event.created_at_formatted || event.created_at || ''));
                row.appendChild(el('td', '', event.event_type || ''));
                row.appendChild(el('td', '', event.session_id || ''));
                row.appendChild(cell(details(event.event_data)));
                tbody.appendChild(row);
            });

            const table = el('table', 'wp-list-table widefat fixed striped');
            table.appendChild(thead);
            table.appendChild(tbody);
            this.dbContainer.replaceChildren(table);
        }

        dbMessage(text) {
            if (this.dbContainer) {
                this.dbContainer.replaceChildren(el('p', 'db-events-message', text));
            }
        }

        async deleteSessionEvents() {
            const dashboard = window.CUFTTestingDashboard;
            const sessionId = dashboard && dashboard.sessionId;
            if (!sessionId) {
                this.dbMessage('Generate sample data first: session events belong to the current test session.');
                return;
            }
            await this.deleteEvents({ delete_type: 'by_session', session_id: sessionId });
        }

        async deleteAllEvents() {
            if (!window.confirm('Delete every stored test event?')) {
                return;
            }
            await this.deleteEvents({ delete_type: 'all' });
        }

        async deleteEvents(fields) {
            try {
                await this.post(Object.assign({ action: 'cuft_delete_test_events' }, fields));
            } catch (error) {
                this.dbMessage('Could not delete test events: ' + error.message);
                return;
            }
            await this.loadDbEvents();
        }
    }

    function start() {
        if (typeof window.CUFTDataLayerMonitor !== 'function' || !document.getElementById('cuft-event-list')) {
            return;
        }
        const validator = typeof window.CUFTEventValidator === 'function' ? new window.CUFTEventValidator() : null;
        window.CUFTEventViewer = new CUFTEventViewer(new window.CUFTDataLayerMonitor(), validator);
        window.CUFTEventViewer.bind();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
