/**
 * Choice Universal Form Tracker - Observer Cleanup Manager
 * Version: 4.0.0-phase2
 * Date: 2025-09-25
 *
 * Manages MutationObserver lifecycle to prevent memory leaks and optimize performance.
 * Provides scoped observers with automatic cleanup and timeout management.
 */
(function() {
  'use strict';

  var observerCleanup = {
    observers: new Map(), // Use Map for better performance
    timeouts: new Map(),
    defaultTimeout: 15000, // 15 seconds default timeout
    maxObservers: 20, // Maximum concurrent observers
    cleanupInterval: null,
    stats: {
      created: 0,
      cleaned: 0,
      timedOut: 0,
      active: 0
    }
  };

  /**
   * Create a scoped MutationObserver with automatic cleanup
   */
  function createScopedObserver(target, callback, options) {
    if (!target || !callback) {
      return null;
    }

    // Check observer limit
    if (observerCleanup.observers.size >= observerCleanup.maxObservers) {
      log('Observer limit reached, cleaning up oldest observers');
      cleanupOldestObservers(5);
    }

    var observerId = generateObserverId();
    var timeout = (options && options.timeout) || observerCleanup.defaultTimeout;

    // Create wrapped callback with error boundary
    var safeCallback = function(mutations, observer) {
      if (window.cuftErrorBoundary) {
        return window.cuftErrorBoundary.safeExecute(function() {
          return callback(mutations, observer);
        }, 'MutationObserver Callback');
      } else {
        try {
          return callback(mutations, observer);
        } catch (e) {
          log('Observer callback error: ' + e.message);
        }
      }
    };

    // Create observer
    var observer = new MutationObserver(safeCallback);

    // Configure observer options
    var config = Object.assign({
      childList: true,
      subtree: true
    }, options ? options.config : {});

    try {
      observer.observe(target, config);
    } catch (e) {
      log('Failed to create observer: ' + e.message);
      return null;
    }

    // Store observer info
    var observerInfo = {
      id: observerId,
      observer: observer,
      target: target,
      callback: callback,
      config: config,
      created: Date.now(),
      timeout: timeout,
      cleaned: false,
      context: (options && options.context) || 'Unknown'
    };

    observerCleanup.observers.set(observerId, observerInfo);
    observerCleanup.stats.created++;
    observerCleanup.stats.active++;

    // Set up automatic cleanup timeout
    var timeoutId = setTimeout(function() {
      cleanupObserver(observerId, 'timeout');
    }, timeout);

    observerCleanup.timeouts.set(observerId, timeoutId);

    // Track with performance monitor if available
    if (window.cuftPerformanceMonitor) {
      window.cuftPerformanceMonitor.trackObserverCreated(observerId, target, config);
    }

    log('Created scoped observer: ' + observerId + ' (' + observerInfo.context + ')');

    return {
      id: observerId,
      observer: observer,
      disconnect: function() {
        cleanupObserver(observerId, 'manual');
      },
      isActive: function() {
        var info = observerCleanup.observers.get(observerId);
        return info && !info.cleaned;
      }
    };
  }

  /**
   * Generate unique observer ID
   */
  function generateObserverId() {
    return 'obs-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
  }

  /**
   * Cleanup a specific observer
   */
  function cleanupObserver(observerId, reason) {
    var observerInfo = observerCleanup.observers.get(observerId);
    if (!observerInfo || observerInfo.cleaned) {
      return false;
    }

    try {
      // Disconnect observer
      observerInfo.observer.disconnect();
      observerInfo.cleaned = true;
      observerInfo.cleanedAt = Date.now();
      observerInfo.lifetime = observerInfo.cleanedAt - observerInfo.created;
      observerInfo.cleanupReason = reason || 'manual';

      // Clear timeout
      var timeoutId = observerCleanup.timeouts.get(observerId);
      if (timeoutId) {
        clearTimeout(timeoutId);
        observerCleanup.timeouts.delete(observerId);
      }

      // Update stats
      observerCleanup.stats.cleaned++;
      observerCleanup.stats.active = Math.max(0, observerCleanup.stats.active - 1);

      if (reason === 'timeout') {
        observerCleanup.stats.timedOut++;
      }

      // Track with performance monitor if available
      if (window.cuftPerformanceMonitor) {
        window.cuftPerformanceMonitor.trackObserverCleaned(observerId);
      }

      log('Cleaned up observer: ' + observerId + ' (reason: ' + reason + ', lifetime: ' + observerInfo.lifetime + 'ms)');

      return true;
    } catch (e) {
      log('Error cleaning up observer ' + observerId + ': ' + e.message);
      return false;
    }
  }

  /**
   * Cleanup oldest observers
   */
  function cleanupOldestObservers(count) {
    var observers = Array.from(observerCleanup.observers.values());
    var activeObservers = observers.filter(function(info) { return !info.cleaned; });

    // Sort by creation time (oldest first)
    activeObservers.sort(function(a, b) { return a.created - b.created; });

    var cleanedCount = 0;
    for (var i = 0; i < Math.min(count, activeObservers.length); i++) {
      if (cleanupObserver(activeObservers[i].id, 'forced')) {
        cleanedCount++;
      }
    }

    log('Cleaned up ' + cleanedCount + ' oldest observers');
    return cleanedCount;
  }

  /**
   * Cleanup all observers
   */
  function cleanupAllObservers(reason) {
    var cleanedCount = 0;
    var observerIds = Array.from(observerCleanup.observers.keys());

    for (var i = 0; i < observerIds.length; i++) {
      if (cleanupObserver(observerIds[i], reason || 'cleanup-all')) {
        cleanedCount++;
      }
    }

    log('Cleaned up all observers: ' + cleanedCount + ' total');
    return cleanedCount;
  }

  /**
   * Cleanup observers for a specific target
   */
  function cleanupObserversForTarget(target, reason) {
    if (!target) return 0;

    var cleanedCount = 0;
    var observers = Array.from(observerCleanup.observers.values());

    for (var i = 0; i < observers.length; i++) {
      var info = observers[i];
      if (!info.cleaned && info.target === target) {
        if (cleanupObserver(info.id, reason || 'target-cleanup')) {
          cleanedCount++;
        }
      }
    }

    return cleanedCount;
  }

  /**
   * Get observer statistics
   */
  function getObserverStats() {
    // Clean up references to cleaned observers
    garbageCollectObservers();

    var activeObservers = Array.from(observerCleanup.observers.values())
      .filter(function(info) { return !info.cleaned; });

    var longestLived = activeObservers.reduce(function(max, info) {
      var lifetime = Date.now() - info.created;
      return lifetime > max ? lifetime : max;
    }, 0);

    return {
      created: observerCleanup.stats.created,
      cleaned: observerCleanup.stats.cleaned,
      timedOut: observerCleanup.stats.timedOut,
      active: observerCleanup.stats.active,
      stored: observerCleanup.observers.size,
      longestLivedMs: longestLived,
      averageLifetime: calculateAverageLifetime(),
      memoryUsage: estimateMemoryUsage()
    };
  }

  /**
   * Calculate average lifetime of cleaned observers
   */
  function calculateAverageLifetime() {
    var cleanedObservers = Array.from(observerCleanup.observers.values())
      .filter(function(info) { return info.cleaned && info.lifetime; });

    if (cleanedObservers.length === 0) return 0;

    var totalLifetime = cleanedObservers.reduce(function(sum, info) {
      return sum + info.lifetime;
    }, 0);

    return Math.round(totalLifetime / cleanedObservers.length);
  }

  /**
   * Estimate memory usage
   */
  function estimateMemoryUsage() {
    // Rough estimate: each observer info object ~1KB
    return observerCleanup.observers.size + ' observers (~' +
           Math.round(observerCleanup.observers.size * 1.2) + ' KB estimated)';
  }

  /**
   * Garbage collect cleaned observers
   */
  function garbageCollectObservers() {
    var cutoffTime = Date.now() - 60000; // Keep cleaned observers for 1 minute
    var removedCount = 0;

    observerCleanup.observers.forEach(function(info, id) {
      if (info.cleaned && info.cleanedAt && info.cleanedAt < cutoffTime) {
        observerCleanup.observers.delete(id);
        removedCount++;
      }
    });

    if (removedCount > 0) {
      log('Garbage collected ' + removedCount + ' observer references');
    }

    return removedCount;
  }

  /**
   * Start periodic cleanup
   */
  function startPeriodicCleanup(interval) {
    if (observerCleanup.cleanupInterval) {
      clearInterval(observerCleanup.cleanupInterval);
    }

    observerCleanup.cleanupInterval = setInterval(function() {
      garbageCollectObservers();

      // Check for stuck observers (active for more than 5 minutes)
      var stuckObservers = Array.from(observerCleanup.observers.values())
        .filter(function(info) {
          return !info.cleaned && (Date.now() - info.created) > 300000;
        });

      if (stuckObservers.length > 0) {
        log('Found ' + stuckObservers.length + ' stuck observers, cleaning up');
        stuckObservers.forEach(function(info) {
          cleanupObserver(info.id, 'stuck');
        });
      }
    }, interval || 30000); // Default 30 seconds
  }

  /**
   * Stop periodic cleanup
   */
  function stopPeriodicCleanup() {
    if (observerCleanup.cleanupInterval) {
      clearInterval(observerCleanup.cleanupInterval);
      observerCleanup.cleanupInterval = null;
    }
  }

  /**
   * Initialize observer cleanup system
   */
  function initialize() {
    startPeriodicCleanup();

    // Cleanup all observers when page unloads
    window.addEventListener('beforeunload', function() {
      cleanupAllObservers('page-unload');
      stopPeriodicCleanup();
    });

    log('Observer cleanup manager initialized');
  }

  /**
   * Safe logging function
   */
  function log(message) {
    if (window.cuftPerformanceMonitor && window.cuftPerformanceMonitor.enabled()) {
      if (window.console && window.console.log) {
        try {
          console.log('[CUFT Observer Cleanup]', message);
        } catch (e) {
          // Silent failure
        }
      }
    }
  }

  // Initialize cleanup system
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
  } else {
    initialize();
  }

  // Expose observer cleanup API
  window.cuftObserverCleanup = {
    createScopedObserver: createScopedObserver,
    cleanupObserver: cleanupObserver,
    cleanupAllObservers: cleanupAllObservers,
    cleanupObserversForTarget: cleanupObserversForTarget,
    getObserverStats: getObserverStats,
    garbageCollectObservers: garbageCollectObservers
  };

})();