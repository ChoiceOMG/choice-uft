/**
 * Choice Universal Form Tracker - Error Boundary System
 * Version: 4.0.0-phase2
 * Date: 2025-09-25
 *
 * Comprehensive error handling and isolation system for all framework interactions.
 * Prevents cascade failures and provides centralized error reporting.
 */
(function() {
  'use strict';

  // Global error boundary configuration
  var errorBoundary = {
    enabled: true,
    maxErrors: 50,
    errors: [],
    errorCounts: {},
    suppressThreshold: 10, // Suppress repeated errors after this count
    reportingEnabled: false, // Can be enabled for debugging
    initialized: false
  };

  /**
   * Initialize error boundary system
   */
  function initializeErrorBoundary() {
    if (errorBoundary.initialized) return;

    // Set up global error handlers
    setupGlobalErrorHandlers();

    // Initialize error storage
    errorBoundary.errors = [];
    errorBoundary.errorCounts = {};
    errorBoundary.initialized = true;

    // Enable reporting if in debug mode
    if (window.cuftMigration && window.cuftMigration.debugMode) {
      errorBoundary.reportingEnabled = true;
    }

    log('Error boundary system initialized');
  }

  /**
   * Set up global error handlers
   */
  function setupGlobalErrorHandlers() {
    // Catch uncaught JavaScript errors
    window.addEventListener('error', function(event) {
      if (isCUFTError(event)) {
        handleGlobalError(event.error, 'Global Error', {
          filename: event.filename,
          lineno: event.lineno,
          colno: event.colno
        });
      }
    });

    // Catch unhandled promise rejections
    window.addEventListener('unhandledrejection', function(event) {
      if (isCUFTError(event)) {
        handleGlobalError(event.reason, 'Unhandled Promise Rejection', {
          promise: event.promise
        });
      }
    });
  }

  /**
   * Check if error is CUFT-related
   */
  function isCUFTError(event) {
    var error = event.error || event.reason;
    var message = error && error.message ? error.message : '';
    var filename = event.filename || '';
    var stack = error && error.stack ? error.stack : '';

    return (
      message.indexOf('cuft') > -1 ||
      filename.indexOf('cuft') > -1 ||
      stack.indexOf('cuft') > -1 ||
      (event.target && event.target.id && event.target.id.indexOf('cuft') > -1)
    );
  }

  /**
   * Handle global errors
   */
  function handleGlobalError(error, context, metadata) {
    if (!errorBoundary.enabled) return;

    var errorInfo = {
      message: error && error.message ? error.message : String(error),
      stack: error && error.stack ? error.stack : '',
      context: context,
      timestamp: Date.now(),
      metadata: metadata || {}
    };

    recordError(errorInfo);
  }

  /**
   * Safe execution wrapper for any function
   */
  function safeExecute(fn, context, fallback) {
    if (!errorBoundary.enabled) {
      return fn();
    }

    try {
      return fn();
    } catch (error) {
      var errorInfo = {
        message: error.message || 'Unknown error',
        stack: error.stack || '',
        context: context || 'Unknown context',
        timestamp: Date.now(),
        metadata: {
          functionName: fn.name || 'anonymous',
          arguments: Array.prototype.slice.call(arguments, 3)
        }
      };

      recordError(errorInfo);

      // Return fallback value if provided
      if (typeof fallback !== 'undefined') {
        return fallback;
      }

      // Return safe defaults based on expected return type
      return null;
    }
  }

  /**
   * Safe DOM operation wrapper
   */
  function safeDOMOperation(operation, context, fallback) {
    return safeExecute(function() {
      return operation();
    }, context + ' (DOM Operation)', fallback);
  }

  /**
   * Safe event listener wrapper
   */
  function safeEventListener(originalHandler, context) {
    return function(event) {
      return safeExecute(function() {
        return originalHandler.call(this, event);
      }, context + ' (Event Handler)', undefined);
    };
  }

  /**
   * Safe AJAX wrapper
   */
  function safeAjaxOperation(operation, context, fallback) {
    return safeExecute(function() {
      return operation();
    }, context + ' (AJAX Operation)', fallback);
  }

  /**
   * Safe form operation wrapper
   */
  function safeFormOperation(form, operation, context, fallback) {
    if (!form || !operation) {
      recordError({
        message: 'Invalid form or operation provided',
        context: context,
        timestamp: Date.now()
      });
      return fallback;
    }

    return safeExecute(function() {
      return operation(form);
    }, context + ' (Form Operation)', fallback);
  }

  /**
   * Record error information
   */
  function recordError(errorInfo) {
    var errorKey = errorInfo.message + '|' + errorInfo.context;

    // Count error occurrences
    errorBoundary.errorCounts[errorKey] = (errorBoundary.errorCounts[errorKey] || 0) + 1;

    // Suppress if too many of the same error
    if (errorBoundary.errorCounts[errorKey] > errorBoundary.suppressThreshold) {
      return;
    }

    // Add to error list
    errorBoundary.errors.push(errorInfo);

    // Limit error storage
    if (errorBoundary.errors.length > errorBoundary.maxErrors) {
      errorBoundary.errors.shift();
    }

    // Report error if enabled
    if (errorBoundary.reportingEnabled) {
      reportError(errorInfo);
    }

    // Update feature flags metrics if available
    if (window.cuftMigration && window.cuftMigration.recordError) {
      window.cuftMigration.recordError(errorInfo);
    }
  }

  /**
   * Report error to console
   */
  function reportError(errorInfo) {
    var count = errorBoundary.errorCounts[errorInfo.message + '|' + errorInfo.context];
    var prefix = '[CUFT Error Boundary]';

    if (count === 1) {
      console.error(prefix, errorInfo.context + ':', errorInfo.message);
      if (errorInfo.stack) {
        console.error('Stack trace:', errorInfo.stack);
      }
      if (errorInfo.metadata && Object.keys(errorInfo.metadata).length > 0) {
        console.error('Metadata:', errorInfo.metadata);
      }
    } else if (count === errorBoundary.suppressThreshold) {
      console.warn(prefix, 'Suppressing further instances of:', errorInfo.context);
    }
  }

  /**
   * Get error boundary status
   */
  function getErrorStatus() {
    var totalErrors = Object.keys(errorBoundary.errorCounts).reduce(function(sum, key) {
      return sum + errorBoundary.errorCounts[key];
    }, 0);

    return {
      enabled: errorBoundary.enabled,
      totalErrors: totalErrors,
      uniqueErrors: Object.keys(errorBoundary.errorCounts).length,
      recentErrors: errorBoundary.errors.slice(-10),
      suppressedErrors: Object.keys(errorBoundary.errorCounts).filter(function(key) {
        return errorBoundary.errorCounts[key] > errorBoundary.suppressThreshold;
      }),
      initialized: errorBoundary.initialized
    };
  }

  /**
   * Clear error history
   */
  function clearErrors() {
    errorBoundary.errors = [];
    errorBoundary.errorCounts = {};
    log('Error boundary: Error history cleared');
  }

  /**
   * Enable/disable error boundary
   */
  function setEnabled(enabled) {
    errorBoundary.enabled = !!enabled;
    log('Error boundary: ' + (enabled ? 'Enabled' : 'Disabled'));
  }

  /**
   * Safe logging function
   */
  function log(message) {
    if (window.console && window.console.log && errorBoundary.reportingEnabled) {
      try {
        console.log('[CUFT Error Boundary]', message);
      } catch (e) {
        // Silent failure - don't create infinite loop
      }
    }
  }

  // Initialize error boundary
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeErrorBoundary);
  } else {
    initializeErrorBoundary();
  }

  // Expose error boundary API
  window.cuftErrorBoundary = {
    safeExecute: safeExecute,
    safeDOMOperation: safeDOMOperation,
    safeEventListener: safeEventListener,
    safeAjaxOperation: safeAjaxOperation,
    safeFormOperation: safeFormOperation,
    getErrorStatus: getErrorStatus,
    clearErrors: clearErrors,
    setEnabled: setEnabled,
    recordError: recordError
  };

})();