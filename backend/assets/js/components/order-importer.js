/**
 * Order Importer Component
 * 
 * Handles order import functionality for Shopify to WooCommerce Importer
 */

"use strict";

class OrderImporter {
    constructor() {
        this.progressContainer = null;
        this.currentImport = null;
        this.maxPollingAttempts = 300; // Maximum 10 minutes of polling (300 * 2 seconds)
        this.pollingAttempts = 0;
        this.init();
    }

    init() {
        // Hide progress container by default
        this.progressContainer = jQuery('#orders-import-progress');
        this.progressContainer.hide();
        

        
        this.bindEvents();
        
        // Check for active imports on page load
        this.checkForActiveImports();
    }

    bindEvents() {
        // Order import form submission
        jQuery(document).on('submit', '#orders-import-form', (e) => {
            this.handleImportSubmit(e);
        });

        // Preview button click - using the actual button ID from the HTML
        jQuery(document).on('click', '#preview-orders', (e) => {
            this.handlePreviewClick(e);
        });

        // Store selection handler
        jQuery(document).on('change', '#store_id', () => {
            this.checkForActiveImports();
        });
    }

    handleImportSubmit(e) {
        e.preventDefault();

        const form = jQuery(e.target);
        const formData = new FormData(e.target);
        formData.append('action', 'wmsw_start_orders_import');
        formData.append('nonce', wmsw_ajax.nonce);

        this.setFormLoading(form, true);

        jQuery.ajax({
            url: wmsw_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: (response) => {
                if (response.success) {
                    this.showNotification('success', response.data.message, 'Import Started');

                    if (response.data.progress_key) {
                        this.startProgressMonitoring(response.data.progress_key);
                    }
                } else {
                    this.showNotification('error', response.data.message || 'Failed to start orders import.', 'Import Failed');
                    this.setFormLoading(form, false);
                }
            },
            error: () => {
                this.showNotification('error', 'Failed to start orders import.', 'Import Failed');
                this.setFormLoading(form, false);
            }
        });
    }

    handlePreviewClick(e) {
        e.preventDefault();

        const $button = jQuery(e.target);
        const form = jQuery('#orders-import-form');
        const storeId = form.find('select[name="store_id"]').val();
        const orderStatus = form.find('input[name="order_status[]"]:checked').map(function () {
            return this.value;
        }).get();

        if (!storeId) {
            this.showNotification('warning', 'Please select a store first.', 'No Store Selected');
            return;
        }

        // Show loading state on the button
        const originalHtml = $button.html();
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Loading...');

        // Create or show inline preview container
        this.showInlinePreview(storeId, orderStatus, () => {
            // Restore button state when preview loads
            $button.prop('disabled', false).html(originalHtml);
        });
    }

    setFormLoading(form, loading) {
        const button = form.find('button[type="submit"]');

        if (loading) {
            button.prop('disabled', true);
            button.html('<span class="dashicons dashicons-update spin"></span> Starting Import...');
        } else {
            button.prop('disabled', false);
            button.html('<span class="dashicons dashicons-download"></span> Start Orders Import');
        }
    }

    startProgressMonitoring(progressKey) {
        this.currentImport = progressKey;
        this.pollingAttempts = 0;
        this.createProgressContainer();

        const interval = setInterval(() => {
            this.checkProgress(progressKey, interval);
        }, 2000);
    }

    checkProgress(progressKey, interval) {
        this.pollingAttempts++;

        // Check for maximum polling attempts to prevent indefinite polling
        if (this.pollingAttempts > this.maxPollingAttempts) {
            clearInterval(interval);
            this.showNotification('error', 'Import polling timeout. Please check the import logs for status.', 'Polling Timeout');
            this.setFormLoading(jQuery('#orders-import-form'), false);
            return;
        }

        jQuery.ajax({
            url: wmsw_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wmsw_get_orders_import_progress',
                progress_key: progressKey,
                nonce: wmsw_ajax.nonce
            },
            success: (response) => {
                if (response.success) {
                    this.updateProgress(response.data);

                    if (response.data.status === 'completed' || response.data.status === 'error' || response.data.status === 'timeout') {
                        clearInterval(interval);
                        this.handleImportComplete(response.data);
                    }
                }
            },
            error: () => {
                clearInterval(interval);
                this.showNotification('error', 'Failed to get import progress.', 'Progress Error');
                this.setFormLoading(jQuery('#orders-import-form'), false);
            }
        });
    }

    createProgressContainer() {
        
        // Use existing progress container instead of creating a new one
        this.progressContainer = jQuery('#orders-import-progress');
        
        if (this.progressContainer.length === 0) {
            // If no existing container, create one
            const progressHtml = `
                <div id="orders-import-progress" class="swi-progress-container swi-card">
                    <div class="swi-card-header">
                        <h3 class="swi-card-title">
                            <span class="dashicons dashicons-chart-line"></span>
                            Import Progress
                        </h3>
                    </div>
                    <div class="swi-card-body">
                        <div class="swi-progress-bar">
                            <div id="orders-progress-fill" class="swi-progress-fill" style="width: 0%"></div>
                        </div>
                        <div class="swi-progress-info">
                            <span id="orders-progress-text">Processing...</span>
                            <span id="orders-progress-percentage">0%</span>
                        </div>
                        <div id="orders-progress-log"></div>
                    </div>
                </div>
            `;

            this.progressContainer = jQuery(progressHtml);
            jQuery('#orders-import-form').after(this.progressContainer);
        }
        
        // Ensure the container is visible
        this.progressContainer.show();
        
        // Check if progress fill element exists
        const $progressFill = this.progressContainer.find('#orders-progress-fill');
    }

    updateProgress(progress) {
        if (!this.progressContainer) {
            return;
        }


        // Handle both new structure (current/total) and legacy structure (processed/total)
        const current = progress.current || progress.processed || 0;
        const total = progress.total || 0;
        const percentage = progress.percentage || (total > 0 ? Math.round((current / total) * 100) : 0);


        // Update progress bar - ensure it's visible and properly sized
        const $progressFill = this.progressContainer.find('#orders-progress-fill');

        $progressFill.css('width', percentage + '%').show();
        
        // Update percentage text
        this.progressContainer.find('#orders-progress-percentage').text(percentage + '%');

        // Update message
        this.progressContainer.find('#orders-progress-text').text(progress.message || wmsw_ajax.strings.processing);

        // Update stats - handle both new and legacy data structures
        if (total > 0) {
            const statsHtml = `
                <span>Progress: ${current} / ${total}</span>
                ${progress.imported_count !== undefined ? `<span>Imported: ${progress.imported_count}</span>` : ''}
                ${progress.skipped_count !== undefined ? `<span>Skipped: ${progress.skipped_count}</span>` : ''}
                ${progress.error_count !== undefined ? `<span>Errors: ${progress.error_count}</span>` : ''}
            `;
            this.progressContainer.find('#orders-progress-log').html(statsHtml);
        }

        // Show errors if any
        if (progress.errors && progress.errors.length > 0) {
            let errorsHtml = '<h5>Errors:</h5><ul>';
            progress.errors.forEach(error => {
                errorsHtml += `<li>${error}</li>`;
            });
            errorsHtml += '</ul>';
            this.progressContainer.find('#orders-progress-log').append(errorsHtml);
        }
    }

    handleImportComplete(progress) {
        const form = jQuery('#orders-import-form');

        if (progress.status === 'completed') {
            this.showNotification('success',
                `Orders import completed! Imported: ${progress.imported_count || 0}, Skipped: ${progress.skipped_count || 0}, Errors: ${progress.error_count || 0}`,
                'Import Complete'
            );
        } else if (progress.status === 'timeout') {
            this.showNotification('warning',
                progress.message || 'Orders import stopped due to timeout. Some orders may have been processed.',
                'Import Timeout'
            );
        } else {
            this.showNotification('error', progress.message || 'Orders import failed.', 'Import Failed');
        }

        this.setFormLoading(form, false);
        this.currentImport = null;

        // Keep progress container visible for review
        setTimeout(() => {
            if (this.progressContainer) {
                this.progressContainer.fadeOut(3000);
            }
        }, 5000);
    }

    showInlinePreview(storeId, orderStatus, onCompleteCallback) {
        // Create or get the inline preview container
        let previewContainer = jQuery('#orders-preview-inline');

        if (!previewContainer.length) {
            // Create the inline preview container after the form actions
            const previewHtml = `
                <div id="orders-preview-inline" class="swi-card swi-preview-container" style="display: none; margin-top: 20px;">
                    <div class="swi-card-header">
                        <h3 class="swi-card-title">Orders Preview</h3>
                        <button type="button" class="button swi-close-preview" id="close-inline-preview">
                            <span class="dashicons dashicons-no-alt"></span> Close Preview
                        </button>
                    </div>
                    <div class="swi-card-body">
                        <div id="orders-preview-content">
                            <!-- Preview content will be loaded here -->
                        </div>
                    </div>
                </div>
            `;

            // Insert after the form actions
            const $form = jQuery('#orders-import-form');
            const $actionsRow = $form.find('.swi-form-actions').last();
            if ($actionsRow.length) {
                $actionsRow.after(previewHtml);
            } else {
                $form.after(previewHtml);
            }

            previewContainer = jQuery('#orders-preview-inline');

            // Bind close button event
            previewContainer.find('#close-inline-preview').on('click', () => {
                previewContainer.slideUp(300);
            });
        }

        // Show loading content
        previewContainer.find('#orders-preview-content').html(`
            <div class="swi-loading-preview">
                <span class="dashicons dashicons-update spin"></span>
                Loading orders preview...
            </div>
        `);

        // Show the container
        previewContainer.slideDown(300);

        // Scroll to the preview container
        setTimeout(() => {
            previewContainer[0].scrollIntoView({
                behavior: 'smooth',
                block: 'start',
                inline: 'nearest'
            });
        }, 350);

        // Load preview data
        jQuery.ajax({
            url: wmsw_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wmsw_preview_orders',
                store_id: storeId,
                order_status: orderStatus,
                limit: 10,
                nonce: wmsw_ajax.nonce
            },
            success: (response) => {
                if (response.success && response.data.orders) {
                    previewContainer.find('#orders-preview-content').html(this.generateInlinePreviewHtml(response.data));
                } else {
                    previewContainer.find('#orders-preview-content').html(this.generateInlineErrorHtml(response.data?.message));
                }
                if (onCompleteCallback) onCompleteCallback();
            },
            error: () => {
                previewContainer.find('#orders-preview-content').html(this.generateInlineErrorHtml('Failed to load orders preview.'));
                if (onCompleteCallback) onCompleteCallback();
            }
        });
    }

    generateInlinePreviewHtml(data) {
        let html = `
            <div class="swi-preview-summary">
                <div class="swi-preview-stats">
                    <span class="swi-stat">
                        <strong>${data.total_count}</strong> total orders found
                    </span>
                    <span class="swi-stat">
                        from <strong>${data.store_name}</strong>
                    </span>
                    <span class="swi-stat">
                        showing first <strong>${data.orders.length}</strong> orders
                    </span>
                </div>
            </div>
            <div class="swi-orders-preview-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" class="column-order">Order</th>
                            <th scope="col" class="column-customer">Customer</th>
                            <th scope="col" class="column-email">Email</th>
                            <th scope="col" class="column-total">Total</th>
                            <th scope="col" class="column-status">Status</th>
                            <th scope="col" class="column-items">Items</th>
                            <th scope="col" class="column-date">Date</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        data.orders.forEach((order, index) => {
            const alternateClass = index % 2 === 0 ? '' : 'alternate';
            html += `
                <tr class="${alternateClass}">
                    <td class="column-order"><strong>${order.name}</strong></td>
                    <td class="column-customer">${order.customer_name}</td>
                    <td class="column-email">${order.customer_email}</td>
                    <td class="column-total">
                        <span class="swi-amount">${order.currency} ${order.total}</span>
                    </td>
                    <td class="column-status">
                        <span class="swi-status-badge status-${order.status.toLowerCase()}">${order.status}</span>
                        ${order.fulfillment_status !== 'unfulfilled' ? `<br><small class="swi-fulfillment-status">Fulfillment: ${order.fulfillment_status}</small>` : ''}
                    </td>
                    <td class="column-items">
                        <span class="swi-item-count">${order.line_items_count} item(s)</span>
                    </td>
                    <td class="column-date">
                        <span class="swi-date">${this.formatDateTime(order.created_at)}</span>
                    </td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;

        return html;
    }

    generateInlineErrorHtml(message) {
        return `
            <div class="swi-preview-error">
                <div class="swi-error-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="swi-error-content">
                    <h4>No orders found</h4>
                    <p>Unable to fetch orders from the selected store.</p>
                    ${message ? `<p class="swi-error-message">${message}</p>` : ''}
                </div>
            </div>
        `;
    }

    showPreviewModal(storeId, orderStatus, onCloseCallback) {
        const modal = jQuery(`
            <div class="swi-modal-overlay">
                <div class="swi-modal swi-modal-large">
                    <div class="swi-modal-header">
                        <h3>Preview Orders</h3>
                        <button class="swi-modal-close">&times;</button>
                    </div>
                    <div class="swi-modal-body">
                        <div class="swi-loading">
                            <span class="dashicons dashicons-update spin"></span>
                            Loading orders preview...
                        </div>
                    </div>
                </div>
            </div>
        `);

        jQuery('body').append(modal);

        // Close modal functionality
        modal.find('.swi-modal-close, .swi-modal-overlay').on('click', function (e) {
            if (e.target === this) {
                modal.remove();
                if (onCloseCallback) onCloseCallback();
            }
        });

        // Load preview data
        jQuery.ajax({
            url: wmsw_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wmsw_preview_orders',
                store_id: storeId,
                order_status: orderStatus,
                limit: 10,
                nonce: wmsw_ajax.nonce
            },
            success: (response) => {
                if (response.success && response.data.orders) {
                    modal.find('.swi-modal-body').html(this.generatePreviewHtml(response.data));
                } else {
                    modal.find('.swi-modal-body').html(this.generateErrorHtml(response.data?.message));
                }
            },
            error: () => {
                modal.find('.swi-modal-body').html(this.generateErrorHtml('Failed to load orders preview.'));
            }
        });
    }

    generatePreviewHtml(data) {
        let html = `
            <div class="swi-preview-summary">
                <h4>Found ${data.total_count} orders from ${data.store_name}</h4>
                <p>Showing first ${data.orders.length} orders:</p>
            </div>
            <div class="swi-orders-preview-table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Items</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        data.orders.forEach(order => {
            html += `
                <tr>
                    <td><strong>${order.name}</strong></td>
                    <td>${order.customer_name}</td>
                    <td>${order.customer_email}</td>
                    <td>${order.currency} ${order.total}</td>
                    <td>
                        <span class="swi-status-badge status-${order.status}">${order.status}</span>
                        ${order.fulfillment_status !== 'unfulfilled' ? `<br><small>Fulfillment: ${order.fulfillment_status}</small>` : ''}
                    </td>
                    <td>${order.line_items_count} item(s)</td>
                    <td>${this.formatDateTime(order.created_at)}</td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
            <div class="swi-preview-actions">
                <button type="button" class="button button-primary swi-modal-close">Close Preview</button>
            </div>
        `;

        return html;
    }

    generateErrorHtml(message) {
        return `
            <div class="swi-preview-error">
                <p>No orders found or unable to fetch orders.</p>
                ${message ? `<p>${message}</p>` : ''}
                <button type="button" class="button swi-modal-close">Close</button>
            </div>
        `;
    }


    formatDateTime(dateString) {
        if (!dateString) return 'N/A';

        try {
            const date = new Date(dateString);
            const options = {
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            };

            return date.toLocaleString('en-US', options);
        } catch (error) {
            // If date parsing fails, return the original string
            console.warn('Failed to parse date:', dateString, error);
            return dateString;
        }
    }


    showNotification(type, message, title, dataType) {
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
        const notification = jQuery('<div class="swi-notification swi-notification-' + type + ' swi-notification-dismissible swi-toast-notification"' + dataAttribute + '>' +
            '<div class="swi-notification-content">' +
            '<div class="swi-notification-icon">' +
            '<span class="dashicons ' + iconClass + '"></span>' +
            '</div>' +
            '<div class="swi-notification-message">' +
            (notificationTitle ? '<div class="swi-notification-title">' + notificationTitle + '</div>' : '') +
            '<div class="swi-notification-text">' + message + '</div>' +
            '</div>' +
            '</div>' +
            '<button type="button" class="swi-notification-dismiss" aria-label="Dismiss notification">' +
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
        jQuery('body').append(notification);

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
            this.hideNotification(notification);
        }.bind(this), autoRemoveDelay);

        // Add dismiss functionality
        notification.find('.swi-notification-dismiss').on('click', function (e) {
            e.preventDefault();
            this.hideNotification(notification);
        }.bind(this));

        return notification;
    }

    hideNotification(notification) {
        notification.css({
            transform: 'translateX(100%)',
            opacity: '0',
            transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)'
        });

        setTimeout(function () {
            notification.remove();
        }, 300);
    }

    checkForActiveImports() {
        // Get the selected store ID
        const storeId = jQuery('#store_id').val();
        
        if (!storeId) {
            return; // No store selected, can't check for imports
        }

        jQuery.ajax({
            url: wmsw_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wmsw_check_active_order_imports',
                store_id: storeId,
                nonce: wmsw_ajax.nonce
            },
            success: (response) => {
                if (response.success && response.data.active_import) {
                    const importData = response.data.active_import;
                    
                    // Create and show progress container
                    this.createProgressContainer();
                    
                    // Update progress display
                    this.updateProgress(importData);
                    
                    // Start monitoring progress
                    if (importData.progress_key) {
                        this.startProgressMonitoring(importData.progress_key);
                    }
                    
                    // Show notification
                    this.showNotification('info', 'Resuming order import progress tracking...', 'Import Active');
                    
                        
                } else {
                    // No active import found, ensure progress container is hidden if it exists
                    if (this.progressContainer) {
                        this.progressContainer.hide();
                    }
                }
            },
            error: () => {
                showNotification('error', wmsw_ajax.strings.error_checking_imports, wmsw_ajax.strings.error_checking_imports);
            }
        });
    }

}

// Initialize when DOM is ready
jQuery(document).ready(function () {
    window.orderImporter = new OrderImporter();
});
