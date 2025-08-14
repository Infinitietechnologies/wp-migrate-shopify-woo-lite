/**
 * Shopify WooCommerce Importer - Product Importer Component
 * 
 * Handles all product import functionality, including:
 * - Form handling and validation
 * - Product imports (direct and scheduled)
 * - Product previews
 * - Progress tracking
 * - Modal functionality for product preview
 */

const wmsw_ProductImporter = (function ($) {
    'use strict';

    // Private variables
    let _settings = {
        selectors: {
            form: '#products-import-form',
            storeSelect: '#store_id',
            startImportBtn: '#start-products-import',
            previewBtn: '#preview-products',
            scheduleCheckbox: '#schedule_import',
            dateRangeRow: '#date-range-row',
            importTypeRadio: 'input[name="import_type"]',
            advancedFiltersToggle: '#toggle-advanced-filters',
            advancedFiltersSection: '#advanced-filters-section',
            progressContainer: '#import-progress',
            progressBar: '.swi-progress-fill',
            progressText: '#progress-text',
            progressPercentage: '#progress-percentage',
            progressLog: '#progress-log'
        },
        classes: {
            formCard: '.swi-form-card',
            modalActive: 'swi-modal-active'
        }
    };    /**
     * Initialize the product importer
     * @param {Object} options Custom options to override defaults
     */
    function init(options = {}) {
        // Merge custom options with defaults
        _settings = $.extend(true, _settings, options);

        // Initialize all UI components
        initFormComponents();

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



        // Style the price range inputs like date range
        $('.price-range-inputs').addClass('date-range-inputs');

        // Add hover effects to form cards
        setupCardHoverEffects();

        // Add tooltips to option groups
        setupOptionTooltips();

        // Initialize option states
        initializeOptionStates();
    }

    /**
     * Set up all event handlers for the form
     */
    function setupEventHandlers() {
        // Date range toggle based on import type
        setupDateRangeToggle();

        // Advanced filters toggle
        setupAdvancedFiltersToggle();

        // Form submission handler
        setupFormSubmission();

        // Product preview handler
        setupProductPreview();

        // Option interdependencies
        setupOptionInterdependencies();

        // Store selection handler
        setupStoreSelectionHandler();
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
            }
        });
    }

    /**
     * Set up card hover effects
     */
    function setupCardHoverEffects() {
        $(_settings.classes.formCard).on('mouseenter',
            function () {
                $(this).addClass('swi-card-hover');
            })
            .on('mouseleave', function () {
                $(this).removeClass('swi-card-hover');
            }
            );
    }

    /**
     * Set up date range toggle based on import type
     */
    function setupDateRangeToggle() {
        $(_settings.selectors.importTypeRadio).on("change", function () {
            if ($(this).val() === 'custom') {
                $(_settings.selectors.dateRangeRow).slideDown(200);
            } else {
                $(_settings.selectors.dateRangeRow).slideUp(200);
            }
        });
    }

    /**
     * Set up advanced filters toggle
     */
    function setupAdvancedFiltersToggle() {
        $(_settings.selectors.advancedFiltersToggle).on('click', function (e) {
            e.preventDefault();
            const $advancedFilters = $(_settings.selectors.advancedFiltersSection);
            const $button = $(this);
            const isVisible = $advancedFilters.is(':visible');

            if (isVisible) {
                $advancedFilters.slideUp(300);
                $button.html('<span class="dashicons dashicons-filter"></span> ' + wmsw_ajax.strings.showAdvancedFilters);
                $button.removeClass('is-active');
            } else {
                $advancedFilters.slideDown(300);
                $button.html('<span class="dashicons dashicons-no-alt"></span> ' + wmsw_ajax.strings.hideAdvancedFilters);
                $button.addClass('is-active');
            }
        });
    }

    /**
     * Set up form submission
     */
    function setupFormSubmission() {
        $(_settings.selectors.progressContainer).hide();

        $(_settings.selectors.form).on('submit', function (e) {
            e.preventDefault();
            const formData = $(this).serialize();
            const scheduleImport = $(_settings.selectors.scheduleCheckbox).is(':checked');

            if (scheduleImport) {
                // Schedule background import
                scheduleProductsImport(formData);
            } else {
                // Start real-time import
                startProductsImport(formData);
            }
        });
    }

    /**
     * Set up product preview
     */
    function setupProductPreview() {
        $(_settings.selectors.previewBtn).on('click', function () {
            const storeId = $(_settings.selectors.storeSelect).val();
            if (!storeId) {
                showNotification('error', wmsw_ajax.strings.selectStoreFirst, 'Validation Error');
                return;
            }        // Get all filter values
            const filters = collectFilterValues();

            // Debug log filters
            console.debug('Preview filters:', filters);

            // Preview products
            previewProducts(filters);
        });
    }

    /**
     * Collect all filter values from the form
     * @returns {Object} The filter values
     */
    function collectFilterValues() {        // Basic filters
        const filters = {
            storeId: $(_settings.selectors.storeSelect).val(),
            importType: $(_settings.selectors.importTypeRadio + ':checked').val(),
            dateFrom: $('#date_from').val(),
            dateTo: $('#date_to').val(),
            productType: $('#product_type').val(),
            vendor: $('#vendor').val(),
            tags: $('#tags').val(),
            minPrice: $('#min_price').val(),
            maxPrice: $('#max_price').val(),
            inventoryStatus: $('#inventory_status').val(),
            status: $('#product_status').val(), // Add the new product status filter
            includeDrafts: $('#include_drafts').is(':checked')
        };

        // Processing settings
        filters.batchSize = $('#batch_size').val();
        filters.processingThreads = $('#processing_threads').val();
        filters.errorHandling = $('#error_handling').val();

        // Scheduling options
        filters.scheduleImport = $('#schedule_import').is(':checked');
        filters.scheduleType = $('#schedule_type').val();
        filters.scheduleStart = $('#schedule_start').val();

        // Notification settings
        filters.emailNotification = $('#email_notification').is(':checked');
        filters.notificationEmail = $('#notification_email').val();

        // Content import options
        filters.importImages = $('#import_images').is(':checked');
        filters.importVariants = $('#import_variants').is(':checked');
        filters.importVideos = $('#import_videos').is(':checked');
        filters.importDescriptions = $('#import_descriptions').is(':checked');
        filters.importSEO = $('#import_seo').is(':checked');

        // Taxonomy options
        filters.importCollections = $('#import_collections').is(':checked');
        filters.importTags = $('#import_tags').is(':checked');
        filters.importVendorAsBrand = $('#import_vendor_as_brand').is(':checked');

        // Data structure options
        filters.preserveIds = $('#preserve_ids').is(':checked');
        filters.importMetafields = $('#import_metafields').is(':checked');
        filters.createLookupTable = $('#create_product_lookup_table').is(':checked');

        // Advanced options
        filters.overwriteExisting = $('#overwrite_existing').is(':checked');
        filters.skipNoInventory = $('#skip_no_inventory').is(':checked');
        filters.syncInventory = $('#sync_inventory').is(':checked');

        return filters;
    }

    /**
     * Start products import
     * @param {string} formData The serialized form data
     */    function startProductsImport(formData) {
        $(_settings.selectors.progressContainer).show();
        $(_settings.selectors.startImportBtn).prop('disabled', true);        // Implementation for real-time import
        // Using just one endpoint to prevent parallel imports

        // Convert form data to use namespaced fields
        let namespacedFormData = convertToNamespacedFields(formData);

        $.ajax({
            url: wmsw_ajax.ajax_url,
            type: 'POST',
            data: namespacedFormData + '&action=wmsw_start_products_import&nonce=' + wmsw_ajax.nonce,
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // Start progress monitoring - use import_id from response
                    monitorImportProgress(response.data.import_id);
                    showNotification('success', response.data.message || wmsw_ajax.strings.import_started, wmsw_ajax.strings.import_started);
                } else {
                    showNotification('error', response.data.message || wmsw_ajax.strings.importFailed, wmsw_ajax.strings.import_error);
                    $(_settings.selectors.startImportBtn).prop('disabled', false);
                }
            },
            error: function () {
                showNotification('error', wmsw_ajax.strings.serverError, wmsw_ajax.strings.server_error);
                $(_settings.selectors.startImportBtn).prop('disabled', false);
            }
        });
    }

    /**
     * Schedule products import
     * @param {string} formData The serialized form data
     */    function scheduleProductsImport(formData) {
        // Convert form data to use namespaced fields
        let namespacedFormData = convertToNamespacedFields(formData);

        $.ajax({
            url: wmsw_ajax.ajax_url,
            type: 'POST',
            data: namespacedFormData + '&action=wmsw_schedule_products_import&nonce=' + wmsw_ajax.nonce,
            success: function (response) {
                if (response.success) {
                    showNotification('success', wmsw_ajax.strings.importScheduled, wmsw_ajax.strings.import_scheduled);
                    setTimeout(function () {
                        location.reload();
                    }, 1000); // Brief delay to show the notification before reload
                } else {
                    showNotification('error', response.data.message || wmsw_ajax.strings.scheduleImportFailed, wmsw_ajax.strings.scheduling_failed);
                }
            },
            error: function () {
                showNotification('error', wmsw_ajax.strings.serverError, wmsw_ajax.strings.server_error);
            }
        });
    }

    /**
     * Preview products based on filters
     * @param {Object} filters The filter values
     */
    function previewProducts(filters) {
        // Show loading state
        $(_settings.selectors.previewBtn).prop('disabled', true);

        // Create or clear the products container
        if (!$('#swi-products-preview-container').length) {
            // Create the container
            const $container = $('<div>', {
                'id': 'swi-products-preview-container',
                'class': 'swi-products-preview-container'
            });

            // Create header
            const $header = $('<div>', {
                'class': 'swi-products-grid-header'
            });

            // Left side with title
            const $headerLeft = $('<div>', {
                'class': 'swi-header-left'
            });

            $headerLeft.append(
                $('<h3>', {
                    'text': wmsw_ajax.strings.productPreviewTitle || 'Product Preview'
                })
            );

            $headerLeft.append(
                $('<p>', {
                    'class': 'swi-products-count swi-skeleton-text',
                    'html': '&nbsp;'
                })
            );

            $header.append($headerLeft);
            $container.append($header);

            // Add skeleton loading UI
            $container.append(createSkeletonLoading(6));

            // Insert after the form
            $container.insertAfter($(_settings.selectors.form));
        } else {
            // Clear the container but preserve the header
            const $container = $('#swi-products-preview-container');
            const $header = $container.find('.swi-products-grid-header').clone();            // Update the product count to skeleton using the skeleton loader
            if (window.wmsw_SkeletonLoader) {
                wmsw_SkeletonLoader.replace($header.find('.swi-products-count'), 'text');
            } else {
                $header.find('.swi-products-count').addClass('swi-skeleton-text').html('&nbsp;');
            }

            // Clear and add new skeleton loading UI
            $container.empty().append($header).append(createSkeletonLoading(6));
        } $.ajax({
            url: wmsw_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wmsw_preview_products',
                nonce: wmsw_ajax.nonce,
                store_id: filters.storeId,
                options: {
                    preview_limit: 10,
                    import_type: filters.importType || 'all',
                    date_from: filters.dateFrom || '',
                    date_to: filters.dateTo || '',
                    import_drafts: filters.includeDrafts || false,
                    product_type: filters.productType || '',
                    vendor: filters.vendor || '',
                    tags: filters.tags || '',
                    min_price: filters.minPrice || '',
                    max_price: filters.maxPrice || '',
                    inventory_status: filters.inventoryStatus || '',
                    status: filters.status || '', // Product status filter (draft/active)
                    collection_id: filters.collectionId || '',
                    batch_size: filters.batchSize || '50',
                    processing_threads: filters.processingThreads || '1',
                    error_handling: filters.errorHandling || 'skip'
                }
            },
            dataType: 'json',
            success: function (response) {
                $(_settings.selectors.previewBtn).prop('disabled', false); if (response.success) {
                    // Log debug info if available
                    if (typeof console !== 'undefined' && console.debug && response.data.debug_info) {
                        console.debug('Product preview debug info:', response.data.debug_info);
                    }

                    // Display products in grid view - the skeletons will be replaced
                    displayProductGrid(response.data.preview_data);

                    // Show message about how many products were found in subtitle
                    if (response.data.message) {
                        // Update the message text without notification
                        $('#preview-message').text(response.data.message);
                    }

                    // Log the first product's data for debugging
                    if (typeof console !== 'undefined' && console.debug &&
                        response.data.preview_data && response.data.preview_data.length > 0) {
                        console.debug('First product data:', response.data.preview_data[0]);
                    }
                } else {                    // Show error in the container instead of notification
                    const $container = $('#swi-products-preview-container');
                    const $header = $container.find('.swi-products-grid-header');
                    const errorMessage = response.data.message || wmsw_ajax.strings.previewFailed;

                    $container.find('.swi-products-grid').remove();
                    if (window.wmsw_SkeletonLoader) {
                        wmsw_SkeletonLoader.restore($header.find('.swi-products-count'), wmsw_ajax.strings.no_products_found);
                    } else {
                        $header.find('.swi-products-count').removeClass('swi-skeleton-text').text(wmsw_ajax.strings.no_products_found);
                    }

                    $container.append(
                        $('<div>', {
                            'class': 'swi-empty-state',
                            'html': '<p>' + errorMessage + '</p>'
                        })
                    );
                }
            }, error: function () {
                $(_settings.selectors.previewBtn).prop('disabled', false);

                // Show error in the container without showing notification                
                const $container = $('#swi-products-preview-container');
                const $header = $container.find('.swi-products-grid-header');

                $container.find('.swi-products-grid').remove();
                if (window.wmsw_SkeletonLoader) {
                    wmsw_SkeletonLoader.restore($header.find('.swi-products-count'), wmsw_ajax.strings.no_products_found);
                } else {
                    $header.find('.swi-products-count').removeClass('swi-skeleton-text').text(wmsw_ajax.strings.no_products_found);
                }

                $container.append(
                    $('<div>', {
                        'class': 'swi-empty-state',
                        'html': '<p>' + wmsw_ajax.strings.serverError + '</p>'
                    })
                );
            }
        });
    }

    /**
     * Display product preview in a grid layout
     * @param {Array} products The products to display
     */
    function displayProductGrid(products) {
        const $container = $('#swi-products-preview-container');
        $container.empty();

        // Create header
        const $header = $('<div>', {
            'class': 'swi-products-grid-header'
        });

        // Left side with title and count
        const $headerLeft = $('<div>', {
            'class': 'swi-header-left'
        });

        $headerLeft.append(
            $('<h3>', {
                'text': wmsw_ajax.strings.product_preview_title
            })
        );

        // Status message about number of products
        const productCount = products ? products.length : 0;
        $headerLeft.append(
            $('<p>', {
                'class': 'swi-products-count',
                'text': productCount + ' ' + (productCount === 1 ? wmsw_ajax.strings.product_found : wmsw_ajax.strings.products_found)
            })
        );

        $header.append($headerLeft);

        // Add preview controls to right side
        $header.append(addPreviewControls());

        $container.append($header);

        // Create grid container for products
        const $grid = $('<div>', {
            'class': 'swi-products-grid'
        });

        if (products && products.length > 0) {
            // Add each product as a card to the grid
            products.forEach(function (product) {
                $grid.append(createProductCard(product));
            });
        } else {
            // No products found state
            $grid.append(
                $('<div>', {
                    'class': 'swi-empty-state',
                    'text': wmsw_ajax.strings.no_products_found_criteria
                })
            );
        }
        $container.append($grid);
    }

    /**
     * Create a product card for the grid view
     * @param {Object} product The product data
     * @returns {jQuery} The product card element
     */
    function createProductCard(product) {
        const $card = $('<div>', {
            'class': 'swi-card swi-product-card p-0',
            'data-product-id': product.id || ''
        });
        // Product image section
        const $imageSection = $('<div>', {
            'class': 'swi-product-card-image'
        });

        // Try to get image URL from various sources
        let imageUrl = null;

        // First try the standard image property
        if (product.image) {
            imageUrl = product.image;
        }
        // Then try raw image data if available
        else if (product.raw_image_data) {
            try {
                // Try to parse if it's a JSON string
                const imageData = typeof product.raw_image_data === 'string'
                    ? JSON.parse(product.raw_image_data)
                    : product.raw_image_data;

                if (imageData && imageData.src) {
                    imageUrl = imageData.src;
                    console.debug('Found image URL in raw_image_data', imageUrl);
                }
            } catch (e) {
            }
        }        // Finally try raw images array if available
        else if (product.raw_images_data) {
            try {
                // Try to parse if it's a JSON string
                const imagesData = typeof product.raw_images_data === 'string'
                    ? JSON.parse(product.raw_images_data)
                    : product.raw_images_data;

                if (Array.isArray(imagesData) && imagesData.length > 0 && imagesData[0].src) {
                    imageUrl = imagesData[0].src;
                    console.debug('Found image URL in raw_images_data', imageUrl);
                }
            } catch (e) {
            }
        }
        // Try getting image from variants if available
        else if (product.variants && Array.isArray(product.variants) && product.variants.length > 0) {
            // Try to find a variant with an image
            for (let i = 0; i < product.variants.length; i++) {
                const variant = product.variants[i];
                if (variant.image && variant.image.src) {
                    imageUrl = variant.image.src;
                    break;
                }
            }
        }

        // Ensure HTTPS URL
        if (imageUrl && imageUrl.indexOf('http:') === 0) {
            imageUrl = 'https:' + imageUrl.substring(5);
        }

        if (imageUrl) {

            const $img = $('<img>', {
                'src': imageUrl,
                'alt': product.title,
                'loading': 'lazy',
                'onerror': "this.onerror=null; this.parentNode.innerHTML='<div class=\"swi-product-no-image\"><span class=\"dashicons dashicons-format-image\"></span><p>Image failed to load</p></div>';"
            });

            $imageSection.append($img);
        } else {
            $imageSection.append(
                $('<div>', {
                    'class': 'swi-product-no-image',
                    'html': '<span class="dashicons dashicons-format-image"></span>'
                })
            );
        }

        // Add product type badge if available
        if (product.type) {
            $imageSection.append(
                $('<span>', {
                    'class': 'swi-product-type-badge',
                    'text': product.type
                })
            );
        }        // Add variant count badge if available
        // Get variant count from either variants array or variants_count property
        const variantCount = product.variants?.length || product.variants_count || 0;
        if (variantCount > 0) {
            $imageSection.append(
                $('<span>', {
                    'class': 'swi-product-variants-badge',
                    'text': variantCount + (variantCount === 1 ? ' variant' : ' variants')
                })
            );
        }

        // Product info section
        const $infoSection = $('<div>', {
            'class': 'swi-card-body swi-product-card-info mb-0'
        });        // Title
        $infoSection.append(
            $('<h4>', {
                'class': 'swi-product-title',
                'text': product.title
            })
        );

        // Add short description if available
        if (product.description) {
            $infoSection.append(
                $('<div>', {
                    'class': 'swi-product-description',
                    'text': truncateText(product.description, 80)
                })
            );
        }

        // Product meta section
        const $metaSection = $('<div>', {
            'class': 'swi-product-meta'
        });

        // Vendor if available
        if (product.vendor) {
            $metaSection.append(
                $('<div>', {
                    'class': 'swi-product-meta-item',
                    'html': '<span class="swi-product-meta-label">Vendor:</span> ' + product.vendor
                })
            );
        }        // Categories/collections if available
        if (product.collections?.length) {
            const collectionsText = product.collections.slice(0, 2).join(', ') +
                (product.collections.length > 2 ? '...' : '');

            $metaSection.append(
                $('<div>', {
                    'class': 'swi-product-meta-item',
                    'html': '<span class="swi-product-meta-label">Category:</span> ' + collectionsText
                })
            );
        } else if (product.category) {
            $metaSection.append(
                $('<div>', {
                    'class': 'swi-product-meta-item',
                    'html': '<span class="swi-product-meta-label">Category:</span> ' + product.category
                })
            );
        }

        $infoSection.append($metaSection);

        // Price section
        const $priceSection = $('<div>', {
            'class': 'swi-product-price'
        });

        $priceSection.append(
            $('<span>', {
                'class': 'swi-price-amount',
                'text': formatPrice(product.price)
            })
        );

        $infoSection.append($priceSection);        // Footer section with product status
        const $footerSection = $('<div>', {
            'class': 'swi-card-footer swi-product-card-actions'
        });        // Show product status (e.g. "Active", "Draft", "Archived") if available
        // First try to use the status field, then fall back to published flag
        let statusText;
        if (product.status) {
            // Shopify statuses are: active, draft, archived
            statusText = product.status.charAt(0).toUpperCase() + product.status.slice(1);
        } else {
            statusText = product.published === true ? 'Active' : 'Draft';
        }

        $footerSection.append(
            $('<div>', {
                'class': 'swi-product-status status-tag ' + statusText.toLowerCase(),
                'text': statusText
            })
        );// Assemble the card
        $card.append($imageSection);
        $card.append($infoSection);
        $card.append($footerSection);

        // Debug the final card structure
        if (typeof console !== 'undefined' && console.debug) {
            console.debug('Product card created:', {
                productId: product.id,
                hasImageSection: $imageSection.children().length > 0,
                imageUrl: imageUrl,
                imageHtml: $imageSection.html()
            });
        }

        return $card;
    }

    /**
     * Add refresh button and clear button to the product preview
     * Creates functionality to refresh the product preview or clear it completely
     */
    function addPreviewControls() {
        // Check if controls already exist
        if ($('#swi-preview-controls').length) {
            return;
        }

        // Create controls container
        const $controls = $('<div>', {
            'id': 'swi-preview-controls',
            'class': 'swi-preview-controls'
        });

        // Add refresh button
        $controls.append(
            $('<button>', {
                'class': 'button button-primary', 'text': wmsw_ajax.strings.refreshPreview || 'Refresh Preview',
                'id': 'refresh-preview'
            }).on('click', function () {
                const filters = collectFilterValues();
                previewProducts(filters);
            })
        );

        // Add clear button
        $controls.append(
            $('<button>', {
                'class': 'button',
                'text': wmsw_ajax.strings.clearPreview || 'Clear Preview',
                'id': 'clear-preview'
            }).on('click', function () {
                $('#swi-products-preview-container').slideUp(300, function () {
                    $(this).remove();
                });
            })
        );

        return $controls;
    }    /**
     * Format price for display
     * @param {string|number} price The price to format
     * @returns {string} The formatted price
     */
    function formatPrice(price) {
        if (!price) return 'N/A';

        // Check if price is already formatted
        if (typeof price === 'string' && price.includes('$')) {
            return price;
        }

        // Try to parse the price as a number
        const numericPrice = parseFloat(price);
        if (isNaN(numericPrice)) {
            return price;
        }

        // Format the price with 2 decimal places
        return '$' + numericPrice.toFixed(2);
    }

    /**
     * Truncate text to a certain length and add ellipsis if needed
     * @param {string} text The text to truncate
     * @param {number} maxLength The maximum length
     * @returns {string} The truncated text
     */
    function truncateText(text, maxLength = 100) {
        if (!text) return '';

        // Strip any HTML tags first
        const strippedText = text.replace(/<\/?[^>]+(>|$)/g, "");

        if (strippedText.length <= maxLength) {
            return strippedText;
        }

        // Find a space near the limit to avoid cutting words in the middle
        let truncated = strippedText.substr(0, maxLength);
        let lastSpaceIndex = truncated.lastIndexOf(' ');

        if (lastSpaceIndex > maxLength * 0.8) { // Only use space if it's not too far back
            truncated = truncated.substr(0, lastSpaceIndex);
        }

        return truncated + '...';
    }

    /**
     * Monitor import progress
     * @param {string} taskId The task ID to monitor
     */    function monitorImportProgress(importId) {
        // Check progress every few seconds
        const progressInterval = setInterval(function () {
            $.ajax({
                url: wmsw_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wmsw_get_import_progress',
                    import_id: importId, // Changed to match the PHP parameter name
                    nonce: wmsw_ajax.nonce
                },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        updateProgressUI(response.data);

                        // Check if complete
                        if (response.data.is_complete) {
                            clearInterval(progressInterval);
                            handleImportCompletion(response.data, response.data.items_total);
                        }
                    } else {
                        showNotification('error', response.data.message || 'Error checking import progress', 'Progress Error');
                    }
                },
                error: function () {
                    showNotification('error', 'Server error while checking import progress', 'Connection Error');
                }
            });
        }, 3000); // Check every 3 seconds
    }


    /**
     * Check for active imports on page load
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
                action: 'wmsw_check_active_imports',
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
                    monitorImportProgress(importData.import_id);

                    // Show notification
                    showNotification('info', 'Resuming import progress tracking...', 'Import Active');

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

    /**
 * Update progress UI
 * @param {Object} data The progress data
 */
    function updateProgressUI(data) {
        let processedItems = parseInt(data.items_processed);
        let totalItems = parseInt(data.items_total);

        // Fallback if total is missing or zero
        if (isNaN(totalItems) || totalItems < 1) {
            if (typeof data.total !== 'undefined') {
                totalItems = parseInt(data.total);
            }
            if (isNaN(totalItems) || totalItems < 1) {
                totalItems = 0;
            }
        }

        if (isNaN(processedItems) || processedItems < 0) {
            processedItems = 0;
        }

        let progressText = totalItems === 0
            ? 'No products to import'
            : processedItems + ' / ' + totalItems;

        let percentage = totalItems > 0
            ? (processedItems / totalItems) * 100
            : 0;

        // Update progress bar
        $('#products-progress-fill').css('width', percentage + '%');

        // Update text using correct IDs
        $('#products-progress-text').html(progressText);
        $('#products-progress-percentage').html(Math.round(percentage) + '%');

        // Add import status indicator
        const statusText = data.status === 'in_progress' ? wmsw_ajax.strings.product_import_in_progress : 'Import Status: ' + data.status;
        const statusClass = data.status === 'completed' ? 'completed' : (data.status === 'failed' ? 'failed' : '');

        if (!$('.swi-import-status').length) {
            $('#products-progress-text').before('<div class="swi-import-status ' + statusClass + '">' + statusText + '</div>');
        } else {
            $('.swi-import-status').removeClass('completed failed').addClass(statusClass).html(statusText);
        }

        // Update log
        if (data.recent_logs) {
            let logHtml = '';
            data.recent_logs.forEach(function (log) {
                logHtml += '<div class="swi-log-entry swi-log-' + log.level + '">';
                logHtml += '<span class="swi-log-time">' + log.created_at + '</span>';
                logHtml += '<span class="swi-log-message">' + log.message + '</span>';
                logHtml += '</div>';
            });
            $('#products-progress-log').html(logHtml);
        }
        $('#products-import-progress').show();
    }



    /**
     * Handle import completion
     * @param {Object} completionData The completion data
     */
    function handleImportCompletion(completionData, message) {
        // Enable the import button
        $(_settings.selectors.startImportBtn).prop('disabled', false);

        // Show completion message
        const $completionMessage = $('<div>', {
            'class': 'swi-completion-message',
            'html': '<strong>' + wmsw_ajax.strings.importComplete + '</strong> ' +
                message
        });

        $(_settings.selectors.progressLog).prepend($completionMessage);

        // Display a success notification
        showNotification('success', message, 'Import Complete');
    }

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

    /**
     * Set up interdependencies between options
     */
    function setupOptionInterdependencies() {
        // When "Import Collections" is unchecked, disable the "Import as Categories" option
        $('#import_collections').on('change', function () {
            const $this = $(this);
            if (!$this.is(':checked')) {
                // Could add more actions here for related options
            }
        });

        // When "Import Variants" is toggled, handle related options
        $('#import_variants').on('change', function () {
            const $this = $(this);
            if (!$this.is(':checked')) {
                // Actions when variants are disabled
            }
        });

        // When "Import Custom Metafields" is checked, we could show a metafields selector
        $('#import_metafields').on('change', function () {
            const $this = $(this);
            if ($this.is(':checked')) {
                // In the future, could show a metafields selector here
            }
        });

        // Skip products with no inventory - when checked, update related options
        $('#skip_no_inventory').on('change', function () {
            const $syncInventory = $('#sync_inventory');

            if ($(this).is(':checked')) {
                // If we're skipping no-inventory products, 
                // it makes sense to sync inventory by default
                if (!$syncInventory.is(':checked')) {
                    $syncInventory.prop('checked', true);
                }
            }
        });

        // Schedule import toggle
        $('#schedule_import').on('change', function () {
            if ($(this).is(':checked')) {
                $('#schedule-options').slideDown(200);
            } else {
                $('#schedule-options').slideUp(200);
            }
        });

        // Email notifications toggle
        $('#email_notification').on('change', function () {
            if ($(this).is(':checked')) {
                $('#email-options').slideDown(200);
            } else {
                $('#email-options').slideUp(200);
            }
        });

        // Processing threads changes
        $('#processing_threads').on('change', function () {
            const threadCount = parseInt($(this).val(), 10);

            if (threadCount > 4) {
                // Show a warning for high thread counts
                if (!$('#thread-warning').length) {
                    $(this).after(
                        $('<p>', {
                            'id': 'thread-warning',
                            'class': 'description warning',
                            'text': wmsw_ajax.strings.highThreadWarning || 'High thread counts may cause server performance issues.'
                        })
                    );
                }
            } else {
                $('#thread-warning').remove();
            }
        });
    }

    /**
     * Set up tooltips for option groups
     */
    function setupOptionTooltips() {
        // Define tooltip data
        const tooltips = {
            'import_images': 'Import all product images from Shopify, including gallery images',
            'import_variants': 'Import product variants as WooCommerce variations',
            'import_videos': 'Import product videos from Shopify if available',
            'import_descriptions': 'Import product descriptions and maintain formatting',
            'import_seo': 'Import SEO meta title and description from Shopify',
            'import_collections': 'Import Shopify collections as WooCommerce categories',
            'import_tags': 'Import product tags from Shopify',
            'import_vendor_as_brand': 'Use Shopify vendor field as product brand',
            'preserve_ids': 'Keep Shopify IDs in product meta for synchronization',
            'import_metafields': 'Import custom metafields from Shopify products',
            'create_product_lookup_table': 'Create a lookup table to map Shopify product IDs to WooCommerce product IDs',
            'overwrite_existing': 'Update existing products when matching Shopify products are found',
            'skip_no_inventory': 'Skip importing products that have zero inventory',
            'sync_inventory': 'Keep inventory levels synchronized between Shopify and WooCommerce'
        };

        // Create tooltips
        Object.keys(tooltips).forEach(function (id) {
            const $option = $('#' + id).closest('.checkbox-label');
            const tooltipText = tooltips[id];

            $option.append(
                $('<span>', {
                    'class': 'option-tooltip dashicons dashicons-info-outline',
                    'title': tooltipText
                })
            );
        });
    }

    /**
     * Initialize option states based on default settings
     */
    function initializeOptionStates() {
        // Initialize schedule options visibility
        if ($('#schedule_import').is(':checked')) {
            $('#schedule-options').show();
        }

        // Initialize email notification options visibility
        if ($('#email_notification').is(':checked')) {
            $('#email-options').show();
        }

        // Set default datetime for schedule start if empty
        if (!$('#schedule_start').val()) {
            const now = new Date();
            // Format date as YYYY-MM-DDThh:mm
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');

            $('#schedule_start').val(`${year}-${month}-${day}T${hours}:${minutes}`);
        }

        // Handle thread count warning on initialization
        const threadCount = parseInt($('#processing_threads').val(), 10);
        if (threadCount > 4) {
            $('#processing_threads').after(
                $('<p>', {
                    'id': 'thread-warning',
                    'class': 'description warning',
                    'text': wmsw_ajax.strings.highThreadWarning || 'High thread counts may cause server performance issues.'
                })
            );
        }
    }

    /**
     * Create skeleton loading UI for product cards
     * @param {number} count Number of skeleton cards to create
     * @returns {jQuery} Container with skeleton cards
     */
    function createSkeletonLoading(count = 6) {
        // Use the new wmsw_SkeletonLoader component
        if (window.wmsw_SkeletonLoader) {
            return wmsw_SkeletonLoader.createGrid('product', count, {
                containerClass: 'swi-products-grid'
            });
        }

        // Fallback to original implementation if skeleton loader not available
        const $skeletonContainer = $('<div>', {
            'class': 'swi-products-grid'
        });

        for (let i = 0; i < count; i++) {
            const $skeletonCard = $('<div>', {
                'class': 'swi-card swi-product-card swi-skeleton-card'
            });

            // Image skeleton
            const $imageSection = $('<div>', {
                'class': 'swi-product-card-image swi-skeleton-image'
            });

            // Content skeleton
            const $infoSection = $('<div>', {
                'class': 'swi-card-body swi-product-card-info mb-0'
            });

            // Title skeleton
            $infoSection.append($('<div>', { 'class': 'swi-skeleton-title' }));

            // Description skeleton
            $infoSection.append($('<div>', { 'class': 'swi-skeleton-description' }));

            // Meta skeleton
            const $metaSection = $('<div>', { 'class': 'swi-product-meta' });
            $metaSection.append($('<div>', { 'class': 'swi-skeleton-meta' }));
            $metaSection.append($('<div>', { 'class': 'swi-skeleton-meta' }));
            $infoSection.append($metaSection);

            // Price skeleton
            $infoSection.append($('<div>', { 'class': 'swi-skeleton-price' }));

            // Footer skeleton
            const $footerSection = $('<div>', { 'class': 'swi-card-footer swi-product-card-actions' });
            $footerSection.append($('<div>', { 'class': 'swi-skeleton-status' }));

            // Assemble the skeleton card
            $skeletonCard.append($imageSection).append($infoSection).append($footerSection);
            $skeletonContainer.append($skeletonCard);
        }

        return $skeletonContainer;
    }

    /**
     * Converts flat form data keys to namespaced options format
     * @param {string} formData Serialized form data
     * @return {string} Form data with namespaced options
     */
    function convertToNamespacedFields(formData) {
        // Convert fields to options[field_name] format
        // This regex matches field names that should be inside the options namespace
        return formData.replace(
            /(?:^|&)(import_[^=]+|product_[^=]+|date_[^=]+|vendor|tags|collection_id|status|min_price|max_price|include_drafts|inventory_status|batch_size|processing_threads|error_handling)=/g,
            '&options[$1]='
        );
    }

    // Public API
    const publicAPI = {
        init: init,
        previewProducts: previewProducts,
        startImport: startProductsImport,
        scheduleImport: scheduleProductsImport
    };

    return publicAPI;
})(jQuery);

// Initialize when document is ready
jQuery(document).ready(function ($) {
    // Initialize the product importer
    window.wmsw_ProductImporter = wmsw_ProductImporter.init();
});
