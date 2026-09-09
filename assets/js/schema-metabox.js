/**
 * RankKernel schema metabox builders.
 *
 * Plain script, no build step. Appends blank FAQ rows and HowTo steps,
 * and removes rows on request. All values stay empty until the editor
 * fills them in, storage sanitizing happens server side on save.
 */
(function () {
    'use strict';

    /**
     * Next row index for a table body.
     *
     * @param {HTMLElement} tbody Table body holding the rows.
     * @return {number} Next zero based index.
     */
    function nextIndex(tbody) {
        return tbody.querySelectorAll('tr').length;
    }

    /**
     * Append a blank FAQ row.
     *
     * @param {HTMLElement} tbody Table body holding the rows.
     */
    function addFaqRow(tbody) {
        var i = nextIndex(tbody);
        var html = '<tr>' +
            '<td><input type="text" class="regular-text" name="rankkernel_schema_faq[' + i + '][question]" value="" /></td>' +
            '<td><textarea class="large-text" rows="2" name="rankkernel_schema_faq[' + i + '][answer]"></textarea></td>' +
            '<td><button type="button" class="button rankkernel-schema-remove-row">Remove</button></td>' +
            '</tr>';
        tbody.insertAdjacentHTML('beforeend', html);
    }

    /**
     * Append a blank HowTo step row.
     *
     * @param {HTMLElement} tbody Table body holding the rows.
     */
    function addHowtoRow(tbody) {
        var i = nextIndex(tbody);
        var html = '<tr>' +
            '<td><input type="text" class="regular-text" name="rankkernel_schema_howto_steps[' + i + '][title]" value="" /></td>' +
            '<td><textarea class="large-text" rows="2" name="rankkernel_schema_howto_steps[' + i + '][text]"></textarea></td>' +
            '<td><input type="url" class="regular-text" name="rankkernel_schema_howto_steps[' + i + '][image]" value="" /></td>' +
            '<td><button type="button" class="button rankkernel-schema-remove-row">Remove</button></td>' +
            '</tr>';
        tbody.insertAdjacentHTML('beforeend', html);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var faqBody = document.getElementById('rankkernel-faq-rows');
        var faqAdd = document.getElementById('rankkernel-faq-add');

        if (faqBody && faqAdd) {
            faqAdd.addEventListener('click', function () {
                addFaqRow(faqBody);
            });
        }

        var howtoBody = document.getElementById('rankkernel-howto-rows');
        var howtoAdd = document.getElementById('rankkernel-howto-add');

        if (howtoBody && howtoAdd) {
            howtoAdd.addEventListener('click', function () {
                addHowtoRow(howtoBody);
            });
        }

        var box = document.getElementById('rankkernel-schema');

        if (box) {
            box.addEventListener('click', function (event) {
                var target = event.target;

                if (target && target.classList && target.classList.contains('rankkernel-schema-remove-row')) {
                    var row = target.closest('tr');

                    if (row) {
                        row.remove();
                    }
                }
            });
        }
    });
})();
