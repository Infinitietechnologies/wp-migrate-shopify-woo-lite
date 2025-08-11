// settings.js
// Handles AJAX requests for saving, getting, and deleting settings via the WordPress backend

"use strict";

(function ($) {
    // Use the global wmsw_ajax object for consistency with the rest of the plugin
    const ajaxUrl = window.wmsw_ajax?.ajax_url || window.ajaxurl || '/wp-admin/admin-ajax.php';


    /**
     * Save a setting with UX feedback (spinner, button state)
     * @param {Object} params - { key, value, storeId, isGlobal, type, $form, $button, autoSave, showSuccessNotification }
     */
    function saveSetting({
        key,
        value,
        storeId = null,
        isGlobal = false,
        type = 'string',
        $form = null,
        $button = null,
        autoSave = false,
        showSuccessNotification = true
    }) {
        const loadingText = autoSave ? 'Auto-saving...' : 'Saving...';
        const originalHtml = $button?.length ? $button.html() : '';
        $button?.prop('disabled', true).html(`<span class="swi-spinner swi-spinner-small" aria-hidden="true"></span> ${loadingText}`);
        $form?.addClass('processing');

        $.post({
            url: ajaxUrl,
            data: {
                action: 'wmsw_save_setting',
                key,
                value,
                store_id: storeId,
                is_global: isGlobal ? 1 : 0,
                type,
                nonce: window.wmsw_ajax?.nonce || ''
            },
            dataType: 'json'
        })
            .done(response => {
                if (response.success) {
                    if (showSuccessNotification) {
                        const message = autoSave ? 'Setting auto-saved.' : (response.data?.message || 'Setting saved successfully.');
                        showNotification('success', message);
                    }
                    if ($form && !autoSave) $form[0].reset();
                } else {
                    showNotification('error', response.data?.message || (wmsw_ajax.strings.failed_to_save_setting || 'Failed to save setting.'));
                }
            })
            .fail((xhr, status, error) => {
                showNotification('error', analyzeAjaxError(xhr, status, error));
            })
            .always(() => {
                $button?.prop('disabled', false).html(originalHtml);
                $form?.removeClass('processing');
            });
    }

    /**
     * Get a setting with optional callback for handling the data
     * @param {Object} params - { key, storeId, isGlobal, $button, callback }
     */
    function getSetting({ key, storeId = null, isGlobal = false, $button = null, callback = null }) {
        let originalHtml = '';
        if ($button?.length) {
            originalHtml = $button.html();
            $button.prop('disabled', true).html('<span class="swi-spinner swi-spinner-small" aria-hidden="true"></span> ' + (wmsw_ajax.strings.loading_text || 'Loading...'));
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'wmsw_get_setting',
                key,
                store_id: storeId,
                is_global: isGlobal ? 1 : 0,
                nonce: window.wmsw_ajax?.nonce || ''
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    if (callback && typeof callback === 'function') {
                        callback(response.data);
                    } else {
                        showNotification('success', wmsw_ajax.strings.setting_loaded || 'Setting loaded.');
                    }
                } else {
                    showNotification('error', response.data?.message || (wmsw_ajax.strings.setting_not_found || 'Setting not found.'));
                }
            },
            error: function (xhr, status, error) {
                const errorMessage = analyzeAjaxError(xhr, status, error);
                showNotification('error', errorMessage);
            },
            complete: function () {
                if ($button?.length) {
                    $button.prop('disabled', false).html(originalHtml);
                }
            }
        });
    }

    /**
     * Delete a setting with UX feedback and confirmation
     * @param {Object} params - { key, storeId, isGlobal, $button, confirmDelete }
     */
    function deleteSetting({ key, storeId = null, isGlobal = false, $button = null, confirmDelete = true }) {
        const performDelete = () => {
            let originalHtml = '';
            if ($button?.length) {
                originalHtml = $button.html();
                $button.prop('disabled', true).html('<span class="swi-spinner swi-spinner-small" aria-hidden="true"></span> ' + (wmsw_ajax.strings.deleting_text || 'Deleting...'));
            }

            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'wmsw_delete_setting',
                    key,
                    store_id: storeId,
                    is_global: isGlobal ? 1 : 0,
                    nonce: window.wmsw_ajax?.nonce || ''
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        showNotification('success', response.data?.message || (wmsw_ajax.strings.setting_deleted || 'Setting deleted.'));
                    } else {
                        showNotification('error', response.data?.message || (wmsw_ajax.strings.failed_to_delete_setting || 'Failed to delete setting.'));
                    }
                },
                error: function (xhr, status, error) {
                    const errorMessage = analyzeAjaxError(xhr, status, error);
                    showNotification('error', errorMessage);
                },
                complete: function () {
                    if ($button?.length) {
                        $button.prop('disabled', false).html(originalHtml);
                    }
                }
            });
        };

        if (confirmDelete && window.wmsw_ConfirmBox) {
            window.wmsw_ConfirmBox.show(
                `Are you sure you want to delete the setting "${key}"?`,
                performDelete,
                null,
                {
                    title: wmsw_ajax.strings.delete_setting_title || 'Delete Setting',
                    okButtonText: 'Delete',
                    cancelButtonText: 'Cancel'
                }
            );
        } else {
            performDelete();
        }
    }

    /**
     * Analyze AJAX errors and return user-friendly messages
     * @param {Object} xhr - XMLHttpRequest object
     * @param {string} status - Status string
     * @param {string} error - Error string
     * @returns {string} User-friendly error message
     */
    function analyzeAjaxError(xhr, status, error) {
        const strings = window.wmsw_ajax?.strings || {};

        if (status === 'timeout') {
            return strings.timeout_error || 'Request timed out. Please try again.';
        }

        switch (xhr.status) {
            case 0:
                return strings.network_error || 'Network error. Please check your connection.';
            case 403:
                return strings.access_denied || 'Access denied. Please refresh the page and try again.';
            case 404:
                return strings.endpoint_not_found || 'Server endpoint not found.';
            case 500:
                return strings.server_error || 'Server error. Please try again later.';
            default: {
                const errorData = xhr.responseJSON;
                return errorData?.data?.message || strings.error || `AJAX error: ${error}`;
            }
        }
    }

    /**
     * Validate form fields before submission
     * @param {jQuery} $form - Form element
     * @returns {boolean} True if valid
     */
    function validateSettingsForm($form) {
        let isValid = true;

        // Remove previous error styling
        $form.find('.error').removeClass('error');

        // Validate required fields
        $form.find('[required]').each(function () {
            const $field = $(this);
            if (!$field.val().trim()) {
                $field.addClass('error');
                isValid = false;
            }
        });

        // Validate setting key format (alphanumeric, underscore, dash)
        const $keyField = $form.find('[name="key"], [name="setting_key"]');
        if ($keyField.length) {
            const keyValue = $keyField.val().trim();
            if (keyValue && !/^[a-zA-Z0-9_-]+$/.test(keyValue)) {
                $keyField.addClass('error');
                isValid = false;
                showNotification('error', wmsw_ajax.strings.setting_key_validation_error || 'Setting key can only contain letters, numbers, underscores, and dashes.', wmsw_ajax.strings.validation_error_title || 'Validation Error');
            }
        }

        if (!isValid) {
            const strings = window.wmsw_ajax?.strings || {};
            showNotification('error', strings.validation_error_message || wmsw_ajax.strings.please_fill_required_fields || 'Please fill in all required fields correctly.', strings.validation_error || wmsw_ajax.strings.validation_error_title || 'Validation Error');
        }

        return isValid;
    }

    /**
     * Enhanced form submission handler for settings forms
     * @param {jQuery} $form - Form element
     * @param {Object} options - Additional options
     */
    /**
     * Unified settings form submission handler (single or bulk)
     * @param {jQuery} $form - Form element
     * @param {Object} options - Additional options
     */
    // Prevent double submit and duplicate AJAX calls
    function handleSettingsFormSubmit($form, options = {}) {
        if ($form.data('swi-submitting')) {
            // Prevent duplicate submission
            return false;
        }
        $form.data('swi-submitting', true);

        const $submitButton = $form.find('button[type="submit"], input[type="submit"]');
        if (!validateSettingsForm($form)) {
            $form.data('swi-submitting', false);
            return false;
        }

        // Detect if this is a bulk settings form (main settings page)
        const isBulk = $form.find('input[name="action"]').val() === 'wmsw_save_settings';
        const loadingText = isBulk ? 'Saving Settings...' : 'Saving...';
        const originalHtml = $submitButton?.length ? $submitButton.html() : '';
        $submitButton?.prop('disabled', true).html(`<span class="swi-spinner swi-spinner-small" aria-hidden="true"></span> ${loadingText}`);
        $form?.addClass('processing');

        // Use bulk AJAX for main settings, otherwise use saveSetting
        if (isBulk) {
            $.post({
                url: ajaxUrl,
                data: $form.serialize(),
                dataType: 'json'
            })
                .done(response => {
                    // Enhanced error reporting for partial saves
                    if (response.success) {
                        showNotification('success', response.data?.message || (wmsw_ajax.strings.all_settings_saved_successfully || 'All settings have been saved successfully.'));
                    } else {
                        // If backend provides details, show which settings failed
                        if (response.data && response.data.failed_settings) {
                            let msg = response.data.message || 'Some settings could not be saved.';
                            msg += '<br><strong>Failed keys:</strong> ' + response.data.failed_settings.map(f => `<code>${f.key}</code> (${f.reason || 'Unknown error'})`).join(', ');
                            showNotification('error', msg);
                        } else {
                            showNotification('error', response.data?.message || (wmsw_ajax.strings.failed_to_save_settings || 'Failed to save settings.'));
                        }
                    }
                })
                .fail((xhr, status, error) => {
                    showNotification('error', analyzeAjaxError(xhr, status, error));
                })
                .always(() => {
                    $submitButton?.prop('disabled', false).html(originalHtml);
                    $form?.removeClass('processing');
                    $form.data('swi-submitting', false);
                });
        } else {
            // Extract form data for single setting
            const formData = {};
            $form.serializeArray().forEach(field => {
                formData[field.name] = field.value;
            });
            saveSetting({
                key: formData.key || formData.setting_key,
                value: formData.value || formData.setting_value,
                storeId: formData.store_id || null,
                isGlobal: formData.is_global === '1' || formData.is_global === 'true',
                type: formData.type || 'string',
                $form: $form,
                $button: $submitButton,
                ...options
            });
            // Always clear submitting flag after saveSetting completes (async)
            setTimeout(() => {
                $form.data('swi-submitting', false);
            }, 1500);
        }
        return true;
    }

    /**
     * Auto-save functionality for settings forms
     * @param {jQuery} $form - Form element
     * @param {number} delay - Delay in milliseconds (default: 2000)
     */
    function enableAutoSave($form, delay = 2000) {
        let saveTimeout;

        $form.find('input, textarea, select').on('input change', function () {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                if (validateSettingsForm($form)) {
                    handleSettingsFormSubmit($form, {
                        autoSave: true,
                        showSuccessNotification: false
                    });
                }
            }, delay);
        });
    }

    /**
     * Initialize settings form handlers
     */
    function initSettingsForms() {
        // Handle main settings form submission (bulk save)
        $(document).on('submit', '.swi-settings-form', function (e) {
            e.preventDefault();
            handleSettingsFormSubmit($(this));
        });

        // Handle delete setting buttons
        $(document).on('click', '.swi-delete-setting', function (e) {
            e.preventDefault();
            const $button = $(this);
            const key = $button.data('key');
            const storeId = $button.data('store-id') || null;
            const isGlobal = $button.data('global') === true || $button.data('global') === '1';

            if (key) {
                deleteSetting({
                    key,
                    storeId,
                    isGlobal,
                    $button,
                    confirmDelete: true
                });
            } else {
                showNotification('error', wmsw_ajax.strings.missing_setting_key || 'Missing setting key.', 'Error');
            }
        });

        // Handle get setting buttons
        $(document).on('click', '.swi-get-setting', function (e) {
            e.preventDefault();
            const $button = $(this);
            const key = $button.data('key');
            const storeId = $button.data('store-id') || null;
            const isGlobal = $button.data('global') === true || $button.data('global') === '1';
            const targetField = $button.data('target-field');

            if (key) {
                getSetting({
                    key,
                    storeId,
                    isGlobal,
                    $button,
                    callback: function (data) {
                        if (targetField) {
                            $(targetField).val(data.value);
                        }
                        showNotification('success', wmsw_ajax.strings.setting_loaded_successfully || 'Setting loaded successfully.');
                    }
                });
            } else {
                showNotification('error', wmsw_ajax.strings.missing_setting_key || 'Missing setting key.', 'Error');
            }
        });

        // Enable auto-save for forms with data-auto-save attribute
        $('.swi-settings-form[data-auto-save]').each(function () {
            const delay = parseInt($(this).data('auto-save-delay')) || 2000;
            enableAutoSave($(this), delay);
        });
    }

    // Initialize when DOM is ready
    $(document).ready(function () {
        initSettingsForms();
    });


    /**
     * Show a notification toast
     * @param {string} type Notification type: 'success', 'error', 'warning', 'info'
     * @param {string} message The notification message
     * @param {string} title Optional title for the notification
     * @param {string} dataType Optional data type for special formatting
    */
    function showNotification(type, message, title, dataType) {


        // Fallback implementation if backend.js notification is not available
        // Icon mapping
        const icons = {
            'success': 'dashicons-yes-alt',
            'error': 'dashicons-dismiss',
            'warning': 'dashicons-warning',
            'info': 'dashicons-info'
        };

        const iconClass = icons[type] || icons['info'];
        const notificationTitle = title || '';
        const dataAttribute = dataType ? ` data-type="${dataType}"` : '';

        // Create notification with proper structure
        const notification = $('<div class="swi-notification swi-notification-' + type + ' swi-notification-dismissible swi-toast-notification"' + dataAttribute + '>' +
            '<div class="swi-notification-content">' +
            '<div class="swi-notification-icon">' +
            '<span class="dashicons ' + iconClass + '"></span>' +
            '</div>' +
            '<div class="swi-notification-message">' +
            (notificationTitle ? '<div class="swi-notification-title">' + notificationTitle + '</div>' : '') +
            '<div class="swi-notification-text">' + message + '</div>' +
            '</div>' +
            '</div>' +
            '<button type="button" class="swi-notification-dismiss" aria-label="' + (wmsw_ajax.strings.dismiss_notification || 'Dismiss notification') + '">' +
            '<span class="dashicons dashicons-no-alt"></span>' +
            '</button>' +
            '</div>');

        // Position as toast notification on the right side
        notification.css({
            position: 'fixed',
            top: '20px',
            right: '20px',
            zIndex: 999999,
            minWidth: '320px',
            maxWidth: '450px',
            transform: 'translateX(100%)',
            opacity: '0',
            background: 'rgba(255, 255, 255, 0.95)',
            backdropFilter: 'blur(20px)',
            webkitBackdropFilter: 'blur(20px)',
            boxShadow: '0 20px 35px rgba(0, 0, 0, 0.15), 0 8px 20px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(255, 255, 255, 0.1)'
        });

        // Apply type-specific styling
        if (type === 'success') {
            notification.css('background', 'rgba(240, 253, 244, 0.95)');
        } else if (type === 'error') {
            notification.css('background', 'rgba(254, 242, 242, 0.95)');
        } else if (type === 'warning') {
            notification.css('background', 'rgba(255, 251, 235, 0.95)');
        } else if (type === 'info') {
            notification.css('background', 'rgba(239, 246, 255, 0.95)');
        }

        // Append to body
        $('body').append(notification);

        // Animate in
        setTimeout(function () {
            notification.css({
                transform: 'translateX(0)',
                opacity: '1',
                transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)'
            });
        }, 10);

        // Auto-remove success notifications after 5 seconds, errors after 8 seconds
        const autoRemoveDelay = type === 'success' ? 5000 : 8000;
        setTimeout(function () {
            hideNotificationGraceful(notification, { "duration": 400, "easing": "cubic-bezier(0.25, 0.46, 0.45, 0.94)" });
        }, autoRemoveDelay);

        // Add dismiss functionality
        notification.find('.swi-notification-dismiss').on('click', function (e) {
            e.preventDefault();
            hideNotificationGraceful(notification, { "duration": 400, "easing": "cubic-bezier(0.25, 0.46, 0.45, 0.94)" });
        });

        return notification;
    }


    /**
 * Hide a notification with smooth animation and proper cleanup
 * @param {jQuery} notification The notification element to hide
 * @param {Object} options Configuration options
 * @param {number} options.duration Animation duration in milliseconds (default: 300)
 * @param {string} options.easing CSS easing function (default: cubic-bezier)
 * @param {string} options.direction Animation direction ('right', 'left', 'up', 'down', 'fade')
 * @param {Function} options.onComplete Callback function after animation completes
 */
    function hideNotification(notification, options = {}) {
        // Default configuration
        const config = {
            duration: 300,
            easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
            direction: 'right',
            onComplete: null,
            ...options
        };

        // Validate input
        if (!notification || !notification.length) {
            console.warn('hideNotification: Invalid notification element');
            return Promise.resolve();
        }

        // Prevent multiple hide attempts
        if (notification.data('hiding')) {
            return Promise.resolve();
        }
        notification.data('hiding', true);

        // Define animation transforms
        const animations = {
            right: { transform: 'translateX(100%)', opacity: '0' },
            left: { transform: 'translateX(-100%)', opacity: '0' },
            up: { transform: 'translateY(-100%)', opacity: '0' },
            down: { transform: 'translateY(100%)', opacity: '0' },
            fade: { transform: 'scale(0.8)', opacity: '0' }
        };

        const animationProps = animations[config.direction] || animations.right;

        // Return a Promise for better async handling
        return new Promise((resolve) => {
            // Apply animation styles
            notification.css({
                ...animationProps,
                transition: `all ${config.duration}ms ${config.easing}`,
                pointerEvents: 'none' // Prevent interaction during animation
            });

            // Clean up after animation
            setTimeout(() => {
                // Execute callback if provided
                if (typeof config.onComplete === 'function') {
                    try {
                        config.onComplete(notification);
                    } catch (error) {
                        console.error('hideNotification callback error:', error);
                    }
                }

                // Remove element and resolve promise
                notification.remove();
                resolve();
            }, config.duration);
        });
    }

    // Enhanced version with fade-out and scale effect
    function hideNotificationGraceful(notification, options = {}) {
        const config = {
            duration: 400,
            easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)',
            ...options
        };

        if (!notification?.length) return Promise.resolve();
        if (notification.data('hiding')) return Promise.resolve();

        notification.data('hiding', true);

        return new Promise((resolve) => {
            // First phase: slight scale and opacity change
            notification.css({
                transform: 'scale(0.98) translateX(10px)',
                opacity: '0.8',
                transition: `all ${config.duration * 0.3}ms ${config.easing}`,
                pointerEvents: 'none'
            });

            // Second phase: slide out with fade
            setTimeout(() => {
                notification.css({
                    transform: 'scale(0.9) translateX(100%)',
                    opacity: '0',
                    transition: `all ${config.duration * 0.7}ms ${config.easing}`
                });
            }, config.duration * 0.3);

            // Cleanup
            setTimeout(() => {
                config.onComplete?.(notification);
                notification.remove();
                resolve();
            }, config.duration);
        });
    }

    // Expose to global scope if needed
    window.wmsw_SettingsAPI = {
        saveSetting,
        getSetting,
        deleteSetting,
        handleSettingsFormSubmit,
        validateSettingsForm,
        enableAutoSave,
        initSettingsForms
    };
})(jQuery);
