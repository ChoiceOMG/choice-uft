(function() {
    'use strict';
    
    var DEBUG = !!(window.cuftAvada && window.cuftAvada.debug);
    
    function log() {
        try {
            if (DEBUG && window.console && window.console.log) {
                window.console.log.apply(window.console, ['[CUFT Avada]'].concat(Array.prototype.slice.call(arguments)));
            }
        } catch(e) {}
    }
    
    function getDL() {
        try {
            return (window.dataLayer = window.dataLayer || []);
        } catch (e) {
            return { push: function(){} };
        }
    }
    
    function ready(fn) {
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(fn, 1);
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }
    
    function findField(form, type) {
        var inputs = form.querySelectorAll('input');
        for (var i = 0; i < inputs.length; i++) {
            var input = inputs[i];
            var inputType = (input.getAttribute('type') || '').toLowerCase();
            var inputMode = (input.getAttribute('inputmode') || '').toLowerCase();
            var dataValidate = ((input.getAttribute('data-validate') || input.getAttribute('data-validation') || '') + '').toLowerCase();
            var pattern = input.getAttribute('pattern') || '';
            
            if (type === 'email') {
                if (inputType === 'email' || inputMode === 'email' || dataValidate.indexOf('email') > -1 || /@/.test(pattern)) {
                    return input;
                }
            } else if (type === 'phone') {
                if (inputType === 'tel' || inputMode === 'tel' || inputMode === 'numeric' || /\d|\[0-9]/.test(pattern)) {
                    return input;
                }
            }
        }
        return null;
    }
    
    function getFieldValue(form, type) {
        var field = findField(form, type);
        if (!field) return '';
        
        var value = (field.value || '').trim();
        if (type === 'phone' && value) {
            return value.replace(/(?!^\+)[^\d]/g, '');
        }
        return value;
    }
    
    function isSuccessState(form) {
        var successSelectors = [
            '.fusion-alert.success',
            '.fusion-form-success',
            '.fusion-success',
            '.avada-form-success',
            '.fusion-form-success-message',
            '[data-status="sent"]',
            '[data-avada-form-status="success"]'
        ];
        
        for (var i = 0; i < successSelectors.length; i++) {
            if (form.querySelector(successSelectors[i])) {
                return true;
            }
        }
        
        if (!form.offsetParent) {
            var parent = form.parentNode;
            if (parent && parent.querySelector('.thank-you, .success, [role="alert"]')) {
                return true;
            }
        }
        
        return form.classList.contains('sent') || form.classList.contains('is-success');
    }
    
    function pushToDataLayer(form, email, phone) {
        var payload = {
            event: 'form_submit',
            formType: 'avada',
            formId: form.getAttribute('id') || null,
            formName: form.getAttribute('name') || form.getAttribute('data-form-name') || null,
            submittedAt: new Date().toISOString()
        };
        
        if (email) payload.user_email = email;
        if (phone) payload.user_phone = phone;
        
        // Add UTM data if available
        if (window.cuftUtmUtils) {
            payload = window.cuftUtmUtils.addUtmToPayload(payload);
        }
        
        try {
            getDL().push(payload);
            log('Form submission tracked:', payload);
        } catch(e) {
            log('DataLayer push error:', e);
        }
    }
    
    function observeSuccess(form, email, phone) {
        var pushed = false;
        var cleanup = function() {};
        
        function tryPush() {
            if (!pushed && isSuccessState(form)) {
                pushed = true;
                pushToDataLayer(form, email, phone);
                cleanup();
            }
        }
        
        // Try immediately
        tryPush();
        
        // Set up observers
        var timeouts = [
            setTimeout(tryPush, 1000),
            setTimeout(tryPush, 3000),
            setTimeout(tryPush, 7000)
        ];
        
        var stopTimeout = setTimeout(function() {
            if (!pushed) cleanup();
        }, 10000);
        
        cleanup = function() {
            timeouts.forEach(function(t) { clearTimeout(t); });
            clearTimeout(stopTimeout);
        };
        
        // Mutation observer
        if (window.MutationObserver) {
            var observer = new MutationObserver(tryPush);
            observer.observe(form.parentNode || document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['class', 'style']
            });
            
            var originalCleanup = cleanup;
            cleanup = function() {
                observer.disconnect();
                originalCleanup();
            };
        }
    }
    
    function handleFormSubmit(event) {
        try {
            var form = event.target;
            if (!form || form.tagName !== 'FORM') return;
            
            // Check if this is an Avada/Fusion form
            var isAvadaForm = form.classList.contains('fusion-form') ||
                             form.classList.contains('avada-form') ||
                             form.className.indexOf('fusion-form') > -1 ||
                             form.id.indexOf('avada') > -1;
            
            if (!isAvadaForm) return;
            
            if (form.hasAttribute('data-cuft-observing')) return;
            form.setAttribute('data-cuft-observing', 'true');
            
            var email = getFieldValue(form, 'email');
            var phone = getFieldValue(form, 'phone');
            
            observeSuccess(form, email, phone);
            log('Form submit listener attached for:', form.id || 'unnamed form');
        } catch(e) {
            log('Submit handler error:', e);
        }
    }
    
    ready(function() {
        document.addEventListener('submit', handleFormSubmit, true);
        log('Avada forms tracking initialized');
    });
    
})();
