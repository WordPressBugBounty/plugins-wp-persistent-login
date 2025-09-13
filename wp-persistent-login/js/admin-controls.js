/**
 * Check if user clicks .js-send-test-email button
 * 
 * ! response message not displaying
 */
function sendNewLoginTestEmail() {

    var email_field = jQuery('.js-test-email');
    var email = email_field.val();
    var nonce = jQuery('#update_login_history_settings_nonce').val();
    var response_message = jQuery('.js-test-email-response');

    response_message.empty();
    
    if (email === '') {
        alert('Please enter email address');
        return false;
    }
    
    var data = {
        action: 'wppl_send_test_email',
        email: email,
        nonce: nonce
    };

    jQuery.ajax({
        type: 'POST',
        dataType: 'json',
        url: ajaxurl,
        data: data,
        complete: function(response) {
            var message = response.responseJSON.data;
            email_field.val('');
            response_message.html(message);
        }
    });

}

/**
 * Check if user clicks .js-send-test-inactivity-email button
 */
function sendInactivityTestEmail() {
    
    var email_field = jQuery('.js-test-inactivity-email');
    var email = email_field.val();
    var nonce = jQuery('#update_login_history_settings_nonce').val();
    var response_message = jQuery('.js-test-inactivity-email-response');

    response_message.empty();

    if (email === '') {
        alert('Please enter an email address');
        return false;
    }

    var data = {
        action: 'wppl_send_inactivity_test_email',
        email: email,
        nonce: nonce
    };

    jQuery.ajax({
        type: 'POST',
        dataType: 'json',
        url: ajaxurl,
        data: data,
        complete: function(response) {
            var message = response.responseJSON.data;
            email_field.val('');
            response_message.html(message);
        }
    });

}

// ================= WPPL Toast Utility (no dependencies) =================
(function (window, document) {
    if (window.WPPLToast) { return; } // Prevent double registration

    const TOAST_DEFAULT_DURATION = 4000;
    const MAX_VISIBLE = 4;

    let container = null;
    let queue = [];
    let active = [];

    function ensureContainer() {
        if (container) return;
        container = document.createElement('div');
        container.id = 'wppl-toast-container';
        container.setAttribute('aria-live', 'polite');
        container.setAttribute('aria-atomic', 'false');
        document.body.appendChild(container);
    }

    function createToastElement(message, type, id) {
        const toast = document.createElement('div');
        toast.className = 'wppl-toast wppl-toast-' + type;
        toast.setAttribute('role', 'status');
        toast.setAttribute('data-toast-id', id);

        // Message span
        const textSpan = document.createElement('span');
        textSpan.className = 'wppl-toast-message';
        textSpan.textContent = message;
        toast.appendChild(textSpan);

        // Close button
        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'wppl-toast-close';
        closeBtn.setAttribute('aria-label', 'Dismiss notification');
        closeBtn.innerHTML = '&times;';
        closeBtn.addEventListener('click', () => dismissToast(id, true));
        toast.appendChild(closeBtn);

        return toast;
    }

    function speak(message) {
        // Optional: leverage WP a11y if present
        if (window.wp && wp.a11y && typeof wp.a11y.speak === 'function') {
            wp.a11y.speak(message);
        }
    }

    function showNext() {
        if (!queue.length) return;
        if (active.length >= MAX_VISIBLE) return;

        const next = queue.shift();
        active.push(next);

        const el = createToastElement(next.message, next.type, next.id);
        next.element = el;
        container.appendChild(el);

        // Allow CSS transition
        requestAnimationFrame(() => {
            el.classList.add('visible');
        });

        speak(next.message);

        if (next.duration > 0) {
            next.timer = setTimeout(() => dismissToast(next.id), next.duration);
        }
    }

    function dismissToast(id, userInitiated = false) {
        const idx = active.findIndex(t => t.id === id);
        if (idx === -1) return;
        const toastObj = active[idx];
        active.splice(idx, 1);

        if (toastObj.timer) {
            clearTimeout(toastObj.timer);
        }

        if (toastObj.element) {
            toastObj.element.classList.remove('visible');
            // Remove after transition
            setTimeout(() => {
                if (toastObj.element && toastObj.element.parentNode) {
                    toastObj.element.parentNode.removeChild(toastObj.element);
                }
            }, 300);
        }

        // If user manually closed, optionally announce dismissal
        if (userInitiated) {
            speak('Notification dismissed');
        }

        // Show more if queued
        showNext();
    }

    function add(message, opts = {}) {
        ensureContainer();
        const id = 'wppl-toast-' + Date.now() + '-' + Math.random().toString(16).slice(2);
        const toast = {
            id,
            message: String(message),
            type: (opts.type || 'info').toLowerCase(), // info | success | warning | error
            duration: typeof opts.duration === 'number' ? opts.duration : TOAST_DEFAULT_DURATION,
            element: null,
            timer: null
        };
        queue.push(toast);
        showNext();
        return id;
    }

    function clearAll() {
        // Dismiss active
        active.slice().forEach(t => dismissToast(t.id));
        // Empty queue
        queue = [];
    }

    window.WPPLToast = {
        show: add,
        success: (m, o={}) => add(m, Object.assign({ type: 'success' }, o)),
        info:    (m, o={}) => add(m, Object.assign({ type: 'info' }, o)),
        warning: (m, o={}) => add(m, Object.assign({ type: 'warning' }, o)),
        error:   (m, o={}) => add(m, Object.assign({ type: 'error' }, o)),
        dismiss: dismissToast,
        clear: clearAll
    };
})(window, document);
// ================= End WPPL Toast Utility =================

/**
 * Track changes in forms to warn users about unsaved changes
 */
let formHasUnsavedChanges = false;

function trackFormChanges() {
    // Mark the form as having unsaved changes
    formHasUnsavedChanges = true;
}

function resetFormChangesFlag() {
    // Reset the form changes flag
    formHasUnsavedChanges = false;
}

/**
 * Show warning when user tries to navigate away with unsaved changes
 */
function setupBeforeUnloadWarning() {
    window.addEventListener('beforeunload', function(e) {
        if (formHasUnsavedChanges) {
            // Standard message (browsers will show their own standardized message)
            const message = 'You have unsaved changes. Are you sure you want to leave this page?';
            e.returnValue = message; // Standard for most browsers
            return message; // For older browsers
        }
    });
}

/**
 * Handle feature toggle switches
 */
function handleFeatureToggle() {
    var toggleSwitch = jQuery(this);
    var feature = toggleSwitch.data('feature');
    var optionName = toggleSwitch.data('option-name');
    var isEnabled = toggleSwitch.prop('checked') ? 1 : 0;    var featureName = getFeatureName(feature);
    var labelId = feature.replace('_', '-') + '-label';
    var featureBox = toggleSwitch.closest('.feature-box');
    
    // Mark that we have unsaved changes
    trackFormChanges();
    
    // Update screen reader text
    var screenReaderText = isEnabled ? 
        featureName + ' is enabled. Click to disable.' : 
        featureName + ' is disabled. Click to enable.';
    jQuery('#' + labelId).text(screenReaderText);
    
    // Update feature box class for visual feedback
    if (isEnabled) {
        featureBox.removeClass('feature-disabled');
    } else {
        featureBox.addClass('feature-disabled');
    }
    
    // Disable the toggle while processing
    toggleSwitch.prop('disabled', true);
    
    var data = {
        action: 'wppl_toggle_feature',
        feature: feature,
        option_name: optionName,
        enabled: isEnabled,
        nonce: wppl_nonce // This nonce is localized from PHP
    };
    
    jQuery.ajax({
        type: 'POST',
        dataType: 'json',
        url: ajaxurl,
        data: data,
        success: function(response) {
            if (response.success) {
                // Log success notification
                console.log('Feature ' + feature + ' ' + (isEnabled ? 'enabled' : 'disabled'));

                // Display success notification toast within the ui
                var msg = 'Feature ' + feature + ' ' + (isEnabled ? 'enabled' : 'disabled');
                if (isEnabled) {
                    window.WPPLToast.success(msg);
                } else {
                    window.WPPLToast.info(msg);
                }

                // Reset the form changes flag since the change was saved via AJAX
                resetFormChangesFlag();
            } else {
                // Revert toggle if there was an error
                toggleSwitch.prop('checked', !isEnabled);
                alert(response.data.message || 'Error updating feature status');
            }
        },
        error: function() {
            // Revert toggle on error
            toggleSwitch.prop('checked', !isEnabled);
            alert('Error connecting to server. Please try again.');
        },
        complete: function() {
            // Re-enable the toggle
            toggleSwitch.prop('disabled', false);
        }
    });
}

/**
 * Get human-readable feature name
 */
function getFeatureName(feature) {
    switch (feature) {
        case 'persistent_login':
            return 'Persistent Login';
        case 'active_logins':
            return 'Active Logins';
        case 'login_history':
            return 'Login History';
        default:
            return feature.replace('_', ' ');
    }
}

/**
 * Live User Count Update Functions
 */
let userCountPollingInterval = null;
const USER_COUNT_POLLING_FREQUENCY = 5000; // Poll every 5 seconds

function startUserCountPolling() {
    // Clear any existing interval
    if (userCountPollingInterval) {
        clearInterval(userCountPollingInterval);
    }
    
    // Start polling
    userCountPollingInterval = setInterval(updateUserCountStatus, USER_COUNT_POLLING_FREQUENCY);
    
    // Also update immediately
    updateUserCountStatus();
}

function stopUserCountPolling() {
    if (userCountPollingInterval) {
        clearInterval(userCountPollingInterval);
        userCountPollingInterval = null;
    }
}

function updateUserCountStatus() {
    jQuery.ajax({
        type: 'POST',
        dataType: 'json',
        url: ajaxurl,
        data: {
            action: 'wppl_get_user_count_status',
            nonce: wppl_nonce
        },
        success: function(response) {
            if (response.success) {
                const data = response.data;
                
                // If count is running, update the UI
                if (data.is_counting) {
                    updateUserCountUI(data);
                } else {
                    // If count has finished, stop polling and reload the page
                    stopUserCountPolling();
                    window.location.reload();
                }
            }
        }
    });
}

function updateUserCountUI(data) {
    // Update current counting role message
    if (data.current_role_message) {
        const usageBreakdownEl = jQuery('.wppl-user-count-status');
        if (usageBreakdownEl.length > 0) {
            usageBreakdownEl.html(data.current_role_message);
        }
    }
    
    // Update role counts
    if (data.role_counts) {
        // First, remove current-counting-role class from all roles
        jQuery('.user-roles-breakdown .role').removeClass('current-counting-role');
        
        // Iterate through roles and update their values
        Object.keys(data.role_counts).forEach(role => {
            // Find role element by data attribute
            const roleEl = jQuery(`.user-roles-breakdown .role[data-role="${role}"]`);
            
            if (roleEl.length) {
                // Update count
                roleEl.find('.value').text(data.role_counts[role]);
                
                // Update current counting role styling
                if (role === data.current_role) {
                    roleEl.addClass('current-counting-role');
                }
            }
        });
    }
}

function formatRoleName(role) {
    // Handle multiple spaces, underscores, and hyphens
    return role.replace(/[_-]/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

// Helper function to check if user count is running
function checkIfUserCountIsRunning() {
    const statusEl = jQuery('.wppl-user-count-status');
    if (statusEl.length > 0) {
        const countingMessage = statusEl.text();
        return countingMessage.indexOf('user count is currently running') !== -1;
    }
    return false;
}

jQuery(document).ready(function () {

    // Set up beforeunload warning
    setupBeforeUnloadWarning();

    // Track changes on input fields, select dropdowns, and radio buttons
    jQuery('input:not([type="submit"]), select, textarea, input[type="radio"]').on('change', function() {
        trackFormChanges();
    });

    // new login history notification email test
    var send_test_email_button = jQuery('.js-send-test-email');
    if (send_test_email_button.length > 0) {

        send_test_email_button.on('click', function(e) {
            e.preventDefault();
            sendNewLoginTestEmail();
        });

    }


    // account inactivity notification email test
    var send_test_email_button = jQuery('.js-send-test-inactivity-email');
    if (send_test_email_button.length > 0) {

        send_test_email_button.on('click', function(e) {
            e.preventDefault();
            sendInactivityTestEmail();
        });

    }
      // Feature toggle switches
    var featureToggles = jQuery('.feature-toggle');
    if (featureToggles.length > 0) {
        // Initialize disabled state classes
        featureToggles.each(function() {
            var toggle = jQuery(this);
            var featureBox = toggle.closest('.feature-box');
            
            if (!toggle.prop('checked')) {
                featureBox.addClass('feature-disabled');
            }
        });
        
        featureToggles.on('change', handleFeatureToggle);
    }

    // Reset form changes flag when form is submitted
    jQuery('form').on('submit', function() {
        resetFormChangesFlag();
    });

    // Check if we're on the dashboard page with user count running
    if (jQuery('.users_page_wp-persistent-login').length && 
        jQuery('.usage-breakdown').length &&
        checkIfUserCountIsRunning()) {
        startUserCountPolling();
    }
});

// Extend the document ready function to handle our custom settings toggles
jQuery(document).ready(function($) {
    // Handle toggle switches on the settings page
    var settingsToggles = $('#duplicate-sessions-toggle, #hide-dashboard-stats-toggle, #limit-active-logins-toggle');
    
    if (settingsToggles.length > 0) {
        // Apply proper styling to toggles on page load
        settingsToggles.each(function() {
            var toggleSwitch = $(this);
            
            // Hide checkbox visually
            toggleSwitch.css({
                'opacity': '0',
                'width': '0',
                'height': '0',
                'position': 'absolute',
                'z-index': '1',
                'cursor': 'pointer'
            });
            
            // Update toggle visibility based on checked state
            var isChecked = toggleSwitch.prop('checked');
            var toggleId = toggleSwitch.attr('id');
            var labelId = toggleId.replace('-toggle', '-label');
            
            // Show/hide appropriate toggle text
            if (isChecked) {
                toggleSwitch.siblings('.toggle-text-off').hide();
                toggleSwitch.siblings('.toggle-text-on').show();
            } else {
                toggleSwitch.siblings('.toggle-text-on').hide();
                toggleSwitch.siblings('.toggle-text-off').show();
            }
        });
        
        // Handle toggle changes
        settingsToggles.on('change', function() {
            var toggleSwitch = $(this);
            var isChecked = toggleSwitch.prop('checked');
            var toggleId = toggleSwitch.attr('id');
            var labelId = toggleId.replace('-toggle', '-label');
            
            // Mark that we have unsaved changes
            trackFormChanges();
            
            // Update toggle text visibility
            if (isChecked) {
                toggleSwitch.siblings('.toggle-text-off').hide();
                toggleSwitch.siblings('.toggle-text-on').show();
            } else {
                toggleSwitch.siblings('.toggle-text-on').hide();                toggleSwitch.siblings('.toggle-text-off').show();
            }
            
            // Update screen reader text
            var settingName = '';
            var screenReaderText = '';
            
            if (toggleId === 'duplicate-sessions-toggle') {
                settingName = 'Duplicate Sessions';
                screenReaderText = isChecked ? 
                    'Duplicate Sessions is enabled. Click to disable.' : 
                    'Duplicate Sessions is disabled. Click to enable.';
            } else if (toggleId === 'hide-dashboard-stats-toggle') {
                settingName = 'Dashboard Stats';
                screenReaderText = isChecked ? 
                    'Dashboard Stats are hidden. Click to show.' : 
                    'Dashboard Stats are visible. Click to hide.';
            } else if (toggleId === 'limit-active-logins-toggle') {
                settingName = 'Limit Active Logins';
                screenReaderText = isChecked ? 
                    'Limit Active Logins is enabled. Click to disable.' : 
                    'Limit Active Logins is disabled. Click to enable.';
            }
            
            $('#' + labelId).text(screenReaderText);
        });
    }
});