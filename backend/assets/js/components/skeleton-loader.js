/**
 * Shopify WooCommerce Importer - Skeleton Loader Component
 * 
 * A versatile skeleton loader that can create various types of skeleton layouts
 * for use in loading states throughout the plugin.
 */

const wmsw_SkeletonLoader = (function ($) {
    'use strict';

    /**
     * Create a basic skeleton element with given properties
     * @param {String} type The type of skeleton element (e.g., 'text', 'image', 'title')
     * @param {Object} options Additional options for customization
     * @returns {jQuery} The skeleton element
     */
    function createSkeletonElement(type, options = {}) {
        const defaults = {
            className: '',
            width: null,
            height: null,
            borderRadius: null,
            marginBottom: null
        };

        const settings = $.extend({}, defaults, options);

        // Create the base element with the skeleton class
        const $element = $('<div>', {
            'class': 'swi-skeleton-' + type + (settings.className ? ' ' + settings.className : '')
        });

        // Apply inline styles if provided
        const styles = {};
        if (settings.width !== null) styles.width = typeof settings.width === 'number' ? settings.width + 'px' : settings.width;
        if (settings.height !== null) styles.height = typeof settings.height === 'number' ? settings.height + 'px' : settings.height;
        if (settings.borderRadius !== null) styles.borderRadius = typeof settings.borderRadius === 'number' ? settings.borderRadius + 'px' : settings.borderRadius;
        if (settings.marginBottom !== null) styles.marginBottom = typeof settings.marginBottom === 'number' ? settings.marginBottom + 'px' : settings.marginBottom;

        $element.css(styles);

        return $element;
    }

    /**
     * Create a product card skeleton
     * @returns {jQuery} The skeleton card element
     */
    function createProductCardSkeleton() {
        const $skeletonCard = $('<div>', {
            'class': 'swi-card swi-product-card swi-skeleton-card'
        });

        // Image skeleton
        const $imageSection = createSkeletonElement('image', {
            className: 'swi-product-card-image'
        });

        // Content skeleton
        const $infoSection = $('<div>', {
            'class': 'swi-card-body swi-product-card-info mb-0'
        });

        // Title skeleton
        $infoSection.append(createSkeletonElement('title'));

        // Description skeleton
        $infoSection.append(createSkeletonElement('description'));

        // Meta skeleton
        const $metaSection = $('<div>', {
            'class': 'swi-product-meta'
        });

        $metaSection.append(createSkeletonElement('meta'));
        $metaSection.append(createSkeletonElement('meta'));
        $infoSection.append($metaSection);

        // Price skeleton
        $infoSection.append(createSkeletonElement('price'));

        // Footer skeleton
        const $footerSection = $('<div>', {
            'class': 'swi-card-footer swi-product-card-actions'
        });

        $footerSection.append(createSkeletonElement('status'));

        // Assemble the skeleton card
        $skeletonCard.append($imageSection);
        $skeletonCard.append($infoSection);
        $skeletonCard.append($footerSection);

        return $skeletonCard;
    }

    /**
     * Create a table row skeleton
     * @param {Number} columns Number of columns in the row
     * @returns {jQuery} The skeleton row element
     */
    function createTableRowSkeleton(columns = 4) {
        const $row = $('<tr>', {
            'class': 'swi-skeleton-row'
        });

        // Create cells for each column
        for (let i = 0; i < columns; i++) {
            const $cell = $('<td>');
            $cell.append(createSkeletonElement('text', {
                width: i === 0 ? '60%' : '80%',
                height: '20px'
            }));
            $row.append($cell);
        }

        return $row;
    }

    /**
     * Create a list item skeleton
     * @returns {jQuery} The skeleton list item element
     */
    function createListItemSkeleton() {
        const $item = $('<div>', {
            'class': 'swi-skeleton-list-item'
        });

        // Create a simple two-column layout
        const $left = $('<div>', {
            'class': 'swi-skeleton-list-left'
        });

        $left.append(createSkeletonElement('text', { width: '70%', height: '20px', marginBottom: '8px' }));
        $left.append(createSkeletonElement('text', { width: '40%', height: '16px' }));

        const $right = $('<div>', {
            'class': 'swi-skeleton-list-right'
        });

        $right.append(createSkeletonElement('text', { width: '60px', height: '24px', borderRadius: '12px' }));

        $item.append($left);
        $item.append($right);

        return $item;
    }

    /**
     * Create a grid of skeleton items
     * @param {String} type The type of skeleton to create ('product', 'table-row', 'list-item')
     * @param {Number} count Number of skeleton items to create
     * @param {Object} options Additional options for configuration
     * @returns {jQuery} Container with skeleton items
     */
    function createSkeletonGrid(type, count = 6, options = {}) {
        const defaults = {
            containerClass: 'swi-skeleton-container',
            itemCallback: null
        };

        const settings = $.extend({}, defaults, options);

        // Create container
        const $container = $('<div>', {
            'class': settings.containerClass
        });

        // For products, use specific product grid class
        if (type === 'product') {
            $container.addClass('swi-products-grid');
        }

        // Create the skeleton items based on type
        for (let i = 0; i < count; i++) {
            let $skeletonItem;

            switch (type) {
                case 'product':
                    $skeletonItem = createProductCardSkeleton();
                    break;

                case 'table-row':
                    // For table rows, create a wrapper table if it doesn't exist
                    if (i === 0) {
                        const $table = $('<table>', {
                            'class': 'swi-skeleton-table'
                        });
                        const $tbody = $('<tbody>');
                        $table.append($tbody);
                        $container.append($table);
                    }

                    $skeletonItem = createTableRowSkeleton(options.columns || 4);
                    $container.find('tbody').append($skeletonItem);
                    continue; // Skip the append at the end as we're appending to tbody

                case 'list-item':
                    $skeletonItem = createListItemSkeleton();
                    break;

                default:
                    // For custom types, use the item callback if provided
                    if (typeof settings.itemCallback === 'function') {
                        $skeletonItem = settings.itemCallback(i);
                    } else {
                        $skeletonItem = createSkeletonElement('text', { width: '100%', height: '20px' });
                    }
            }

            // Apply callback for additional customization if provided
            if (typeof settings.itemCallback === 'function' && type !== 'table-row') {
                settings.itemCallback($skeletonItem, i);
            }

            // Add to container (except for table rows which are already added)
            if (type !== 'table-row') {
                $container.append($skeletonItem);
            }
        }

        return $container;
    }

    /**
     * Create text skeleton with given dimensions
     * @param {Object} options Options for text skeleton
     * @returns {jQuery} The text skeleton element
     */
    function createTextSkeleton(options = {}) {
        const defaults = {
            width: '100%',
            height: '20px',
            lines: 1,
            lineSpacing: '10px'
        };

        const settings = $.extend({}, defaults, options);

        if (settings.lines === 1) {
            return createSkeletonElement('text', settings);
        }

        const $container = $('<div>', {
            'class': 'swi-skeleton-text-container'
        });

        for (let i = 0; i < settings.lines; i++) {
            // Last line is typically shorter
            const lineOptions = {
                width: i === settings.lines - 1 ? '70%' : settings.width,
                height: settings.height,
                marginBottom: i < settings.lines - 1 ? settings.lineSpacing : 0
            };

            $container.append(createSkeletonElement('text', lineOptions));
        }

        return $container;
    }

    /**
     * Create a skeleton form control (input, select, etc.)
     * @param {Object} options Options for form control skeleton
     * @returns {jQuery} The form control skeleton element
     */
    function createFormControlSkeleton(options = {}) {
        const defaults = {
            type: 'input', // input, select, textarea
            width: '100%',
            height: '36px'
        };

        const settings = $.extend({}, defaults, options);

        // Adjust height for textarea
        if (settings.type === 'textarea') {
            settings.height = '80px';
        }

        return createSkeletonElement('form-control', {
            width: settings.width,
            height: settings.height,
            borderRadius: '4px'
        });
    }

    /**
     * Create a skeleton button
     * @param {Object} options Options for button skeleton
     * @returns {jQuery} The button skeleton element
     */
    function createButtonSkeleton(options = {}) {
        const defaults = {
            width: '120px',
            height: '36px'
        };

        const settings = $.extend({}, defaults, options);

        return createSkeletonElement('button', {
            width: settings.width,
            height: settings.height,
            borderRadius: '4px'
        });
    }

    /**
     * Replace an element with a skeleton version
     * @param {jQuery|String} element The element or selector to replace
     * @param {String} type The type of skeleton to use
     * @param {Object} options Additional options
     * @returns {jQuery} The replaced element
     */
    function replaceElementWithSkeleton(element, type, options = {}) {
        const $element = $(element);
        if (!$element.length) return null;

        // Store original HTML and classes
        $element.data('original-html', $element.html());
        $element.data('original-type', type);

        $element.empty();

        let $skeleton;
        switch (type) {
            case 'text':
                $skeleton = createTextSkeleton(options);
                break;

            case 'button':
                $skeleton = createButtonSkeleton(options);
                break;

            case 'form-control':
                $skeleton = createFormControlSkeleton(options);
                break;

            case 'grid':
                $skeleton = createSkeletonGrid(options.gridType || 'product', options.count || 6, options);
                break;

            default:
                $skeleton = createSkeletonElement(type, options);
        }

        $element.append($skeleton);
        $element.addClass('swi-skeleton-active');

        return $element;
    }

    /**
     * Restore an element that was replaced with a skeleton
     * @param {jQuery|String} element The element or selector to restore
     * @param {String|Null} newHtml Optional new HTML to set instead of original
     * @returns {jQuery} The restored element
     */
    function restoreElement(element, newHtml = null) {
        const $element = $(element);
        if (!$element.length) return null;

        const originalHtml = $element.data('original-html');

        $element.empty();

        if (newHtml !== null) {
            $element.html(newHtml);
        } else if (originalHtml) {
            $element.html(originalHtml);
        }

        $element.removeClass('swi-skeleton-active');

        return $element;
    }

    // Public API
    return {
        create: createSkeletonElement,
        createGrid: createSkeletonGrid,
        createText: createTextSkeleton,
        createButton: createButtonSkeleton,
        createFormControl: createFormControlSkeleton,
        replace: replaceElementWithSkeleton,
        restore: restoreElement,

        // Preset types
        createProductCard: createProductCardSkeleton,
        createTableRow: createTableRowSkeleton,
        createListItem: createListItemSkeleton
    };
})(jQuery);

// Initialize when document is ready
jQuery(document).ready(function ($) {
    // Make it globally available
    window.wmsw_SkeletonLoader = wmsw_SkeletonLoader;
});
