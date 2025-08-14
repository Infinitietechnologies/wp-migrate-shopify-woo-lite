'use strict';

/**
 * wmsw_ConfirmBox - Global reusable confirm dialog using Notiflix
 * Usage: wmsw_ConfirmBox.show(message, okCallback, cancelCallback, options)
 */
window.wmsw_ConfirmBox = {
    show: function(message, okCallback, cancelCallback, options = {}) {
        if (typeof Notiflix === 'undefined' || !Notiflix.Confirm) {
            alert(message); // fallback
            if (typeof okCallback === 'function') okCallback();
            return;
        }
        Notiflix.Confirm.show(
            options.title || wmsw_ajax.strings.confirm_default_title || 'Are you sure?',
            message,
            options.okButtonText || wmsw_ajax.strings.yes || 'Yes',
            options.cancelButtonText || wmsw_ajax.strings.no || 'No',
            function() { if (typeof okCallback === 'function') okCallback(); },
            function() { if (typeof cancelCallback === 'function') cancelCallback(); }
        );
    }
};
