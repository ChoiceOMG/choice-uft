// Tests for assets/admin/cuft-event-viewer.js, the Testing Dashboard's Event
// Viewer and Test Database Events panels (3.30.1). Before it, nothing built the
// dataLayer monitor or bound a button, so both panels stayed empty.
// Run: node --test 'tests/js/*.test.js'
// No dependencies: the scripts run in a vm context against a minimal DOM stub.

"use strict";

const test = require("node:test");
const assert = require("node:assert");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

const source = (file) =>
  fs.readFileSync(path.join(__dirname, "..", "..", "assets", "admin", file), "utf8");

// Element stub. innerHTML throws: event data must reach the page as text.
function element(tag) {
  const node = {
    tagName: tag.toUpperCase(),
    children: [],
    attributes: {},
    listeners: {},
    className: "",
    checked: false,
    own: "",
    open: false,
    appendChild(child) {
      node.children.push(child);
      return child;
    },
    insertBefore(child, ref) {
      const at = node.children.indexOf(ref);
      node.children.splice(at === -1 ? node.children.length : at, 0, child);
      return child;
    },
    removeChild(child) {
      node.children = node.children.filter((c) => c !== child);
      return child;
    },
    replaceChildren(...kids) {
      node.children = [];
      node.own = "";
      kids.forEach((k) => node.appendChild(k));
    },
    setAttribute(name, value) {
      node.attributes[name] = String(value);
    },
    addEventListener(type, fn) {
      (node.listeners[type] = node.listeners[type] || []).push(fn);
    },
    fire(type) {
      (node.listeners[type] || []).forEach((fn) => fn({ target: node }));
    },
    find(tagName) {
      const out = [];
      (function walk(n) {
        n.children.forEach((c) => {
          if (c.tagName === tagName.toUpperCase()) out.push(c);
          walk(c);
        });
      })(node);
      return out;
    },
  };
  Object.defineProperty(node, "firstChild", { get: () => node.children[0] || null });
  Object.defineProperty(node, "lastChild", { get: () => node.children[node.children.length - 1] || null });
  Object.defineProperty(node, "textContent", {
    get: () => node.own + node.children.map((c) => c.textContent).join(""),
    set: (v) => {
      node.own = String(v);
      node.children = [];
    },
  });
  Object.defineProperty(node, "innerHTML", {
    get: () => {
      throw new Error("innerHTML read");
    },
    set: () => {
      throw new Error("innerHTML written");
    },
  });
  return node;
}

function load({
  confirmAnswer = true,
  sessionId = null,
  responses = [],
  validator = true,
  config = { ajaxUrl: "/wp-admin/admin-ajax.php", nonce: "n0nce" },
  stored = {},
  placeholder = "No events captured yet. Generate test data and simulate events to begin.",
} = {}) {
  const ids = {};
  [
    "cuft-event-list",
    "cuft-filter-test-only",
    "cuft-clear-events",
    "cuft-refresh-events",
    "cuft-load-db-events",
    "cuft-delete-session-events",
    "cuft-delete-all-events",
    "cuft-db-events-container",
  ].forEach((id) => (ids[id] = element(id === "cuft-event-list" ? "tbody" : "div")));
  ids["cuft-filter-test-only"].checked = true;
  // The page arrives with PHP's translated placeholder row.
  const placeholderRow = element("tr");
  const placeholderCell = element("td");
  placeholderCell.textContent = placeholder;
  placeholderRow.appendChild(placeholderCell);
  ids["cuft-event-list"].appendChild(placeholderRow);

  const requests = [];
  class FormData {
    constructor() {
      this.fields = {};
    }
    append(k, v) {
      this.fields[k] = String(v);
    }
  }
  const window = {
    dataLayer: [],
    cuftConfig: config,
    CUFTTestingDashboard: { sessionId },
    confirm: () => confirmAnswer,
    localStorage: { getItem: (k) => (k in stored ? stored[k] : null) },
  };
  const document = {
    readyState: "complete",
    getElementById: (id) => ids[id] || null,
    createElement: element,
    addEventListener: () => {},
  };
  const fetch = (url, opts) => {
    requests.push(opts.body.fields);
    const body = responses.length ? responses.shift() : { success: true, data: { events: [] } };
    return Promise.resolve({ json: () => Promise.resolve(body) });
  };
  const sandbox = {
    window,
    document,
    FormData,
    fetch,
    performance: { now: () => 0 },
    console: { log() {}, warn() {}, error() {} },
  };
  vm.createContext(sandbox);
  // Each script publishes its class on window, as in the browser.
  const files = ["cuft-datalayer-monitor.js", "cuft-event-validator.js", "cuft-event-viewer.js"];
  for (const file of validator ? files : files.filter((f) => f !== "cuft-event-validator.js")) {
    vm.runInContext(source(file), sandbox);
  }
  return { window, ids, requests };
}

const settle = () => new Promise((r) => setImmediate(r));

const TEST_SUBMIT = {
  event: "form_submit",
  cuft_tracked: true,
  cuft_source: "contact_form_7",
  form_type: "contact_form_7",
  form_id: "cf7-12",
  test_mode: true,
};

test("a simulated event pushed to the dataLayer appears in the Event Viewer", () => {
  const { window, ids } = load();
  window.dataLayer.push(Object.assign({}, TEST_SUBMIT));

  const rows = ids["cuft-event-list"].find("tr");
  assert.strictEqual(rows.length, 1);
  const text = rows[0].textContent;
  assert.match(text, /form_submit/);
  assert.match(text, /Valid/);
  assert.match(text, /"form_id": "cf7-12"/);
});

test("non-test events stay hidden until the filter is unticked", () => {
  const { window, ids } = load();
  window.dataLayer.push({ event: "gtm.js" });
  assert.match(ids["cuft-event-list"].textContent, /No events captured yet/);

  ids["cuft-filter-test-only"].checked = false;
  ids["cuft-filter-test-only"].fire("change");
  assert.match(ids["cuft-event-list"].textContent, /gtm\.js/);
});

test("an event missing required fields is marked invalid with the reason", () => {
  const { window, ids } = load();
  window.dataLayer.push({ event: "form_submit", cuft_tracked: true, test_mode: true });
  assert.match(ids["cuft-event-list"].textContent, /Missing required field: cuft_source/);
});

test("phone and email clicks as the front end sends them are valid", () => {
  // assets/cuft-links.js pushes clicked_phone and clicked_email; the validator
  // asked for phone_number and email_address, which nothing sends.
  const { window, ids } = load();
  const base = { cuft_tracked: true, cuft_source: "link_tracking", test_mode: true };
  window.dataLayer.push(Object.assign({ event: "phone_click", clicked_phone: "+17805550173", href: "tel:+17805550173" }, base));
  window.dataLayer.push(Object.assign({ event: "email_click", clicked_email: "jane@example.com", href: "mailto:jane@example.com" }, base));

  const text = ids["cuft-event-list"].textContent;
  assert.doesNotMatch(text, /Missing required field/);
  assert.strictEqual((text.match(/Valid/g) || []).length, 2);
});

test("Clear Events empties the viewer", () => {
  const { window, ids } = load();
  window.dataLayer.push(Object.assign({}, TEST_SUBMIT));
  ids["cuft-clear-events"].fire("click");
  assert.match(ids["cuft-event-list"].textContent, /No events captured yet/);
  assert.strictEqual(ids["cuft-event-list"].find("details").length, 0);
});

test("markup in event data is shown as text", () => {
  const { window, ids } = load();
  // The stub's innerHTML throws, so reaching the assertion proves text-only rendering.
  window.dataLayer.push({ event: '<img src=x onerror="alert(1)">', test_mode: true });
  assert.match(ids["cuft-event-list"].textContent, /<img src=x/);
});

test("Load Events lists the stored test events", async () => {
  const { ids, requests } = load({
    responses: [
      {
        success: true,
        data: {
          events: [
            { event_type: "form_submit", session_id: "s-1", created_at_formatted: "October 17, 2026 3:20 pm", event_data: { form_id: "cf7-12" } },
            { event_type: "phone_click", session_id: "s-1", created_at_formatted: "October 17, 2026 3:21 pm", event_data: {} },
          ],
        },
      },
    ],
  });
  ids["cuft-load-db-events"].fire("click");
  await settle();

  assert.strictEqual(requests[0].action, "cuft_get_test_events");
  assert.strictEqual(requests[0].nonce, "n0nce");
  const rows = ids["cuft-db-events-container"].find("tbody")[0].find("tr");
  assert.strictEqual(rows.length, 2);
  assert.match(rows[1].textContent, /phone_click/);
});

test("Delete All Test Events asks first, deletes, then reloads the list", async () => {
  const { ids, requests } = load({ confirmAnswer: true });
  ids["cuft-delete-all-events"].fire("click");
  await settle();
  await settle();

  assert.strictEqual(requests[0].action, "cuft_delete_test_events");
  assert.strictEqual(requests[0].delete_type, "all");
  assert.strictEqual(requests[1].action, "cuft_get_test_events");
});

test("Delete All Test Events does nothing when the confirmation is declined", async () => {
  const { ids, requests } = load({ confirmAnswer: false });
  ids["cuft-delete-all-events"].fire("click");
  await settle();
  assert.strictEqual(requests.length, 0);
});

test("Delete Session Events deletes the current session's events", async () => {
  const { ids, requests } = load({ sessionId: "s-42" });
  ids["cuft-delete-session-events"].fire("click");
  await settle();

  assert.strictEqual(requests[0].delete_type, "by_session");
  assert.strictEqual(requests[0].session_id, "s-42");
});

test("Delete Session Events without a session explains and sends nothing", async () => {
  const { ids, requests } = load({ sessionId: null });
  ids["cuft-delete-session-events"].fire("click");
  await settle();

  assert.strictEqual(requests.length, 0);
  assert.match(ids["cuft-db-events-container"].textContent, /Generate sample data first/);
});

test("a hidden push leaves the list alone, so an open View stays open", () => {
  // With a GTM click trigger, opening View pushes gtm.click; rebuilding the
  // table on that push replaced the row before it could open.
  const { window, ids } = load();
  window.dataLayer.push(Object.assign({}, TEST_SUBMIT));
  const row = ids["cuft-event-list"].find("tr")[0];
  row.find("details")[0].open = true;

  window.dataLayer.push({ event: "gtm.click" });
  assert.strictEqual(ids["cuft-event-list"].find("tr")[0], row);
  assert.strictEqual(row.find("details")[0].open, true);

  window.dataLayer.push(Object.assign({}, TEST_SUBMIT, { form_id: "cf7-13" }));
  const rows = ids["cuft-event-list"].find("tr");
  assert.strictEqual(rows.length, 2);
  assert.strictEqual(rows[1], row, "the new event goes on top; the open row is kept");
  assert.strictEqual(row.find("details")[0].open, true);
});

test("without the validator, events read Not checked rather than Valid", () => {
  const { window, ids } = load({ validator: false });
  window.dataLayer.push(Object.assign({}, TEST_SUBMIT));
  assert.match(ids["cuft-event-list"].textContent, /Not checked/);
  assert.doesNotMatch(ids["cuft-event-list"].textContent, /Valid/);
});

test("a payload that cannot be serialised still lists the event", () => {
  const { window, ids } = load();
  const circular = Object.assign({}, TEST_SUBMIT);
  circular.self = circular;
  window.dataLayer.push(circular);
  window.dataLayer.push(Object.assign({}, TEST_SUBMIT, { form_id: "cf7-14" }));
  assert.strictEqual(ids["cuft-event-list"].find("tr").length, 2);
  assert.match(ids["cuft-event-list"].textContent, /could not be shown/);
});

test("the translated placeholder from the page is kept for the empty list", () => {
  const { window, ids } = load({ placeholder: "Aucun evenement pour le moment." });
  window.dataLayer.push(Object.assign({}, TEST_SUBMIT));
  ids["cuft-clear-events"].fire("click");
  assert.match(ids["cuft-event-list"].textContent, /Aucun evenement pour le moment\./);
});

test("without cuftConfig, Load Events explains instead of posting", async () => {
  const { ids, requests } = load({ config: null });
  ids["cuft-load-db-events"].fire("click");
  await settle();
  assert.strictEqual(requests.length, 0);
  assert.match(ids["cuft-db-events-container"].textContent, /settings are missing/);
});

test("after a reload, Delete Session Events uses the last session saved in the browser", async () => {
  const { ids, requests } = load({
    sessionId: null,
    stored: { cuft_test_sessions: JSON.stringify([{ session_id: "s-saved" }, { session_id: "s-older" }]) },
  });
  ids["cuft-delete-session-events"].fire("click");
  await settle();
  assert.strictEqual(requests[0].delete_type, "by_session");
  assert.strictEqual(requests[0].session_id, "s-saved");
});
