(function() {
    'use strict';
    
    var DEBUG = !!(window.cuftNinja && window.cuftNinja.debug);
    
    function log() {
        try {
            if (DEBUG && window.console && window.console.log) {
                window.console.log.apply(window.console, ['[CUFT Ninja]'].concat(Array.prototype.slice.call(arguments)));
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
    
    function getFieldValue(form, type) {
        var fields = form.querySelectorAll('.nf-field');
        
        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            var input = field.querySelector('input');
            if (!input) continue;
            
            var fieldType = field.getAttribute('data-field-type') || '';
            var inputType = (input.getAttribute('type') || '').toLowerCase();
            var value = (input.value || '').trim();
            
            if (type === 'email') {
                if (fieldType === 'email' || inputType === 'email') {
                    return value;
                }
            } else if (type === 'phone') {
                if (fieldType === 'phone' || inputType === 'tel') {
                    return value ? value.replace(/(?!^\+)[^\d]/g, '') : '';
                }
            }
        }
        
        return '';
    }
    
    function pushToDataLayer(form, email, phone) {
        var formId = form.getAttribute('data-form-id') || form.getAttribute('id');
        
        var payload = {
            event: 'form_submit',
            formType: 'ninja_forms',
            formId: formId,
            formName: null, // Ninja Forms doesn't typically expose form names in frontend
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
    
    function isSuccessState(form) {
        // Check for success message
        var successMsg = form.querySelector('.nf-response-msg');
        if (successMsg && successMsg.style.display !== 'none') {
            return true;
        }
        
        // Check if form is hidden (typical after success)
        if (form.style.display === 'none' || !form.offsetParent) {
            var parent = form.parentNode;
            if (parent && parent.querySelector('.nf-response-msg')) {
                return true;
            }
        }
        
        return false;
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
        
        // Set up observers
        var timeouts = [
            setTimeout(tryPush, 500),
            setTimeout(tryPush, 1500),
            setTimeout(tryPush, 3000)
        ];
        
        var stopTimeout = setTimeout(function() {
            if (!pushed) cleanup();
        }, 8000);
        
        cleanup = function() {
            timeouts.forEach(function(t) { clearTimeout(t); });
            clearTimeout(stopTimeout);
        };
        
        // Mutation observer
        if (window.MutationObserver) {
            var observer = new MutationObserver(tryPush);
            observer.observe(form, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['style', 'class']
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
            
            // Check if this is a Ninja Forms form
            var isNinjaForm = form.classList.contains('nf-form-cont') ||
                             form.querySelector('.nf-field') ||
                             form.closest('.nf-form-wrap');
            
            if (!isNinjaForm) return;
            
            if (form.hasAttribute('data-cuft-observing')) return;
            form.setAttribute('data-cuft-observing', 'true');
            
            var email = getFieldValue(form, 'email');
            var phone = getFieldValue(form, 'phone');
            
            observeSuccess(form, email, phone);
            log('Ninja form submit tracked for:', formId);
        } catch(e) {
            log('Submit handler error:', e);
        }
    }
    
    ready(function() {
        document.addEventListener('submit', handleFormSubmit, true);
        
        // Also listen for Ninja Forms specific events if available
        if (window.Marionette && window.nfRadio) {
            window.nfRadio.channel('forms').on('submit:response', function(response) {
                log('Ninja Forms response received:', response);
            });
        }
        
        log('Ninja Forms tracking initialized');
    });
    
})();
