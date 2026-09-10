( function () {
    var el = wp.element.createElement;
    var RichText = wp.blockEditor.RichText;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var TextControl = wp.components.TextControl;
    var Button = wp.components.Button;
    var __ = wp.i18n.__;

    var nextStepId = 1;

    function allowedWrapper( value ) {
        if ( 'h2' === value || 'h3' === value || 'h4' === value ) {
            return value;
        }

        return 'h3';
    }

    function emptyStep() {
        var id = 'rkh' + (nextStepId++);
        return { id: id, title: '', text: '', image: '' };
    }

    function rowKey( row, index ) {
        if (row && 'string' === typeof row.id && '' !== row.id) {
            return row.id;
        }

        return 'row-' + index;
    }

    wp.blocks.registerBlockType('rankkernel/howto', {
        edit: function ( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var title = attributes.title || '';
            var titleWrapper = allowedWrapper(attributes.titleWrapper || 'h3');
            var steps = attributes.steps || [];

            function updateStep( key, field, value ) {
                var current = null;
                var i;

                for (i = 0; i < steps.length; i++) {
                    if (rowKey(steps[i], i) === key) {
                        current = steps[i];
                        break;
                    }
                }

                if (current && current[field] === value) {
                    return;
                }

                var next = steps.map(function ( row, i ) {
                    if (rowKey(row, i) !== key) {
                        return row;
                    }

                    var copy = {
                        title: row.title || '',
                        text: row.text || '',
                        image: row.image || ''
                    };

                    if (row.id) {
                        copy.id = row.id;
                    }

                    copy[ field ] = value;

                    return copy;
                });

                setAttributes({ steps: next });
            }

            function removeStep( key ) {
                setAttributes({
                    steps: steps.filter(function ( row, i ) {
                        return rowKey(row, i) !== key;
                    })
                });
            }

            function addStep() {
                setAttributes({ steps: steps.concat([ emptyStep() ]) });
            }

            var rows = steps.map(function ( row, index ) {
                var key = rowKey(row, index);
                return el(
                    'div',
                    { key: key, className: 'rankkernel-howto-step' },
                    el(RichText, {
                        tagName: titleWrapper,
                        className: 'rankkernel-howto-step-title',
                        placeholder: __('Step title...', 'rankkernel'),
                        value: row.title || '',
                        onChange: function ( value ) {
                            updateStep(key, 'title', value);
                        }
                    }),
                    el(RichText, {
                        tagName: 'div',
                        multiline: 'p',
                        className: 'rankkernel-howto-step-text',
                        placeholder: __('Step instructions...', 'rankkernel'),
                        value: row.text || '',
                        onChange: function ( value ) {
                            updateStep(key, 'text', value);
                        }
                    }),
                    el(TextControl, {
                        label: __('Image URL (optional)', 'rankkernel'),
                        value: row.image || '',
                        onChange: function ( value ) {
                            if (value !== (row.image || '')) {
                                updateStep(key, 'image', value);
                            }
                        }
                    }),
                    el(
                        Button,
                        {
                            isDestructive: true,
                            onClick: function () {
                                removeStep(key);
                            }
                        },
                        __('Remove step', 'rankkernel')
                    )
                );
            });

            return el(
                'div',
                { className: 'rankkernel-howto-editor' },
                el(InspectorControls, null, el(
                    PanelBody,
                    { title: __('HowTo Settings', 'rankkernel'), initialOpen: true },
                    el(SelectControl, {
                        label: __('Title size', 'rankkernel'),
                        value: titleWrapper,
                        options: [
                            { label: __('Heading 2', 'rankkernel'), value: 'h2' },
                            { label: __('Heading 3', 'rankkernel'), value: 'h3' },
                            { label: __('Heading 4', 'rankkernel'), value: 'h4' }
                        ],
                        onChange: function ( value ) {
                            var next = allowedWrapper(value);
                            if (next !== titleWrapper) {
                                setAttributes({ titleWrapper: next });
                            }
                        }
                    })
                )),
                el(RichText, {
                    tagName: titleWrapper,
                    className: 'rankkernel-howto-editor-title',
                    placeholder: __('Add a title...', 'rankkernel'),
                    value: title,
                    onChange: function ( value ) {
                        if (value !== title) {
                            setAttributes({ title: value });
                        }
                    }
                }),
                el('ol', { className: 'rankkernel-howto-editor-list' }, rows),
                el(
                    Button,
                    { variant: 'primary', onClick: addStep },
                    __('Add step', 'rankkernel')
                )
            );
        },

        save: function () {
            return null;
        }
    });
} )();
