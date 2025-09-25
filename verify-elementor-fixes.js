/**
 * Verification script for Elementor form fixes
 * Run this in browser console after loading test page
 */

function verifyElementorFixes() {
    const results = [];

    console.log('🔍 Verifying Elementor form fixes...\n');

    // Test 1: Pattern fix
    console.log('1. Testing pattern validation fix...');
    const phoneInput = document.querySelector('input[pattern]');
    if (phoneInput) {
        const originalPattern = '[0-9()#&+*-=.]+';
        const fixedPattern = '[0-9()#&+*=.-]+';

        if (phoneInput.getAttribute('pattern') === fixedPattern) {
            results.push('✅ Pattern fix applied successfully');

            // Test if pattern validation works
            try {
                phoneInput.checkValidity();
                results.push('✅ Pattern validation works without errors');
            } catch (e) {
                results.push('❌ Pattern validation still fails: ' + e.message);
            }
        } else {
            results.push('❌ Pattern fix not applied or not found');
        }
    } else {
        results.push('⚠️ No input with pattern found');
    }

    // Test 2: Event listeners
    console.log('2. Testing event listener setup...');
    const form = document.querySelector('.elementor-form');
    if (form) {
        // Test submit_success event
        let eventFired = false;

        // Listen for dataLayer updates
        const originalPush = window.dataLayer.push;
        window.dataLayer.push = function(data) {
            if (data.event === 'form_submit') {
                eventFired = true;
                results.push('✅ form_submit event captured in dataLayer');

                // Verify required CUFT fields
                if (data.cuft_tracked === true) {
                    results.push('✅ cuft_tracked: true present');
                } else {
                    results.push('❌ Missing cuft_tracked: true');
                }

                if (data.cuft_source) {
                    results.push('✅ cuft_source present: ' + data.cuft_source);
                } else {
                    results.push('❌ Missing cuft_source field');
                }

                results.push('   Event data: ' + JSON.stringify(data, null, 2));
            }
            return originalPush.call(this, data);
        };

        // Fire test event
        const successEvent = new CustomEvent('submit_success', {
            detail: {
                success: true,
                data: { form_id: 'test-form' }
            },
            bubbles: true
        });

        form.dispatchEvent(successEvent);

        // Wait a moment for async processing
        setTimeout(() => {
            if (!eventFired) {
                results.push('❌ submit_success event did not trigger tracking');
            }

            // Restore original push
            window.dataLayer.push = originalPush;

            // Print all results
            console.log('\n📋 Verification Results:');
            results.forEach(result => console.log(result));

            // Summary
            const successes = results.filter(r => r.startsWith('✅')).length;
            const failures = results.filter(r => r.startsWith('❌')).length;
            const warnings = results.filter(r => r.startsWith('⚠️')).length;

            console.log(`\n📊 Summary: ${successes} successes, ${failures} failures, ${warnings} warnings`);

            if (failures === 0) {
                console.log('🎉 All Elementor form fixes verified successfully!');
            } else {
                console.log('⚠️ Some issues found. Please review the results above.');
            }
        }, 1000);

    } else {
        results.push('❌ No Elementor form found on page');
    }

    return results;
}

// Auto-run if loaded in test environment
if (document.querySelector('.elementor-form')) {
    console.log('Auto-running Elementor fix verification...');
    setTimeout(verifyElementorFixes, 2000); // Wait for scripts to load
}

console.log('Verification script loaded. Run verifyElementorFixes() to test.');