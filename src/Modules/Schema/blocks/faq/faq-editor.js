( function () {
    var el = wp.element.createElement;
    var RichText = wp.blockEditor.RichText;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var Button = wp.components.Button;
    var __ = wp.i18n.__;

    function allowedWrapper( value ) {
        if ( 'h2' === value || 'h3' === value || 'h4' === value ) {
            return value;
        }

        return 'h3';
    }

    function emptyQuestion() {
        return { question: '', answer: '' };
    }

    wp.blocks.registerBlockType('rankkernel/faq', {
        edit: function ( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var title = attributes.title || '';
            var titleWrapper = allowedWrapper(attributes.titleWrapper || 'h3');
            var listStyle = 'ol' === attributes.listStyle ? 'ol' : 'ul';
            var questions = attributes.questions || [];

            function updateQuestion( index, field, value ) {
                var next = questions.map(function ( row, i ) {
                    if ( i !== index ) {
                        return row;
                    }

                    var copy = {
                        question: row.question || '',
                        answer: row.answer || ''
                    };

                    copy[ field ] = value;

                    return copy;
                });

                setAttributes({ questions: next });
            }

            function removeQuestion( index ) {
                setAttributes({
                    questions: questions.filter(function ( row, i ) {
                        return i !== index;
                    })
                });
            }

            function addQuestion() {
                setAttributes({ questions: questions.concat([ emptyQuestion() ]) });
            }

            var rows = questions.map(function ( row, index ) {
                return el(
                    'div',
                    { key: index, className: 'rankkernel-faq-row' },
                    el(RichText, {
                        tagName: titleWrapper,
                        className: 'rankkernel-faq-row-question',
                        placeholder: __('Enter a question...', 'rankkernel'),
                        value: row.question || '',
                        onChange: function ( value ) {
                            updateQuestion(index, 'question', value);
                        }
                    }),
                    el(RichText, {
                        tagName: 'div',
                        multiline: 'p',
                        className: 'rankkernel-faq-row-answer',
                        placeholder: __('Enter the answer...', 'rankkernel'),
                        value: row.answer || '',
                        onChange: function ( value ) {
                            updateQuestion(index, 'answer', value);
                        }
                    }),
                    el(
                        Button,
                        {
                            isDestructive: true,
                            onClick: function () {
                                removeQuestion(index);
                            }
                        },
                        __('Remove question', 'rankkernel')
                    )
                );
            });

            return el(
                'div',
                { className: 'rankkernel-faq-editor' },
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
                            setAttributes({ listStyle: value });
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
                            setAttributes({ titleWrapper: allowedWrapper(value) });
                        }
                    })
                )),
                el(RichText, {
                    tagName: titleWrapper,
                    className: 'rankkernel-faq-editor-title',
                    placeholder: __('Add a title...', 'rankkernel'),
                    value: title,
                    onChange: function ( value ) {
                        setAttributes({ title: value });
                    }
                }),
                el(listStyle, { className: 'rankkernel-faq-editor-list' }, rows),
                el(
                    Button,
                    { variant: 'primary', onClick: addQuestion },
                    __('Add question', 'rankkernel')
                )
            );
        },

        save: function () {
            return null;
        }
    });
} )();
