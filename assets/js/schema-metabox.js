/**
 * RankKernel schema metabox helpers.
 *
 * Plain script, no build step. Shows only the manual field rows that
 * matter for the chosen schema type. Storage sanitizing happens server
 * side on save.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var typeSelect = document.getElementById('rankkernel-schema-type');

        function filterFieldRows() {
            var current = typeSelect ? typeSelect.value : '';
            var rows = document.querySelectorAll('tr[data-rankkernel-field-types]');

            rows.forEach(function (row) {
                var allowed = (row.getAttribute('data-rankkernel-field-types') || '').split(',');

                if (allowed.indexOf('*') !== -1 || ('' !== current && allowed.indexOf(current) !== -1)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        if (typeSelect) {
            typeSelect.addEventListener('change', filterFieldRows);
            filterFieldRows();
        }
    });
})();
