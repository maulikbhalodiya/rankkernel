( function () {
    var el = wp.element.createElement;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps = wp.blockEditor.useBlockProps;
    var PanelBody = wp.components.PanelBody;
    var TextControl = wp.components.TextControl;
    var ToggleControl = wp.components.ToggleControl;
    var __ = wp.i18n.__;

    wp.blocks.registerBlockType( 'rankkernel/breadcrumbs', {
        edit: function ( props ) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;
            var showHomeItem = attributes.showHomeItem !== false;
            var showCurrentItem = attributes.showCurrentItem !== false;
            var showOnHomePage = attributes.showOnHomePage === true;
            var separator = attributes.separator || '';

            var blockProps = useBlockProps( { className: 'rankkernel-breadcrumbs-editor' } );

            return el(
                'div',
                blockProps,
                el( InspectorControls, null, el(
                    PanelBody,
                    { title: __( 'Breadcrumbs Settings', 'rankkernel' ), initialOpen: true },
                    el( ToggleControl, {
                        label: __( 'Show home item', 'rankkernel' ),
                        checked: showHomeItem,
                        onChange: function ( value ) {
                            setAttributes( { showHomeItem: !! value } );
                        }
                    } ),
                    el( ToggleControl, {
                        label: __( 'Show current item', 'rankkernel' ),
                        checked: showCurrentItem,
                        onChange: function ( value ) {
                            setAttributes( { showCurrentItem: !! value } );
                        }
                    } ),
                    el( ToggleControl, {
                        label: __( 'Show on home page', 'rankkernel' ),
                        checked: showOnHomePage,
                        onChange: function ( value ) {
                            setAttributes( { showOnHomePage: !! value } );
                        }
                    } ),
                    el( TextControl, {
                        label: __( 'Separator', 'rankkernel' ),
                        help: __( 'Leave empty to use the separator from Settings.', 'rankkernel' ),
                        value: separator,
                        onChange: function ( value ) {
                            setAttributes( { separator: value } );
                        }
                    } )
                ) ),
                el(
                    'p',
                    { className: 'rankkernel-breadcrumbs-preview' },
                    __( 'Breadcrumbs preview: the live trail renders on the frontend.', 'rankkernel' )
                )
            );
        },

        save: function () {
            return null;
        }
    } );
} )();
