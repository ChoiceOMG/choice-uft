/**
 * Choice Universal Form Tracker - Performance Monitoring System
 * Version: 4.0.0-phase2
 * Date: 2025-09-25
 *
 * Performance monitoring and optimization utilities for form tracking operations.
 * Tracks timing, memory usage, and provides optimization recommendations.
 */
(function() {
  'use strict';

  var performanceMonitor = {
    enabled: false,
    samples: [],
    maxSamples: 100,
    thresholds: {
      formProcessing: 50,    // Target: <50ms total processing
      formDetection: 3,      // Target: <3ms form detection
      fieldExtraction: 10,   // Target: <10ms field extraction
      eventProcessing: 15,   // Target: <15ms event processing
      observerSetup: 5       // Target: <5ms observer setup
    },
    metrics: {
      totalProcessed: 0,
      averageTimes: {},
      peakTimes: {},
      violations: []
    },
    observers: {
      active: 0,
      created: 0,
      cleaned: 0,
      leaks: []
    },
    memory: {
      baseline: 0,
      peak: 0,
      current: 0,
      samples: []
    }
  };

  /**
   * Initialize performance monitoring
   */
  function initialize() {
    // Only enable in debug mode or when explicitly requested
    if ((window.cuftMigration && window.cuftMigration.performanceTracking) ||
        (window.cuftMigration && window.cuftMigration.debugMode)) {
      performanceMonitor.enabled = true;
    }

    if (performanceMonitor.enabled) {
      setupMemoryTracking();
      setupPerformanceObserver();
      recordBaseline();
      log('Performance monitoring initialized');
    }
  }

  /**
   * Set up memory usage tracking
   */
  function setupMemoryTracking() {
    if (window.performance && window.performance.memory) {
      performanceMonitor.memory.baseline = window.performance.memory.usedJSHeapSize;
      performanceMonitor.memory.current = performanceMonitor.memory.baseline;
    }
  }

  /**
   * Set up Performance Observer if available
   */
  function setupPerformanceObserver() {
    if (window.PerformanceObserver) {
      try {
        var observer = new PerformanceObserver(function(list) {
          var entries = list.getEntries();
          for (var i = 0; i < entries.length; i++) {
            processPerformanceEntry(entries[i]);
          }
        });

        observer.observe({ entryTypes: ['measure', 'navigation'] });
      } catch (e) {
        // PerformanceObserver not fully supported
        log('PerformanceObserver not available');
      }
    }
  }

  /**
   * Process performance entries
   */
  function processPerformanceEntry(entry) {
    if (entry.name && entry.name.indexOf('cuft') > -1) {
      recordSample({
        name: entry.name,
        duration: entry.duration,
        startTime: entry.startTime,
        timestamp: Date.now()
      });
    }
  }

  /**
   * Record baseline performance metrics
   */
  function recordBaseline() {
    var baseline = {
      domContentLoaded: document.readyState === 'complete',
      timestamp: Date.now(),
      userAgent: navigator.userAgent,
      documentReady: document.readyState
    };

    if (window.performance && window.performance.timing) {
      var timing = window.performance.timing;
      baseline.pageLoadTime = timing.loadEventEnd - timing.navigationStart;
      baseline.domReadyTime = timing.domContentLoadedEventEnd - timing.navigationStart;
    }

    performanceMonitor.baseline = baseline;
  }

  /**
   * Start performance measurement
   */
  function startMeasurement(name, context) {
    if (!performanceMonitor.enabled) return null;

    var measureName = 'cuft-' + name;
    var startMark = measureName + '-start';

    try {
      if (window.performance && window.performance.mark) {
        window.performance.mark(startMark);
      }

      return {
        name: measureName,
        startMark: startMark,
        startTime: Date.now(),
        context: context || {}
      };
    } catch (e) {
      return {
        name: measureName,
        startTime: Date.now(),
        context: context || {},
        fallback: true
      };
    }
  }

  /**
   * End performance measurement
   */
  function endMeasurement(measurement) {
    if (!performanceMonitor.enabled || !measurement) return null;

    var endTime = Date.now();
    var duration = endTime - measurement.startTime;
    var endMark = measurement.name + '-end';

    try {
      if (window.performance && window.performance.mark && !measurement.fallback) {
        window.performance.mark(endMark);
        window.performance.measure(measurement.name, measurement.startMark, endMark);
      }
    } catch (e) {
      // Fallback to manual timing
    }

    var sample = {
      name: measurement.name,
      duration: duration,
      startTime: measurement.startTime,
      endTime: endTime,
      context: measurement.context,
      timestamp: Date.now()
    };

    recordSample(sample);
    checkThresholds(sample);

    return sample;
  }

  /**
   * Record performance sample
   */
  function recordSample(sample) {
    performanceMonitor.samples.push(sample);

    // Limit sample storage
    if (performanceMonitor.samples.length > performanceMonitor.maxSamples) {
      performanceMonitor.samples.shift();
    }

    // Update metrics
    updateMetrics(sample);

    // Update feature flags if available
    if (window.cuftMigration && window.cuftMigration.recordPerformance) {
      window.cuftMigration.recordPerformance(sample.duration);
    }
  }

  /**
   * Update performance metrics
   */
  function updateMetrics(sample) {
    performanceMonitor.metrics.totalProcessed++;

    var category = getCategoryFromName(sample.name);

    // Update averages
    if (!performanceMonitor.metrics.averageTimes[category]) {
      performanceMonitor.metrics.averageTimes[category] = [];
    }
    performanceMonitor.metrics.averageTimes[category].push(sample.duration);

    // Keep only recent samples for average calculation
    if (performanceMonitor.metrics.averageTimes[category].length > 20) {
      performanceMonitor.metrics.averageTimes[category].shift();
    }

    // Update peak times
    if (!performanceMonitor.metrics.peakTimes[category] ||
        sample.duration > performanceMonitor.metrics.peakTimes[category]) {
      performanceMonitor.metrics.peakTimes[category] = sample.duration;
    }
  }

  /**
   * Get category from measurement name
   */
  function getCategoryFromName(name) {
    if (name.indexOf('form-processing') > -1) return 'formProcessing';
    if (name.indexOf('form-detection') > -1) return 'formDetection';
    if (name.indexOf('field-extraction') > -1) return 'fieldExtraction';
    if (name.indexOf('event-processing') > -1) return 'eventProcessing';
    if (name.indexOf('observer-setup') > -1) return 'observerSetup';
    return 'other';
  }

  /**
   * Check performance thresholds
   */
  function checkThresholds(sample) {
    var category = getCategoryFromName(sample.name);
    var threshold = performanceMonitor.thresholds[category];

    if (threshold && sample.duration > threshold) {
      var violation = {
        category: category,
        duration: sample.duration,
        threshold: threshold,
        excess: sample.duration - threshold,
        timestamp: sample.timestamp,
        context: sample.context
      };

      performanceMonitor.metrics.violations.push(violation);

      // Limit violation storage
      if (performanceMonitor.metrics.violations.length > 50) {
        performanceMonitor.metrics.violations.shift();
      }

      log('Performance threshold violation: ' + category + ' took ' +
          sample.duration + 'ms (threshold: ' + threshold + 'ms)');
    }
  }

  /**
   * Track observer creation
   */
  function trackObserverCreated(observerId, target, config) {
    performanceMonitor.observers.created++;
    performanceMonitor.observers.active++;

    var observer = {
      id: observerId || 'observer-' + performanceMonitor.observers.created,
      target: target ? target.tagName + (target.id ? '#' + target.id : '') : 'unknown',
      config: config || {},
      created: Date.now(),
      cleaned: false
    };

    if (!performanceMonitor.observers.list) {
      performanceMonitor.observers.list = [];
    }
    performanceMonitor.observers.list.push(observer);

    return observer;
  }

  /**
   * Track observer cleanup
   */
  function trackObserverCleaned(observerId) {
    performanceMonitor.observers.cleaned++;
    performanceMonitor.observers.active = Math.max(0, performanceMonitor.observers.active - 1);

    if (performanceMonitor.observers.list) {
      var observer = performanceMonitor.observers.list.find(function(obs) {
        return obs.id === observerId;
      });

      if (observer) {
        observer.cleaned = true;
        observer.cleanedAt = Date.now();
        observer.lifetime = observer.cleanedAt - observer.created;
      }
    }
  }

  /**
   * Check for memory leaks
   */
  function checkMemoryLeaks() {
    if (performanceMonitor.observers.active > 10) {
      log('Warning: ' + performanceMonitor.observers.active + ' active observers detected');
    }

    // Check for long-lived observers
    if (performanceMonitor.observers.list) {
      var now = Date.now();
      var longLived = performanceMonitor.observers.list.filter(function(obs) {
        return !obs.cleaned && (now - obs.created) > 30000; // 30 seconds
      });

      if (longLived.length > 0) {
        performanceMonitor.observers.leaks = longLived;
        log('Memory leak detected: ' + longLived.length + ' long-lived observers');
      }
    }

    // Track memory usage
    trackMemoryUsage();
  }

  /**
   * Track memory usage
   */
  function trackMemoryUsage() {
    if (window.performance && window.performance.memory) {
      var current = window.performance.memory.usedJSHeapSize;
      performanceMonitor.memory.current = current;

      if (current > performanceMonitor.memory.peak) {
        performanceMonitor.memory.peak = current;
      }

      performanceMonitor.memory.samples.push({
        usage: current,
        timestamp: Date.now()
      });

      // Limit memory samples
      if (performanceMonitor.memory.samples.length > 50) {
        performanceMonitor.memory.samples.shift();
      }
    }
  }

  /**
   * Get performance report
   */
  function getPerformanceReport() {
    var report = {
      enabled: performanceMonitor.enabled,
      totalProcessed: performanceMonitor.metrics.totalProcessed,
      averageTimes: {},
      peakTimes: performanceMonitor.metrics.peakTimes,
      violations: performanceMonitor.metrics.violations.length,
      recentViolations: performanceMonitor.metrics.violations.slice(-5),
      observers: {
        active: performanceMonitor.observers.active,
        created: performanceMonitor.observers.created,
        cleaned: performanceMonitor.observers.cleaned,
        leakCount: performanceMonitor.observers.leaks.length
      },
      memory: {
        current: formatBytes(performanceMonitor.memory.current),
        peak: formatBytes(performanceMonitor.memory.peak),
        baseline: formatBytes(performanceMonitor.memory.baseline)
      }
    };

    // Calculate averages
    Object.keys(performanceMonitor.metrics.averageTimes).forEach(function(category) {
      var times = performanceMonitor.metrics.averageTimes[category];
      var avg = times.reduce(function(sum, time) { return sum + time; }, 0) / times.length;
      report.averageTimes[category] = Math.round(avg * 100) / 100;
    });

    return report;
  }

  /**
   * Format bytes for display
   */
  function formatBytes(bytes) {
    if (!bytes) return '0 B';
    var k = 1024;
    var sizes = ['B', 'KB', 'MB'];
    var i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
  }

  /**
   * Safe logging function
   */
  function log(message) {
    if (window.console && window.console.log && performanceMonitor.enabled) {
      try {
        console.log('[CUFT Performance]', message);
      } catch (e) {
        // Silent failure
      }
    }
  }

  // Initialize monitoring
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
  } else {
    initialize();
  }

  // Periodic memory leak check
  setInterval(checkMemoryLeaks, 15000); // Every 15 seconds

  // Expose performance monitoring API
  window.cuftPerformanceMonitor = {
    startMeasurement: startMeasurement,
    endMeasurement: endMeasurement,
    trackObserverCreated: trackObserverCreated,
    trackObserverCleaned: trackObserverCleaned,
    getPerformanceReport: getPerformanceReport,
    checkMemoryLeaks: checkMemoryLeaks,
    enabled: function() { return performanceMonitor.enabled; }
  };

})();