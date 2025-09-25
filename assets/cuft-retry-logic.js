/**
 * Choice Universal Form Tracker - Retry Logic Utility
 * Version: 4.0.0-phase2
 * Date: 2025-09-25
 *
 * Provides intelligent retry mechanisms for form tracking operations.
 * Implements exponential backoff, circuit breaker patterns, and failure recovery.
 */
(function() {
  'use strict';

  var retryLogic = {
    enabled: true,
    maxAttempts: 3,
    baseDelay: 1000, // 1 second base delay
    maxDelay: 10000, // 10 second max delay
    operations: new Map(),
    circuitBreakers: new Map(),
    stats: {
      totalAttempts: 0,
      successful: 0,
      failed: 0,
      circuitBreakersTripped: 0
    }
  };

  /**
   * Initialize retry logic system
   */
  function initialize() {
    // Enable in debug mode or when explicitly requested
    if ((window.cuftMigration && window.cuftMigration.retryEnabled) ||
        (window.cuftMigration && window.cuftMigration.debugMode)) {
      retryLogic.enabled = true;
    }

    if (retryLogic.enabled) {
      log('Retry logic system initialized');
    }
  }

  /**
   * Execute operation with retry logic
   */
  function executeWithRetry(operationName, operation, options) {
    if (!retryLogic.enabled) {
      return operation();
    }

    var config = Object.assign({
      maxAttempts: retryLogic.maxAttempts,
      baseDelay: retryLogic.baseDelay,
      maxDelay: retryLogic.maxDelay,
      circuitBreakerThreshold: 5,
      circuitBreakerTimeout: 30000, // 30 seconds
      exponentialBackoff: true,
      onAttempt: null,
      onSuccess: null,
      onFailure: null,
      onCircuitBreak: null
    }, options || {});

    var operationId = generateOperationId(operationName);

    return new Promise(function(resolve, reject) {
      executeAttempt(operationId, operationName, operation, config, 1, resolve, reject);
    });
  }

  /**
   * Execute a single attempt
   */
  function executeAttempt(operationId, operationName, operation, config, attemptNumber, resolve, reject) {
    // Check circuit breaker
    if (isCircuitBreakerOpen(operationName, config)) {
      var error = new Error('Circuit breaker is open for operation: ' + operationName);
      error.circuitBreakerOpen = true;
      retryLogic.stats.circuitBreakersTripped++;

      if (config.onCircuitBreak) {
        config.onCircuitBreak(error, attemptNumber);
      }

      return reject(error);
    }

    retryLogic.stats.totalAttempts++;

    // Call attempt callback
    if (config.onAttempt) {
      config.onAttempt(attemptNumber, operationName);
    }

    try {
      // Wrap operation in error boundary if available
      var result;
      if (window.cuftErrorBoundary) {
        result = window.cuftErrorBoundary.safeExecute(operation, 'Retry Operation: ' + operationName);
      } else {
        result = operation();
      }

      // Handle promises
      if (result && typeof result.then === 'function') {
        result
          .then(function(value) {
            handleSuccess(operationId, operationName, config, attemptNumber, resolve, value);
          })
          .catch(function(error) {
            handleFailure(operationId, operationName, operation, config, attemptNumber, resolve, reject, error);
          });
      } else {
        // Synchronous operation succeeded
        handleSuccess(operationId, operationName, config, attemptNumber, resolve, result);
      }
    } catch (error) {
      handleFailure(operationId, operationName, operation, config, attemptNumber, resolve, reject, error);
    }
  }

  /**
   * Handle successful operation
   */
  function handleSuccess(operationId, operationName, config, attemptNumber, resolve, result) {
    retryLogic.stats.successful++;
    recordOperationSuccess(operationName);

    if (config.onSuccess) {
      config.onSuccess(result, attemptNumber);
    }

    log('Operation succeeded: ' + operationName + ' (attempt ' + attemptNumber + ')');
    resolve(result);
  }

  /**
   * Handle failed operation
   */
  function handleFailure(operationId, operationName, operation, config, attemptNumber, resolve, reject, error) {
    retryLogic.stats.failed++;
    recordOperationFailure(operationName);

    // Check if we should retry
    if (attemptNumber >= config.maxAttempts || !shouldRetry(error)) {
      if (config.onFailure) {
        config.onFailure(error, attemptNumber);
      }

      log('Operation failed permanently: ' + operationName + ' after ' + attemptNumber + ' attempts');
      return reject(error);
    }

    // Calculate delay for next attempt
    var delay = calculateDelay(attemptNumber, config);

    log('Operation failed, retrying in ' + delay + 'ms: ' + operationName + ' (attempt ' + (attemptNumber + 1) + ')');

    setTimeout(function() {
      executeAttempt(operationId, operationName, operation, config, attemptNumber + 1, resolve, reject);
    }, delay);
  }

  /**
   * Calculate retry delay with exponential backoff
   */
  function calculateDelay(attemptNumber, config) {
    if (!config.exponentialBackoff) {
      return config.baseDelay;
    }

    // Exponential backoff with jitter
    var exponentialDelay = config.baseDelay * Math.pow(2, attemptNumber - 1);
    var jitter = Math.random() * 0.1 * exponentialDelay; // ±10% jitter
    var delay = exponentialDelay + jitter;

    return Math.min(delay, config.maxDelay);
  }

  /**
   * Determine if error should trigger retry
   */
  function shouldRetry(error) {
    // Don't retry for these error types
    if (error && error.name === 'TypeError' && error.message.indexOf('null') > -1) {
      return false; // DOM element not found
    }

    if (error && error.message && error.message.indexOf('Permission denied') > -1) {
      return false; // Security error
    }

    if (error && error.circuitBreakerOpen) {
      return false; // Circuit breaker is open
    }

    // Retry for network errors, temporary DOM issues, etc.
    return true;
  }

  /**
   * Generate unique operation ID
   */
  function generateOperationId(operationName) {
    return operationName + '-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
  }

  /**
   * Record operation success for circuit breaker
   */
  function recordOperationSuccess(operationName) {
    if (!retryLogic.circuitBreakers.has(operationName)) {
      retryLogic.circuitBreakers.set(operationName, {
        failures: 0,
        lastFailure: null,
        state: 'closed' // closed, open, half-open
      });
    }

    var breaker = retryLogic.circuitBreakers.get(operationName);
    breaker.failures = 0;
    breaker.state = 'closed';
  }

  /**
   * Record operation failure for circuit breaker
   */
  function recordOperationFailure(operationName) {
    if (!retryLogic.circuitBreakers.has(operationName)) {
      retryLogic.circuitBreakers.set(operationName, {
        failures: 0,
        lastFailure: null,
        state: 'closed'
      });
    }

    var breaker = retryLogic.circuitBreakers.get(operationName);
    breaker.failures++;
    breaker.lastFailure = Date.now();
  }

  /**
   * Check if circuit breaker is open
   */
  function isCircuitBreakerOpen(operationName, config) {
    var breaker = retryLogic.circuitBreakers.get(operationName);
    if (!breaker) return false;

    // Check if breaker should be opened
    if (breaker.failures >= config.circuitBreakerThreshold && breaker.state === 'closed') {
      breaker.state = 'open';
      log('Circuit breaker opened for operation: ' + operationName);
      return true;
    }

    // Check if breaker should transition to half-open
    if (breaker.state === 'open' && breaker.lastFailure) {
      var timeSinceLastFailure = Date.now() - breaker.lastFailure;
      if (timeSinceLastFailure >= config.circuitBreakerTimeout) {
        breaker.state = 'half-open';
        log('Circuit breaker half-open for operation: ' + operationName);
        return false;
      }
      return true;
    }

    return breaker.state === 'open';
  }

  /**
   * Execute operation with simple retry (synchronous)
   */
  function executeWithSimpleRetry(operation, maxAttempts, delay, context) {
    if (!retryLogic.enabled) {
      return operation();
    }

    maxAttempts = maxAttempts || 3;
    delay = delay || 100;
    context = context || 'Unknown Operation';

    var lastError;

    for (var attempt = 1; attempt <= maxAttempts; attempt++) {
      try {
        retryLogic.stats.totalAttempts++;

        var result = window.cuftErrorBoundary
          ? window.cuftErrorBoundary.safeExecute(operation, context + ' (Attempt ' + attempt + ')')
          : operation();

        retryLogic.stats.successful++;

        if (attempt > 1) {
          log('Operation succeeded on attempt ' + attempt + ': ' + context);
        }

        return result;
      } catch (error) {
        lastError = error;
        retryLogic.stats.failed++;

        if (attempt < maxAttempts && shouldRetry(error)) {
          log('Attempt ' + attempt + ' failed, retrying: ' + context + ' (' + error.message + ')');

          // Synchronous delay (not ideal, but maintains compatibility)
          if (delay > 0) {
            var start = Date.now();
            while (Date.now() - start < delay) {
              // Busy wait - only for very short delays
            }
          }
        }
      }
    }

    log('All retry attempts failed: ' + context + ' (' + lastError.message + ')');
    throw lastError;
  }

  /**
   * Retry DOM operation with element waiting
   */
  function retryDOMOperation(selector, operation, options) {
    var config = Object.assign({
      maxAttempts: 5,
      delay: 200,
      timeout: 5000,
      context: 'DOM Operation'
    }, options || {});

    return executeWithRetry('dom-' + selector, function() {
      var element = document.querySelector(selector);
      if (!element) {
        throw new Error('Element not found: ' + selector);
      }
      return operation(element);
    }, {
      maxAttempts: config.maxAttempts,
      baseDelay: config.delay,
      exponentialBackoff: false
    });
  }

  /**
   * Retry form operation with validation
   */
  function retryFormOperation(form, operation, options) {
    var config = Object.assign({
      maxAttempts: 3,
      delay: 500,
      validateForm: true,
      context: 'Form Operation'
    }, options || {});

    return executeWithRetry('form-operation', function() {
      if (!form) {
        throw new Error('Form element is null or undefined');
      }

      if (config.validateForm && !form.tagName) {
        throw new Error('Invalid form element provided');
      }

      return window.cuftErrorBoundary
        ? window.cuftErrorBoundary.safeFormOperation(form, operation, config.context)
        : operation(form);
    }, {
      maxAttempts: config.maxAttempts,
      baseDelay: config.delay
    });
  }

  /**
   * Get retry logic statistics
   */
  function getRetryStats() {
    var successRate = retryLogic.stats.totalAttempts > 0
      ? Math.round((retryLogic.stats.successful / retryLogic.stats.totalAttempts) * 100)
      : 0;

    return {
      enabled: retryLogic.enabled,
      totalAttempts: retryLogic.stats.totalAttempts,
      successful: retryLogic.stats.successful,
      failed: retryLogic.stats.failed,
      successRate: successRate + '%',
      circuitBreakersTripped: retryLogic.stats.circuitBreakersTripped,
      activeCircuitBreakers: Array.from(retryLogic.circuitBreakers.keys()).filter(function(name) {
        return retryLogic.circuitBreakers.get(name).state !== 'closed';
      })
    };
  }

  /**
   * Reset circuit breakers
   */
  function resetCircuitBreakers() {
    retryLogic.circuitBreakers.clear();
    log('All circuit breakers reset');
  }

  /**
   * Reset retry statistics
   */
  function resetStats() {
    retryLogic.stats = {
      totalAttempts: 0,
      successful: 0,
      failed: 0,
      circuitBreakersTripped: 0
    };
    log('Retry statistics reset');
  }

  /**
   * Safe logging function
   */
  function log(message) {
    if (window.cuftPerformanceMonitor && window.cuftPerformanceMonitor.enabled()) {
      if (window.console && window.console.log) {
        try {
          console.log('[CUFT Retry Logic]', message);
        } catch (e) {
          // Silent failure
        }
      }
    }
  }

  // Initialize retry logic system
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
  } else {
    initialize();
  }

  // Expose retry logic API
  window.cuftRetryLogic = {
    executeWithRetry: executeWithRetry,
    executeWithSimpleRetry: executeWithSimpleRetry,
    retryDOMOperation: retryDOMOperation,
    retryFormOperation: retryFormOperation,
    getRetryStats: getRetryStats,
    resetCircuitBreakers: resetCircuitBreakers,
    resetStats: resetStats,
    enabled: function() { return retryLogic.enabled; }
  };

})();