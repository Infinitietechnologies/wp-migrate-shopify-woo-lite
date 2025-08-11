/**
 * Shopify WooCommerce Importer - Customer Importer Component
 * 
 * Handles all customer import functionality, including:
 * - Form handling and validation
 * - Customer imports
 * - Customer previews
 * - Progress tracking
 * - Modal functionality for customer preview
 */

const wmsw_CustomerImporter = (function ($) {
    'use strict';    // Private variables    
    let _settings = {
        selectors: {
            form: '#customer-import-form',
            storeSelect: '#customer-store-id',
            startImportBtn: '#start-customers-import',
            previewBtn: '#preview-customers',
            advancedFiltersToggle: '#customer-toggle-advanced-filters',
            advancedFiltersSection: '#customer-advanced-filters-section',
            progressContainer: '#customers-import-progress',
            progressBar: '#customers-progress-fill',
            progressText: '#customers-progress-text',
            progressPercentage: '#customers-progress-percentage',
            progressLog: '#customers-progress-log'
        },
        classes: {
            formCard: '.swi-form-card',
            modalActive: 'swi-modal-active'
        },
        ids: {
            previewContainer: 'customer-preview-container'
        }
    };

    /**
     * Initialize the customer importer
     * @param {Object} options Custom options to override defaults
     */
    function init(options = {}) {
        // Merge custom options with defaults
        _settings = $.extend(true, _settings, options);

        // Initialize all UI components
        initFormComponents();

        // Hide initially hidden elements
        $(_settings.selectors.advancedFiltersSection).hide();
        $(_settings.selectors.progressContainer).hide();

        // Set up event handlers
        setupEventHandlers();

        // Check for active imports on page load
        checkForActiveImports();

        // Return the public API
        return publicAPI;
    }

    /**
     * Initialize all form components and UI elements
     */
    function initFormComponents() {
        // Hide progress container by default
        $(_settings.selectors.progressContainer).hide();
        


        // Add hover effects to form cards
        setupCardHoverEffects();
    }

    /**
     * Set up all event handlers for the form
     */
    function setupEventHandlers() {
        // Advanced filters toggle
        setupAdvancedFiltersToggle();

        // Form submission handler
        setupFormSubmission();

        // Customer preview handler
        setupCustomerPreview();

        // Store selection handler
        setupStoreSelectionHandler();
    }

    /**
     * Set up card hover effects
     */
    function setupCardHoverEffects() {
        $(_settings.classes.formCard).on('mouseenter',
            function () {
                $(this).addClass('swi-form-card-hover');
            })
            .on('mouseleave', function () {
                $(this).removeClass('swi-form-card-hover');
            });
    }    /**
     * Set up the toggle for advanced filters
     */
    function setupAdvancedFiltersToggle() {
        $(_settings.selectors.advancedFiltersToggle).on('click', function () {
            const $filtersSection = $(_settings.selectors.advancedFiltersSection);
            const $button = $(this);
            const isVisible = $filtersSection.is(':visible');

            if (isVisible) {
                $filtersSection.hide();
                $button.html('<span class="dashicons dashicons-filter"></span> ' + wmsw_ajax.strings.showAdvancedFilters);
            } else {
                $filtersSection.show();
                $button.html('<span class="dashicons dashicons-filter"></span> ' + wmsw_ajax.strings.hideAdvancedFilters);
            }
        });
    }

    /**
     * Set up form submission for customer import
     */
    function setupFormSubmission() {
        $(_settings.selectors.form).on('submit', function (e) {
            e.preventDefault();

            const storeId = $(_settings.selectors.storeSelect).val();
            if (!storeId) {
                showNotification('error', wmsw_ajax.strings.selectStoreFirst, 'Validation Error');
                return;
            }

            // Get all form data
            const formData = $(this).serialize();

            // Start import
            startCustomerImport(formData);
        });
    }

    /**
     * Set up store selection handler
     */
    function setupStoreSelectionHandler() {
        $(_settings.selectors.storeSelect).on('change', function () {
            const storeId = $(this).val();
            if (storeId) {
                // Check for active imports when store changes
                checkForActiveImports();
            } else {
                // Hide progress container if no store is selected
                $(_settings.selectors.progressContainer).hide();
                // Re-enable the import button
                $(_settings.selectors.startImportBtn).prop('disabled', false);
            }
        });
    }

    /**
     * Set up customer preview button handler
     */
    function setupCustomerPreview() {
        $(_settings.selectors.previewBtn).on('click', function () {
            const storeId = $(_settings.selectors.storeSelect).val();
            if (!storeId) {
                showNotification('error', wmsw_ajax.strings.selectStoreFirst, 'Validation Error');
                return;
            }

            // Get all filter values
            const filters = collectFilterValues();

            // Debug log filters
            console.debug('Preview filters:', filters);

            // Preview customers
            previewCustomers(filters);
        });
    }

    /**
     * Collect all filter values from the form
     * @returns {Object} The filter values
     */    function collectFilterValues() {
        // Basic filters
        const filters = {
            storeId: $(_settings.selectors.storeSelect).val(),
            importAddresses: $('input[name="import_addresses"]').is(':checked'),
            importTags: $('input[name="import_tags"]').is(':checked'),
            sendWelcomeEmail: $('input[name="send_welcome_email"]').is(':checked'),
            batchSize: $('#customer-batch-size').val()
        };

        // Advanced filters if they exist
        if ($(_settings.selectors.advancedFiltersSection).length) {
            filters.customerState = $('#customer-state').val();
            filters.tags = $('#customer-tags').val();
            filters.dateFrom = $('#customer-date-from').val();
            filters.dateTo = $('#customer-date-to').val();
        }

        return filters;
    }

    /**
     * Preview customers with the selected filters
     * @param {Object} filters The filter values
     */    function previewCustomers(filters) {
        // Show loading state
        $(_settings.selectors.previewBtn).prop('disabled', true);

        // Create or clear the customers container
        if (!$('#' + _settings.ids.previewContainer).length) {
            // Create the container
            const $container = $('<div>', {
                'id': _settings.ids.previewContainer,
                'class': 'swi-customers-preview-container swi-action-grid'
            });

            // Create header
            const $header = $('<div>', {
                'class': 'swi-customers-grid-header swi-col-12'
            });

            // Left side with title and count
            const $headerLeft = $('<div>', {
                'class': 'swi-header-left'
            });

            $headerLeft.append(
                $('<h3>', {
                    'text': wmsw_ajax.strings.customerPreviewTitle || 'Customer Preview'
                })
            );

            $headerLeft.append(
                $('<p>', {
                    'class': 'swi-customers-count swi-skeleton-text',
                    'html': '&nbsp;'
                })
            );

            // Right side with actions/tools
            const $headerRight = $('<div>', {
                'class': 'swi-header-right'
            });

            // Add view toggle button (grid/list view)
            $headerRight.append(
                $('<button>', {
                    'type': 'button',
                    'class': 'button swi-view-toggle',
                    'data-view': 'grid',
                    'html': '<span class="dashicons dashicons-grid-view"></span>'
                }).on('click', function () {
                    const $button = $(this);
                    const currentView = $button.attr('data-view');

                    if (currentView === 'grid') {
                        // Switch to list view
                        $button.attr('data-view', 'list');
                        $button.html('<span class="dashicons dashicons-list-view"></span>');
                        $('.swi-customers-grid').removeClass('swi-action-grid').addClass('swi-list-view');
                    } else {
                        // Switch to grid view
                        $button.attr('data-view', 'grid');
                        $button.html('<span class="dashicons dashicons-grid-view"></span>');
                        $('.swi-customers-grid').removeClass('swi-list-view').addClass('swi-action-grid');
                    }
                })
            );

            $header.append($headerLeft);
            $header.append($headerRight);
            $container.append($header);

            // Add skeleton loading UI
            $container.append(createSkeletonLoading(6));

            // Insert after the form
            $container.insertAfter($(_settings.selectors.form));
        } else {
            // Clear the container but preserve the header
            const $container = $('#' + _settings.ids.previewContainer);
            const $header = $container.find('.swi-customers-grid-header').clone();

            // Update the customer count to skeleton using the skeleton loader
            if (window.wmsw_SkeletonLoader) {
                wmsw_SkeletonLoader.replace($header.find('.swi-customers-count'), 'text');
            } else {
                $header.find('.swi-customers-count').addClass('swi-skeleton-text').html('&nbsp;');
            }

            // Clear and add new skeleton loading UI
            $container.empty().append($header).append(createSkeletonLoading(6));
        }

        $.ajax({
            url: wmsw_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wmsw_preview_customers',
                nonce: wmsw_ajax.nonce,
                store_id: filters.storeId,
                options: {
                    import_addresses: filters.importAddresses ? 1 : 0,
                    import_tags: filters.importTags ? 1 : 0,
                    send_welcome_email: filters.sendWelcomeEmail ? 1 : 0,
                    batch_size: filters.batchSize,
                    preview_limit: 10,
                    // Add any advanced filters
                    tags: filters.tags || '',
                    customer_state: filters.customerState || '',
                    date_from: filters.dateFrom || '',
                    date_to: filters.dateTo || ''
                }
            },
            success: function (response) {
                if (response.success) {
                    displayCustomerPreview(response.data);
                } else {
                    showNotification('error', response.data.message, 'Preview Error');
                    $(_settings.selectors.previewBtn).prop('disabled', false);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                showNotification('error', wmsw_ajax.strings.error_loading_preview + ' ' + errorThrown, 'AJAX Error');
                $(_settings.selectors.previewBtn).prop('disabled', false);
            }
        });
    }

    /**
     * Display the customer preview results
     * @param {Object} data The response data from the AJAX call
     */    function displayCustomerPreview(data) {
        const $container = $('#' + _settings.ids.previewContainer);

        // Re-enable the preview button
        $(_settings.selectors.previewBtn).prop('disabled', false);

        // Update the customer count
        $container.find('.swi-customers-count')
            .removeClass('swi-skeleton-text')
            .text(data.message);

        // Clear any existing preview content except header
        $container.find('.swi-customers-grid').remove();
        $container.find('.swi-skeleton-loader').remove();

        if (!data.preview_data || data.preview_data.length === 0) {
            $container.append(
                $('<div>', {
                    'class': 'swi-no-results',
                    'text': wmsw_ajax.strings.noCustomersFound || 'No customers found matching your criteria.'
                })
            );
            return;
        }

        // Create grid container
        const $grid = $('<div>', {
            'class': 'swi-action-grid swi-customers-grid swi-col-12'
        });

        // Add customers to grid
        data.preview_data.forEach(function (customer) {
            $grid.append(createCustomerCard(customer));
        });

        // Add grid to container
        $container.append($grid);

        // If more than 10 customers, add a load more button
        if (data.total_count > data.preview_data.length) {
            const $loadMore = $('<div>', {
                'class': 'swi-load-more-container'
            }).append(
                $('<button>', {
                    'class': 'button',
                    'type': 'button',
                    'text': wmsw_ajax.strings.load_more + ` (${data.preview_data.length} ${wmsw_ajax.strings.of} ${data.total_count})`
                }).on('click', function () {
                    // For now this is just a placeholder button
                    // In a future implementation, this could load more customers
                    $(this).prop('disabled', true).text(wmsw_ajax.strings.feature_coming_soon);
                })
            );

            $container.append($loadMore);
        }
    }

    /**
     * Create a customer card for the preview grid
     * @param {Object} customer Customer data
     * @returns {jQuery} jQuery element for the customer card
     */
    function createCustomerCard(customer) {
        const $card = $('<div>', {
            'class': 'swi-customer-card swi-col-4',
            'data-id': customer.id
        });

        // Customer name and details
        const $cardHeader = $('<div>', {
            'class': 'swi-customer-header'
        });

        // Add customer profile image container (always show for consistent layout)
        const $imageContainer = $('<div>', {
            'class': 'swi-customer-image-container'
        });

        if (customer?.image) {
            // Add customer profile image if available
            $imageContainer.append(
                $('<img>', {
                    'class': 'swi-customer-image',
                    'src': customer.image,
                    'alt': wmsw_ajax.strings.profile_photo
                })
            );
        } else {
            // Add default profile icon if no image
            $imageContainer.append(
                $('<div>', {
                    'class': 'swi-customer-image swi-customer-image-placeholder',
                    'html': '<span class="dashicons dashicons-admin-users"></span>'
                })
            );
        }

        $cardHeader.append($imageContainer);

        // Customer info section (name, status)
        const $customerInfo = $('<div>', {
            'class': 'swi-customer-header-info'
        });

        $customerInfo.append(
            $('<h4>', {
                'class': 'swi-customer-name',
                'text': customer.name || wmsw_ajax.strings.unnamed_customer
            })
        );

        // Add status badge
        const statusClass = customer.status === 'disabled' ? 'swi-status-inactive' : 'swi-status-active';
        const statusText = customer.status === 'disabled' ? wmsw_ajax.strings.disabled : wmsw_ajax.strings.active;

        $customerInfo.append(
            $('<span>', {
                'class': 'swi-customer-status ' + statusClass,
                'text': statusText
            })
        );

        $cardHeader.append($customerInfo);
        $card.append($cardHeader);

        // Customer info
        const $cardBody = $('<div>', {
            'class': 'swi-customer-body swi-card-body'
        });

        // Email
        if (customer.email) {
            $cardBody.append(
                $('<div>', {
                    'class': 'swi-customer-detail'
                }).append(
                    $('<span>', {
                        'class': 'swi-detail-label',
                        'text': wmsw_ajax.strings.email_label
                    })
                ).append(
                    $('<span>', {
                        'class': 'swi-detail-value',
                        'text': customer.email
                    })
                )
            );
        }

        // Phone
        if (customer.phone) {
            $cardBody.append(
                $('<div>', {
                    'class': 'swi-customer-detail'
                }).append(
                    $('<span>', {
                        'class': 'swi-detail-label',
                        'text': 'Phone:'
                    })
                ).append(
                    $('<span>', {
                        'class': 'swi-detail-value',
                        'text': customer.phone
                    })
                )
            );
        }

        // Address
        if (customer.address) {
            $cardBody.append(
                $('<div>', {
                    'class': 'swi-customer-detail'
                }).append(
                    $('<span>', {
                        'class': 'swi-detail-label',
                        'text': 'Address:'
                    })
                ).append(
                    $('<span>', {
                        'class': 'swi-detail-value',
                        'text': customer.address
                    })
                )
            );
        }

        // Tags
        if (customer.tags) {
            const $tagsDetail = $('<div>', {
                'class': 'swi-customer-detail'
            }).append(
                $('<span>', {
                    'class': 'swi-detail-label',
                    'text': 'Tags:'
                })
            );

            const $tagsContainer = $('<span>', {
                'class': 'swi-detail-value swi-tags-container'
            });


            const tags = customer.tags.length > 0 ? customer.tags.map(tag => tag.trim()).filter(tag => tag !== '') : [];

            if (tags.length > 0) {
                tags.forEach(function (tag) {
                    $tagsContainer.append(
                        $('<span>', {
                            'class': 'swi-tag',
                            'text': tag
                        })
                    );
                });
            } else {
                $tagsContainer.text(wmsw_ajax.strings.no_tags);
            }

            $tagsDetail.append($tagsContainer);
            $cardBody.append($tagsDetail);
        }

        // Orders count
        $cardBody.append(
            $('<div>', {
                'class': 'swi-customer-detail'
            }).append(
                $('<span>', {
                    'class': 'swi-detail-label',
                    'text': 'Orders:'
                })
            ).append(
                $('<span>', {
                    'class': 'swi-detail-value',
                    'text': customer.orders_count
                })
            )
        );

        // Total spent
        $cardBody.append(
            $('<div>', {
                'class': 'swi-customer-detail'
            }).append(
                $('<span>', {
                    'class': 'swi-detail-label',
                    'text': 'Total Spent:'
                })
            ).append(
                $('<span>', {
                    'class': 'swi-detail-value swi-customer-total',
                    'text': '$' + customer.total_spent
                })
            )
        );

        $card.append($cardBody);

        // Card footer with created date
        const $cardFooter = $('<div>', {
            'class': 'swi-customer-footer swi-card-footer'
        });

        $cardFooter.append(
            $('<span>', {
                'class': 'swi-customer-created',
                'text': 'Created: ' + customer.created_at
            })
        );

        $card.append($cardFooter);

        return $card;
    }

    /**
     * Create skeleton loader for customer preview
     * @param {number} count Number of skeleton items
     * @returns {jQuery} jQuery element with skeleton loaders
     */
    function createSkeletonLoading(count) {
        const $skeletonContainer = $('<div>', {
            'class': 'swi-skeleton-loader'
        });

        for (let i = 0; i < count; i++) {
            const $skeletonCard = $('<div>', {
                'class': 'swi-skeleton-card'
            });

            // Skeleton header
            const $skeletonHeader = $('<div>', {
                'class': 'swi-skeleton-header'
            });

            $skeletonHeader.append(
                $('<div>', {
                    'class': 'swi-skeleton-title'
                })
            );

            $skeletonHeader.append(
                $('<div>', {
                    'class': 'swi-skeleton-badge'
                })
            );

            $skeletonCard.append($skeletonHeader);

            // Skeleton body
            const $skeletonBody = $('<div>', {
                'class': 'swi-skeleton-body'
            });

            for (let j = 0; j < 4; j++) {
                $skeletonBody.append(
                    $('<div>', {
                        'class': 'swi-skeleton-line'
                    })
                );
            }

            $skeletonCard.append($skeletonBody);

            // Skeleton footer
            const $skeletonFooter = $('<div>', {
                'class': 'swi-skeleton-footer'
            });

            $skeletonFooter.append(
                $('<div>', {
                    'class': 'swi-skeleton-text'
                })
            );

            $skeletonCard.append($skeletonFooter);

            $skeletonContainer.append($skeletonCard);
        }

        return $skeletonContainer;
    }

    /**
     * Start the customer import process
     * @param {string} formData Serialized form data
     */    function startCustomerImport(formData) {
        // Show loading state
        $(_settings.selectors.startImportBtn).prop('disabled', true);

        // Show progress container
        $(_settings.selectors.progressContainer).fadeIn(300);

        // Reset progress bar
        $(_settings.selectors.progressBar).css('width', '0%');
        $(_settings.selectors.progressText).text('Starting import...');
        $(_settings.selectors.progressPercentage).text('0%');
        $(_settings.selectors.progressLog).empty();

        $.ajax({
            url: wmsw_ajax.ajax_url,
            type: 'POST',
            data: formData + '&action=wmsw_start_customers_import&nonce=' + wmsw_ajax.nonce,
            success: function (response) {
                if (response.success) {
                    showNotification('success', response.data.message, 'Import Started');

                    if (response.data.job_id) {
                        // Start polling for progress updates
                        pollImportProgress(response.data.job_id);
                    }
                } else {
                    $(_settings.selectors.startImportBtn).prop('disabled', false);
                    showNotification('error', response.data.message, 'Import Error');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $(_settings.selectors.startImportBtn).prop('disabled', false);
                showNotification('error', wmsw_ajax.strings.error_starting_import + ' ' + errorThrown, 'AJAX Error');
            }
        });
    }

    /**
     * Poll for import progress updates
     * @param {string} jobId The job ID to check progress for
     */
    function pollImportProgress(jobId) {
        $.ajax({
            url: wmsw_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wmsw_get_customer_import_progress',
                nonce: wmsw_ajax.nonce,
                job_id: jobId
            },
            success: function (response) {
                if (response.success) {
                    updateProgressUI(response.data);

                    if (!response.data.completed) {
                        // Continue polling
                        setTimeout(function () {
                            pollImportProgress(jobId);
                        }, 2000);
                    } else {
                        // Import complete - handle completion
                        handleImportCompletion(response.data);
                    }
                } else {
                    $(_settings.selectors.startImportBtn).prop('disabled', false);
                    showNotification('error', response.data.message, 'Progress Error');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $(_settings.selectors.startImportBtn).prop('disabled', false);
                showNotification('error', wmsw_ajax.strings.error_checking_import_progress + ' ' + errorThrown, 'AJAX Error');
            }
        });
    }

    /**
     * Update the progress UI with current progress data
     * @param {Object} data The progress data
     */
    function updateProgressUI(data) {
        const percentage = data.percentage || 0;
        const isCompleted = data.completed || false;
        const $progressContainer = $(_settings.selectors.progressContainer);
        
        // Update progress bar
        $(_settings.selectors.progressBar).css('width', percentage + '%');
        $(_settings.selectors.progressText).text(data.message || 'Processing...');
        $(_settings.selectors.progressPercentage).text(Math.round(percentage) + '%');

        // Add completion styling when import is complete
        if (isCompleted && percentage >= 100) {
            // Add green styling for completed import
            $progressContainer.addClass('swi-progress-green');
            $(_settings.selectors.progressBar).addClass('swi-progress-green');
            
            // Update progress bar to full width with green color
            $(_settings.selectors.progressBar).css('width', '100%');
            $(_settings.selectors.progressPercentage).text('100%');
            
            // Add status indicator for completion
            if (!$('.swi-import-status').length) {
                $(_settings.selectors.progressText).before('<div class="swi-import-status completed">' + wmsw_ajax.strings.customer_import_completed_successfully + '</div>');
            } else {
                $('.swi-import-status').removeClass('failed').addClass('completed').html(wmsw_ajax.strings.customer_import_completed_successfully);
            }
            
        } else if (!isCompleted) {
            // Remove completed styling if not complete (for resuming imports)
            $progressContainer.removeClass('swi-progress-green');
            $(_settings.selectors.progressBar).removeClass('swi-progress-green');
            
            // Add status indicator for in-progress
            const statusText = wmsw_ajax.strings.customer_import_in_progress;
            if (!$('.swi-import-status').length) {
                $(_settings.selectors.progressText).before('<div class="swi-import-status">' + statusText + '</div>');
            } else {
                $('.swi-import-status').removeClass('completed failed').html(statusText);
            }
        }

        // Add to log if provided
        if (data.log_message) {
            $(_settings.selectors.progressLog).prepend(
                '<p>' + data.log_message + '</p>'
            );
        }

        // Show detailed stats if available
        if (data.imported !== undefined || data.updated !== undefined) {
            let statsHtml = '<div class="swi-progress-stats">';
            statsHtml += data.imported !== undefined ? '<span>Imported: ' + data.imported + '</span> ' : '';
            statsHtml += data.updated !== undefined ? '<span>Updated: ' + data.updated + '</span> ' : '';
            statsHtml += data.failed !== undefined ? '<span>Failed: ' + data.failed + '</span> ' : '';
            statsHtml += data.skipped !== undefined ? '<span>Skipped: ' + data.skipped + '</span> ' : '';
            statsHtml += '</div>';
            
            // Update or add stats
            if ($('.swi-progress-stats').length) {
                $('.swi-progress-stats').html(statsHtml);
            } else {
                $(_settings.selectors.progressPercentage).after(statsHtml);
            }
        }
    }

    /**
     * Handle import completion with proper styling and notifications
     * @param {Object} completionData The completion data
     */
    function handleImportCompletion(completionData) {
        // Enable the import button
        $(_settings.selectors.startImportBtn).prop('disabled', false);

        // Build completion message with stats
        let message = wmsw_ajax.strings.customer_import_completed_successfully;
        if (completionData.imported !== undefined || completionData.updated !== undefined) {
            const stats = [];
            if (completionData.imported > 0) stats.push(`Imported: ${completionData.imported}`);
            if (completionData.updated > 0) stats.push(`Updated: ${completionData.updated}`);
            if (completionData.failed > 0) stats.push(`Failed: ${completionData.failed}`);
            if (completionData.skipped > 0) stats.push(`Skipped: ${completionData.skipped}`);
            
            if (stats.length > 0) {
                message += ` (${stats.join(', ')})`;
            }
        }

        // Show completion message in the log
        const $completionMessage = $('<div>', {
            'class': 'swi-completion-message',
            'html': '<strong>Customer Import Complete!</strong> ' + message
        });

        $(_settings.selectors.progressLog).prepend($completionMessage);

        // Display a success notification
        showNotification('success', message, 'Import Complete');

        // Final update to ensure completion styling is applied
        updateProgressUI({
            percentage: 100,
            message: completionData.message || 'Customer import completed successfully!',
            completed: true,
            imported: completionData.imported,
            updated: completionData.updated,
            failed: completionData.failed,
            skipped: completionData.skipped
        });
    }

    /**
     * Check for active customer imports on page load
     */
    function checkForActiveImports() {
        // Get the selected store ID
        const storeId = $(_settings.selectors.storeSelect).val();
        
        if (!storeId) {
            return; // No store selected, can't check for imports
        }

        $.ajax({
            url: wmsw_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wmsw_check_active_customer_imports',
                store_id: storeId,
                nonce: wmsw_ajax.nonce
            },
            success: function (response) {
                if (response.success && response.data.active_import) {
                    const importData = response.data.active_import;
                    
                    // Show the progress container
                    $(_settings.selectors.progressContainer).show();
                    
                    // Disable the import button
                    $(_settings.selectors.startImportBtn).prop('disabled', true);
                    
                    // Update progress display
                    updateProgressUI(importData);
                    
                    // Start monitoring progress
                    pollImportProgress(importData.job_id);
                    
                    // Show notification
                    showNotification('info', wmsw_ajax.strings.resuming_customer_import_progress_tracking, wmsw_ajax.strings.import_active);
                    
                } else {
                    // No active import found, ensure progress is hidden
                    $(_settings.selectors.progressContainer).hide();
                    // Re-enable the import button
                    $(_settings.selectors.startImportBtn).prop('disabled', false);
                }
            },
            error: function () {
                showNotification('error', wmsw_ajax.strings.error_checking_imports, wmsw_ajax.strings.error_checking_imports);
            }
        });
    }

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
            hideNotification(notification);
        }, autoRemoveDelay);

        // Add dismiss functionality
        notification.find('.swi-notification-dismiss').on('click', function (e) {
            e.preventDefault();
            hideNotification(notification);
        });

        return notification;
    }

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

    // Public API
    const publicAPI = {
        init: init,
        previewCustomers: previewCustomers,
        startCustomerImport: startCustomerImport
    };

    return publicAPI;
})(jQuery);

// Initialize when document is ready
jQuery(document).ready(function () {
    // Initialize customer importer
    wmsw_CustomerImporter.init();
});
