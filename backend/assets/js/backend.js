/**
 * Backend JavaScript for Shopify WooCommerce Importer
 */
'use strict';
jQuery(document).ready(function ($) {

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
            hideNotification(notification);
        }, autoRemoveDelay);

        // Add dismiss functionality
        notification.find('.swi-notification-dismiss').on('click', function (e) {
            e.preventDefault();
            hideNotification(notification);
        });

        return notification;
    }

    /**
     * Hide a notification with animation
     * @param {jQuery} notification The notification element to hide
     */
    function hideNotification(notification) {
        notification.css({
            transform: 'translateX(100%)',
            opacity: '0',
            transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)'
        });
        setTimeout(function () {
            notification.remove();
        }, 300);
    }
    // Make showNotification available globally
    window.showNotification = showNotification;
    window.swiShowToast = showNotification; // Alias for compatibility

    // Form reset functionality moved to order-importer.js

    /**
     * Generic preview function for different data types
     * @param {string} storeId The store ID
     * @param {string} dataType The type of data to preview
     */
    function showPreview(storeId, dataType) {
        // For now, show a notification that the feature is not yet implemented
        // showNotification('info', `${dataType.charAt(0).toUpperCase() + dataType.slice(1)} preview functionality is not yet implemented.`, 'Feature Not Available');
    }

    /**
     * Poll for import progress updates
     * @param {string} progressKey The progress key to track
     */
    function pollImportProgress(progressKey, importType = 'products') {
        let failureCount = 0;
        const maxFailures = 5; // Stop polling after 5 consecutive failures

        // Determine the correct AJAX action based on import type
        const actionMap = {
            'products': 'wmsw_get_import_progress',
            'orders': 'wmsw_get_orders_import_progress',
            'customers': 'wmsw_get_customers_import_progress'
        };

        const action = actionMap[importType] || 'wmsw_get_import_progress';

        const pollInterval = setInterval(() => {
            $.ajax({
                url: wmsw_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: action,
                    import_id: progressKey, // Try import_id first
                    progress_key: progressKey, // Also send progress_key as fallback
                    nonce: wmsw_ajax.nonce
                },
                success: function (response) {
                    if (response.success && response.data) {
                        failureCount = 0; // Reset failure count on success
                        updateProgressDisplay(response.data);

                        // Stop polling if import is complete
                        if (response.data.status === 'completed' || response.data.status === 'error') {
                            clearInterval(pollInterval);
                            handleImportComplete(response.data);
                        }
                    } else {
                        failureCount++;

                        // Stop polling after too many failures
                        if (failureCount >= maxFailures) {
                            clearInterval(pollInterval);
                            showNotification('warning', wmsw_ajax.strings.unable_to_get_progress_updates || 'Unable to get import progress updates. Import may still be running in the background.', wmsw_ajax.strings.progress_update_failed || 'Progress Update Failed');
                        }
                    }
                },
                error: function (xhr, status, error) {
                    failureCount++;

                    // Stop polling after too many failures
                    if (failureCount >= maxFailures) {
                        clearInterval(pollInterval);
                        showNotification('warning', wmsw_ajax.strings.unable_to_get_progress_updates || 'Unable to get import progress updates. Import may still be running in the background.', wmsw_ajax.strings.connection_failed_title || 'Connection Failed');
                    }
                }
            });
        }, 2000); // Poll every 2 seconds

        // Store interval ID so we can clear it if needed
        window.importProgressInterval = pollInterval;
    }

    /**
     * Update the progress display
     * @param {Object} progressData The progress data from server
     */
    function updateProgressDisplay(progressData) {
        const percentage = progressData.percentage || 0;
        const message = progressData.message || 'Processing...';
        const processed = progressData.processed || 0;
        const total = progressData.total || 0;

        // Update progress bar
        $('.swi-progress-fill').css('width', percentage + '%');
        $('#progress-percentage').text(percentage + '%');

        // Update progress text
        if (total > 0) {
            $('#progress-text').text(`${message} (${processed}/${total})`);
        } else {
            $('#progress-text').text(message);
        }

        // Add any log messages
        if (progressData.log && progressData.log.length > 0) {
            const $progressLog = $('#progress-log');
            progressData.log.forEach(logEntry => {
                $progressLog.append(`<div class="progress-log-entry">${logEntry}</div>`);
            });
            // Scroll to bottom
            $progressLog.scrollTop($progressLog[0].scrollHeight);
        }
    }

    /**
     * Handle import completion
     * @param {Object} progressData The final progress data
     */
    function handleImportComplete(progressData) {
        const isSuccess = progressData.status === 'completed';
        const message = progressData.message || (isSuccess ? (wmsw_ajax.strings.import_completed_successfully || 'Import completed successfully!') : (wmsw_ajax.strings.import_failed || 'Import failed'));

        // Show completion notification
        showNotification(
            isSuccess ? 'success' : 'error',
            message,
            isSuccess ? (wmsw_ajax.strings.import_complete || 'Import Complete') : (wmsw_ajax.strings.import_failed_title || 'Import Failed')
        );

        // Update final progress display
        if (isSuccess) {
            $('.swi-progress-fill').css('width', '100%');
            $('#progress-percentage').text('100%');
            $('#progress-text').text(wmsw_ajax.strings.import_completed_successfully);
        }

        // Note: Form reset is now handled by individual import components
    }

    /**
     * Stop any running import progress polling
     */
    function stopProgressPolling() {
        if (window.importProgressInterval) {
            clearInterval(window.importProgressInterval);
            window.importProgressInterval = null;
        }
    }

    // Stop polling when user leaves the page
    $(window).on('beforeunload', function () {
        stopProgressPolling();
    });

    // Tab functionality with sliding indicator
    function updateTabIndicator($activeTab) {
        if (!$('.swi-tab-indicator').length) {
            $('.swi-tab-nav').append('<div class="swi-tab-indicator"></div>');
        }

        const $indicator = $('.swi-tab-indicator');
        const leftOffset = $activeTab.position().left;

        $indicator.css({
            left: leftOffset + 'px',
            width: $activeTab.outerWidth() + 'px'
        });
    }

    // Initialize tab indicator on page load
    function initTabIndicator() {
        const $activeTab = $('.swi-tab-button.active');
        if ($activeTab.length) {
            updateTabIndicator($activeTab);
        }
    }

    // Run on page load
    initTabIndicator();    // Check if we have a saved tab in localStorage
    function loadSavedTab() {
        const savedTab = localStorage.getItem('wmsw_active_tab');

        if (savedTab) {
            const $tabButton = $(`.swi-tab-button[data-tab="${savedTab}"]`);

            if ($tabButton.length) {
                // Update tab buttons
                $('.swi-tab-button').removeClass('active');
                $tabButton.addClass('active');

                // Update tab content
                $('.swi-tab-pane').removeClass('active');
                $('#' + savedTab + '-tab').addClass('active');

                // Update indicator position
                updateTabIndicator($tabButton);
            }
        }
    }

    // Load saved tab on page load
    loadSavedTab();

    // Handle tab clicks
    $('.swi-tab-button').on('click', function (e) {
        e.preventDefault();

        let tabId = $(this).data('tab');
        const $this = $(this);

        // Save active tab to localStorage
        localStorage.setItem('wmsw_active_tab', tabId);

        // Update tab buttons
        $('.swi-tab-button').removeClass('active');
        $this.addClass('active');

        // Update tab content with animation
        $('.swi-tab-pane').removeClass('active');
        $('#' + tabId + '-tab').addClass('active');

        // Update indicator position
        updateTabIndicator($this);
    });

    // Update indicator on window resize
    $(window).on('resize', function () {
        initTabIndicator();
    });


    // Utility function for safe try-catch operations with notifications
    function safeExecute(operation, errorMessage = wmsw_ajax.strings.an_unexpected_error_occurred || 'An unexpected error occurred') {
        try {
            return operation();
        } catch (error) {
            showNotification('error', errorMessage, 'Error');
            return false;
        }
    }

    // Utility function for safe JSON parsing
    function safeParseResponse(response, fallbackMessage = wmsw_ajax.strings.invalid_server_response || 'Invalid server response') {
        return safeExecute(() => {
            if (!response) {
                throw new Error('Empty response');
            }
            return response;
        }, fallbackMessage);
    }

    // Main form handler
    $('#store-connection-form').on('submit', function (e) {
        e.preventDefault();

        const $form = $(this);
        const $button = $('#store-connection-submit');
        const originalText = $button.text();

        // Prevent double submissions
        if ($button.prop('disabled')) {
            return false;
        }

        // Validate form before submitting
        if (!validateForm($form)) {
            return false;
        }

        // Show loading state
        setLoadingState($button, true);
        $form.addClass('processing');

        // Prepare form data
        const formData = prepareFormData($form);

        $.ajax({
            url: wmsw_ajax.ajax_url,
            type: 'POST',
            data: formData,
            dataType: 'json',
            timeout: 30000,

            beforeSend: function (xhr) {
                xhr.setRequestHeader('Cache-Control', 'no-cache');
            },

            success: function (response) {
                handleSuccessResponse($form, response);
            },

            error: function (xhr, status, error) {
                handleErrorResponse(xhr, status, error, $form);
            },

            complete: function (xhr, status) {
                setLoadingState($button, false, originalText);
                $form.removeClass('processing');
                $form.trigger('store-connection-complete', [xhr, status]);
            }
        });
    });

    // Helper function to set loading state
    function setLoadingState($button, isLoading, originalText = '') {
        if (isLoading) {
            // Store original content if not provided
            if (!originalText) {
                originalText = $button.data('original-text') || $button.text();
                $button.data('original-text', originalText);
            }

            // Add loading class and spinner
            $button
                .prop('disabled', true)
                .addClass('swi-loading')
                .attr('aria-busy', 'true')
                .html('<span class="swi-spinner-inline"></span> ' + (wmsw_ajax.strings.connecting || 'Loading...'));

            // Disable all form inputs to prevent interaction
            $button.closest('form').find('input, select, textarea, button').prop('disabled', true);

        } else {
            // Restore original state
            $button
                .prop('disabled', false)
                .removeClass('swi-loading')
                .removeAttr('aria-busy')
                .text(originalText);

            // Re-enable all form inputs
            $button.closest('form').find('input, select, textarea, button').prop('disabled', false);
        }
    }

    // Helper function to prepare form data
    function prepareFormData($form) {
        let formData = $form.serialize();

        // Add WordPress action if not present
        if (!formData.includes('action=')) {
            formData += '&action=your_ajax_action'; // Replace with your actual action
        }

        return formData;
    }

    // Success response handler
    function handleSuccessResponse($form, response) {
        safeExecute(() => {
            const parsedResponse = safeParseResponse(response, wmsw_ajax.strings.failed_to_parse_server_response || 'Failed to parse server response');

            if (parsedResponse && parsedResponse.success) {
                const message = parsedResponse.data?.message || wmsw_ajax.strings.success;
                showNotification('success', message, 'Success!');

                // Reset form and trigger success event
                $form[0].reset();
                $form.trigger('store-connection-success', [parsedResponse]);
            } else {
                handleServerError(parsedResponse);
            }
        }, wmsw_ajax.strings.failed_to_process_server_response || 'Failed to process server response');
    }

    // Error response handler
    function handleErrorResponse(xhr, status, error, $form) {
        safeExecute(() => {
            const errorInfo = analyzeError(xhr, status, error);

            showNotification(
                'error',
                errorInfo.message,
                'Connection Failed',
                errorInfo.isSecurityError ? 'security-error' : null
            );

            $form.trigger('store-connection-error', [xhr, status, error]);
        }, wmsw_ajax.strings.failed_to_handle_error_response || 'Failed to handle error response');
    }

    // Server error handler for success responses with error data
    function handleServerError(response) {
        const isSecurityError = response?.data?.error_type === 'security';
        const errorMessage = response?.data?.message || wmsw_ajax.strings.error;

        showNotification(
            'error',
            errorMessage,
            'Connection Failed',
            isSecurityError ? 'security-error' : null
        );
    }

    // Error analysis function
    function analyzeError(xhr, status, error) {
        let errorMessage = wmsw_ajax.strings.error;
        let isSecurityError = false;

        return safeExecute(() => {
            const errorData = xhr.responseJSON;

            // Analyze error by status code
            switch (xhr.status) {
                case 0:
                    errorMessage = 'Network error. Please check your connection.';
                    break;
                case 403:
                    errorMessage = 'Access denied. Please refresh the page and try again.';
                    isSecurityError = true;
                    break;
                case 404:
                    errorMessage = 'Server endpoint not found.';
                    break;
                case 500:
                    errorMessage = 'Server error. Please try again later.';
                    break;
                default:
                    if (status === 'timeout') {
                        errorMessage = 'Request timed out. Please try again.';
                    } else if (errorData?.data?.message) {
                        errorMessage = errorData.data.message;
                    }

                    if (errorData?.data?.error_type === 'security') {
                        isSecurityError = true;
                    }
            }

            return { message: errorMessage, isSecurityError };
        }, wmsw_ajax.strings.error_analyzing_server_response || 'Error analyzing server response') || { message: errorMessage, isSecurityError };
    }

    // Form validation function
    function validateForm($form) {
        return safeExecute(() => {
            let isValid = true;

            // Remove previous error styling
            $form.find('.error').removeClass('error');

            // Validate required fields
            isValid = validateRequiredFields($form) && isValid;

            // Validate email fields
            isValid = validateEmailFields($form) && isValid;

            // Show validation error if form is invalid
            if (!isValid) {
                showNotification('error', wmsw_ajax.strings.please_fill_required_fields || 'Please fill in all required fields correctly.', wmsw_ajax.strings.validation_error_title || 'Validation Error');
            }

            return isValid;
        }, wmsw_ajax.strings.form_validation_failed || 'Form validation failed') || false;
    }

    // Validate required fields
    function validateRequiredFields($form) {
        let isValid = true;

        $form.find('[required]').each(function () {
            const $field = $(this);
            if (!$field.val().trim()) {
                $field.addClass('error');
                isValid = false;
            }
        });

        return isValid;
    }

    // Validate email fields
    function validateEmailFields($form) {
        let isValid = true;

        $form.find('input[type="email"]').each(function () {
            const $field = $(this);
            const email = $field.val().trim();
            if (email && !isValidEmail(email)) {
                $field.addClass('error');
                isValid = false;
            }
        });

        return isValid;
    }

    // Email validation helper
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Initialize progress bars with dynamic width
     */
    function initProgressBars() {
        $('.swi-progress-fill[data-width]').each(function() {
            const $progressFill = $(this);
            const width = $progressFill.attr('data-width');
            if (width !== undefined && width !== null) {
                $progressFill.css('--progress-width', width + '%');
            }
        });
    }

    /**
     * Show modal by removing d-none class
     * @param {string} modalId The ID of the modal to show
     */
    function showModal(modalId) {
        const $modal = $('#' + modalId);
        if ($modal.length) {
            $modal.removeClass('d-none');
        }
    }

    /**
     * Hide modal by adding d-none class
     * @param {string} modalId The ID of the modal to hide
     */
    function hideModal(modalId) {
        const $modal = $('#' + modalId);
        if ($modal.length) {
            $modal.addClass('d-none');
        }
    }

    /**
     * Toggle context content visibility
     * @param {string} logId The log ID to toggle
     */
    function toggleContextContent(logId) {
        const $contextContent = $('#log-context-' + logId);
        if ($contextContent.length) {
            $contextContent.toggleClass('d-none');
        }
    }


    // Test connection button handler
    $('#test-connection, #test-connection-btn').on('click', function (e) {
        e.preventDefault();

        const $button = $(this);
        const $form = $('#store-connection-form');
        const originalText = $button.text();

        // Validate form first
        const shopDomain = $form.find('input[name="shop_domain"]').val();
        const accessToken = $form.find('input[name="access_token"]').val();

        if (!shopDomain || !accessToken) {
            showNotification('error', wmsw_ajax.strings.please_fill_shop_domain_and_token, wmsw_ajax.strings.validation_error);
            return;
        }

        // Show loading state
        $button.prop('disabled', true).text(wmsw_ajax.strings.connecting);
        $.ajax({
            url: wmsw_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wmsw_test_connection',
                nonce: wmsw_ajax.nonce,
                shop_domain: $form.find('input[name="shop_domain"]').val(),
                access_token: $form.find('input[name="access_token"]').val(),
                api_version: $form.find('input[name="api_version"]').val(),
            },
            success: function (response) {
                if (response.success) {
                    showNotification('success', response.data.message || wmsw_ajax.strings.success, wmsw_ajax.strings.success_title);
                } else {
                    // Check if this is a security/permission error based on error_type
                    const isSecurityError = response.data && response.data.error_type === 'security';

                    showNotification(
                        'error',
                        response.data.message || wmsw_ajax.strings.error,
                        wmsw_ajax.strings.connection_failed,
                        isSecurityError ? 'security-error' : null
                    );
                }
            },
            error: function (xhr, status, error) {
                const errorData = xhr.responseJSON;
                const errorMessage = errorData?.data?.message || wmsw_ajax.strings.error;
                const isSecurityError = xhr.status === 403 ||
                    (errorData && !errorData.success && errorData.data?.error_type === 'security');

                showNotification(
                    'error',
                    errorMessage,
                    wmsw_ajax.strings.connection_failed,
                    isSecurityError ? 'security-error' : null
                );
            },
            complete: function () {
                $button.prop('disabled', false).text(originalText);
            }
        });
    });

    // Import forms - Generic handler with fallback
    $('.swi-import-form form').on('submit', function (e) {
        e.preventDefault();

        let $form = $(this);
        let importType = $form.attr('id').replace('-form', '').replace('-import', '');

        // Prevent product import from backend.js (handled by product-importer.js)
        if (importType === 'products') {
            console.warn('Product import is now handled exclusively by product-importer.js. Skipping backend.js handler.');
            return;
        }

        // Prevent orders import from this generic handler (has its own specific handler)
        if (importType === 'orders') {
            console.warn('Orders import has its own specific handler. Skipping generic handler.');
            return;
        }

        // For other import types, show a notification that the feature is not yet implemented
        if (importType === 'customers') {
            console.warn('Customer import is now handled exclusively by customer-importer.js. Skipping backend.js handler.');
            return;
        }
    });

    // Preview functionality
    $('.swi-preview-button').on('click', function () {
        let storeId = $(this).closest('form').find('select[name="store_id"]').val();
        let dataType = $(this).data('type');

        if (!storeId) {
            alert(wmsw_ajax.strings.please_select_store_first);
            return;
        }

        showPreview(storeId, dataType);
    });

    // Orders import functionality is now handled by order-importer.js component
    // The orders-import-form submission is handled there

    // Orders preview functionality is now handled by order-importer.js component
    // The preview functionality is handled there

    // Helper functions for orders preview moved to order-importer.js


    $('.swi-test-connection').on('click', function (e) {
        e.preventDefault();
        const $button = $(this);

        const originalHtml = $button.html();

        // Add spinner and disable button
        $button.prop('disabled', true).html(`
            <span class="swi-spinner swi-spinner-small" aria-hidden="true"></span>
            ${(wmsw_ajax.strings.testing_connection || 'Testing connection...')}`);

        const storeId = $button.data('storeId') || "";
        const nonce = wmsw_ajax.nonce;

        if (!storeId || !nonce) {
            showNotification('error', 'Missing store ID or security token.', 'Error');
            $button.prop('disabled', false).html(originalHtml);
            return;
        }

        $.ajax({
            url: wmsw_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wmsw_check_store_connection',
                store_id: storeId,
                nonce: nonce,
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    showNotification('success', response.data?.message || 'Connection with store successful.', 'Success!');
                } else {
                    showNotification('error', response.data?.message || 'Failed to connect with store.', 'Error');
                }
            },
            error: function (xhr, status, error) {
                showNotification('error', 'Failed to connect with store.', 'Error');
            },
            complete: function () {
                $button.prop('disabled', false).html(originalHtml);
            }
        });
    });


    // Replace delete-store handler with confirm dialog
    $('.deactivate-store, .delete-store').off('click').on('click', function (e) {
        e.preventDefault();
        const $button = $(this);
        const isDelete = $button.hasClass('delete-store');
        const actionText = isDelete ? 'delete' : 'deactivate';
        const confirmMsg = isDelete ? (wmsw_ajax.strings.confirm_delete_store || '') : (wmsw_ajax.strings.confirm_deactivate_store || '');
        const originalHtml = $button.html();

        wmsw_ConfirmBox.show(confirmMsg, function () {
            // Add spinner and disable button
            $button.prop('disabled', true).html(`
                    <span class="swi-spinner swi-spinner-small" aria-hidden="true"></span>
                    ${isDelete ? (wmsw_ajax.strings.deleting) : (wmsw_ajax.strings.deactivating)}
                `);
            const url = $button.attr('href') || '';
            const urlParams = new URLSearchParams(url.split('?')[1] || '');
            const storeId = $button.data('store-id') || urlParams.get('store_id');
            const nonce = wmsw_ajax.nonce;
            const action = isDelete ? 'wmsw_delete_store' : 'wmsw_deactivate_store';

            if (!storeId || !nonce) {
                showNotification('error', wmsw_ajax.strings.missing_store_id_or_nonce, wmsw_ajax.strings.error);
                $button.prop('disabled', false).html(originalHtml);
                return;
            }

            $.ajax({
                url: wmsw_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: action,
                    store_id: storeId,
                    nonce: nonce
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        showNotification('success', response.data?.message || wmsw_ajax.strings.action_successful, wmsw_ajax.strings.success);
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        showNotification('error', response.data?.message || wmsw_ajax.strings.failed_to_process_request, wmsw_ajax.strings.error);
                    }
                },
                error: function () {
                    showNotification('error', wmsw_ajax.strings.ajax_request_failed, wmsw_ajax.strings.error);
                },
                complete: function () {
                    $button.prop('disabled', false).html(originalHtml);
                }
            });
        }, function () {
            // Cancel callback: restore button state if needed
            $button.prop('disabled', false).html(originalHtml);
        }, {
            title: isDelete ? (wmsw_ajax.strings.delete_store_title || '') : (wmsw_ajax.strings.deactivate_store_title || ''),
            okButtonText: isDelete ? (wmsw_ajax.strings.delete || '') : (wmsw_ajax.strings.deactivate || ''),
            cancelButtonText: wmsw_ajax.strings.cancel || ''
        });
    });


    // Handle setting a store as default
    $('.swi-stores-table, .swi-action-grid').on('click', '.swi-set-default', function (e) {
        e.preventDefault();
        const $button = $(this);
        const storeId = $button.data('store-id');

        // Save original content to restore later
        const originalHtml = $button.html();
        const confirmMsg = wmsw_ajax.strings.confirm_set_default_store || '';

        wmsw_ConfirmBox.show(confirmMsg, function () {

            // Show loading state
            $button.prop('disabled', true).html(`
                <span class="swi-spinner swi-spinner-small" aria-hidden="true"></span>
                ${wmsw_ajax.strings.processing}
            `);

            // Make Ajax request
            $.ajax({
                url: wmsw_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wmsw_set_default_store',
                    store_id: storeId,
                    nonce: wmsw_ajax.nonce
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        showNotification('success', response.data?.message || wmsw_ajax.strings.store_set_default_success, wmsw_ajax.strings.success);
                        // Reload page after a delay to show the updated UI
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showNotification('error', response.data?.message || wmsw_ajax.strings.failed_to_set_store_default, wmsw_ajax.strings.error);
                        // Restore button
                        $button.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function () {
                    showNotification('error', wmsw_ajax.strings.ajax_request_failed, wmsw_ajax.strings.error);
                    // Restore button
                    $button.prop('disabled', false).html(originalHtml);
                }
            });

        }, function () {
            $button.prop('disabled', false).html(originalHtml);
        }, {
            title: wmsw_ajax.strings.set_default_store_title || '',
            okButtonText: wmsw_ajax.strings.set_default || '',
            cancelButtonText: wmsw_ajax.strings.cancel || ''
        });
    });


    // Handle copying a store
    $('.swi-action-grid, .swi-stores-table').on('click', '.swi-copy-store', function (e) {
        e.preventDefault();
        const $button = $(this);
        const storeId = $button.data('store-id');

        // Save original content to restore later
        const originalHtml = $button.html();

        // Show loading state
        $button.prop('disabled', true).html(`
                <span class="swi-spinner swi-spinner-small" aria-hidden="true"></span>
                ${wmsw_ajax.strings.copying}
            `);

        // Make Ajax request
        $.ajax({
            url: wmsw_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wmsw_copy_store',
                store_id: storeId,
                nonce: wmsw_ajax.nonce
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // Clear any existing notifications first to ensure this one is seen
                    $('.swi-notification').remove();

                    // Display a more detailed notification
                    showNotification(
                        'success',
                        response.data?.message || wmsw_ajax.strings.store_copied_success,
                        wmsw_ajax.strings.store_copied_title
                    );

                    // Add a slight delay to ensure notification is seen before redirect
                    setTimeout(() => {
                        // Redirect to edit the new store
                        if (response.data?.new_store_id) {
                            // Use the admin_url provided in the global object or construct a default URL
                            const adminBase = wmsw_ajax?.admin_url || window.location.pathname.replace(/\/wp-admin\/.*/, '/wp-admin/');
                            window.location.href = `${adminBase}admin.php?page=wp-migrate-shopify-woo-lite-stores&action=edit&store_id=${response.data.new_store_id}`;
                        } else {
                            // Fallback if no ID returned, just reload the page
                            window.location.reload();
                        }
                    }, 1500); // Longer delay to ensure user sees the notification
                } else {
                    showNotification('error', response.data?.message || wmsw_ajax.strings.store_copied_error, wmsw_ajax.strings.error);
                    // Restore button
                    $button.prop('disabled', false).html(originalHtml);
                }
            },
            error: function () {
                showNotification('error', wmsw_ajax.strings.ajax_request_failed, wmsw_ajax.strings.error);
                // Restore button
                $button.prop('disabled', false).html(originalHtml);
            }
        });
    });



    $('#add-new-store, #add-first-store').on("click", function (e) {
        e.preventDefault();
        $('#swi-store-form-title').text(wmsw_ajax.strings.add_new_store);
        $('#swi-store-form')[0].reset();
        $('#store_id').val('');
        $('#swi-store-nonce').val(WMSW_ajax.save_store_nonce); // Set the correct nonce
        $('#swi-store-form-modal').show();
    });

    $('.swi-edit-store').on("click", function (e) {
        e.preventDefault();
        var storeId = $(this).data('store-id');

        $('#swi-store-form-title').text(wmsw_ajax.strings.edit_store);
        $('#store_id').val(storeId);
        $('#swi-store-nonce').val(WMSW_ajax.save_store_nonce); // Set the correct nonce
        // Load store data
        $.post(ajaxurl, {
            action: 'wmsw_get_store',
            store_id: storeId,
            nonce: WMSW_ajax.get_store_nonce // Use JS nonce instead of PHP inline
        }, function (response) {
            if (response.success) {
                var store = response.data.store;
                $('#store_name').val(store.name);
                $('#shop_domain').val(store.shop_domain);
                $('#api_key').val(store.api_key);
                $('#api_secret_key').val(store.api_secret_key);
                $('#access_token').val(store.access_token);
                $('#api_version').val(store.api_version || '2023-10'); // Set API version
                $('#swi-store-form-modal').show();
            } else {
                alert(wmsw_ajax.strings.error_loading_store + ' ' + response.data.message);
            }
        });
    });

    // Save store
    $('#swi-store-form').on("submit", function (e) {
        e.preventDefault();

        var formData = $(this).serialize();
        var submitButton = $(this).find('button[type="submit"]');

        submitButton.prop('disabled', true).text(wmsw_ajax.strings.saving);

        $.post(ajaxurl, formData, function (response) {
            submitButton.prop('disabled', false).text(wmsw_ajax.strings.save_store);

            if (response.success) {
                $('#swi-store-form-modal').hide();
                location.reload();
            } else {
                alert(wmsw_ajax.strings.error_prefix + ' ' + response.data.message);
            }
        });
    });

    // Test connection
    $('.swi-test-connection').on("click", function (e) {
        e.preventDefault();
        var storeId = $(this).data('store-id');

        $('#swi-connection-test-results').html('<p>' + wmsw_ajax.strings.testing_connection + '</p>');
        showModal('swi-connection-test-modal');
        $.post(ajaxurl, {
            action: 'wmsw_test_store_connection',
            store_id: storeId,
            nonce: WMSW_ajax.test_connection_nonce // Use JS nonce
        }, function (response) {
            if (response.success) {
                $('#swi-connection-test-results').html(response.data.html);
            } else {
                $('#swi-connection-test-results').html('<p class="error">' + response.data.message + '</p>');
            }
        });
    });

    // Sync store
    $('.swi-sync-store').on("click", function (e) {
        e.preventDefault();
        if (confirm(wmsw_ajax.strings.confirm_sync_store)) {
            var storeId = $(this).data('store-id');
            var button = $(this);

            button.text(wmsw_ajax.strings.syncing);
            $.post(ajaxurl, {
                action: 'wmsw_sync_store',
                store_id: storeId,
                nonce: WMSW_ajax.sync_store_nonce // Use JS nonce
            }, function (response) {
                button.text(wmsw_ajax.strings.sync_now);

                if (response.success) {
                    alert(wmsw_ajax.strings.sync_started_successfully);
                    location.reload();
                } else {
                    alert(wmsw_ajax.strings.error_prefix + ' ' + response.data.message);
                }
            });
        }
    });

    // Enable/Disable store
    $('.swi-enable-store, .swi-disable-store').on("click", function (e) {
        e.preventDefault();
        var storeId = $(this).data('store-id');
        var isEnable = $(this).hasClass('swi-enable-store');
        var action = isEnable ? 'enable' : 'disable';

        if (confirm(wmsw_ajax.strings.confirm_toggle_store_status + action + wmsw_ajax.strings.confirm_toggle_store_status_2)) {
            $.post(ajaxurl, {
                action: 'wmsw_toggle_store_status',
                store_id: storeId,
                status: isEnable ? 'active' : 'inactive',
                nonce: WMSW_ajax.toggle_store_nonce // Use JS nonce
            }, function (response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(wmsw_ajax.strings.error_prefix + ' ' + response.data.message);
                }
            });
        }
    });

    // Delete store
    $('.swi-delete-store').on("click", function (e) {
        e.preventDefault();
        if (confirm(wmsw_ajax.strings.confirm_delete_store)) {
            var storeId = $(this).data('store-id');
            $.post(ajaxurl, {
                action: 'wmsw_delete_store',
                store_id: storeId,
                nonce: WMSW_ajax.delete_store_nonce // Use JS nonce
            }, function (response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(wmsw_ajax.strings.error_prefix + ' ' + response.data.message);
                }
            });
        }
    });

    // Close modals
    $('.swi-modal-close, .swi-modal').on("click", function (e) {
        if (e.target === this || $(e.target).hasClass('swi-modal-close')) {
            $('.swi-modal').addClass('d-none');
        }
    });

    // Context toggle buttons
    $('.swi-context-toggle').on('click', function(e) {
        e.preventDefault();
        const logId = $(this).data('log-id');
        toggleContextContent(logId);
    });

    // Initialize progress bars on page load
    initProgressBars();

    // Make functions globally available for other scripts
    window.WMSW = window.WMSW || {};
    window.WMSW.showModal = showModal;
    window.WMSW.hideModal = hideModal;
    window.WMSW.toggleContextContent = toggleContextContent;
    window.WMSW.initProgressBars = initProgressBars;
});
