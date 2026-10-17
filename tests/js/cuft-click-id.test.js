// Tests for where the browser scripts take the visitor's click ID (3.30.0).
// Run: node --test 'tests/js/*.test.js'
//
// The click ID comes only from the visitor's own cuft_click_id cookie. Pages
// rendered before 3.30.0 printed it into window.cuftClickData, and a full-page
// cache can still serve one of those pages with another visitor's click ID in
// it, so that value must be ignored.

"use strict";

const test = require("node:test");
const assert = require("node:assert");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

function source(file) {
  return fs.readFileSync(path.join(__dirname, "..", "..", "assets", file), "utf8");
}

// A cached pre-3.30.0 page: someone else's click data printed into the HTML.
const STALE_CLICK_DATA = {
  click_id: "SOMEONE-ELSES-GCLID",
  ip_hash: "a".repeat(64),
  platform: "google",
  ajax_url: "https://clinic.example/wp-admin/admin-ajax.php",
  nonce: "abc",
};

function form() {
  const children = [];
  return {
    children,
    className: "contact",
    querySelector: (sel) =>
      sel === 'input[name="cuft_click_id"]'
        ? children.find((c) => c.name === "cuft_click_id") || null
        : null,
    appendChild: (child) => children.push(child),
  };
}

function sandboxFor(cookie, clickData, forms) {
  const window = { dataLayer: [] };
  if (clickData) window.cuftClickData = clickData;
  const document = {
    cookie: cookie || "",
    readyState: "complete",
    body: {},
    querySelectorAll: (sel) => (sel === "form" ? forms || [] : []),
    createElement: () => ({}),
    addEventListener: () => {},
  };
  window.document = document;
  return {
    window,
    document,
    setTimeout: (fn) => fn(),
    console,
  };
}

function runClickIntegration(cookie, clickData) {
  const forms = [form()];
  const sandbox = sandboxFor(cookie, clickData, forms);
  vm.runInNewContext(source("cuft-click-integration.js"), sandbox);
  return { dataLayer: sandbox.window.dataLayer, form: forms[0] };
}

function formSubmitPayload(cookie, clickData) {
  const sandbox = sandboxFor(cookie, clickData, []);
  vm.runInNewContext(source("cuft-dataLayer-utils.js"), sandbox);
  return sandbox.window.cuftDataLayerUtils.createFormSubmitPayload("cf7", "form-1", {
    user_email: "visitor@example.com",
  });
}

test("click integration uses the visitor's own click ID cookie", () => {
  const { dataLayer, form: f } = runClickIntegration("cuft_click_id=GCLID-OWN", {
    ajax_url: STALE_CLICK_DATA.ajax_url,
    nonce: "abc",
  });
  const detected = dataLayer.find((e) => e.event === "click_id_detected");
  assert.ok(detected, "click_id_detected was pushed");
  assert.strictEqual(detected.click_id, "GCLID-OWN");
  assert.strictEqual(f.children.length, 1);
  assert.strictEqual(f.children[0].value, "GCLID-OWN");
});

test("click integration ignores a click ID printed into a cached page", () => {
  const { dataLayer, form: f } = runClickIntegration("", STALE_CLICK_DATA);
  assert.deepStrictEqual(dataLayer, []);
  assert.strictEqual(f.children.length, 0);
});

test("the cookie wins over a click ID printed into a cached page", () => {
  const { dataLayer, form: f } = runClickIntegration(
    "_ga=GA1.1.123.456; cuft_click_id=GCLID-OWN",
    STALE_CLICK_DATA
  );
  assert.strictEqual(dataLayer[0].click_id, "GCLID-OWN");
  assert.strictEqual(f.children[0].value, "GCLID-OWN");
});

test("form_submit takes no click data from a cached page", () => {
  const payload = formSubmitPayload("", STALE_CLICK_DATA);
  assert.strictEqual(payload.click_id, undefined);
  assert.strictEqual(payload.ip_hash, undefined);
  assert.strictEqual(payload.click_platform, undefined);
});

test("form_submit falls back to the visitor's click ID cookie", () => {
  const payload = formSubmitPayload("cuft_click_id=GCLID-OWN", STALE_CLICK_DATA);
  assert.strictEqual(payload.click_id, "GCLID-OWN");
  assert.strictEqual(payload.ip_hash, undefined);
});
