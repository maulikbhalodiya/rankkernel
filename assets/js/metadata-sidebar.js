/**
 * RankKernel SEO plugin sidebar (Gutenberg).
 *
 * Plain script, no build step. Uses wp globals directly with
 * wp.element.createElement, no JSX. Reads and writes the post meta key
 * _rankkernel_meta_data through wp.data. Adds no REST route.
 */
( function () {
	'use strict';

	if ( ! window.wp || ! window.wp.plugins || ! window.wp.editPost || ! window.wp.element || ! window.wp.data ) {
		return;
	}

	var el = window.wp.element.createElement;
	var useState = window.wp.element.useState;
	var useSelect = window.wp.data.useSelect;
	var useDispatch = window.wp.data.useDispatch;
	var registerPlugin = window.wp.plugins.registerPlugin;
	var PluginSidebar = window.wp.editPost.PluginSidebar;
	var __ = window.wp.i18n && window.wp.i18n.__ ? window.wp.i18n.__ : function ( text ) { return text; };

	var cfg = window.rankkernelMetaEditor || {};
	var templates = cfg.templates || {};
	var tokenLabels = cfg.tokenLabels || {};
	var limits = cfg.limits || {};
	var defaults = cfg.defaults || {};
	var META_KEY = '_rankkernel_meta_data';
	var TITLE_LIMIT = parseInt( limits.title, 10 ) || 60;
	var DESC_LIMIT = parseInt( limits.description, 10 ) || 160;

	var shared = window.rankkernelMetaEditorPreview || {};

	function effectiveValue( raw, templateKey ) {
		if ( shared.effectiveValue ) {
			return shared.effectiveValue( raw, templateKey );
		}
		var value = String( raw == null ? '' : raw ).trim();
		if ( '' !== value ) {
			return { text: String( raw ), inherited: true === false };
		}
		return { text: String( templates[ templateKey ] || '' ), inherited: true };
	}

	function counterStatus( length, limit ) {
		if ( shared.counterStatus ) {
			return shared.counterStatus( length, limit );
		}
		if ( length <= limit ) {
			return 'ok';
		}
		if ( length <= limit + 20 ) {
			return 'warn';
		}
		return 'over';
	}

	function statusWord( status ) {
		if ( shared.statusWord ) {
			return shared.statusWord( status );
		}
		if ( 'warn' === status ) {
			return 'Long';
		}
		if ( 'over' === status ) {
			return 'Too long';
		}
		return 'OK';
	}

	function shortUrl( permalink ) {
		if ( shared.shortUrl ) {
			return shared.shortUrl( permalink );
		}
		return String( permalink || '' ).replace( /^https?:\/\//, '' ).replace( /\/$/, '' );
	}

	function defaultMeta() {
		return {
			title: '',
			description: '',
			canonical: '',
			robots: { index: true, nofollow: false, noarchive: false, nosnippet: false, noimageindex: false, max_snippet: '', max_image_preview: '', max_video_preview: '' },
			og: { title: '', description: '', image: '', image_id: 0 },
			twitter: { card: 'summary_large_image', title: '', description: '', image: '', image_id: 0 }
		};
	}

	function withMeta( meta ) {
		var base = defaultMeta();
		var input = meta && 'object' === typeof meta ? meta : {};
		var robots = input.robots && 'object' === typeof input.robots ? input.robots : {};
		var og = input.og && 'object' === typeof input.og ? input.og : {};
		var twitter = input.twitter && 'object' === typeof input.twitter ? input.twitter : {};
		return {
			title: 'string' === typeof input.title ? input.title : base.title,
			description: 'string' === typeof input.description ? input.description : base.description,
			canonical: 'string' === typeof input.canonical ? input.canonical : base.canonical,
			robots: {
				index: robots.index !== false,
				nofollow: true === robots.nofollow,
				noarchive: true === robots.noarchive,
				nosnippet: true === robots.nosnippet,
				noimageindex: true === robots.noimageindex,
				max_snippet: robots.max_snippet || '',
				max_image_preview: robots.max_image_preview || '',
				max_video_preview: robots.max_video_preview || ''
			},
			og: {
				title: og.title || '',
				description: og.description || '',
				image: og.image || '',
				image_id: parseInt( og.image_id, 10 ) || 0
			},
			twitter: {
				card: 'summary' === twitter.card ? 'summary' : 'summary_large_image',
				title: twitter.title || '',
				description: twitter.description || '',
				image: twitter.image || '',
				image_id: parseInt( twitter.image_id, 10 ) || 0
			}
		};
	}

	function TabButton( props ) {
		return el(
			'button',
			{
				type: 'button',
				role: 'tab',
				id: props.id,
				'aria-selected': props.selected ? 'true' : 'false',
				'aria-controls': props.panelId,
				tabIndex: props.selected ? 0 : -1,
				className: 'rk-meta-tab' + ( props.selected ? ' is-active' : '' ),
				onClick: props.onSelect
			},
			props.label
		);
	}

	function FieldReset( props ) {
		return el(
			'button',
			{
				type: 'button',
				className: 'button button-small',
				onClick: props.onReset,
				'aria-label': __( 'Reset field', 'rankkernel' ) + ': ' + props.label
			},
			__( 'Reset', 'rankkernel' )
		);
	}

	function StateBadge( props ) {
		return el(
			'span',
			{
				className: 'rk-meta-badge' + ( props.inherited ? ' rk-is-inherited' : ' rk-is-custom' ),
				'data-rk-state': props.inherited ? 'inherited' : 'custom'
			},
			props.inherited ? __( 'Inherited', 'rankkernel' ) : __( 'Custom', 'rankkernel' )
		);
	}

	function Counter( props ) {
		var chars = String( props.text || '' ).length;
		var status = counterStatus( chars, props.limit );
		return el(
			'p',
			{
				className: 'rk-meta-count ' + ( 'ok' === status ? 'rk-is-ok' : ( 'warn' === status ? 'rk-is-warn' : 'rk-is-over' ) ),
				'data-rk-state': status,
				role: 'status'
			},
			chars + ' / ' + props.limit + ' ' + __( 'chars', 'rankkernel' ) + ', ' + statusWord( status )
		);
	}

	function TokenPicker( props ) {
		var tokens = Object.keys( tokenLabels );
		if ( ! tokens.length ) {
			return el( 'p', { className: 'description' }, __( 'No tokens available for this post type.', 'rankkernel' ) );
		}
		return el(
			'div',
			{ className: 'rk-meta-tokens' },
			el( 'span', { className: 'rk-meta-tokens-label', id: props.labelId }, __( 'Insert token', 'rankkernel' ) ),
			el(
				'div',
				{ className: 'rk-meta-token-list', role: 'group', 'aria-labelledby': props.labelId },
				tokens.map( function ( token ) {
					return el(
						'button',
						{
							key: token,
							type: 'button',
							className: 'button button-small',
							title: tokenLabels[ token ] || token,
							'aria-label': __( 'Insert token', 'rankkernel' ) + ' ' + token,
							onClick: function () {
								props.onInsert( token );
							}
						},
						token
					);
				} )
			)
		);
	}

	function SerpPreview( props ) {
		var title = effectiveValue( props.meta.title, 'title' );
		var desc = effectiveValue( props.meta.description, 'description' );
		return el(
			'div',
			{ className: 'rk-meta-serp' + ( 'mobile' === props.device ? ' rk-is-mobile' : '' ) },
			el(
				'div',
				{ className: 'rk-meta-serp-tools', role: 'group', 'aria-label': __( 'Preview width', 'rankkernel' ) },
				el( 'button', {
					type: 'button',
					className: 'button button-small' + ( 'desktop' === props.device ? ' is-active' : '' ),
					'aria-pressed': 'desktop' === props.device ? 'true' : 'false',
					onClick: function () { props.onDevice( 'desktop' ); }
				}, __( 'Desktop', 'rankkernel' ) ),
				el( 'button', {
					type: 'button',
					className: 'button button-small' + ( 'mobile' === props.device ? ' is-active' : '' ),
					'aria-pressed': 'mobile' === props.device ? 'true' : 'false',
					onClick: function () { props.onDevice( 'mobile' ); }
				}, __( 'Mobile', 'rankkernel' ) )
			),
			el( 'p', { className: 'rk-meta-serp-site' }, cfg.siteName || cfg.siteUrl || '' ),
			el( 'p', { className: 'rk-meta-serp-title' }, title.text || __( 'Untitled', 'rankkernel' ) ),
			el( 'p', { className: 'rk-meta-serp-url' }, shortUrl( cfg.permalink || cfg.homeUrl || '' ) ),
			el( 'p', { className: 'rk-meta-serp-desc' }, desc.text || '' ),
			el( 'p', { className: 'description' }, __( 'Preview is approximate, not exact Google rendering.', 'rankkernel' ) )
		);
	}

	function SocialPreview( props ) {
		var title = String( props.meta.og.title ).trim() !== '' ? props.meta.og.title : effectiveValue( props.meta.title, 'title' ).text;
		var desc = String( props.meta.og.description ).trim() !== '' ? props.meta.og.description : effectiveValue( props.meta.description, 'description' ).text;
		var image = String( props.meta.og.image ).trim() !== '' ? props.meta.og.image : String( defaults.ogImage || '' );
		var card = 'summary' === props.meta.twitter.card ? 'summary' : 'summary_large_image';
		return el(
			'div',
			{ className: 'rk-meta-social rk-card-' + card },
			image ? el( 'img', { className: 'rk-meta-social-image', src: image, alt: '' } ) : null,
			el(
				'div',
				{ className: 'rk-meta-social-body' },
				el( 'p', { className: 'rk-meta-social-title' }, title || __( 'Untitled', 'rankkernel' ) ),
				el( 'p', { className: 'rk-meta-social-desc' }, desc || '' ),
				el( 'p', { className: 'rk-meta-social-site' }, cfg.siteName || cfg.siteUrl || '' ),
				el( 'p', { className: 'description' }, 'summary' === card ? __( 'Small image card', 'rankkernel' ) : __( 'Large image card', 'rankkernel' ) )
			)
		);
	}

	function pickImage( onPick ) {
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		var frame = window.wp.media( {
			title: __( 'Select preview image', 'rankkernel' ),
			button: { text: __( 'Use this image', 'rankkernel' ) },
			multiple: false
		} );
		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first();
			if ( ! attachment ) {
				return;
			}
			var json = attachment.toJSON();
			var src = ( json.sizes && json.sizes.full && json.sizes.full.url ) || json.url || '';
			onPick( src, json.id || 0 );
		} );
		frame.open();
	}

	function RankKernelSidebar() {
		var tabState = useState( 'general' );
		var activeTab = tabState[ 0 ];
		var setActiveTab = tabState[ 1 ];
		var deviceState = useState( 'desktop' );
		var device = deviceState[ 0 ];
		var setDevice = deviceState[ 1 ];

		var stored = useSelect( function ( select ) {
			var edited = select( 'core/editor' ).getEditedPostAttribute( 'meta' );
			return edited && edited[ META_KEY ] ? edited[ META_KEY ] : null;
		}, [] );
		var editPost = useDispatch( 'core/editor' ).editPost;
		var meta = withMeta( stored );

		function save( next ) {
			var payload = {};
			payload[ META_KEY ] = next;
			editPost( { meta: payload } );
		}

		function setTop( key, value ) {
			var next = withMeta( meta );
			next[ key ] = value;
			save( next );
		}

		function setNested( group, key, value ) {
			var next = withMeta( meta );
			next[ group ][ key ] = value;
			save( next );
		}

		function resetField( group, key ) {
			if ( group ) {
				setNested( group, key, '' );
			} else {
				setTop( key, '' );
			}
		}

		function appendToken( group, key, token ) {
			var current = group ? String( meta[ group ][ key ] || '' ) : String( meta[ key ] || '' );
			var next = current + token;
			if ( group ) {
				setNested( group, key, next );
			} else {
				setTop( key, next );
			}
		}

		var TextControl = window.wp.components.TextControl;
		var TextareaControl = window.wp.components.TextareaControl;
		var SelectControl = window.wp.components.SelectControl;
		var CheckboxControl = window.wp.components.CheckboxControl;

		function tokenGroup( group, key, labelId ) {
			return el( TokenPicker, { labelId: labelId, onInsert: function ( token ) { appendToken( group, key, token ); } } );
		}

		function textRow( opts ) {
			var value = opts.group ? meta[ opts.group ][ opts.key ] : meta[ opts.key ];
			var templateKey = opts.template || null;
			var eff = templateKey ? effectiveValue( value, templateKey ) : null;
			return el(
				'div',
				{ className: 'rk-meta-field' },
				el(
					'div',
					{ className: 'rk-meta-field-head' },
					el( 'label', { htmlFor: opts.id }, opts.label ),
					eff ? el( StateBadge, { inherited: eff.inherited } ) : null
				),
				opts.textarea ? el( TextareaControl, {
					id: opts.id,
					value: value,
					placeholder: eff && eff.inherited ? eff.text : '',
					onChange: function ( next ) {
						if ( opts.group ) {
							setNested( opts.group, opts.key, next );
						} else {
							setTop( opts.key, next );
						}
					}
				} ) : ( 'select' === opts.kind ? el( SelectControl, {
					id: opts.id,
					value: value,
					options: opts.options,
					onChange: function ( next ) {
						if ( opts.group ) {
							setNested( opts.group, opts.key, next );
						} else {
							setTop( opts.key, next );
						}
					}
				} ) : el( TextControl, {
					id: opts.id,
					value: value,
					placeholder: eff && eff.inherited ? eff.text : '',
					onChange: function ( next ) {
						if ( opts.group ) {
							setNested( opts.group, opts.key, next );
						} else {
							setTop( opts.key, next );
						}
					}
				} ) ),
				opts.limit ? el( Counter, { text: eff ? eff.text : value, limit: opts.limit } ) : null,
				opts.tokenizable ? tokenGroup( opts.group || null, opts.key, opts.id + '-tokens' ) : null,
				opts.resettable ? el( FieldReset, {
					label: opts.label,
					onReset: function () { resetField( opts.group || null, opts.key ); }
				} ) : null
			);
		}

		function imageRow( group, label ) {
			var current = meta[ group ];
			var prefix = 'rk-meta-' + group + '-image';
			return el(
				'div',
				{ className: 'rk-meta-field' },
				el( 'span', { className: 'rk-meta-field-label', id: prefix + '-label' }, label ),
				current.image ? el( 'img', { className: 'rk-meta-thumb', src: current.image, alt: '' } ) : null,
				el(
					'div',
					{ className: 'rk-meta-row-actions' },
					el( 'button', {
						type: 'button',
						className: 'button button-small',
						onClick: function () {
							pickImage( function ( src, id ) {
								var next = withMeta( meta );
								next[ group ].image = src;
								next[ group ].image_id = id;
								save( next );
							} );
						}
					}, current.image ? __( 'Change image', 'rankkernel' ) : __( 'Select image', 'rankkernel' ) ),
					current.image ? el( 'button', {
						type: 'button',
						className: 'button button-small',
						onClick: function () {
							var next = withMeta( meta );
							next[ group ].image = '';
							next[ group ].image_id = 0;
							save( next );
						}
					}, __( 'Remove', 'rankkernel' ) ) : null
				)
			);
		}

		var tabs = [
			{ id: 'general', label: __( 'General', 'rankkernel' ) },
			{ id: 'social', label: __( 'Social', 'rankkernel' ) },
			{ id: 'advanced', label: __( 'Advanced', 'rankkernel' ) }
		];

		function onTabKey( event, index ) {
			if ( 'ArrowRight' === event.key || 'ArrowDown' === event.key ) {
				event.preventDefault();
				setActiveTab( tabs[ ( index + 1 ) % tabs.length ].id );
			} else if ( 'ArrowLeft' === event.key || 'ArrowUp' === event.key ) {
				event.preventDefault();
				setActiveTab( tabs[ ( index - 1 + tabs.length ) % tabs.length ].id );
			} else if ( 'Home' === event.key ) {
				event.preventDefault();
				setActiveTab( tabs[ 0 ].id );
			} else if ( 'End' === event.key ) {
				event.preventDefault();
				setActiveTab( tabs[ tabs.length - 1 ].id );
			}
		}

		var panel = null;
		if ( 'general' === activeTab ) {
			panel = el(
				'div',
				{ role: 'tabpanel', id: 'rk-meta-panel-general', 'aria-labelledby': 'rk-meta-tab-general' },
				textRow( { id: 'rk-meta-title', label: __( 'SEO title', 'rankkernel' ), key: 'title', template: 'title', limit: TITLE_LIMIT, tokenizable: true, resettable: true, textarea: true } ),
				textRow( { id: 'rk-meta-description', label: __( 'Meta description', 'rankkernel' ), key: 'description', template: 'description', limit: DESC_LIMIT, tokenizable: true, resettable: true, textarea: true } ),
				el( SerpPreview, { meta: meta, device: device, onDevice: setDevice } )
			);
		} else if ( 'social' === activeTab ) {
			panel = el(
				'div',
				{ role: 'tabpanel', id: 'rk-meta-panel-social', 'aria-labelledby': 'rk-meta-tab-social' },
				textRow( { id: 'rk-meta-og-title', label: __( 'Open Graph title', 'rankkernel' ), group: 'og', key: 'title', template: 'title', tokenizable: true, resettable: true } ),
				textRow( { id: 'rk-meta-og-description', label: __( 'Open Graph description', 'rankkernel' ), group: 'og', key: 'description', template: 'description', tokenizable: true, resettable: true, textarea: true } ),
				imageRow( 'og', __( 'Open Graph image', 'rankkernel' ) ),
				el( SelectControl, {
					label: __( 'Twitter card type', 'rankkernel' ),
					value: meta.twitter.card,
					options: [
						{ label: __( 'Large image', 'rankkernel' ), value: 'summary_large_image' },
						{ label: __( 'Small image', 'rankkernel' ), value: 'summary' }
					],
					onChange: function ( next ) { setNested( 'twitter', 'card', next ); }
				} ),
				textRow( { id: 'rk-meta-twitter-title', label: __( 'Twitter title', 'rankkernel' ), group: 'twitter', key: 'title', template: 'title', tokenizable: true, resettable: true } ),
				textRow( { id: 'rk-meta-twitter-description', label: __( 'Twitter description', 'rankkernel' ), group: 'twitter', key: 'description', template: 'description', tokenizable: true, resettable: true, textarea: true } ),
				imageRow( 'twitter', __( 'Twitter image', 'rankkernel' ) ),
				el( SocialPreview, { meta: meta } )
			);
		} else {
			panel = el(
				'div',
				{ role: 'tabpanel', id: 'rk-meta-panel-advanced', 'aria-labelledby': 'rk-meta-tab-advanced' },
				textRow( { id: 'rk-meta-canonical', label: __( 'Canonical URL', 'rankkernel' ), key: 'canonical' } ),
				el(
					'fieldset',
					{ className: 'rk-meta-field' },
					el( 'legend', null, __( 'Robots', 'rankkernel' ) ),
					el( CheckboxControl, {
						label: __( 'Allow indexing', 'rankkernel' ),
						checked: true === meta.robots.index,
						onChange: function ( next ) { setNested( 'robots', 'index', true === next ); }
					} ),
					el( CheckboxControl, {
						label: __( 'No follow links', 'rankkernel' ),
						checked: meta.robots.nofollow,
						onChange: function ( next ) { setNested( 'robots', 'nofollow', true === next ); }
					} ),
					el( CheckboxControl, {
						label: __( 'No archive', 'rankkernel' ),
						checked: meta.robots.noarchive,
						onChange: function ( next ) { setNested( 'robots', 'noarchive', true === next ); }
					} ),
					el( CheckboxControl, {
						label: __( 'No snippet', 'rankkernel' ),
						checked: meta.robots.nosnippet,
						onChange: function ( next ) { setNested( 'robots', 'nosnippet', true === next ); }
					} ),
					el( CheckboxControl, {
						label: __( 'No image index', 'rankkernel' ),
						checked: meta.robots.noimageindex,
						onChange: function ( next ) { setNested( 'robots', 'noimageindex', true === next ); }
					} )
				),
				el( SelectControl, {
					label: __( 'Max snippet', 'rankkernel' ),
					value: meta.robots.max_snippet,
					options: [
						{ label: __( 'Default', 'rankkernel' ), value: '' },
						{ label: '-1', value: '-1' },
						{ label: '0', value: '0' }
					],
					onChange: function ( next ) { setNested( 'robots', 'max_snippet', next ); }
				} ),
				el( SelectControl, {
					label: __( 'Max image preview', 'rankkernel' ),
					value: meta.robots.max_image_preview,
					options: [
						{ label: __( 'Default', 'rankkernel' ), value: '' },
						{ label: 'none', value: 'none' },
						{ label: 'standard', value: 'standard' },
						{ label: 'large', value: 'large' }
					],
					onChange: function ( next ) { setNested( 'robots', 'max_image_preview', next ); }
				} ),
				el( SelectControl, {
					label: __( 'Max video preview', 'rankkernel' ),
					value: meta.robots.max_video_preview,
					options: [
						{ label: __( 'Default', 'rankkernel' ), value: '' },
						{ label: '-1', value: '-1' },
						{ label: '0', value: '0' }
					],
					onChange: function ( next ) { setNested( 'robots', 'max_video_preview', next ); }
				} )
			);
		}

		return el(
			PluginSidebar,
			{ name: 'rankkernel-seo-sidebar', title: __( 'RankKernel SEO', 'rankkernel' ) },
			el(
				'div',
				{ className: 'rk-meta' },
				el(
					'div',
					{ role: 'tablist', 'aria-label': __( 'SEO settings sections', 'rankkernel' ), className: 'rk-meta-tabs' },
					tabs.map( function ( tab, index ) {
						return el(
							'div',
							{ key: tab.id, onKeyDown: function ( event ) { onTabKey( event, index ); } },
							el( TabButton, {
								id: 'rk-meta-tab-' + tab.id,
								panelId: 'rk-meta-panel-' + tab.id,
								label: tab.label,
								selected: activeTab === tab.id,
								onSelect: function () { setActiveTab( tab.id ); }
							} )
						);
					} )
				),
				panel
			)
		);
	}

	// Keep a reference for tests and for future reuse.
	window.rankkernelSeoSidebar = window.rankkernelSeoSidebar || {};
	window.rankkernelSeoSidebar.withMeta = withMeta;
	window.rankkernelSeoSidebar.usesSharedPreview = function () {
		return Boolean( window.rankkernelMetaEditorPreview && window.rankkernelMetaEditorPreview.effectiveValue );
	};

	registerPlugin( 'rankkernel-seo', { render: RankKernelSidebar } );
} )();
