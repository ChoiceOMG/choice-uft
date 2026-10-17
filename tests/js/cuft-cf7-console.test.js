// Tests for the Contact Form 7 script's console output (3.30.0).
// Run: node --test 'tests/js/*.test.js'
//
// A failed, invalid or spam-flagged submission used to print a console
// warning to every visitor. The warnings now follow the Console Logging
// setting; a mail failure is still logged on the server.

"use strict";

const test = require("node:test");
const assert = require("node:assert");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

function source(file) {
  return fs.readFileSync(path.join(__dirname, "..", "..", "assets", file), "utf8");
}

function load(consoleLogging) {
  const listeners = {};
  const warnings = [];
  const window = {
    dataLayer: [],
    cuftCF7: { console_logging: consoleLogging },
  };
  const document = {
    cookie: "",
    readyState: "complete",
    querySelectorAll: () => [],
    addEventListener: (type, fn) => {
      (listeners[type] = listeners[type] || []).push(fn);
    },
  };
  window.document = document;
  const fakeConsole = {
    log: () => {},
    warn: (...args) => warnings.push(args.join(" ")),
    error: () => {},
  };
  window.console = fakeConsole;
  const sandbox = { window, document, console: fakeConsole, setTimeout: (fn) => fn() };
  vm.runInNewContext(source("cuft-dataLayer-utils.js"), sandbox);
  vm.runInNewContext(source("forms/cuft-cf7-forms.js"), sandbox);
  const fire = (type) => (listeners[type] || []).forEach((fn) => fn({ target: {}, detail: {} }));
  return { fire, warnings, listeners };
}

for (const type of ["wpcf7mailfailed", "wpcf7invalid", "wpcf7spam"]) {
  test(`${type} prints nothing to visitors with console logging off`, () => {
    const { fire, warnings, listeners } = load(false);
    assert.ok(listeners[type], `${type} listener registered`);
    fire(type);
    assert.deepStrictEqual(warnings, []);
  });

  test(`${type} still warns with console logging on`, () => {
    const { fire, warnings } = load(true);
    fire(type);
    assert.strictEqual(warnings.length, 1);
    assert.match(warnings[0], /will NOT fire/);
  });
}
