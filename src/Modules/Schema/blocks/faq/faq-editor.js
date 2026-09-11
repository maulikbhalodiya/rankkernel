( function () {
    var el = wp.element.createElement;
    var useEffect = wp.element.useEffect;
    var RichText = wp.blockEditor.RichText;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var TextControl = wp.components.TextControl;
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

    function allowedQuestionTag( value ) {
        if ( 'h2' === value || 'h3' === value || 'h4' === value ) {
            return value;
        }

        return '';
    }

    function newRowId() {
        return 'rkq-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
    }

    function useMigratedQuestions( questions, setAttributes ) {
        useEffect(function () {
            var seen = {};
            var changed = false;

            var next = questions.map(function ( row ) {
                var base = row && 'object' === typeof row ? row : {};
                var id = base.id;

                if ( 'string' === typeof id && '' !== id && ! seen[ id ] ) {
                    seen[ id ] = true;
                    return base;
                }

                changed = true;
                id = newRowId();

                while ( seen[ id ] ) {
                    id = newRowId();
                }

                seen[ id ] = true;

                return { ...base, id: id, question: base.question || '', answer: base.answer || '' };
            });

            if ( changed ) {
                setAttributes({ questions: next });
            }
        });
    }

    wp.blocks.registerBlockType('rankkernel/faq', {
        edit: function ( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var title = attributes.title || '';
            var titleWrapper = allowedWrapper(attributes.titleWrapper || 'h3');
            var questionTag = allowedQuestionTag(attributes.questionTag || '');
            var activeTag = '' !== questionTag ? questionTag : titleWrapper;
            var listStyle = 'ol' === attributes.listStyle ? 'ol' : 'ul';
            var questions = attributes.questions || [];

            useMigratedQuestions(questions, setAttributes);

            var blockProps = useBlockProps({ className: 'rankkernel-faq-editor' });

            function updateQuestionAt( index, field, value ) {
                var row = questions[ index ];

                if ( ! row || 'object' !== typeof row || row[ field ] === value ) {
                    return;
                }

                var next = questions.map(function ( current, i ) {
                    if ( i !== index ) {
                        return current;
                    }

                    return { ...current, [ field ]: value };
                });

                setAttributes({ questions: next });
            }

            function removeQuestionAt( index ) {
                setAttributes({
                    questions: questions.filter(function ( row, i ) {
                        return i !== index;
                    })
                });
            }

            function moveQuestionAt( index, direction ) {
                var to = index + direction;

                if ( index < 0 || index >= questions.length ) {
                    return;
                }

                if ( to < 0 || to >= questions.length ) {
                    return;
                }

                var next = questions.slice();
                var temp = next[ index ];
                next[ index ] = next[ to ];
                next[ to ] = temp;

                setAttributes({ questions: next });
            }

            function addQuestion() {
                setAttributes({ questions: questions.concat([ { id: newRowId(), question: '', answer: '' } ]) });
            }

            var filledCount = 0;
            var rows = questions.map(function ( row, index ) {
                var itemNumber = index + 1;
                var item = row && 'object' === typeof row ? row : { id : 'row-' + index, question : '', answer : '' };
                var questionText = 'string' === typeof item.question ? item.question.trim() : '';
                var badgeText;
                var badgeClass = 'rankkernel-faq-badge';

                if ( '' !== questionText ) {
                    filledCount++;
                    badgeText = String(filledCount);
                } else {
                    badgeText = __('New', 'rankkernel');
                    badgeClass = 'rankkernel-faq-badge rankkernel-faq-badge-draft';
                }

                var isFirst = 0 === index;
                var isLast = index === questions.length - 1;

                /* translators: %d is the FAQ item number. */
                var cardLabel = sprintf(__('FAQ %d', 'rankkernel'), itemNumber);
                /* translators: %d is the FAQ item number. */
                var questionLabel = sprintf(__('Question %d', 'rankkernel'), itemNumber);
                /* translators: %d is the FAQ item number. */
                var answerLabel = sprintf(__('Answer %d', 'rankkernel'), itemNumber);
                /* translators: %d is the FAQ item number. */
                var moveUpLabel = sprintf(__('Move FAQ %d up', 'rankkernel'), itemNumber);
                /* translators: %d is the FAQ item number. */
                var moveDownLabel = sprintf(__('Move FAQ %d down', 'rankkernel'), itemNumber);
                /* translators: %d is the FAQ item number. */
                var removeLabel = sprintf(__('Remove FAQ %d', 'rankkernel'), itemNumber);

                return el(
                    'li',
                    { key: item.id || ( 'row-' + index ), className: 'rankkernel-faq-row' },
                    el(
                        Card,
                        { className: 'rankkernel-faq-card' },
                        el(
                            CardBody,
                            null,
                            el(
                                'div',
                                { className: 'rankkernel-faq-row-head' },
                                el('span', { className: badgeClass, 'aria-hidden': 'true' }, badgeText),
                                el('span', { className: 'rankkernel-faq-row-title' }, cardLabel),
                                el(
                                    'div',
                                    { className: 'rankkernel-faq-row-actions' },
                                    el(
                                        Button,
                                        {
                                            variant: 'secondary',
                                            size: 'small',
                                            disabled: isFirst,
                                            label: moveUpLabel,
                                            'aria-label': moveUpLabel,
                                            onClick: function () {
                                                moveQuestionAt(index, -1);
                                            }
                                        },
                                        __('Up', 'rankkernel')
                                    ),
                                    el(
                                        Button,
                                        {
                                            variant: 'secondary',
                                            size: 'small',
                                            disabled: isLast,
                                            label: moveDownLabel,
                                            'aria-label': moveDownLabel,
                                            onClick: function () {
                                                moveQuestionAt(index, 1);
                                            }
                                        },
                                        __('Down', 'rankkernel')
                                    ),
                                    el(
                                        Button,
                                        {
                                            isDestructive: true,
                                            size: 'small',
                                            label: removeLabel,
                                            'aria-label': removeLabel,
                                            onClick: function () {
                                                removeQuestionAt(index);
                                            }
                                        },
                                        __('Remove', 'rankkernel')
                                    )
                                )
                            ),
                            el(TextControl, {
                                label: questionLabel,
                                value: item.question || '',
                                placeholder: __('Enter a question...', 'rankkernel'),
                                onChange: function ( value ) {
                                    updateQuestionAt(index, 'question', value);
                                }
                            }),
                            el('span', { className: 'rankkernel-faq-field-label' }, answerLabel),
                            el(RichText, {
                                tagName: 'div',
                                multiline: 'p',
                                className: 'rankkernel-faq-row-answer',
                                'aria-label': answerLabel,
                                placeholder: __('Enter the answer...', 'rankkernel'),
                                value: item.answer || '',
                                onChange: function ( value ) {
                                    updateQuestionAt(index, 'answer', value);
                                }
                            }),
                            '' === questionText ? el(
                                'p',
                                { className: 'rankkernel-faq-row-hint' },
                                __('Add a question so this entry appears on the page and in the schema.', 'rankkernel')
                            ) : null
                        )
                    )
                );
            });

            var hasRows = questions.length > 0;

            var listContent = hasRows
                ? el(listStyle, { className : 'rankkernel-faq-editor-list' }, rows)
                : el(
                    'div',
                    { className: 'rankkernel-faq-empty' },
                    el(
                        'p',
                        null,
                        __('No FAQs yet. Add your first question and answer. Each entry shows on the page and in the FAQ schema.', 'rankkernel')
                    ),
                    el(
                        Button,
                        { variant: 'primary', onClick: addQuestion },
                        __('Add FAQ', 'rankkernel')
                    )
                );

            return el(
                'div',
                blockProps,
                el(InspectorControls, null, el(
                    PanelBody,
                    { title: __('FAQ Settings', 'rankkernel'), initialOpen: true },
                    el(SelectControl, {
                        label: __('List style', 'rankkernel'),
                        value: listStyle,
                        options: [
                            { label: __('Unordered', 'rankkernel'), value: 'ul' },
                            { label: __('Ordered', 'rankkernel'), value: 'ol' }
                        ],
                        onChange: function ( value ) {
                            if ( value !== listStyle ) {
                                setAttributes({ listStyle: value });
                            }
                        }
                    }),
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
                        label: __('Question heading', 'rankkernel'),
                        help: __('Questions follow the title size unless set here.', 'rankkernel'),
                        value: questionTag,
                        options: [
                            { label: __('Same as title', 'rankkernel'), value: '' },
                            { label: __('Heading 2', 'rankkernel'), value: 'h2' },
                            { label: __('Heading 3', 'rankkernel'), value: 'h3' },
                            { label: __('Heading 4', 'rankkernel'), value: 'h4' }
                        ],
                        onChange: function ( value ) {
                            var next = allowedQuestionTag(value);
                            if ( next !== questionTag ) {
                                setAttributes({ questionTag: next });
                            }
                        }
                    })
                )),
                el(RichText, {
                    tagName: titleWrapper,
                    className: 'rankkernel-faq-editor-title',
                    'aria-label': __('FAQ title', 'rankkernel'),
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
                    { variant: 'primary', onClick: addQuestion },
                    __('Add FAQ', 'rankkernel')
                ) : null
            );
        },

        save: function () {
            return null;
        }
    });
} )();
