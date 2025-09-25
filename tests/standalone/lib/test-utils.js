/**
 * Test Utilities for CUFT Standalone Testing
 * Provides assertion framework and test helpers
 */
window.CUFTTestUtils = (function() {
  'use strict';

  // Test results storage
  let testResults = [];
  let currentTestSuite = null;

  /**
   * Simple assertion framework
   */
  function assert(condition, message) {
    if (!condition) {
      throw new Error('Assertion failed: ' + (message || 'Condition is false'));
    }
    return true;
  }

  function assertEqual(actual, expected, message) {
    if (actual !== expected) {
      throw new Error(
        (message || 'Values not equal') +
        '\nExpected: ' + JSON.stringify(expected) +
        '\nActual: ' + JSON.stringify(actual)
      );
    }
    return true;
  }

  function assertDeepEqual(actual, expected, message) {
    if (JSON.stringify(actual) !== JSON.stringify(expected)) {
      throw new Error(
        (message || 'Objects not equal') +
        '\nExpected: ' + JSON.stringify(expected, null, 2) +
        '\nActual: ' + JSON.stringify(actual, null, 2)
      );
    }
    return true;
  }

  function assertContains(obj, key, message) {
    if (!(key in obj)) {
      throw new Error(
        (message || 'Key not found') +
        '\nKey: ' + key +
        '\nObject: ' + JSON.stringify(obj)
      );
    }
    return true;
  }

  function assertNotContains(obj, key, message) {
    if (key in obj) {
      throw new Error(
        (message || 'Key should not exist') +
        '\nKey: ' + key +
        '\nObject: ' + JSON.stringify(obj)
      );
    }
    return true;
  }

  function assertLessThan(actual, expected, message) {
    if (actual >= expected) {
      throw new Error(
        (message || 'Value not less than expected') +
        '\nExpected less than: ' + expected +
        '\nActual: ' + actual
      );
    }
    return true;
  }

  /**
   * Test suite management
   */
  function describe(suiteName, callback) {
    currentTestSuite = {
      name: suiteName,
      tests: [],
      passed: 0,
      failed: 0,
      startTime: performance.now()
    };

    try {
      callback();
    } catch (e) {
      console.error('Test suite setup failed:', e);
    }

    currentTestSuite.endTime = performance.now();
    currentTestSuite.duration = currentTestSuite.endTime - currentTestSuite.startTime;
    testResults.push(currentTestSuite);

    return currentTestSuite;
  }

  function test(testName, callback) {
    if (!currentTestSuite) {
      throw new Error('test() must be called within describe()');
    }

    const testCase = {
      name: testName,
      startTime: performance.now(),
      passed: false,
      error: null
    };

    try {
      callback();
      testCase.passed = true;
      currentTestSuite.passed++;
    } catch (error) {
      testCase.passed = false;
      testCase.error = error.message || error;
      currentTestSuite.failed++;
    }

    testCase.endTime = performance.now();
    testCase.duration = testCase.endTime - testCase.startTime;
    currentTestSuite.tests.push(testCase);

    return testCase;
  }

  /**
   * DOM Helpers
   */
  function createTestContainer() {
    const container = document.createElement('div');
    container.id = 'test-container-' + Date.now();
    container.style.display = 'none';
    document.body.appendChild(container);
    return container;
  }

  function cleanupTestContainer(container) {
    if (container && container.parentNode) {
      container.parentNode.removeChild(container);
    }
  }

  function createFormFromTemplate(html) {
    const container = createTestContainer();
    container.innerHTML = html;
    return container.querySelector('form');
  }

  /**
   * Event Simulation
   */
  function simulateEvent(element, eventType, detail) {
    const event = new CustomEvent(eventType, {
      bubbles: true,
      cancelable: true,
      detail: detail || {}
    });
    element.dispatchEvent(event);
    return event;
  }

  function simulateFormSubmit(form, preventDefault = true) {
    const submitEvent = new Event('submit', {
      bubbles: true,
      cancelable: true
    });

    if (preventDefault) {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
      }, { once: true });
    }

    form.dispatchEvent(submitEvent);
    return submitEvent;
  }

  /**
   * DataLayer Helpers
   */
  function setupMockDataLayer() {
    const events = [];
    const originalPush = window.dataLayer ? window.dataLayer.push : null;

    window.dataLayer = [];
    window.dataLayer.push = function(event) {
      events.push(event);
      Array.prototype.push.call(window.dataLayer, event);
    };

    return {
      events: events,
      restore: function() {
        if (originalPush) {
          window.dataLayer.push = originalPush;
        }
      },
      getLastEvent: function() {
        return events[events.length - 1];
      },
      findEvent: function(eventName) {
        return events.find(e => e.event === eventName);
      },
      clear: function() {
        events.length = 0;
        window.dataLayer.length = 0;
      }
    };
  }

  /**
   * URL Parameter Helpers
   */
  function setURLParams(params) {
    const url = new URL(window.location.href);
    Object.keys(params).forEach(key => {
      url.searchParams.set(key, params[key]);
    });

    // Mock location.search for testing
    Object.defineProperty(window.location, 'search', {
      writable: true,
      value: url.search
    });
  }

  function clearURLParams() {
    Object.defineProperty(window.location, 'search', {
      writable: true,
      value: ''
    });
  }

  /**
   * Storage Helpers
   */
  function setSessionStorage(key, value) {
    try {
      sessionStorage.setItem(key, typeof value === 'object' ? JSON.stringify(value) : value);
    } catch (e) {
      console.warn('SessionStorage not available:', e);
    }
  }

  function setCookie(name, value, days = 30) {
    const expires = new Date();
    expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
    document.cookie = name + '=' + encodeURIComponent(value) +
                     '; expires=' + expires.toUTCString() +
                     '; path=/; SameSite=Lax';
  }

  function clearStorage() {
    try {
      sessionStorage.clear();
      localStorage.clear();
    } catch (e) {
      // Storage might not be available
    }

    // Clear cookies
    document.cookie.split(";").forEach(function(c) {
      document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
    });
  }

  /**
   * Performance Testing
   */
  function measurePerformance(callback, iterations = 100) {
    const measurements = [];

    for (let i = 0; i < iterations; i++) {
      const startTime = performance.now();
      callback();
      const endTime = performance.now();
      measurements.push(endTime - startTime);
    }

    return {
      min: Math.min(...measurements),
      max: Math.max(...measurements),
      average: measurements.reduce((a, b) => a + b, 0) / measurements.length,
      median: measurements.sort((a, b) => a - b)[Math.floor(measurements.length / 2)],
      measurements: measurements
    };
  }

  /**
   * Test Result Reporting
   */
  function generateReport(format = 'html') {
    if (format === 'html') {
      return generateHTMLReport();
    } else if (format === 'json') {
      return JSON.stringify(testResults, null, 2);
    } else if (format === 'console') {
      return generateConsoleReport();
    }
  }

  function generateHTMLReport() {
    let html = '<div class="test-report">';
    html += '<h2>Test Results</h2>';

    let totalPassed = 0;
    let totalFailed = 0;
    let totalDuration = 0;

    testResults.forEach(suite => {
      totalPassed += suite.passed;
      totalFailed += suite.failed;
      totalDuration += suite.duration;

      html += '<div class="test-suite">';
      html += '<h3>' + suite.name + '</h3>';
      html += '<p>Passed: ' + suite.passed + ' | Failed: ' + suite.failed +
              ' | Duration: ' + suite.duration.toFixed(2) + 'ms</p>';

      if (suite.tests && suite.tests.length > 0) {
        html += '<ul class="test-cases">';
        suite.tests.forEach(testCase => {
          const statusClass = testCase.passed ? 'passed' : 'failed';
          const statusSymbol = testCase.passed ? '✓' : '✗';
          html += '<li class="' + statusClass + '">';
          html += statusSymbol + ' ' + testCase.name + ' (' + testCase.duration.toFixed(2) + 'ms)';
          if (testCase.error) {
            html += '<pre class="error">' + testCase.error + '</pre>';
          }
          html += '</li>';
        });
        html += '</ul>';
      }
      html += '</div>';
    });

    html += '<div class="test-summary">';
    html += '<h3>Summary</h3>';
    html += '<p>Total Tests: ' + (totalPassed + totalFailed) + '</p>';
    html += '<p>Passed: ' + totalPassed + '</p>';
    html += '<p>Failed: ' + totalFailed + '</p>';
    html += '<p>Total Duration: ' + totalDuration.toFixed(2) + 'ms</p>';
    html += '<p>Pass Rate: ' + ((totalPassed / (totalPassed + totalFailed)) * 100).toFixed(2) + '%</p>';
    html += '</div>';
    html += '</div>';

    return html;
  }

  function generateConsoleReport() {
    console.log('=== Test Results ===');

    let totalPassed = 0;
    let totalFailed = 0;

    testResults.forEach(suite => {
      totalPassed += suite.passed;
      totalFailed += suite.failed;

      console.log('\n' + suite.name);
      console.log('  Passed: ' + suite.passed + ' | Failed: ' + suite.failed);

      if (suite.tests) {
        suite.tests.forEach(testCase => {
          const status = testCase.passed ? '✓' : '✗';
          console.log('  ' + status + ' ' + testCase.name);
          if (testCase.error) {
            console.error('    Error:', testCase.error);
          }
        });
      }
    });

    console.log('\n=== Summary ===');
    console.log('Total: ' + (totalPassed + totalFailed));
    console.log('Passed: ' + totalPassed);
    console.log('Failed: ' + totalFailed);

    return testResults;
  }

  /**
   * Wait for condition helper
   */
  function waitFor(condition, timeout = 5000, interval = 100) {
    return new Promise((resolve, reject) => {
      const startTime = Date.now();

      const check = () => {
        if (condition()) {
          resolve();
        } else if (Date.now() - startTime > timeout) {
          reject(new Error('Timeout waiting for condition'));
        } else {
          setTimeout(check, interval);
        }
      };

      check();
    });
  }

  // Public API
  return {
    // Assertions
    assert: assert,
    assertEqual: assertEqual,
    assertDeepEqual: assertDeepEqual,
    assertContains: assertContains,
    assertNotContains: assertNotContains,
    assertLessThan: assertLessThan,

    // Test Suite
    describe: describe,
    test: test,

    // DOM Helpers
    createTestContainer: createTestContainer,
    cleanupTestContainer: cleanupTestContainer,
    createFormFromTemplate: createFormFromTemplate,

    // Event Simulation
    simulateEvent: simulateEvent,
    simulateFormSubmit: simulateFormSubmit,

    // DataLayer
    setupMockDataLayer: setupMockDataLayer,

    // URL Parameters
    setURLParams: setURLParams,
    clearURLParams: clearURLParams,

    // Storage
    setSessionStorage: setSessionStorage,
    setCookie: setCookie,
    clearStorage: clearStorage,

    // Performance
    measurePerformance: measurePerformance,

    // Reporting
    generateReport: generateReport,
    getTestResults: function() { return testResults; },
    clearTestResults: function() { testResults = []; },

    // Async
    waitFor: waitFor
  };
})();