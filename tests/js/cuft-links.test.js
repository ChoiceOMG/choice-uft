// Tests for assets/cuft-links.js link exclusion (3.30.0).
// Run: node --test 'tests/js/*.test.js'
// No dependencies: the script runs in a vm context against a minimal DOM stub.

"use strict";

const test = require("node:test");
const assert = require("node:assert");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

const SOURCE = fs.readFileSync(
  path.join(__dirname, "..", "..", "assets", "cuft-links.js"),
  "utf8"
);

// Element stub. closest() understands the selectors the script uses:
// "a[href]" and "[data-cuft-ignore], .cuft-ignore".
function el(tag, attrs, parent) {
  attrs = attrs || {};
  const node = {
    tagName: tag.toUpperCase(),
    parent: parent || null,
    getAttribute: (name) => (name in attrs ? attrs[name] : null),
    matches(selector) {
      return selector.split(",").some((s) => {
        s = s.trim();
        if (s === "a[href]") return node.tagName === "A" && "href" in attrs;
        if (s === "[data-cuft-ignore]") return "data-cuft-ignore" in attrs;
        if (s === ".cuft-ignore")
          return (attrs.class || "").split(/\s+/).indexOf("cuft-ignore") !== -1;
        throw new Error("selector not stubbed: " + s);
      });
    },
    closest(selector) {
      for (let n = node; n; n = n.parent) if (n.matches(selector)) return n;
      return null;
    },
  };
  return node;
}

function load(config) {
  let handler = null;
  const requests = [];
  const window = {
    dataLayer: [],
    location: { href: "https://clinic.example/", search: "?gclid=test-gclid" },
    navigator: { language: "en-CA" },
  };
  Object.assign(window, config || {});
  const sandbox = {
    window,
    document: {
      referrer: "",
      title: "Test",
      addEventListener: (type, fn) => {
        if (type === "click") handler = fn;
      },
    },
    navigator: window.navigator,
    screen: { width: 412, height: 915 },
    sessionStorage: { getItem: () => null, setItem: () => {} },
    URLSearchParams,
    fetch: (url, opts) => {
      requests.push({ url, body: String(opts.body) });
      return Promise.resolve({ ok: true });
    },
    console,
  };
  vm.createContext(sandbox);
  vm.runInContext(SOURCE, sandbox);
  assert.ok(handler, "click handler registered");
  return {
    click: (target) => handler({ target }),
    events: () => window.dataLayer.map((e) => e.event),
    requests,
  };
}

const CONFIG = {
  cuftConfig: { ajaxUrl: "/wp-admin/admin-ajax.php", nonce: "n", ignorePhones: ["7805550173"] },
};

test("an ordinary tel: link is tracked", () => {
  const page = load(CONFIG);
  page.click(el("a", { href: "tel:+1-403-555-0199" }));
  assert.deepStrictEqual(page.events(), ["phone_click"]);
  assert.strictEqual(page.requests.length, 1);
  assert.match(page.requests[0].body, /event_type=phone_click/);
});

test("an ignored number is neither pushed nor recorded, in any format", () => {
  const page = load(CONFIG);
  [
    "tel:+17805550173",
    "tel:780-555-0173",
    "TEL:(780) 555-0173",
    "tel:%2B1%20780%20555%200173",
    "tel:+1-780-555-0173;ext=2",
  ].forEach((href) => page.click(el("a", { href })));
  assert.deepStrictEqual(page.events(), []);
  assert.strictEqual(page.requests.length, 0);
});

test("a click on a child of an ignored link is ignored", () => {
  const page = load(CONFIG);
  const link = el("a", { href: "tel:7805550173" });
  page.click(el("span", {}, link));
  assert.deepStrictEqual(page.events(), []);
  assert.strictEqual(page.requests.length, 0);
});

test("data-cuft-ignore on the link or an ancestor excludes it", () => {
  const page = load(CONFIG);
  page.click(el("a", { href: "tel:4035550199", "data-cuft-ignore": "" }));
  const wrapper = el("div", { "data-cuft-ignore": "" });
  page.click(el("a", { href: "mailto:office@clinic.example" }, wrapper));
  assert.deepStrictEqual(page.events(), []);
  assert.strictEqual(page.requests.length, 0);
});

test("the cuft-ignore class excludes a link", () => {
  const page = load(CONFIG);
  const wrapper = el("div", { class: "elementor-widget cuft-ignore" });
  page.click(el("a", { href: "tel:4035550199" }, wrapper));
  page.click(el("a", { href: "mailto:office@clinic.example", class: "cuft-ignore" }));
  assert.deepStrictEqual(page.events(), []);
});

test("the list printed with the script works when cuftConfig is absent", () => {
  const page = load({ cuftIgnorePhones: ["7805550173"] });
  page.click(el("a", { href: "tel:780.555.0173" }));
  page.click(el("a", { href: "tel:403.555.0199" }));
  assert.deepStrictEqual(page.events(), ["phone_click"]);
});

test("numbers sharing only their last digits are not confused", () => {
  const page = load(CONFIG);
  page.click(el("a", { href: "tel:780-555-0170" }));
  page.click(el("a", { href: "tel:0173" }));
  assert.deepStrictEqual(page.events(), ["phone_click", "phone_click"]);
});

test("mailto links are still tracked unless marked", () => {
  const page = load(CONFIG);
  page.click(el("a", { href: "mailto:office@clinic.example" }));
  assert.deepStrictEqual(page.events(), ["email_click"]);
});

test("a malformed ignore list does not break tracking", () => {
  const page = load({ cuftIgnorePhones: "7805550173" });
  page.click(el("a", { href: "tel:7805550173" }));
  assert.deepStrictEqual(page.events(), ["phone_click"]);
});
