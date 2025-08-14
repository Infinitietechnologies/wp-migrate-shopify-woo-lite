/**
 * Import Logs Page Scripts
 */
(function ($) {
    'use strict';

    // Initialize the Import Logs page functionality
    $(document).ready(function () {

        // --- Log Table Actions ---

        // View log details
        $(document).on('click', '.swi-view-log', function (e) {
            e.preventDefault();
            const logId = $(e.currentTarget).data('log-id');

            // Validate logId
            if (!logId) {
                showNotification('error', wmsw_ajax.strings.invalid_log_id, wmsw_ajax.strings.error);
                return;
            }

            $('#swi-log-details-content').html('<p>' + (wmsw_ajax.strings.loading || 'Loading...') + '</p>');
            $('#swi-log-details-modal').show();

            $.ajax({
                url: wmsw_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wmsw_get_log_details',
                    log_id: logId,
                    nonce: wmsw_ajax.nonce_get_log_details
                },
                timeout: 30000, // 30 second timeout
                success: function (response) {
                    try {
                        if (response && response.success) {
                            $('#swi-log-details-content').html(response.data.html || '<p>No details available</p>');
                        } else {
                            const errorMsg = response?.data?.message || response?.message || wmsw_ajax.strings.general_error || 'An error occurred';
                            $('#swi-log-details-content').html('<p class="error">' + errorMsg + '</p>');
                            showNotification('error', errorMsg, wmsw_ajax.strings.failed_to_load_log_details);
                        }
                    } catch (error) {
                        $('#swi-log-details-content').html('<p class="error">Error parsing response</p>');
                        showNotification('error', wmsw_ajax.strings.error_parsing_response, wmsw_ajax.strings.parse_error);
                    }
                },
                error: function (xhr, status, error) {
                    let errorMessage = wmsw_ajax.strings.connection_error;

                    if (status === 'timeout') {
                        errorMessage = wmsw_ajax.strings.request_timed_out;
                    } else if (status === 'parsererror') {
                        errorMessage = wmsw_ajax.strings.invalid_server_response;
                    } else if (xhr.status === 403) {
                        errorMessage = wmsw_ajax.strings.access_denied;
                    } else if (xhr.status === 404) {
                        errorMessage = wmsw_ajax.strings.action_not_found;
                    } else if (xhr.status >= 500) {
                        errorMessage = wmsw_ajax.strings.server_error_occurred;
                    }

                    $('#swi-log-details-content').html('<p class="error">' + errorMessage + '</p>');
                    showNotification('error', errorMessage, wmsw_ajax.strings.failed_to_load_log_details);
                }
            });
        });

        // Cancel import
        $(document).on('click', '.swi-cancel-import', function (e) {
            e.preventDefault();
            const $button = $(this);
            const logId = $button.data('log-id');
            const originalHtml = $button.html();

            // Validate logId
            if (!logId) {
                showNotification('error', wmsw_ajax.strings.invalid_log_id, wmsw_ajax.strings.error);
                return;
            }

            const confirmMsg = wmsw_ajax.strings.confirm_cancel_import || 'Are you sure you want to cancel this import?';

            wmsw_ConfirmBox.show(confirmMsg, function () {
                // Add spinner and disable button
                $button.prop('disabled', true).html(`
                    <span class="swi-spinner swi-spinner-small" aria-hidden="true"></span>
                    ${wmsw_ajax.strings.cancelling || 'Cancelling...'}
                `);

                $.ajax({
                    url: wmsw_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wmsw_cancel_import',
                        log_id: logId,
                        nonce: wmsw_ajax.nonce_cancel_import
                    },
                    timeout: 30000,
                    dataType: 'json',
                    success: function (response) {
                        try {
                            if (response && response.success) {
                                showNotification('success', wmsw_ajax.strings.import_cancelled_successfully, wmsw_ajax.strings.success);
                                setTimeout(function () {
                                    location.reload();
                                }, 1500);
                            } else {
                                const errorMsg = response?.data?.message || response?.message || wmsw_ajax.strings.general_error || 'Failed to cancel import';
                                showNotification('error', errorMsg, wmsw_ajax.strings.cancel_failed);
                            }
                        } catch (error) {
                            showNotification('error', wmsw_ajax.strings.error_parsing_response, wmsw_ajax.strings.parse_error);
                        }
                    },
                    error: function (xhr, status, error) {
                        let errorMessage = wmsw_ajax.strings.connection_error_occurred;

                        if (status === 'timeout') {
                            errorMessage = wmsw_ajax.strings.request_timeout;
                        } else if (xhr.status === 403) {
                            errorMessage = wmsw_ajax.strings.access_denied;
                        } else if (xhr.status >= 500) {
                            errorMessage = wmsw_ajax.strings.server_error;
                        }

                        showNotification('error', errorMessage, wmsw_ajax.strings.cancel_failed);
                    },
                    complete: function () {
                        $button.prop('disabled', false).html(originalHtml);
                    }
                });
            }, function () {
                // Cancel callback: restore button state if needed
                $button.prop('disabled', false).html(originalHtml);
            }, {
                title: wmsw_ajax.strings.cancel_import_title || 'Cancel Import',
                okButtonText: wmsw_ajax.strings.yes_cancel || 'Yes, Cancel',
                cancelButtonText: wmsw_ajax.strings.no_keep || 'No, Keep'
            });
        });

        // Retry import
        $(document).on('click', '.swi-retry-import', function (e) {
            e.preventDefault();
            const $button = $(this);
            const logId = $button.data('log-id');
            const originalHtml = $button.html();

            // Validate logId
            if (!logId) {
                showNotification('error', wmsw_ajax.strings.invalid_log_id, wmsw_ajax.strings.error);
                return;
            }

            const confirmMsg = wmsw_ajax.strings.confirm_retry_import || 'Are you sure you want to retry this import?';

            wmsw_ConfirmBox.show(confirmMsg, function () {
                // Add spinner and disable button
                $button.prop('disabled', true).html(`
                    <span class="swi-spinner swi-spinner-small" aria-hidden="true"></span>
                    ${wmsw_ajax.strings.retrying || 'Retrying...'}
                `);

                $.ajax({
                    url: wmsw_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wmsw_retry_import',
                        log_id: logId,
                        nonce: wmsw_ajax.nonce_retry_import
                    },
                    timeout: 30000,
                    dataType: 'json',
                    success: function (response) {
                        try {
                            if (response && response.success) {
                                showNotification('success', wmsw_ajax.strings.import_retry_initiated, wmsw_ajax.strings.success);
                                setTimeout(function () {
                                    location.reload();
                                }, 1500);
                            } else {
                                const errorMsg = response?.data?.message || response?.message || wmsw_ajax.strings.general_error || 'Failed to retry import';
                                showNotification('error', errorMsg, wmsw_ajax.strings.retry_failed);
                            }
                        } catch (error) {
                            showNotification('error', wmsw_ajax.strings.error_parsing_response, wmsw_ajax.strings.parse_error);
                        }
                    },
                    error: function (xhr, status, error) {
                        let errorMessage = wmsw_ajax.strings.connection_error_occurred;

                        if (status === 'timeout') {
                            errorMessage = wmsw_ajax.strings.request_timeout;
                        } else if (xhr.status === 403) {
                            errorMessage = wmsw_ajax.strings.access_denied;
                        } else if (xhr.status >= 500) {
                            errorMessage = wmsw_ajax.strings.server_error;
                        }

                        showNotification('error', errorMessage, wmsw_ajax.strings.retry_failed);
                    },
                    complete: function () {
                        $button.prop('disabled', false).html(originalHtml);
                    }
                });
            }, function () {
                // Cancel callback: restore button state if needed
                $button.prop('disabled', false).html(originalHtml);
            }, {
                title: wmsw_ajax.strings.retry_import_title || 'Retry Import',
                okButtonText: wmsw_ajax.strings.yes_retry || 'Yes, Retry',
                cancelButtonText: wmsw_ajax.strings.cancel || 'Cancel'
            });
        });

        // Delete log
        $(document).on('click', '.swi-delete-log', function (e) {
            e.preventDefault();
            const $button = $(this);
            const logId = $button.data('log-id');
            const originalHtml = $button.html();

            // Validate logId
            if (!logId) {
                showNotification('error', wmsw_ajax.strings.invalid_log_id, wmsw_ajax.strings.error);
                return;
            }

            const confirmMsg = wmsw_ajax.strings.confirm_delete_log || 'Are you sure you want to delete this log? This action cannot be undone.';

            wmsw_ConfirmBox.show(confirmMsg, function () {
                // Add spinner and disable button
                $button.prop('disabled', true).html(`
                    <span class="swi-spinner swi-spinner-small" aria-hidden="true"></span>
                    ${wmsw_ajax.strings.deleting || 'Deleting...'}
                `);

                $.ajax({
                    url: wmsw_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wmsw_delete_log',
                        log_id: logId,
                        nonce: wmsw_ajax.nonce_delete_log
                    },
                    timeout: 30000,
                    dataType: 'json',
                    success: function (response) {
                        try {
                            if (response && response.success) {
                                showNotification('success', wmsw_ajax.strings.log_deleted_successfully, wmsw_ajax.strings.success);
                                setTimeout(function () {
                                    location.reload();
                                }, 1500);
                            } else {
                                const errorMsg = response?.data?.message || response?.message || wmsw_ajax.strings.general_error || 'Failed to delete log';
                                showNotification('error', errorMsg, wmsw_ajax.strings.delete_failed);
                            }
                        } catch (error) {
                            showNotification('error', wmsw_ajax.strings.error_parsing_response, wmsw_ajax.strings.parse_error);
                        }
                    },
                    error: function (xhr, status, error) {
                        let errorMessage = wmsw_ajax.strings.connection_error_occurred;

                        if (status === 'timeout') {
                            errorMessage = wmsw_ajax.strings.request_timeout;
                        } else if (xhr.status === 403) {
                            errorMessage = wmsw_ajax.strings.access_denied;
                        } else if (xhr.status >= 500) {
                            errorMessage = wmsw_ajax.strings.server_error;
                        }

                        showNotification('error', errorMessage, wmsw_ajax.strings.delete_failed);
                    },
                    complete: function () {
                        $button.prop('disabled', false).html(originalHtml);
                    }
                });
            }, function () {
                // Cancel callback: restore button state if needed
                $button.prop('disabled', false).html(originalHtml);
            }, {
                title: wmsw_ajax.strings.delete_log_title || 'Delete Log',
                okButtonText: wmsw_ajax.strings.yes_delete || 'Yes, Delete',
                cancelButtonText: wmsw_ajax.strings.cancel || 'Cancel'
            });
        });

        // Close modal
        $(document).on('click', '.swi-modal-close, .swi-modal', function (e) {
            if (e.target === e.currentTarget) {
                $('#swi-log-details-modal').hide();
            }
        });

        // --- Page Functionality ---

        // Toggle advanced filters
        $('#toggle-filters').on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            $('#filters-body').slideToggle(200);
            $('#filter-toggle-icon').toggleClass('dashicons-arrow-up-alt2 dashicons-arrow-down-alt2');

            return false;
        });

        // Context toggles
        $('.swi-context-toggle').on('click', function () {
            let logId = $(this).data('log-id');
            let context = $('#log-context-' + logId);

            if (context.is(':visible')) {
                context.slideUp(200);
                $(this).text(wmsw_ajax.strings.show_details || 'Show Details');
            } else {
                // Close any open contexts first
                $('.swi-context-content').slideUp(200);
                $('.swi-context-toggle').text(wmsw_ajax.strings.show_details || 'Show Details');

                // Open this context
                context.slideDown(200);
                $(this).text(wmsw_ajax.strings.hide_details || 'Hide Details');
            }
        });

        // Modular AJAX handler for log actions
        function handleLogAjax(options) {
            const $button = options.button;
            const originalHtml = $button ? $button.html() : '';

            // Validation
            if (options.confirm && !confirm(options.confirm)) return;

            // Validate required data
            if (!options.data || !options.data.action) {
                showNotification('error', wmsw_ajax.strings.invalid_request_config, wmsw_ajax.strings.config_error);
                return;
            }

            $.ajax({
                url: wmsw_ajax.ajax_url,
                type: options.type || 'POST',
                data: options.data,
                timeout: options.timeout || 30000,
                beforeSend: function () {
                    if ($button) {
                        $button.prop('disabled', true);
                        if (options.loadingHtml) {
                            $button.html(options.loadingHtml);
                        }
                    }
                    if (typeof options.beforeSend === 'function') {
                        options.beforeSend();
                    }
                },
                success: function (response) {
                    try {
                        if (typeof options.success === 'function') {
                            options.success(response);
                        } else if (response && response.success) {
                            showNotification('success', wmsw_ajax.strings.operation_completed, 'Success');
                        } else {
                            const errorMsg = response?.data?.message || response?.message || wmsw_ajax.strings.operation_failed;
                            showNotification('error', errorMsg, wmsw_ajax.strings.operation_failed_title);
                        }
                    } catch (error) {
                        showNotification('error', wmsw_ajax.strings.error_processing_response, wmsw_ajax.strings.processing_error);
                    }
                },
                error: function (xhr, status, error) {

                    if (typeof options.error === 'function') {
                        options.error(xhr, status, error);
                    } else {
                        let errorMessage = wmsw_ajax.strings.connection_error;

                        if (status === 'timeout') {
                            errorMessage = wmsw_ajax.strings.request_timeout;
                        } else if (status === 'parsererror') {
                            errorMessage = wmsw_ajax.strings.invalid_response_format;
                        } else if (xhr.status === 403) {
                            errorMessage = wmsw_ajax.strings.access_denied;
                        } else if (xhr.status === 404) {
                            errorMessage = wmsw_ajax.strings.action_not_found;
                        } else if (xhr.status >= 500) {
                            errorMessage = wmsw_ajax.strings.server_error;
                        } else if (xhr.status === 0) {
                            errorMessage = wmsw_ajax.strings.network_error;
                        }

                        showNotification('error', errorMessage, wmsw_ajax.strings.request_failed);
                    }
                },
                complete: function () {
                    if ($button) {
                        $button.prop('disabled', false);
                        if (options.completeHtml) {
                            $button.html(options.completeHtml);
                        } else {
                            $button.html(originalHtml);
                        }
                    }
                    if (typeof options.complete === 'function') {
                        options.complete();
                    }
                }
            });
        }

        // Clear old logs
        $('.clear-logs-30').on('click', function () {
            const $button = $(this);
            const originalHtml = $button.html();
            const confirmMsg = wmsw_ajax.strings.confirm_clear || 'Are you sure you want to clear old logs? This action cannot be undone.';

            wmsw_ConfirmBox.show(confirmMsg, function () {
                // Add spinner and disable button
                $button.prop('disabled', true).html(`
                    <span class="swi-spinner swi-spinner-small" aria-hidden="true"></span>
                    ${wmsw_ajax.strings.processing || 'Processing...'}
                `);

                $.ajax({
                    url: wmsw_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wmsw_clear_old_logs',
                        days: 30,
                        nonce: wmsw_ajax.nonce
                    },
                    timeout: 60000, // 60 seconds for potentially long operation
                    dataType: 'json',
                    success: function (response) {
                        if (response && response.success) {
                            const message = response.data?.message || 'Old logs cleared successfully';
                            showNotification('success', message, 'Clear Completed');
                            setTimeout(function () {
                                location.reload();
                            }, 2000);
                        } else {
                            const errorMsg = response?.data?.message || response?.message || 'Failed to clear old logs';
                            showNotification('error', errorMsg, 'Clear Failed');
                        }
                    },
                    error: function (xhr, status, error) {
                        let errorMessage = 'Failed to clear old logs';

                        if (status === 'timeout') {
                            errorMessage = 'Clear operation timed out. It may still be processing in the background.';
                        }

                        showNotification('error', errorMessage, 'Clear Failed');
                    },
                    complete: function () {
                        $button.prop('disabled', false).html(originalHtml);
                    }
                });
            }, function () {
                // Cancel callback: restore button state if needed
                $button.prop('disabled', false).html(originalHtml);
            }, {
                title: wmsw_ajax.strings.clear_logs_title || 'Clear Old Logs',
                okButtonText: wmsw_ajax.strings.yes_clear || 'Yes, Clear',
                cancelButtonText: wmsw_ajax.strings.cancel || 'Cancel'
            });
        });

        // Export logs
        $('#export-logs-csv').on('click', function () {
            const $button = $(this);
            const originalHtml = $button.html();

            // Validate export URL and nonce
            if (!wmsw_ajax.ajax_url || !wmsw_ajax.nonce) {
                showNotification('error', 'Export configuration is missing', 'Export Error');
                return;
            }

            try {
                $button.prop('disabled', true)
                    .html('<span class="swi-spinner swi-spinner-small" aria-hidden="true"></span>' + (wmsw_ajax.strings.exporting || 'Exporting...'));

                // Build export URL with current filters
                const params = new URLSearchParams(window.location.search);
                params.delete('page'); // Remove page parameter
                params.append('action', 'wmsw_export_logs');
                params.append('nonce', wmsw_ajax.nonce);

                const exportUrl = wmsw_ajax.ajax_url + '?' + params.toString();

                // Create hidden iframe for download
                const $iframe = $('<iframe/>')
                    .attr({
                        'src': exportUrl,
                        'style': 'display: none; width: 0; height: 0; border: none;'
                    })
                    .appendTo('body');

                // Handle iframe load events
                $iframe.on('load', function () {
                    try {
                        const iframeDoc = this.contentDocument || this.contentWindow.document;

                        // Check if iframe contains error content
                        if (iframeDoc && iframeDoc.body && iframeDoc.body.innerHTML.trim()) {
                            const content = iframeDoc.body.innerHTML.toLowerCase();
                            if (content.includes('error') || content.includes('fail')) {
                                showNotification('error', 'Export failed. Please check your permissions.', 'Export Error');
                                return;
                            }
                        }

                        // Success
                        showNotification('success', 'Export completed successfully', 'Export Success');

                    } catch (e) {
                        // Cross-origin or other errors are expected for successful downloads
                        showNotification('success', 'Export completed successfully', 'Export Success');
                    }
                });

                // Handle iframe errors
                $iframe.on('error', function () {
                    showNotification('error', 'Export failed. Please try again.', 'Export Error');
                });

                // Reset button and cleanup after delay
                setTimeout(function () {
                    $button.prop('disabled', false).html(originalHtml);

                    // Clean up iframe after delay
                    setTimeout(function () {
                        $iframe.remove();
                    }, 5000);
                }, 3000);

            } catch (error) {
                $button.prop('disabled', false).html(originalHtml);
                showNotification('error', 'Export initialization failed', 'Export Error');
            }
        });

        // Highlight rows on hover
        $('.swi-logs-table tbody tr').on('mouseenter',
            function () {
                $(this).addClass('swi-highlight');
            })
            .on('mouseleave', function () {
                $(this).removeClass('swi-highlight');
            });

        // Date range validation
        $('form').on('submit', function (e) {
            try {
                const dateFrom = $('input[name="date_from"]').val();
                const dateTo = $('input[name="date_to"]').val();

                // Only validate if both dates are provided
                if (dateFrom && dateTo) {
                    const fromDate = new Date(dateFrom);
                    const toDate = new Date(dateTo);

                    // Check for valid dates
                    if (isNaN(fromDate.getTime()) || isNaN(toDate.getTime())) {
                        showNotification('error', 'Please enter valid dates', 'Invalid Date Format');
                        e.preventDefault();
                        return false;
                    }

                    // Check date range
                    if (fromDate > toDate) {
                        showNotification('error', 'Start date cannot be later than end date', 'Invalid Date Range');
                        e.preventDefault();
                        return false;
                    }

                    // Check for reasonable date range (optional - adjust as needed)
                    const daysDiff = Math.abs((toDate - fromDate) / (1000 * 60 * 60 * 24));
                    if (daysDiff > 365) {
                        const confirmMsg = 'You are selecting a date range of more than 1 year. This may take a long time to process. Continue?';

                        wmsw_ConfirmBox.show(confirmMsg, function () {
                            // User confirmed, allow form submission
                            $('form').off('submit').submit();
                        }, function () {
                            // User cancelled, prevent form submission
                            e.preventDefault();
                        }, {
                            title: 'Long Date Range Warning',
                            okButtonText: 'Continue',
                            cancelButtonText: 'Cancel'
                        });

                        e.preventDefault();
                        return false;
                    }
                }

                return true;
            } catch (error) {
                showNotification('error', 'Error validating date range', 'Validation Error');
                e.preventDefault();
                return false;
            }
        });

        // Clear individual filters
        $('.swi-clear-filter').on('click', function (e) {
            e.preventDefault();

            try {
                const filter = $(this).data('filter');

                if (!filter) {
                    showNotification('warning', 'No filter specified to clear', 'Warning');
                    return;
                }

                // Clear the specific filter
                const $filterInput = $('[name="' + filter + '"]');

                if ($filterInput.length === 0) {
                    showNotification('warning', 'Filter input not found: ' + filter, 'Warning');
                    return;
                }

                // Clear the filter value
                $filterInput.val('');

                // Show feedback
                showNotification('info', 'Filter "' + filter + '" cleared', 'Filter Cleared');

                // Submit the form after a short delay to show the notification
                setTimeout(() => {
                    $(this).closest('form').submit();
                }, 500);

            } catch (error) {
                showNotification('error', 'Error clearing filter', 'Clear Filter Error');
            }
        });

        // --- Filter Form Handling (Refresh-based) ---
        // The form now submits normally and refreshes the page with GET parameters
        // No AJAX handling needed - the page will reload with filtered results


        // Add loading state to form submission
        $('#swi-logs-filter-form').on('submit', function () {
            const $submitBtn = $(this).find('button[type="submit"]');
            const originalText = $submitBtn.html();

            // Show loading state
            $submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update swi-spin swi-mr-2"></span>Loading...');

            // Re-enable after a short delay (in case of errors)
            setTimeout(function () {
                $submitBtn.prop('disabled', false).html(originalText);
            }, 5000);
        });

        // Preserve filter state in URL for better UX
        function updateURLWithFilters() {
            const $form = $('#swi-logs-filter-form');
            const formData = new FormData($form[0]);
            const params = new URLSearchParams();

            for (let [key, value] of formData.entries()) {
                if (value) {
                    params.append(key, value);
                }
            }

            // Update URL without reloading (for better UX)
            const newURL = window.location.pathname + '?' + params.toString();
            window.history.replaceState({}, '', newURL);
        }

        // Update URL when filters change
        $('#swi-logs-filter-form input, #swi-logs-filter-form select').on('change', updateURLWithFilters);

        // Add visual indicators for active filters
        function updateActiveFilterIndicators() {
            $('.swi-filter-group').removeClass('has-active-filter');

            // Check each filter for active values
            $('#level').val() && $('#level').closest('.swi-filter-group').addClass('has-active-filter');
            $('#store_id').val() && $('#store_id').closest('.swi-filter-group').addClass('has-active-filter');
            $('#task_type').val() && $('#task_type').closest('.swi-filter-group').addClass('has-active-filter');
            $('input[name="date_from"]').val() && $('input[name="date_from"]').closest('.swi-filter-group').addClass('has-active-filter');
            $('input[name="date_to"]').val() && $('input[name="date_to"]').closest('.swi-filter-group').addClass('has-active-filter');
            $('input[name="search"]').val() && $('input[name="search"]').closest('.swi-search-box').addClass('has-active-filter');
        }

        // Initialize active filter indicators
        updateActiveFilterIndicators();

        // Update indicators when filters change
        $('#swi-logs-filter-form input, #swi-logs-filter-form select').on('change', updateActiveFilterIndicators);

        // Add filter summary display (optional enhancement)
        function showFilterSummary() {
            const activeFilters = [];

            const level = $('#level').val();
            const store = $('#store_id option:selected').text();
            const taskType = $('#task_type option:selected').text();
            const dateFrom = $('input[name="date_from"]').val();
            const dateTo = $('input[name="date_to"]').val();
            const search = $('input[name="search"]').val();

            if (level) activeFilters.push(`Level: ${level}`);
            if ($('#store_id').val()) activeFilters.push(`Store: ${store}`);
            if ($('#task_type').val()) activeFilters.push(`Task: ${taskType}`);
            if (dateFrom || dateTo) {
                const dateRange = [dateFrom, dateTo].filter(Boolean).join(' - ');
                activeFilters.push(`Date: ${dateRange}`);
            }
            if (search) activeFilters.push(`Search: "${search}"`);

            // Remove existing summary
            $('.swi-filter-summary').remove();

            // Add new summary if there are active filters
            if (activeFilters.length > 0) {
                const summaryHTML = `
                    <div class="swi-filter-summary">
                        <strong>Active Filters:</strong>
                        ${activeFilters.map(filter => `
                            <span class="swi-filter-tag">
                                <span>${filter}</span>
                                <span class="dashicons dashicons-no-alt" onclick="removeFilter('${filter.split(':')[0].toLowerCase()}')"></span>
                            </span>
                        `).join('')}
                    </div>
                `;
                $('.swi-filters-panel').after(summaryHTML);
            }
        }

        // Show filter summary on page load
        showFilterSummary();

        // Update summary when filters change
        $('#swi-logs-filter-form input, #swi-logs-filter-form select').on('change', showFilterSummary);

        // Global function to remove filters (for filter tag clicks)
        window.removeFilter = function (filterType) {
            switch (filterType) {
                case 'level':
                    $('#level').val('').trigger('change');
                    break;
                case 'store':
                    $('#store_id').val('').trigger('change');
                    break;
                case 'task':
                    $('#task_type').val('').trigger('change');
                    break;
                case 'date':
                    $('input[name="date_from"], input[name="date_to"]').val('').trigger('change');
                    break;
                case 'search':
                    $('input[name="search"]').val('').trigger('change');
                    break;
            }
        };

        /**
         * Show a notification toast
         * @param {string} type Notification type: 'success', 'error', 'warning', 'info'
         * @param {string} message The notification message
         * @param {string} title Optional title for the notification
         */
        function showNotification(type, message, title) {
            // Remove any existing notifications first
            $('.swi-notification').fadeOut(300, function () {
                $(this).remove();
            });

            // Icon mapping
            const icons = {
                'success': 'dashicons-yes-alt',
                'error': 'dashicons-dismiss',
                'warning': 'dashicons-warning',
                'info': 'dashicons-info'
            };

            // Build notification HTML
            const icon = icons[type] || icons['info'];
            const notificationTitle = title || (type.charAt(0).toUpperCase() + type.slice(1));

            const notificationHTML = `
                <div class="swi-notification swi-notification-${type}">
                    <div class="swi-notification-content">
                        <div class="swi-notification-icon">
                            <span class="dashicons ${icon}"></span>
                        </div>
                        <div class="swi-notification-message">
                            <div class="swi-notification-title">${notificationTitle}</div>
                            <div class="swi-notification-text">${message}</div>
                        </div>
                    </div>
                    <button class="swi-notification-dismiss" aria-label="Dismiss notification">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
            `;

            // Find a good place to insert the notification
            let $container = $('.wrap').first();
            if (!$container.length) {
                $container = $('body');
            }

            // Insert and show notification
            const $notification = $(notificationHTML);
            $container.prepend($notification);

            // Animate in
            $notification.hide().slideDown(300);

            // Auto-dismiss after 5 seconds (except for errors)
            if (type !== 'error') {
                setTimeout(() => {
                    $notification.fadeOut(300, function () {
                        $(this).remove();
                    });
                }, 5000);
            }

            // Handle dismiss button click
            $notification.find('.swi-notification-dismiss').on('click', function () {
                $notification.fadeOut(300, function () {
                    $(this).remove();
                });
            });
        }
    });

    // CSS animations extension
    $.fn.extend({
        wmsw_spin: function () {
            return this.each(function () {
                $(this).addClass('swi-spin');
            });
        }
    });

})(jQuery);
