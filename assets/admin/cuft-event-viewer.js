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
        let text;
        try {
            text = JSON.stringify(data, null, 2);
        } catch (error) {
            text = '(payload could not be shown: ' + error.message + ')';
        }
        const box = el('details');
        box.appendChild(el('summary', '', 'View'));
        box.appendChild(el('pre', '', text));
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
            // Keep the page's own (translated) empty-state text.
            this.emptyText = (this.list && this.list.textContent.trim()) || EMPTY_VIEWER;
            this.showingEmpty = true;
        }

        bind() {
            this.monitor.onEvent(event => this.add(event));

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

        /**
         * Put one new event on top. Rows already listed are left alone, so a
         * push that the filter hides (GTM's own gtm.click, for one) cannot
         * close a payload someone has open.
         */
        add(event) {
            if (!this.list || this.monitor.getFilteredEvents()[0] !== event) {
                return;
            }
            const row = this.row(event);
            if (this.showingEmpty) {
                this.list.replaceChildren(row);
                this.showingEmpty = false;
            } else {
                this.list.insertBefore(row, this.list.firstChild);
            }
            const max = this.monitor.maxEvents || 100;
            while (this.list.children.length > max) {
                this.list.removeChild(this.list.lastChild);
            }
        }

        render() {
            if (!this.list) {
                return;
            }
            const events = this.monitor.getFilteredEvents();
            this.showingEmpty = !events.length;
            if (this.showingEmpty) {
                const empty = el('td', 'no-events', this.emptyText);
                empty.setAttribute('colspan', '4');
                const row = el('tr');
                row.appendChild(empty);
                this.list.replaceChildren(row);
                return;
            }
            this.list.replaceChildren(...events.map(event => this.row(event)));
        }

        row(event) {
            const check = this.validator ? this.validator.validate(event) : null;
            const payload = Object.assign({}, event);
            delete payload._cuft_captured_at;
            delete payload._cuft_index;

            const row = el('tr');
            row.appendChild(el('td', '', new Date(event._cuft_captured_at).toLocaleTimeString()));
            row.appendChild(el('td', '', String(event.event || '(no event name)')));
            if (!check) {
                row.appendChild(cell(el('span', 'event-status', 'Not checked')));
            } else {
                row.appendChild(cell(el('span', 'event-status ' + (check.valid ? 'valid' : 'invalid'),
                    check.valid ? 'Valid' : check.errors.join('; '))));
            }
            row.appendChild(cell(details(payload)));
            return row;
        }

        async post(fields) {
            const config = window.cuftConfig || {};
            if (!config.ajaxUrl || !config.nonce) {
                throw new Error('the dashboard settings are missing; reload the page');
            }
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

        /**
         * The session generated on this page, or after a reload the newest one
         * the dashboard saved in this browser.
         */
        currentSessionId() {
            const dashboard = window.CUFTTestingDashboard;
            if (dashboard && dashboard.sessionId) {
                return dashboard.sessionId;
            }
            try {
                const saved = JSON.parse(window.localStorage.getItem('cuft_test_sessions') || '[]');
                return (saved[0] && saved[0].session_id) || null;
            } catch (error) {
                return null;
            }
        }

        async deleteSessionEvents() {
            const sessionId = this.currentSessionId();
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
