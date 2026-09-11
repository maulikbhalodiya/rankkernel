( function () {
    var el = wp.element.createElement;
    var useEffect = wp.element.useEffect;
    var useRef = wp.element.useRef;
    var RichText = wp.blockEditor.RichText;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var MediaUpload = wp.blockEditor.MediaUpload;
    var MediaPlaceholder = wp.blockEditor.MediaPlaceholder;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var Button = wp.components.Button;
    var Card = wp.components.Card;
    var CardBody = wp.components.CardBody;
    var __ = wp.i18n.__;
    var sprintf = wp.i18n.sprintf;

    function allowedWrapper( value ) {
        if ( 'h2' === value || 'h3' === value || 'h4' === value ) {
            return value;
        }

        return 'h3';
    }

    function allowedStepTag( value ) {
        if ( 'h2' === value || 'h3' === value || 'h4' === value ) {
            return value;
        }

        return '';
    }

    function newStepId() {
        return 'rks-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
    }

    function asStep( row ) {
        var base = row && 'object' === typeof row ? row : {};

        return {
            id: base.id,
            title: base.title || '',
            text: base.text || '',
            image: base.image || '',
            alt: base.alt || ''
        };
    }

    function useMigratedSteps( steps, setAttributes ) {
        useEffect(function () {
            var seen = {};
            var changed = false;

            var next = steps.map(function ( row, index ) {
                var base = row && 'object' === typeof row ? row : {};
                var id = base.id;

                if ( 'string' === typeof id && '' !== id && ! seen[ id ] ) {
                    seen[ id ] = true;

                    if (
                        'string' === typeof base.title &&
                        'string' === typeof base.text &&
                        'string' === typeof base.image &&
                        'string' === typeof base.alt
                    ) {
                        return base;
                    }

                    changed = true;

                    return { ...base, id: id, title: base.title || '', text: base.text || '', image: base.image || '', alt: base.alt || '' };
                }

                changed = true;
                id = newStepId();

                while ( seen[ id ] ) {
                    id = newStepId();
                }

                seen[ id ] = true;

                return { ...base, id: id, title: base.title || '', text: base.text || '', image: base.image || '', alt: base.alt || '' };
            });

            if ( changed ) {
                setAttributes({ steps: next });
            }
        });
    }

    wp.blocks.registerBlockType('rankkernel/howto', {
        edit: function ( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var title = attributes.title || '';
            var titleWrapper = allowedWrapper(attributes.titleWrapper || 'h3');
            var stepTag = allowedStepTag(attributes.stepTag || '');
            var activeTag = '' !== stepTag ? stepTag : titleWrapper;
            var description = attributes.description || '';
            var totalTime = attributes.totalTime || '';
            var estimatedCost = attributes.estimatedCost || '';
            var tools = attributes.tools || [];
            var materials = attributes.materials || [];
            var steps = attributes.steps || [];

            useMigratedSteps(steps, setAttributes);

            var blockRoot = useRef(null);
            var addButtonRef = useRef(null);
            var pendingFocus = useRef(null);

            useEffect(function () {
                if ( ! pendingFocus.current ) {
                    return;
                }

                var target = pendingFocus.current;
                pendingFocus.current = null;

                if ( 'add-button' === target ) {
                    if ( addButtonRef.current && addButtonRef.current.focus ) {
                        addButtonRef.current.focus();
                    }

                    return;
                }

                var root = blockRoot.current;

                if ( ! root || ! root.querySelector ) {
                    return;
                }

                var safeId = ( 'undefined' !== typeof CSS && CSS && CSS.escape ) ? CSS.escape(target) : target;
                var field = root.querySelector('li[data-rk-step="' + safeId + '"] input');

                if ( field && field.focus ) {
                    field.focus();
                }
            });

            var blockProps = useBlockProps({ className: 'rankkernel-howto-editor' });

            function setStepValues( id, values ) {
                var current = null;
                var i;

                for ( i = 0; i < steps.length; i++ ) {
                    if ( steps[ i ] && steps[ i ].id === id ) {
                        current = steps[ i ];
                        break;
                    }
                }

                if ( ! current ) {
                    return;
                }

                var keys = Object.keys(values);
                var same = true;
                var k;

                for ( k = 0; k < keys.length; k++ ) {
                    if ( current[ keys[ k ] ] !== values[ keys[ k ] ] ) {
                        same = false;
                        break;
                    }
                }

                if ( same ) {
                    return;
                }

                var next = steps.map(function ( row ) {
                    if ( ! row || row.id !== id ) {
                        return row;
                    }

                    return { ...row, ...values };
                });

                setAttributes({ steps: next });
            }

            function updateStep( id, field, value ) {
                var patch = {};
                patch[ field ] = value;

                setStepValues(id, patch);
            }

            function removeStep( id ) {
                pendingFocus.current = 'add-button';

                setAttributes({
                    steps: steps.filter(function ( row ) {
                        return ! row || row.id !== id;
                    })
                });
            }

            function moveStep( id, direction ) {
                var from = -1;
                var i;

                for ( i = 0; i < steps.length; i++ ) {
                    if ( steps[ i ] && steps[ i ].id === id ) {
                        from = i;
                        break;
                    }
                }

                if ( from < 0 ) {
                    return;
                }

                var to = from + direction;

                if ( to < 0 || to >= steps.length ) {
                    return;
                }

                var next = steps.slice();
                var temp = next[ from ];
                next[ from ] = next[ to ];
                next[ to ] = temp;

                setAttributes({ steps: next });
            }

            function addStep() {
                var id = newStepId();
                pendingFocus.current = id;

                setAttributes({ steps: steps.concat([ { id: id, title: '', text: '', image: '', alt: '' } ]) });
            }

            function setStepImage( id, media ) {
                if ( ! media || ! media.url ) {
                    return;
                }

                var values = { image: media.url };

                if ( media.alt ) {
                    values.alt = media.alt;
                }

                setStepValues(id, values);
            }

            function updateTool( index, value ) {
                var next = tools.slice();
                next[ index ] = value;

                setAttributes({ tools: next });
            }

            function removeTool( index ) {
                setAttributes({
                    tools: tools.filter(function ( value, i ) {
                        return i !== index;
                    })
                });
            }

            function addTool() {
                setAttributes({ tools: tools.concat([ '' ]) });
            }

            function updateMaterial( index, value ) {
                var next = materials.slice();
                next[ index ] = value;

                setAttributes({ materials: next });
            }

            function removeMaterial( index ) {
                setAttributes({
                    materials: materials.filter(function ( value, i ) {
                        return i !== index;
                    })
                });
            }

            function addMaterial() {
                setAttributes({ materials: materials.concat([ '' ]) });
            }

            var rows = steps.map(function ( row, index ) {
                var itemNumber = index + 1;
                var item = asStep(row);
                var isFirst = 0 === index;
                var isLast = index === steps.length - 1;

                /* translators: %d is the step number. */
                var cardLabel = sprintf(__('Step %d', 'rankkernel'), itemNumber);
                /* translators: %d is the step number. */
                var titleLabel = sprintf(__('Title %d', 'rankkernel'), itemNumber);
                /* translators: %d is the step number. */
                var descriptionLabel = sprintf(__('Description %d', 'rankkernel'), itemNumber);
                /* translators: %d is the step number. */
                var moveUpLabel = sprintf(__('Move step %d up', 'rankkernel'), itemNumber);
                /* translators: %d is the step number. */
                var moveDownLabel = sprintf(__('Move step %d down', 'rankkernel'), itemNumber);
                /* translators: %d is the step number. */
                var removeLabel = sprintf(__('Remove step %d', 'rankkernel'), itemNumber);
                /* translators: %d is the step number. */
                var imageLabel = sprintf(__('Image for step %d', 'rankkernel'), itemNumber);
                /* translators: %d is the step number. */
                var altLabel = sprintf(__('Image alt text for step %d', 'rankkernel'), itemNumber);

                var imageControl = item.image
                    ? el(
                        'div',
                        { className: 'rankkernel-howto-image' },
                        el('img', {
                            className: 'rankkernel-howto-image-preview',
                            src: item.image,
                            alt: item.alt || ''
                        }),
                        el(
                            'div',
                            { className: 'rankkernel-howto-image-actions' },
                            el(MediaUpload, {
                                allowedTypes: [ 'image' ],
                                onSelect: function ( media ) {
                                    setStepImage(item.id, media);
                                },
                                render: function ( obj ) {
                                    return el(
                                        Button,
                                        { variant: 'secondary', onClick: obj.open },
                                        __('Replace image', 'rankkernel')
                                    );
                                }
                            }),
                            el(
                                Button,
                                {
                                    isDestructive: true,
                                    onClick: function () {
                                        setStepValues(item.id, { image: '' });
                                    }
                                },
                                __('Remove image', 'rankkernel')
                            )
                        ),
                        el(TextControl, {
                            label: altLabel,
                            value: item.alt || '',
                            placeholder: __('Describe the image...', 'rankkernel'),
                            onChange: function ( value ) {
                                updateStep(item.id, 'alt', value);
                            }
                        })
                    )
                    : el(MediaPlaceholder, {
                        icon: 'format-image',
                        labels: {
                            title: imageLabel,
                            instructions: __('Upload or pick an image for this step.', 'rankkernel')
                        },
                        accept: 'image/*',
                        allowedTypes: [ 'image' ],
                        onSelect: function ( media ) {
                            setStepImage(item.id, media);
                        }
                    });

                return el(
                    'li',
                    { key: item.id || ( 'row-' + index ), className: 'rankkernel-howto-row', 'data-rk-step': item.id || ( 'row-' + index ) },
                    el(
                        Card,
                        { className: 'rankkernel-howto-card' },
                        el(
                            CardBody,
                            null,
                            el(
                                'div',
                                { className: 'rankkernel-howto-row-head' },
                                el('span', { className: 'rankkernel-howto-badge', 'aria-hidden': 'true' }, String(itemNumber)),
                                el('span', { className: 'rankkernel-howto-row-title' }, cardLabel)
                            ),
                            el(TextControl, {
                                label: titleLabel,
                                value: item.title || '',
                                placeholder: __('Enter a step title...', 'rankkernel'),
                                onChange: function ( value ) {
                                    updateStep(item.id, 'title', value);
                                }
                            }),
                            el('span', { className: 'rankkernel-howto-field-label' }, descriptionLabel),
                            el(RichText, {
                                tagName: 'div',
                                multiline: 'p',
                                className: 'rankkernel-howto-row-text',
                                'aria-label': descriptionLabel,
                                placeholder: __('Enter the step instructions...', 'rankkernel'),
                                value: item.text || '',
                                onChange: function ( value ) {
                                    updateStep(item.id, 'text', value);
                                }
                            }),
                            '' === item.title.trim() && '' === item.text.trim() ? el(
                                'p',
                                { className: 'rankkernel-howto-row-hint' },
                                __('Add a title or instructions so this step appears on the page and in the schema.', 'rankkernel')
                            ) : null,
                            imageControl,
                            el(
                                'div',
                                { className: 'rankkernel-howto-row-actions' },
                                el(
                                    Button,
                                    {
                                        variant: 'secondary',
                                        disabled: isFirst,
                                        label: moveUpLabel,
                                        'aria-label': moveUpLabel,
                                        onClick: function () {
                                            moveStep(item.id, -1);
                                        }
                                    },
                                    __('Move up', 'rankkernel')
                                ),
                                el(
                                    Button,
                                    {
                                        variant: 'secondary',
                                        disabled: isLast,
                                        label: moveDownLabel,
                                        'aria-label': moveDownLabel,
                                        onClick: function () {
                                            moveStep(item.id, 1);
                                        }
                                    },
                                    __('Move down', 'rankkernel')
                                ),
                                el(
                                    Button,
                                    {
                                        isDestructive: true,
                                        label: removeLabel,
                                        'aria-label': removeLabel,
                                        onClick: function () {
                                            removeStep(item.id);
                                        }
                                    },
                                    __('Remove step', 'rankkernel')
                                )
                            )
                        )
                    )
                );
            });

            var toolRows = tools.map(function ( value, index ) {
                /* translators: %d is the tool number. */
                var toolLabel = sprintf(__('Tool %d', 'rankkernel'), index + 1);
                /* translators: %d is the tool number. */
                var toolRemoveLabel = sprintf(__('Remove tool %d', 'rankkernel'), index + 1);

                return el(
                    'div',
                    { key: 'tool-' + index, className: 'rankkernel-howto-tool-row' },
                    el(TextControl, {
                        label: toolLabel,
                        value: value || '',
                        placeholder: __('Enter a tool...', 'rankkernel'),
                        onChange: function ( next ) {
                            updateTool(index, next);
                        }
                    }),
                    el(
                        Button,
                        {
                            isDestructive: true,
                            label: toolRemoveLabel,
                            'aria-label': toolRemoveLabel,
                            onClick: function () {
                                removeTool(index);
                            }
                        },
                        __('Remove', 'rankkernel')
                    )
                );
            });

            var materialRows = materials.map(function ( value, index ) {
                /* translators: %d is the material number. */
                var materialLabel = sprintf(__('Material %d', 'rankkernel'), index + 1);
                /* translators: %d is the material number. */
                var materialRemoveLabel = sprintf(__('Remove material %d', 'rankkernel'), index + 1);

                return el(
                    'div',
                    { key: 'material-' + index, className: 'rankkernel-howto-tool-row' },
                    el(TextControl, {
                        label: materialLabel,
                        value: value || '',
                        placeholder: __('Enter a material...', 'rankkernel'),
                        onChange: function ( next ) {
                            updateMaterial(index, next);
                        }
                    }),
                    el(
                        Button,
                        {
                            isDestructive: true,
                            label: materialRemoveLabel,
                            'aria-label': materialRemoveLabel,
                            onClick: function () {
                                removeMaterial(index);
                            }
                        },
                        __('Remove', 'rankkernel')
                    )
                );
            });

            var hasRows = steps.length > 0;

            var listContent = hasRows
                ? el('ol', { className : 'rankkernel-howto-editor-list' }, rows)
                : el(
                    'div',
                    { className: 'rankkernel-howto-empty' },
                    el(
                        'p',
                        null,
                        __('No steps yet. Add at least one step so the list and the HowTo schema have content to show.', 'rankkernel')
                    ),
                    el(
                        Button,
                        { variant: 'primary', onClick: addStep },
                        __('Add step', 'rankkernel')
                    )
                );

            return el(
                'div',
                { ...blockProps, ref: blockRoot },
                el(
                    InspectorControls,
                    null,
                    el(
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
                                if ( next !== titleWrapper ) {
                                    setAttributes({ titleWrapper: next });
                                }
                            }
                        }),
                        el(SelectControl, {
                            label: __('Step heading', 'rankkernel'),
                            help: __('Steps follow the title size unless set here.', 'rankkernel'),
                            value: stepTag,
                            options: [
                                { label: __('Same as title', 'rankkernel'), value: '' },
                                { label: __('Heading 2', 'rankkernel'), value: 'h2' },
                                { label: __('Heading 3', 'rankkernel'), value: 'h3' },
                                { label: __('Heading 4', 'rankkernel'), value: 'h4' }
                            ],
                            onChange: function ( value ) {
                                var next = allowedStepTag(value);
                                if ( next !== stepTag ) {
                                    setAttributes({ stepTag: next });
                                }
                            }
                        })
                    ),
                    el(
                        PanelBody,
                        { title: __('HowTo Details', 'rankkernel'), initialOpen: false },
                        el(TextareaControl, {
                            label: __('Description', 'rankkernel'),
                            value: description,
                            placeholder: __('Describe the whole guide...', 'rankkernel'),
                            onChange: function ( value ) {
                                if ( value !== description ) {
                                    setAttributes({ description: value });
                                }
                            }
                        }),
                        el(TextControl, {
                            label: __('Total time', 'rankkernel'),
                            help: __('ISO 8601 duration, for example PT30M. Values outside that shape stay out of the schema.', 'rankkernel'),
                            value: totalTime,
                            placeholder: 'PT30M',
                            onChange: function ( value ) {
                                if ( value !== totalTime ) {
                                    setAttributes({ totalTime: value });
                                }
                            }
                        }),
                        el(TextControl, {
                            label: __('Estimated cost', 'rankkernel'),
                            value: estimatedCost,
                            placeholder: __('For example 5 USD...', 'rankkernel'),
                            onChange: function ( value ) {
                                if ( value !== estimatedCost ) {
                                    setAttributes({ estimatedCost: value });
                                }
                            }
                        })
                    ),
                    el(
                        PanelBody,
                        { title: __('Tools', 'rankkernel'), initialOpen: false },
                        toolRows,
                        el(
                            Button,
                            { variant: 'secondary', onClick: addTool },
                            __('Add tool', 'rankkernel')
                        )
                    ),
                    el(
                        PanelBody,
                        { title: __('Materials', 'rankkernel'), initialOpen: false },
                        materialRows,
                        el(
                            Button,
                            { variant: 'secondary', onClick: addMaterial },
                            __('Add material', 'rankkernel')
                        )
                    )
                ),
                el(RichText, {
                    tagName: titleWrapper,
                    className: 'rankkernel-howto-editor-title',
                    'aria-label': __('HowTo title', 'rankkernel'),
                    placeholder: __('Add a title...', 'rankkernel'),
                    value: title,
                    onChange: function ( value ) {
                        if ( value !== title ) {
                            setAttributes({ title: value });
                        }
                    }
                }),
                listContent,
                hasRows ? el(
                    Button,
                    { variant: 'primary', ref: addButtonRef, onClick: addStep },
                    __('Add step', 'rankkernel')
                ) : null
            );
        },

        save: function () {
            return null;
        }
    });
} )();
