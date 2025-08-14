'use strict';

/**
 * Handles category import logic for Shopify to WooCommerce Importer
 * Updated to match product importer UI patterns
 */
jQuery(document).ready(function ($) {
    // Use the shared notification function from backend.js if available
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
    
    // Settings object
    const _settings = {
        selectors: {
            // Updated selectors with unique IDs for the categories page
            storeSelect: '#swi-categories-store-select',
            fetchButton: '#swi-categories-fetch-btn',
            categorySearch: '#swi-categories-search',
            sortCategories: '#swi-categories-sort',
            filterHasProducts: '#swi-categories-filter-has-products',
            advancedFiltersToggle: '#swi-categories-toggle-filters',
            advancedFiltersSection: '#swi-categories-advanced-filters',
            categoryContainer: '#swi-categories-results',
            categoriesList: '#swi-categories-grid-list',
            selectAllButton: '#swi-categories-select-all',
            deselectAllButton: '#swi-categories-deselect-all', importCategoriesButton: '#swi-categories-import-btn',
            previewCategoriesButton: '#swi-categories-preview-btn',
            importStatus: '#swi-categories-status',
            progressContainer: '#categories-import-progress',
            progressBar: '#categories-progress-fill',
            progressText: '#categories-progress-text',
            progressPercentage: '#categories-progress-percentage',
            progressLog: '#categories-progress-log'
        }
    };

    // Store all categories for filtering
    let allCategories = [];    // Initialize
    function init() {
        // Make sure DOM is fully loaded before hiding elements
        $(function () {
            // Hide elements initially
            $(_settings.selectors.advancedFiltersSection).hide();
            $(_settings.selectors.categoryContainer).hide();
            $(_settings.selectors.progressContainer).hide();
            

        });

        // Set up event handlers
        setupEventHandlers();
    }

    function setupEventHandlers() {
        // Fetch button
        $(_settings.selectors.fetchButton).on('click', fetchCategories);

        // Search input
        $(_settings.selectors.categorySearch).on('input', filterCategories);

        // Sort dropdown
        $(_settings.selectors.sortCategories).on('change', filterCategories);

        // Has products filter
        $(_settings.selectors.filterHasProducts).on('change', filterCategories);

        // Advanced filters toggle
        setupAdvancedFiltersToggle();

        // Select/deselect all
        $(_settings.selectors.selectAllButton).on('click', function () {
            $('.swi-category-checkbox').prop('checked', true);
        });

        $(_settings.selectors.deselectAllButton).on('click', function () {
            $('.swi-category-checkbox').prop('checked', false);
        });        // Import categories button
        $(_settings.selectors.importCategoriesButton).on('click', importSelectedCategories);

        // Preview categories button
        $(_settings.selectors.previewCategoriesButton).on('click', previewSelectedCategories);
    }

    // Set up advanced filters toggle
    function setupAdvancedFiltersToggle() {
        // Get references to the elements
        const $advancedFilters = $(_settings.selectors.advancedFiltersSection);
        const $button = $(_settings.selectors.advancedFiltersToggle);

        // Ensure the advanced filters section is hidden initially
        setTimeout(function () {
            $advancedFilters.hide();
        }, 0);

        // Add click handler to toggle button
        $button.on('click', function (e) {
            e.preventDefault();

            // Check visibility state
            const isVisible = $advancedFilters.is(':visible');

            if (isVisible) {
                // Hide the filters
                $advancedFilters.slideUp(300);
                $button.html('<span class="dashicons dashicons-filter"></span> ' + wmsw_ajax.strings.showAdvancedFilters);
                $button.removeClass('is-active');
            } else {
                // Show the filters
                $advancedFilters.slideDown(300);
                $button.html('<span class="dashicons dashicons-no-alt"></span> ' + wmsw_ajax.strings.hideAdvancedFilters);
                $button.addClass('is-active');
            }
        });
    }

    // Fetch categories from Shopify
    function fetchCategories() {
        const storeId = $(_settings.selectors.storeSelect).val();
        if (!storeId) {
            showNotification('warning', 'Please select a store first.', 'Warning');
            return;
        }

        // Show the categories container and hide progress
        $(_settings.selectors.categoryContainer).show();
        $(_settings.selectors.progressContainer).hide();
        $(_settings.selectors.categoryImportProgress).hide();

        // Show loading state
        $(_settings.selectors.categoriesList).html('<div class="swi-skeleton-loader"><div class="swi-skeleton-line"></div><div class="swi-skeleton-line"></div><div class="swi-skeleton-line"></div></div>');

        $.ajax({
            url: wmsw_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'wmsw_fetch_shopify_categories',
                store_id: storeId,
                nonce: wmsw_ajax.nonce
            },
            success: function (response) {
                if (response.success && response.data && response.data.categories) {
                    // Store categories for filtering
                    allCategories = response.data.categories; renderCategories(allCategories);
                    showNotification('success', allCategories.length + ' categories found.', 'Success');
                } else {
                    $(_settings.selectors.categoriesList).html('<div class="swi-empty-state"><div class="swi-empty-icon"><span class="dashicons dashicons-category"></span></div><p>' + (wmsw_ajax.strings.noCategoriesFound || 'No categories found or failed to fetch.') + '</p></div>');
                    showNotification('error', wmsw_ajax.strings.noCategoriesFound || 'No categories found.', 'Error');
                }
            }, error: function () {
                $(_settings.selectors.categoriesList).html('<div class="swi-empty-state"><div class="swi-empty-icon"><span class="dashicons dashicons-warning"></span></div><p>' + (wmsw_ajax.strings.errorFetchingCategories || 'Error fetching categories.') + '</p></div>');
                showNotification('error', wmsw_ajax.strings.errorFetchingCategories || 'Error fetching categories.', 'Error');
            }
        });
    }

    // Render categories with filtering
    function renderCategories(categories) {
        if (categories.length === 0) {
            $(_settings.selectors.categoriesList).html('<div class="swi-empty-state"><p>' + (wmsw_ajax.strings.noCategoriesMatchFilter || 'No categories match your filters.') + '</p></div>');
            return;
        }

        let html = '';
        $.each(categories, function (i, cat) {
            html += `
                <div class="swi-category-card">
                    <div class="swi-category-checkbox">
                        <input type="checkbox" id="cat-${i}" class="swi-category-checkbox" value="${cat.id}">
                        <label for="cat-${i}"></label>
                    </div>
                    <div class="swi-category-content">
                        <h4 class="swi-category-title">${cat.title}</h4>
                        <div class="swi-category-handle">${cat.handle}</div>
                        <div class="swi-category-meta">
                            <span class="swi-category-products">${cat.products_count || 0} ${wmsw_ajax.strings.products || 'products'}</span>
                        </div>
                    </div>
                </div>
            `;
        });

        $(_settings.selectors.categoriesList).html(html);
        $(_settings.selectors.importCategoriesButton).show();
    }

    // Apply all filters
    function filterCategories() {
        const searchTerm = $(_settings.selectors.categorySearch).val().toLowerCase();
        const sortBy = $(_settings.selectors.sortCategories).val();
        const hasProducts = $(_settings.selectors.filterHasProducts).is(':checked');

        // Filter
        let filtered = allCategories.filter(cat => {
            // Search filter
            if (searchTerm && !cat.title.toLowerCase().includes(searchTerm) &&
                !cat.handle.toLowerCase().includes(searchTerm)) {
                return false;
            }

            // Has products filter
            return !(hasProducts && (!cat.products_count || cat.products_count <= 0));
        });

        // Sort
        filtered.sort((a, b) => {
            switch (sortBy) {
                case 'title_asc':
                    return a.title.localeCompare(b.title);
                case 'title_desc':
                    return b.title.localeCompare(a.title);
                case 'products_count':
                    return (b.products_count || 0) - (a.products_count || 0);
                default:
                    return 0;
            }
        });

        renderCategories(filtered);
    }

    // Import selected categories
    function importSelectedCategories() {
        const storeId = $(_settings.selectors.storeSelect).val();
        const selected = $('.swi-category-checkbox:checked').map(function () { return this.value; }).get();

        if (!storeId || selected.length === 0) {
            showNotification('warning', 'Please select a store and at least one category.', 'Warning');
            return;
        }

        // Show progress containers
        $(_settings.selectors.progressContainer).show();
        $(_settings.selectors.categoryImportProgress).show(); $(_settings.selectors.importStatus).html('<div class="swi-status-in-progress">' + (wmsw_ajax.strings.importingCategories || 'Importing categories...') + '</div>');
        $(_settings.selectors.progressBar).css('width', '0%');
        $(_settings.selectors.progressPercentage).text('0%');
        $(_settings.selectors.progressText).text(wmsw_ajax.strings.startingImport || 'Starting import...');
        Notiflix.Confirm.show(
            wmsw_ajax.strings.importCategoriesTitle || 'Import Categories',
            (wmsw_ajax.strings.importCategoriesConfirm || 'You are about to import {count} categories. Continue?').replace('{count}', selected.length),
            wmsw_ajax.strings.yesImport || 'Yes, Import',
            wmsw_ajax.strings.cancel || 'Cancel',
            function () { // Yes
                $.ajax({
                    url: wmsw_ajax.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'wmsw_import_shopify_categories',
                        store_id: storeId,
                        category_ids: selected,
                        nonce: wmsw_ajax.nonce
                    },
                    success: function (response) {
                        updateProgressBar(100, 'Import complete'); if (response.success) {
                            let successMessage = (wmsw_ajax.strings.importCompleteMessage || 'Import complete! {count} categories imported.').replace('{count}', response.data.imported.length);
                            $(_settings.selectors.importStatus).html('<div class="swi-status-success"><span class="dashicons dashicons-yes"></span> ' + successMessage + '</div>');
                            showNotification('success', wmsw_ajax.strings.categoriesImportedSuccess || 'Categories imported successfully!', 'Import Complete');
                        } else {
                            let errorMessage = wmsw_ajax.strings.importFailed || 'Import failed: ';
                            let detailMessage = (response.data && response.data.message) ? response.data.message : (wmsw_ajax.strings.unknownError || 'Unknown error');
                            $(_settings.selectors.importStatus).html('<div class="swi-status-error"><span class="dashicons dashicons-warning"></span> ' + errorMessage + ' ' + detailMessage + '</div>');
                            showNotification('error', wmsw_ajax.strings.importFailed || 'Import failed.', 'Import Failed');
                        }
                    }, error: function () {
                        $(_settings.selectors.importStatus).html('<div class="swi-status-error"><span class="dashicons dashicons-warning"></span> ' + (wmsw_ajax.strings.serverErrorDuringImport || 'Server error during import.') + '</div>');
                        showNotification('error', wmsw_ajax.strings.serverErrorDuringImport || 'Server error during import.', 'Error');
                    }
                });
            },
            function () { // No
                $(_settings.selectors.progressContainer).hide();
            }
        );
    }

    // Update progress bar
    function updateProgressBar(percentage, text) {
        $(_settings.selectors.progressBar).css('width', percentage + '%');
        $(_settings.selectors.progressPercentage).text(percentage + '%');
        if (text) {
            $(_settings.selectors.progressText).text(text);
        }
    }

    // Helper function to verify elements exist (used during development)
    function verifyElements() {
        // No debug checks needed in production
        return true;
    }

    // Preview selected categories
    function previewSelectedCategories() {
        const storeId = $(_settings.selectors.storeSelect).val();

        if (!storeId) {
            showNotification('warning', wmsw_ajax.strings.selectStoreAndCategories || 'Please select a store and at least one category.', 'Warning');
            return;
        }
        // Create a modal or dialog to show the preview

        // Make AJAX request to get category preview data
        $.ajax({
            url: wmsw_ajax.ajax_url,
            method: 'POST',
            data: {
                action: 'wmsw_preview_shopify_categories',
                store_id: storeId,
                nonce: wmsw_ajax.nonce
            },
            success: function (response) {
                Notiflix.Loading.remove();

                if (response.success && response.data) {
                    // Build preview content from the response data
                    let previewContent = '<div class="swi-preview-container">';

                    if (response.data.categories && response.data.categories.length) {
                        previewContent += '<table class="swi-preview-table">';
                        previewContent += '<thead><tr><th>' + (wmsw_ajax.strings.categoryName || 'Category Name') + '</th>';
                        previewContent += '<th>' + (wmsw_ajax.strings.productCount || 'Products') + '</th>';
                        previewContent += '<th>' + (wmsw_ajax.strings.shopifyHandle || 'Shopify Handle') + '</th></tr></thead>';
                        previewContent += '<tbody>';

                        response.data.categories.forEach(function (cat) {
                            previewContent += '<tr>';
                            previewContent += '<td>' + cat.title + '</td>';
                            previewContent += '<td>' + (cat.products_count || 0) + '</td>';
                            previewContent += '<td>' + cat.handle + '</td>';
                            previewContent += '</tr>';
                        });

                        previewContent += '</tbody></table>';
                    } else {
                        previewContent += '<p class="swi-preview-empty">' + (wmsw_ajax.strings.noDataAvailable || 'No preview data available') + '</p>';
                    }

                    previewContent += '</div>';

                    // Show the preview in a report dialog
                    Notiflix.Report.info(
                        wmsw_ajax.strings.categoryPreview || 'Category Preview',
                        previewContent,
                        wmsw_ajax.strings.close || 'Close'
                    );
                } else {
                    showNotification('error', response.data?.message || wmsw_ajax.strings.previewFailed || 'Failed to load preview', 'Preview Error');
                }
            },
            error: function () {
                Notiflix.Loading.remove();
                showNotification('error', wmsw_ajax.strings.serverError || 'Server error occurred while preparing preview', 'Server Error');
            }
        });

    }
    init();
});
