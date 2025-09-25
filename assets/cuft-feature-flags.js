/**
 * Choice Universal Form Tracker - Feature Flag System
 * Version: 1.0
 * Date: 2025-09-25
 *
 * This file manages the feature flag system for the constitutional compliance migration.
 * It allows gradual rollout of changes and quick rollback if issues are detected.
 */
(function() {
  'use strict';

  // Default configuration for feature flags
  var defaultFlags = {
    // Phase 1 Flags - Core Compliance
    useVanillaJSFirst: false,           // Switch to JS-first implementation
    silentFrameworkDetection: true,     // Enable silent exit for non-matching forms (NOW ENABLED)

    // Phase 2 Flags - Event Standardization
    strictGenerateLeadRules: true,      // Enforce email+phone+click_id (ALREADY IMPLEMENTED)
    enhancedErrorHandling: false,       // Complete try-catch coverage

    // Phase 3 Flags - Performance Optimization
    performanceOptimizations: false,    // Lazy loading and caching
    consolidatedUtilities: false,       // Use shared utility functions

    // Monitoring and Debug
    debugMode: false,                   // Enhanced debug logging
    performanceTracking: false,         // Track migration metrics

    // Migration version tracking
    migrationVersion: '4.0.0-phase1',
    migrationPhase: 1
  };

  // Allow override from localStorage for testing
  var storedFlags = {};
  try {
    var stored = localStorage.getItem('cuftMigrationFlags');
    if (stored) {
      storedFlags = JSON.parse(stored);
    }
  } catch(e) {
    // Silent failure - localStorage might not be available
  }

  // Allow override from sessionStorage for temporary testing
  var sessionFlags = {};
  try {
    var session = sessionStorage.getItem('cuftMigrationFlags');
    if (session) {
      sessionFlags = JSON.parse(session);
    }
  } catch(e) {
    // Silent failure - sessionStorage might not be available
  }

  // Merge configurations (priority: window > session > localStorage > defaults)
  window.cuftMigration = Object.assign(
    {},
    defaultFlags,
    storedFlags,
    sessionFlags,
    window.cuftMigration || {}
  );

  // Helper function to check if a flag is enabled
  window.cuftMigration.isEnabled = function(flag) {
    return !!this[flag];
  };

  // Helper function to enable features for a percentage of users
  window.cuftMigration.enableForPercentage = function(percentage) {
    // Generate or retrieve a stable user ID
    var userId = this.getUserId();

    // Use a hash to determine if this user is in the test group
    var hash = this.simpleHash(userId);
    var userPercentile = (hash % 100) + 1;

    if (userPercentile <= percentage) {
      // Enable Phase 1 features
      this.useVanillaJSFirst = true;
      this.silentFrameworkDetection = true;

      // Enable Phase 2 features if percentage > 30
      if (percentage > 30) {
        this.strictGenerateLeadRules = true;
        this.enhancedErrorHandling = true;
      }

      // Enable Phase 3 features if percentage > 60
      if (percentage > 60) {
        this.performanceOptimizations = true;
        this.consolidatedUtilities = true;
      }

      return true;
    }
    return false;
  };

  // Get or create a stable user ID for consistent rollout
  window.cuftMigration.getUserId = function() {
    var userId = null;

    try {
      // Try to get from localStorage first
      userId = localStorage.getItem('cuft_user_id');
      if (!userId) {
        // Generate a new ID
        userId = 'user_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
        localStorage.setItem('cuft_user_id', userId);
      }
    } catch(e) {
      // Fallback to sessionStorage
      try {
        userId = sessionStorage.getItem('cuft_user_id');
        if (!userId) {
          userId = 'session_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
          sessionStorage.setItem('cuft_user_id', userId);
        }
      } catch(e2) {
        // Final fallback - use a random ID for this session only
        userId = 'temp_' + Math.random().toString(36).substr(2, 9);
      }
    }

    return userId;
  };

  // Simple hash function for user ID
  window.cuftMigration.simpleHash = function(str) {
    var hash = 0;
    if (!str || str.length === 0) return hash;

    for (var i = 0; i < str.length; i++) {
      var char = str.charCodeAt(i);
      hash = ((hash << 5) - hash) + char;
      hash = hash & hash; // Convert to 32-bit integer
    }

    return Math.abs(hash);
  };

  // Save current flags to storage
  window.cuftMigration.saveFlags = function(permanent) {
    var storage = permanent ? localStorage : sessionStorage;
    try {
      storage.setItem('cuftMigrationFlags', JSON.stringify({
        useVanillaJSFirst: this.useVanillaJSFirst,
        silentFrameworkDetection: this.silentFrameworkDetection,
        strictGenerateLeadRules: this.strictGenerateLeadRules,
        enhancedErrorHandling: this.enhancedErrorHandling,
        performanceOptimizations: this.performanceOptimizations,
        consolidatedUtilities: this.consolidatedUtilities,
        debugMode: this.debugMode,
        performanceTracking: this.performanceTracking
      }));
      return true;
    } catch(e) {
      return false;
    }
  };

  // Clear all stored flags
  window.cuftMigration.clearFlags = function() {
    try {
      localStorage.removeItem('cuftMigrationFlags');
      sessionStorage.removeItem('cuftMigrationFlags');

      // Reset to defaults
      for (var key in defaultFlags) {
        if (defaultFlags.hasOwnProperty(key)) {
          this[key] = defaultFlags[key];
        }
      }
      return true;
    } catch(e) {
      return false;
    }
  };

  // Get current flag status report
  window.cuftMigration.getStatus = function() {
    return {
      version: this.migrationVersion,
      phase: this.migrationPhase,
      userId: this.getUserId(),
      flags: {
        // Phase 1
        useVanillaJSFirst: this.useVanillaJSFirst,
        silentFrameworkDetection: this.silentFrameworkDetection,

        // Phase 2
        strictGenerateLeadRules: this.strictGenerateLeadRules,
        enhancedErrorHandling: this.enhancedErrorHandling,

        // Phase 3
        performanceOptimizations: this.performanceOptimizations,
        consolidatedUtilities: this.consolidatedUtilities,

        // Monitoring
        debugMode: this.debugMode,
        performanceTracking: this.performanceTracking
      },
      timestamp: new Date().toISOString()
    };
  };

  // Log migration status if debug mode is enabled
  window.cuftMigration.logStatus = function() {
    if (this.debugMode || (window.console && window.console.log)) {
      var status = this.getStatus();
      console.log('[CUFT Migration] Status:', status);
      console.log('[CUFT Migration] Phase 1 (Core Compliance):',
        this.useVanillaJSFirst ? 'ENABLED' : 'DISABLED',
        '|',
        this.silentFrameworkDetection ? 'SILENT MODE ON' : 'VERBOSE MODE'
      );
      console.log('[CUFT Migration] Phase 2 (Event Standards):',
        this.strictGenerateLeadRules ? 'STRICT RULES' : 'LEGACY RULES',
        '|',
        this.enhancedErrorHandling ? 'ENHANCED ERROR HANDLING' : 'STANDARD ERROR HANDLING'
      );
      console.log('[CUFT Migration] Phase 3 (Performance):',
        this.performanceOptimizations ? 'OPTIMIZED' : 'STANDARD',
        '|',
        this.consolidatedUtilities ? 'CONSOLIDATED' : 'DISTRIBUTED'
      );
    }
  };

  // Monitoring and metrics collection
  window.cuftMigration.metrics = {
    startTime: Date.now(),
    formSubmissions: 0,
    successfulTracks: 0,
    errors: [],
    performanceSamples: []
  };

  // Record a form submission
  window.cuftMigration.recordSubmission = function() {
    this.metrics.formSubmissions++;
    if (this.performanceTracking) {
      this.checkMetrics();
    }
  };

  // Record a successful track
  window.cuftMigration.recordSuccess = function() {
    this.metrics.successfulTracks++;
  };

  // Record an error
  window.cuftMigration.recordError = function(error) {
    this.metrics.errors.push({
      timestamp: Date.now(),
      message: error.message || error,
      stack: error.stack || ''
    });

    // Keep only last 50 errors
    if (this.metrics.errors.length > 50) {
      this.metrics.errors.shift();
    }

    // Check if we need to trigger rollback
    this.checkRollbackTriggers();
  };

  // Record performance sample
  window.cuftMigration.recordPerformance = function(duration) {
    this.metrics.performanceSamples.push(duration);

    // Keep only last 100 samples
    if (this.metrics.performanceSamples.length > 100) {
      this.metrics.performanceSamples.shift();
    }
  };

  // Check if rollback should be triggered
  window.cuftMigration.checkRollbackTriggers = function() {
    // Calculate error rate
    var errorRate = this.metrics.errors.length / Math.max(this.metrics.formSubmissions, 1);

    // Calculate success rate
    var successRate = this.metrics.successfulTracks / Math.max(this.metrics.formSubmissions, 1);

    // Check rollback conditions
    if (errorRate > 0.01) { // More than 1% error rate
      this.triggerRollback('High error rate: ' + (errorRate * 100).toFixed(2) + '%');
    } else if (successRate < 0.98 && this.metrics.formSubmissions > 10) { // Less than 98% success rate
      this.triggerRollback('Low success rate: ' + (successRate * 100).toFixed(2) + '%');
    }
  };

  // Check metrics periodically
  window.cuftMigration.checkMetrics = function() {
    if (!this.performanceTracking) return;

    var avgPerformance = 0;
    if (this.metrics.performanceSamples.length > 0) {
      var sum = this.metrics.performanceSamples.reduce(function(a, b) { return a + b; }, 0);
      avgPerformance = sum / this.metrics.performanceSamples.length;
    }

    // Check performance degradation
    if (avgPerformance > 150 && this.metrics.performanceSamples.length > 10) { // More than 150ms average
      this.triggerRollback('Performance degradation: ' + avgPerformance.toFixed(2) + 'ms average');
    }
  };

  // Trigger rollback
  window.cuftMigration.triggerRollback = function(reason) {
    console.error('[CUFT Migration] ROLLBACK TRIGGERED:', reason);

    // Disable all migration flags
    this.useVanillaJSFirst = false;
    this.silentFrameworkDetection = false;
    this.strictGenerateLeadRules = false;
    this.enhancedErrorHandling = false;
    this.performanceOptimizations = false;
    this.consolidatedUtilities = false;

    // Save the rollback state
    this.saveFlags(true);

    // Notify monitoring system (if available)
    if (window.cuftDataLayerUtils && window.cuftDataLayerUtils.pushToDataLayer) {
      window.cuftDataLayerUtils.pushToDataLayer({
        event: 'cuft_rollback',
        rollback_reason: reason,
        rollback_timestamp: new Date().toISOString(),
        metrics: this.getMetricsReport()
      });
    }
  };

  // Get metrics report
  window.cuftMigration.getMetricsReport = function() {
    var uptime = (Date.now() - this.metrics.startTime) / 1000;
    var successRate = (this.metrics.successfulTracks / Math.max(this.metrics.formSubmissions, 1)) * 100;
    var avgPerformance = 0;

    if (this.metrics.performanceSamples.length > 0) {
      var sum = this.metrics.performanceSamples.reduce(function(a, b) { return a + b; }, 0);
      avgPerformance = sum / this.metrics.performanceSamples.length;
    }

    return {
      uptime: uptime + ' seconds',
      submissions: this.metrics.formSubmissions,
      successes: this.metrics.successfulTracks,
      successRate: successRate.toFixed(2) + '%',
      errors: this.metrics.errors.length,
      avgProcessingTime: avgPerformance.toFixed(2) + 'ms',
      migrationFlags: this.getStatus().flags
    };
  };

  // Initialize on page load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      window.cuftMigration.logStatus();
    });
  } else {
    window.cuftMigration.logStatus();
  }

})();