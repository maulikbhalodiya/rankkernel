/**
 * RankKernel schema settings media picker.
 *
 * Plain script, no build step. Opens the WordPress media library for the
 * organization logo, shows a preview, and clears on remove. Loaded on the
 * schema settings screen only.
 */
(function () {
    'use strict';

    function ready( callback ) {
        if ('loading' === document.readyState) {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    ready(function () {
        var select  = document.getElementById('rk-org-logo-select');
        var remove  = document.getElementById('rk-org-logo-remove');
        var input   = document.getElementById('rk-org-logo');
        var preview = document.getElementById('rk-org-logo-preview');

        if (! select || ! input || ! preview) {
            return;
        }

        var frame = null;

        select.addEventListener('click', function ( event ) {
            event.preventDefault();

            if ('undefined' === typeof wp || ! wp.media) {
                return;
            }

            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: 'Select organization logo',
                button: { text: 'Use this image' },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first();

                if (! attachment) {
                    return;
                }

                var url = attachment.get('url');

                if ('string' !== typeof url || '' === url) {
                    return;
                }

                input.value      = url;
                preview.src      = url;
                preview.style.display = '';

                if (remove) {
                    remove.style.display = '';
                }
            });

            frame.open();
        });

        if (remove) {
            remove.addEventListener('click', function ( event ) {
                event.preventDefault();

                input.value           = '';
                preview.src           = '';
                preview.style.display = 'none';
                remove.style.display  = 'none';
            });
        }
    });
})();
