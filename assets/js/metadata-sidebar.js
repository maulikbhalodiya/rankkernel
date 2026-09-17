/**
 * RankKernel SEO sidebar (Gutenberg).
 *
 * Plain script, no build step. Uses wp globals directly with
 * wp.element.createElement, no JSX. Reads and writes the post meta key
 * _rankkernel_meta_data through wp.data only. Adds no REST route and
 * queries no Classic metabox DOM: every value flows through the editor
 * data store, so the hook contract test stays empty for this file.
 *
 * Tab order: General, Advanced, Schema, Social. Designed for narrow
 * sidebar width, not a mirror of the Classic metabox.
 */
( function () {
	'use strict';

	if ( ! window.wp || ! window.wp.plugins || ! window.wp.editPost || ! window.wp.element || ! window.wp.data ) {
		return;
	}

	var el = window.wp.element.createElement;
	var useState = window.wp.element.useState;
	var useEffect = window.wp.element.useEffect;
	var useRef = window.wp.element.useRef;
	var useSelect = window.wp.data.useSelect;
	var useDispatch = window.wp.data.useDispatch;
	var registerPlugin = window.wp.plugins.registerPlugin;
	var PluginSidebar = window.wp.editPost.PluginSidebar;
	var components = window.wp.components || {};
	var __ = window.wp.i18n && window.wp.i18n.__ ? window.wp.i18n.__ : function ( text ) { return text; };

	var cfg = window.rankkernelMetaEditor || {};
	var templates = cfg.templates || {};
	var tokenLabels = cfg.tokenLabels || {};
	var limits = cfg.limits || {};
	var defaults = cfg.defaults || {};
	var META_KEY = '_rankkernel_meta_data';
	var TITLE_LIMIT = parseInt( limits.title, 10 ) || 60;
	var DESC_LIMIT = parseInt( limits.description, 10 ) || 160;
	var DEBOUNCE_MS = 150;

	var shared = window.rankkernelMetaEditorPreview || {};

	function debounce( fn, wait ) {
		if ( shared.debounce ) {
			return shared.debounce( fn, wait );
		}
		var timer = null;
		return function () {
			var args = arguments;
			var self = this;
			if ( timer ) {
				clearTimeout( timer );
			}
			timer = setTimeout( function () {
				timer = null;
				fn.apply( self, args );
			}, wait );
		};
	}

	// Effective value: the stored override when set, otherwise the server
	// resolved template. Never invents token values here; templates arrive
	// resolved in window.rankkernelMetaEditor.
	function effectiveValue( raw, templateKey ) {
		if ( shared.effectiveValue ) {
			return shared.effectiveValue( raw, templateKey );
		}
		var value = String( raw == null ? '' : raw ).trim();
		if ( '' !== value ) {
			return { text: String( raw ), inherited: false };
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

	function isEmpty( value ) {
		return '' === String( value == null ? '' : value ).trim();
	}

	// Supported schema types, mirroring SchemaTypes::SUPPORTED and
	// SchemaTypes::LABELS. The payload schema subtree is the only storage;
	// this list selects into it and is never written itself.
	var SCHEMA_TYPES = [
		[ 'Article', 'Article' ],
		[ 'BlogPosting', 'Blog Posting' ],
		[ 'NewsArticle', 'News Article' ],
		[ 'WebPage', 'Web Page' ],
		[ 'FAQPage', 'FAQ Page' ],
		[ 'HowTo', 'How To' ],
		[ 'Product', 'Product' ],
		[ 'Recipe', 'Recipe' ],
		[ 'Event', 'Event' ],
		[ 'Service', 'Service' ],
		[ 'VideoObject', 'Video' ],
		[ 'ImageObject', 'Image' ],
		[ 'Book', 'Book' ],
		[ 'Course', 'Course' ],
		[ 'JobPosting', 'Job Posting' ],
		[ 'SoftwareApplication', 'Software Application' ],
		[ 'MusicRecording', 'Music Recording' ],
		[ 'LocalBusiness', 'Local Business' ],
		[ 'Review', 'Review' ],
		[ 'Movie', 'Movie' ],
		[ 'ClaimReview', 'Fact Check' ],
		[ 'Dataset', 'Dataset' ],
		[ 'PodcastEpisode', 'Podcast Episode' ],
		[ 'Carousel', 'Carousel' ],
		[ 'QAPage', 'Question and Answer Page' ],
		[ 'ItemList', 'Item List' ]
	];

	// Manual field labels, mirroring the Classic schema builder labels.
	var SCHEMA_FIELD_LABELS = {
		headline: 'Headline',
		description: 'Description',
		author: 'Author',
		price: 'Price',
		priceCurrency: 'Price currency',
		sku: 'SKU',
		availability: 'Availability',
		ratingValue: 'Rating value',
		reviewCount: 'Review count',
		bestRating: 'Best rating',
		worstRating: 'Worst rating',
		isbn: 'ISBN',
		startDate: 'Start date',
		endDate: 'End date',
		locationName: 'Location name',
		streetAddress: 'Street address',
		addressLocality: 'City',
		addressRegion: 'Region',
		postalCode: 'Postal code',
		addressCountry: 'Country',
		performer: 'Performer',
		eventStatus: 'Event status',
		ingredients: 'Ingredients',
		instructions: 'Instructions',
		prepTime: 'Prep time',
		cookTime: 'Cook time',
		totalTime: 'Total time',
		yield: 'Yield',
		areaServed: 'Area served',
		thumbnailUrl: 'Thumbnail URL',
		uploadDate: 'Upload date',
		duration: 'Duration',
		contentUrl: 'Content URL',
		appCategory: 'App category',
		operatingSystem: 'Operating system',
		artist: 'Artist',
		album: 'Album',
		dateCreated: 'Date created',
		director: 'Director',
		company: 'Company',
		jobLocation: 'Job location',
		salary: 'Salary',
		datePosted: 'Date posted',
		validThrough: 'Valid through',
		claimReviewed: 'Claim reviewed',
		datePublished: 'Date published',
		license: 'License URL',
		distributionUrl: 'File URL',
		distributionFormat: 'File format',
		seriesName: 'Series name',
		question: 'Question',
		answer: 'Answer',
		answerAuthor: 'Answer author',
		itemName: 'Reviewed item',
		reviewBody: 'Review text',
		telephone: 'Phone',
		priceRange: 'Price range',
		openingHours: 'Opening hours',
		caption: 'Caption',
		width: 'Width',
		height: 'Height',
		speakable: 'Speakable selectors',
		about: 'About',
		mentions: 'Mentions'
	};

	// Manual field relevance per type, mirroring the Classic builder: '*'
	// always shows, other fields only for the listed types.
	var SCHEMA_FIELD_TYPES = {
		headline: [ '*' ],
		description: [ '*' ],
		author: [ '*' ],
		price: [ 'Product', 'Event', 'Service', 'SoftwareApplication' ],
		priceCurrency: [ 'Product', 'Event', 'Service', 'JobPosting', 'SoftwareApplication' ],
		sku: [ 'Product' ],
		availability: [ 'Product' ],
		ratingValue: [ 'Product', 'SoftwareApplication', 'Movie', 'ClaimReview', 'Review' ],
		reviewCount: [ 'Product', 'SoftwareApplication', 'Movie' ],
		bestRating: [ 'ClaimReview', 'Review' ],
		worstRating: [ 'ClaimReview', 'Review' ],
		isbn: [ 'Book' ],
		startDate: [ 'Event' ],
		endDate: [ 'Event' ],
		locationName: [ 'Event', 'JobPosting' ],
		streetAddress: [ 'Event', 'LocalBusiness', 'JobPosting' ],
		addressLocality: [ 'Event', 'LocalBusiness', 'JobPosting' ],
		addressRegion: [ 'Event', 'LocalBusiness', 'JobPosting' ],
		postalCode: [ 'Event', 'LocalBusiness', 'JobPosting' ],
		addressCountry: [ 'Event', 'LocalBusiness', 'JobPosting' ],
		performer: [ 'Event' ],
		eventStatus: [ 'Event' ],
		ingredients: [ 'Recipe' ],
		instructions: [ 'Recipe' ],
		prepTime: [ 'Recipe' ],
		cookTime: [ 'Recipe' ],
		totalTime: [ 'Recipe' ],
		yield: [ 'Recipe' ],
		areaServed: [ 'Service' ],
		thumbnailUrl: [ 'VideoObject' ],
		uploadDate: [ 'VideoObject' ],
		duration: [ 'VideoObject', 'PodcastEpisode' ],
		contentUrl: [ 'VideoObject', 'PodcastEpisode', 'ImageObject' ],
		appCategory: [ 'SoftwareApplication' ],
		operatingSystem: [ 'SoftwareApplication' ],
		artist: [ 'MusicRecording' ],
		album: [ 'MusicRecording' ],
		dateCreated: [ 'Movie' ],
		director: [ 'Movie' ],
		company: [ 'JobPosting' ],
		jobLocation: [ 'JobPosting' ],
		salary: [ 'JobPosting' ],
		datePosted: [ 'JobPosting' ],
		validThrough: [ 'JobPosting' ],
		claimReviewed: [ 'ClaimReview' ],
		datePublished: [ 'ClaimReview', 'PodcastEpisode', 'Review' ],
		license: [ 'Dataset' ],
		distributionUrl: [ 'Dataset' ],
		distributionFormat: [ 'Dataset' ],
		seriesName: [ 'PodcastEpisode' ],
		question: [ 'QAPage' ],
		answer: [ 'QAPage' ],
		answerAuthor: [ 'QAPage' ],
		itemName: [ 'Review' ],
		reviewBody: [ 'Review' ],
		telephone: [ 'LocalBusiness' ],
		priceRange: [ 'LocalBusiness' ],
		openingHours: [ 'LocalBusiness' ],
		caption: [ 'ImageObject' ],
		width: [ 'ImageObject' ],
		height: [ 'ImageObject' ],
		speakable: [ 'WebPage' ],
		about: [ 'WebPage' ],
		mentions: [ 'WebPage' ]
	};

	var SCHEMA_NO_FIELDS_REQUIRED = [ 'WebPage', 'FAQPage', 'HowTo', 'Carousel', 'QAPage', 'ItemList' ];

	function schemaRequiredFields( type ) {
		if ( 'Event' === type ) {
			return [ 'headline', 'startDate', 'locationName' ];
		}
		if ( 'Product' === type ) {
			return [ 'headline' ];
		}
		if ( '' === type || SCHEMA_NO_FIELDS_REQUIRED.indexOf( type ) >= 0 ) {
			return [];
		}
		return [ 'headline' ];
	}

	function schemaRequiredMessage( type, fieldKey ) {
		if ( 'headline' === fieldKey && 'Product' === type ) {
			return 'Product name (headline) is required for Product.';
		}
		if ( 'headline' === fieldKey && 'Event' === type ) {
			return 'Name (headline) is required for Event.';
		}
		if ( 'headline' === fieldKey ) {
			return 'Headline is required for ' + type + '.';
		}
		var labels = { startDate: 'Start date', locationName: 'Location name' };
		return ( labels[ fieldKey ] || fieldKey ) + ' is required for ' + type + '.';
	}

	function schemaFieldVisible( key, selected ) {
		var types = SCHEMA_FIELD_TYPES[ key ] || [];
		if ( types.indexOf( '*' ) >= 0 ) {
			return true;
		}
		return '' !== selected && types.indexOf( selected ) >= 0;
	}

	function defaultMeta() {
		return {
			title: '',
			description: '',
			canonical: '',
			robots: { index: true, follow: true, noarchive: false, nosnippet: false, noimageindex: false, max_snippet: '', max_image_preview: '', max_video_preview: '' },
			og: { title: '', description: '', image: '', image_id: 0, type: '' },
			twitter: { card: 'summary_large_image', title: '', description: '', image: '', image_id: 0 },
			schema: []
		};
	}

	function strOrEmpty( value ) {
		return 'string' === typeof value ? value : '';
	}

	function withMeta( meta ) {
		var base = defaultMeta();
		var input = meta && 'object' === typeof meta ? meta : {};
		var robots = input.robots && 'object' === typeof input.robots ? input.robots : {};
		var og = input.og && 'object' === typeof input.og ? input.og : {};
		var twitter = input.twitter && 'object' === typeof input.twitter ? input.twitter : {};
		// The schema subtree passes through untouched: fresh rows hold an
		// empty list, later saves normalize it to the object shape. Readers
		// stay defensive and never reshape it here.
		var schema = input.schema && 'object' === typeof input.schema ? input.schema : base.schema;
		return {
			title: strOrEmpty( input.title ),
			description: strOrEmpty( input.description ),
			canonical: strOrEmpty( input.canonical ),
			robots: {
				index: robots.index !== false,
				follow: robots.follow !== false,
				noarchive: true === robots.noarchive,
				nosnippet: true === robots.nosnippet,
				noimageindex: true === robots.noimageindex,
				max_snippet: robots.max_snippet == null ? '' : String( robots.max_snippet ),
				max_image_preview: robots.max_image_preview == null ? '' : String( robots.max_image_preview ),
				max_video_preview: robots.max_video_preview == null ? '' : String( robots.max_video_preview )
			},
			og: {
				title: strOrEmpty( og.title ),
				description: strOrEmpty( og.description ),
				image: strOrEmpty( og.image ),
				image_id: parseInt( og.image_id, 10 ) || 0,
				type: strOrEmpty( og.type )
			},
			twitter: {
				card: 'summary' === twitter.card ? 'summary' : 'summary_large_image',
				title: strOrEmpty( twitter.title ),
				description: strOrEmpty( twitter.description ),
				image: strOrEmpty( twitter.image ),
				image_id: parseInt( twitter.image_id, 10 ) || 0
			},
			schema: schema
		};
	}

	function schemaObject( schema ) {
		if ( schema && 'object' === typeof schema && ! Array.isArray( schema ) ) {
			return schema;
		}
		return {};
	}

	function schemaTypeOf( schema ) {
		var obj = schemaObject( schema );
		return 'string' === typeof obj.type ? obj.type : '';
	}

	function schemaFieldsOf( schema ) {
		var obj = schemaObject( schema );
		return obj.fields && 'object' === typeof obj.fields && ! Array.isArray( obj.fields ) ? obj.fields : {};
	}

	function schemaCustomOf( schema ) {
		var obj = schemaObject( schema );
		return obj.custom && 'object' === typeof obj.custom && ! Array.isArray( obj.custom ) ? obj.custom : {};
	}

	function countRows( list, needsQuestion ) {
		if ( ! Array.isArray( list ) ) {
			return 0;
		}
		var count = 0;
		list.forEach( function ( row ) {
			if ( ! row || 'object' !== typeof row ) {
				return;
			}
			if ( needsQuestion ) {
				if ( '' !== String( row.question == null ? '' : row.question ).trim() ) {
					count++;
				}
				return;
			}
			if ( '' !== String( row.title == null ? '' : row.title ).trim() || '' !== String( row.text == null ? '' : row.text ).trim() ) {
				count++;
			}
		} );
		return count;
	}

	function schemaValidation( schema ) {
		var type = schemaTypeOf( schema );
		var obj = schemaObject( schema );
		var messages = [];
		if ( '' === type ) {
			return messages;
		}
		if ( 'FAQPage' === type ) {
			var faq = obj.faq && 'object' === typeof obj.faq ? obj.faq : {};
			if ( 0 === countRows( faq.questions, true ) ) {
				messages.push( 'At least one question is required for FAQPage. Add questions with the FAQ block or the Classic editor.' );
			}
			return messages;
		}
		if ( 'HowTo' === type ) {
			var howto = obj.howto && 'object' === typeof obj.howto ? obj.howto : {};
			if ( 0 === countRows( howto.steps, false ) ) {
				messages.push( 'At least one step is required for HowTo. Add steps with the How-To block or the Classic editor.' );
			}
			return messages;
		}
		var fields = schemaFieldsOf( schema );
		schemaRequiredFields( type ).forEach( function ( key ) {
			if ( isEmpty( fields[ key ] ) ) {
				messages.push( schemaRequiredMessage( type, key ) );
			}
		} );
		return messages;
	}

	function isValidHttpUrl( value ) {
		if ( '' === String( value == null ? '' : value ).trim() ) {
			return true;
		}
		return /^https?:\/\/\S+\.\S+/.test( String( value ).trim() );
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
			el( 'span', { className: 'dashicons dashicons-' + props.icon, 'aria-hidden': 'true' } ),
			el( 'span', { className: 'rk-meta-tab-label' }, props.label )
		);
	}

	function StateBadge( props ) {
		return el(
			'span',
			{ className: 'rk-meta-badge' + ( props.inherited ? ' rk-is-inherited' : ' rk-is-custom' ) },
			props.inherited ? __( 'Inherited', 'rankkernel' ) : __( 'Custom', 'rankkernel' )
		);
	}

	function Counter( props ) {
		var chars = String( props.text || '' ).length;
		var status = counterStatus( chars, props.limit );
		var pct = Math.min( 100, Math.round( ( chars / props.limit ) * 100 ) );
		return el(
			'div',
			{ className: 'rk-count' },
			el(
				'div',
				{ className: 'rk-count-bar', role: 'presentation' },
				el( 'div', {
					className: 'rk-count-fill ' + ( 'ok' === status ? 'rk-is-ok' : ( 'warn' === status ? 'rk-is-warn' : 'rk-is-over' ) ),
					style: { width: pct + '%' }
				} )
			),
			el(
				'p',
				{
					className: 'rk-meta-count ' + ( 'ok' === status ? 'rk-is-ok' : ( 'warn' === status ? 'rk-is-warn' : 'rk-is-over' ) ),
					role: 'status'
				},
				chars + ' / ' + props.limit + ' ' + __( 'chars', 'rankkernel' ) + ', ' + statusWord( status )
			)
		);
	}

	function FieldReset( props ) {
		return el(
			'button',
			{
				type: 'button',
				className: 'button button-small rk-reset',
				onClick: props.onReset,
				'aria-label': __( 'Reset to template', 'rankkernel' ) + ': ' + props.label
			},
			__( 'Reset to template', 'rankkernel' )
		);
	}

	// Collapsible section, collapsed by default. Uses PanelBody when the
	// components package provides it, native disclosure markup otherwise.
	function Collapsible( props ) {
		var PanelBody = components.PanelBody;
		if ( PanelBody ) {
			return el( PanelBody, { title: props.title, initialOpen: false }, el( 'div', { className: 'rk-collapsible-body' }, props.children ) );
		}
		return el(
			'details',
			{ className: 'rk-collapsible' },
			el( 'summary', { className: 'rk-collapsible-summary' }, props.title ),
			el( 'div', { className: 'rk-collapsible-body' }, props.children )
		);
	}

	function TokenPicker( props ) {
		var tokens = Object.keys( tokenLabels );
		var openState = useState( false );
		var open = openState[ 0 ];
		var setOpen = openState[ 1 ];
		if ( ! tokens.length ) {
			return el( 'p', { className: 'description' }, __( 'No tokens available for this post type.', 'rankkernel' ) );
		}
		return el(
			'div',
			{ className: 'rk-meta-tokens' },
			el(
				'button',
				{
					type: 'button',
					className: 'button button-small',
					'aria-expanded': open ? 'true' : 'false',
					'aria-controls': props.listId,
					onClick: function () { setOpen( ! open ); }
				},
				__( 'Insert token', 'rankkernel' )
			),
			open ? el(
				'div',
				{ className: 'rk-meta-token-list', role: 'group', id: props.listId, 'aria-label': __( 'Insert token', 'rankkernel' ) },
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
			) : null
		);
	}

	function siteMark() {
		var name = String( cfg.siteName || cfg.siteUrl || '' );
		if ( cfg.siteIconUrl ) {
			return el( 'img', { className: 'rk-serp-mark', src: cfg.siteIconUrl, alt: '' } );
		}
		return el( 'span', { className: 'rk-serp-mark rk-serp-letter', 'aria-hidden': 'true' }, name ? name.charAt( 0 ).toUpperCase() : 'R' );
	}

	function SerpPreview( props ) {
		var titleEff = effectiveValue( props.title, 'title' );
		var descEff = effectiveValue( props.description, 'description' );
		return el(
			'div',
			{ className: 'rk-meta-serp rk-serp' + ( 'mobile' === props.device ? ' rk-is-mobile' : '' ) },
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
			el(
				'div',
				{ className: 'rk-serp-row' },
				siteMark(),
				el(
					'div',
					{ className: 'rk-serp-id' },
					el( 'p', { className: 'rk-meta-serp-site' }, cfg.siteName || cfg.siteUrl || '' ),
					el( 'p', { className: 'rk-meta-serp-url' }, shortUrl( cfg.permalink || cfg.homeUrl || '' ) )
				)
			),
			el( 'p', { className: 'rk-meta-serp-title' }, titleEff.text || __( 'Untitled', 'rankkernel' ) ),
			el( 'p', { className: 'rk-meta-serp-desc' }, descEff.text || '' ),
			el( 'p', { className: 'description' }, __( 'Preview is approximate, not exact search rendering.', 'rankkernel' ) )
		);
	}

	function SocialPreview( props ) {
		var titleEff = effectiveValue( props.title, 'title' );
		var descEff = effectiveValue( props.description, 'description' );
		var twTitle = isEmpty( props.meta.twitter.title ) ? '' : props.meta.twitter.title;
		var ogTitle = isEmpty( props.meta.og.title ) ? '' : props.meta.og.title;
		var title = twTitle || ogTitle || titleEff.text;
		var twDesc = isEmpty( props.meta.twitter.description ) ? '' : props.meta.twitter.description;
		var ogDesc = isEmpty( props.meta.og.description ) ? '' : props.meta.og.description;
		var desc = twDesc || ogDesc || descEff.text;
		var image = ! isEmpty( props.meta.twitter.image ) ? props.meta.twitter.image : ( ! isEmpty( props.meta.og.image ) ? props.meta.og.image : String( defaults.ogImage || '' ) );
		var card = 'summary' === props.meta.twitter.card ? 'summary' : 'summary_large_image';
		return el(
			'div',
			{ className: 'rk-meta-social rk-social' + ( 'summary' === card ? ' rk-is-compact' : '' ) },
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

	function RadioPair( props ) {
		return el(
			'fieldset',
			{ className: 'rk-meta-field rk-radio-pair' },
			el( 'legend', null, props.legend ),
			props.options.map( function ( option ) {
				var id = props.name + '-' + option.value;
				return el(
					'label',
					{ key: option.value, className: 'rk-radio', htmlFor: id },
					el( 'input', {
						type: 'radio',
						id: id,
						name: props.name,
						value: option.value,
						checked: props.value === option.value,
						onChange: function () { props.onChange( option.value ); }
					} ),
					option.label
				);
			} ),
			props.hint ? el( 'p', { className: 'description' }, props.hint ) : null
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

	function downloadJson( filename, data ) {
		try {
			var blob = new Blob( [ JSON.stringify( data, null, 2 ) ], { type: 'application/json' } );
			var url = window.URL.createObjectURL( blob );
			var link = document.createElement( 'a' );
			link.href = url;
			link.download = filename;
			document.body.appendChild( link );
			link.click();
			document.body.removeChild( link );
			window.URL.revokeObjectURL( url );
		} catch ( e ) {
			return;
		}
	}

	function RankKernelSidebar() {
		var postId = useSelect( function ( select ) {
			try {
				var editor = select( 'core/editor' );
				if ( editor.getCurrentPostId ) {
					return editor.getCurrentPostId();
				}
			} catch ( e ) {
				return 0;
			}
			return 0;
		}, [] ) || cfg.postId || 0;

		// Remount per post so local drafts never leak across posts.
		return el( SidebarBody, { key: String( postId ) } );
	}

	function SidebarBody() {
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
		var postType = useSelect( function ( select ) {
			try {
				var editor = select( 'core/editor' );
				if ( editor.getEditedPostAttribute ) {
					return editor.getEditedPostAttribute( 'type' ) || '';
				}
			} catch ( e ) {
				return '';
			}
			return '';
		}, [] );
		var editPost = useDispatch( 'core/editor' ).editPost;
		var meta = withMeta( stored );

		// Local drafts keep typing instant; the store write trails by ~150ms.
		var draftsState = useState( {} );
		var drafts = draftsState[ 0 ];
		var setDrafts = draftsState[ 1 ];
		var errorsState = useState( {} );
		var errors = errorsState[ 0 ];
		var setErrors = errorsState[ 1 ];
		var timers = useRef ? useRef( {} ).current : {};

		function pushValue( path, value ) {
			var next = withMeta( meta );
			if ( 'title' === path || 'description' === path ) {
				next[ path ] = value;
			} else if ( 'canonical' === path ) {
				// Invalid input is never written; the error notice stays
				// until the value validates or is cleared.
				if ( ! isValidHttpUrl( value ) ) {
					return;
				}
				next[ path ] = value;
			} else if ( 0 === path.indexOf( 'robots.' ) ) {
				next.robots[ path.slice( 7 ) ] = value;
			} else if ( 0 === path.indexOf( 'og.' ) ) {
				next.og[ path.slice( 3 ) ] = value;
			} else if ( 0 === path.indexOf( 'twitter.' ) ) {
				next.twitter[ path.slice( 8 ) ] = value;
			} else if ( 0 === path.indexOf( 'schema.fields.' ) ) {
				var obj = schemaObject( next.schema );
				var fields = {};
				var current = schemaFieldsOf( next.schema );
				Object.keys( current ).forEach( function ( key ) {
					fields[ key ] = current[ key ];
				} );
				fields[ path.slice( 14 ) ] = value;
				next.schema = {};
				Object.keys( obj ).forEach( function ( key ) {
					next.schema[ key ] = obj[ key ];
				} );
				next.schema.fields = fields;
			}
			var payload = {};
			payload[ META_KEY ] = next;
			editPost( { meta: payload } );
		}

		function display( path, raw ) {
			return drafts[ path ] !== undefined ? drafts[ path ] : raw;
		}

		function setDraft( path, value ) {
			var next = {};
			Object.keys( drafts ).forEach( function ( key ) {
				next[ key ] = drafts[ key ];
			} );
			next[ path ] = value;
			setDrafts( next );
			if ( timers[ path ] ) {
				clearTimeout( timers[ path ] );
			}
			timers[ path ] = setTimeout( function () {
				pushValue( path, value );
			}, DEBOUNCE_MS );
		}

		function clearDraft( path ) {
			if ( timers[ path ] ) {
				clearTimeout( timers[ path ] );
			}
			if ( drafts[ path ] !== undefined ) {
				var next = {};
				Object.keys( drafts ).forEach( function ( key ) {
					if ( key !== path ) {
						next[ key ] = drafts[ key ];
					}
				} );
				setDrafts( next );
			}
		}

		function setError( path, message ) {
			var next = {};
			Object.keys( errors ).forEach( function ( key ) {
				next[ key ] = errors[ key ];
			} );
			if ( message ) {
				next[ path ] = message;
			} else {
				delete next[ path ];
			}
			setErrors( next );
		}

		function resetField( path ) {
			clearDraft( path );
			setError( path, '' );
			pushValue( path, '' );
		}

		function appendToken( path, token ) {
			var raw = display( path, pathValue( path ) );
			setDraft( path, String( raw || '' ) + token );
		}

		function pathValue( path ) {
			if ( 'title' === path || 'description' === path || 'canonical' === path ) {
				return meta[ path ];
			}
			if ( 0 === path.indexOf( 'robots.' ) ) {
				return meta.robots[ path.slice( 7 ) ];
			}
			if ( 0 === path.indexOf( 'og.' ) ) {
				return meta.og[ path.slice( 3 ) ];
			}
			if ( 0 === path.indexOf( 'twitter.' ) ) {
				return meta.twitter[ path.slice( 8 ) ];
			}
			if ( 0 === path.indexOf( 'schema.fields.' ) ) {
				return schemaFieldsOf( meta.schema )[ path.slice( 14 ) ] || '';
			}
			return '';
		}

		function saveSchema( nextSchema ) {
			var next = withMeta( meta );
			next.schema = nextSchema;
			var payload = {};
			payload[ META_KEY ] = next;
			editPost( { meta: payload } );
		}

		function setSchemaKey( key, value ) {
			var obj = schemaObject( meta.schema );
			var next = {};
			Object.keys( obj ).forEach( function ( name ) {
				next[ name ] = obj[ name ];
			} );
			next[ key ] = value;
			saveSchema( next );
		}

		var TextControl = components.TextControl;
		var TextareaControl = components.TextareaControl;
		var SelectControl = components.SelectControl;
		var CheckboxControl = components.CheckboxControl;

		function tokenGroup( path, listId ) {
			return el( TokenPicker, { listId: listId, onInsert: function ( token ) { appendToken( path, token ); } } );
		}

		// Sidebar text field: label, draft-backed input, inherited/custom
		// badge, char counter with thin progress bar, token insert and a
		// reset that clears only this field.
		function textRow( opts ) {
			var path = opts.path;
			var value = display( path, pathValue( path ) );
			var templateKey = opts.template || null;
			var eff = templateKey ? effectiveValue( value, templateKey ) : null;
			var inherited = isEmpty( value );
			var error = errors[ path ] || '';
			var inputId = opts.id;
			var Control = opts.textarea ? TextareaControl : TextControl;
			if ( ! Control ) {
				return null;
			}
			var controlProps = {
				id: inputId,
				label: opts.label,
				value: value,
				placeholder: eff && eff.inherited ? eff.text : '',
				help: opts.help || '',
				onChange: function ( next ) {
					setError( path, '' );
					setDraft( path, next );
				}
			};
			if ( opts.number ) {
				controlProps.type = 'number';
			}
			if ( opts.rows ) {
				controlProps.rows = opts.rows;
			}
			return el(
				'div',
				{ className: 'rk-meta-field' },
				eff ? el(
					'div',
					{ className: 'rk-meta-field-head' },
					el( StateBadge, { inherited: inherited } )
				) : null,
				el( Control, controlProps ),
				error ? el( 'p', { className: 'rk-error', role: 'alert' }, error ) : null,
				opts.limit ? el( Counter, { text: eff ? eff.text : value, limit: opts.limit } ) : null,
				opts.tokenizable ? tokenGroup( path, opts.id + '-tokens' ) : null,
				opts.resettable ? el( FieldReset, {
					label: opts.label,
					onReset: function () { resetField( path ); }
				} ) : null
			);
		}

		// Canonical flushes through URL validation: invalid input shows an
		// error and is never written to the store.
		if ( useEffect ) {
			useEffect( function () {
				var pending = drafts.canonical;
				if ( pending === undefined ) {
					return;
				}
				if ( '' === String( pending ).trim() ) {
					setError( 'canonical', '' );
					return;
				}
				if ( ! isValidHttpUrl( pending ) ) {
					setError( 'canonical', __( 'Enter a full URL starting with http:// or https://. Invalid input is not saved.', 'rankkernel' ) );
					if ( timers.canonical ) {
						clearTimeout( timers.canonical );
					}
					return;
				}
				setError( 'canonical', '' );
			}, [ drafts.canonical ] );
		}

		function canonicalRow() {
			var value = display( 'canonical', meta.canonical );
			var inherited = isEmpty( value );
			var error = errors.canonical || '';
			if ( ! TextControl ) {
				return null;
			}
			return el(
				'div',
				{ className: 'rk-meta-field' },
				el(
					'div',
					{ className: 'rk-meta-field-head' },
					el( StateBadge, { inherited: inherited } )
				),
				el( TextControl, {
					id: 'rk-canonical',
					label: __( 'Canonical URL', 'rankkernel' ),
					value: value,
					placeholder: cfg.permalink || cfg.homeUrl || '',
					help: __( 'Leave blank to use the generated canonical. A full URL is required when set.', 'rankkernel' ),
					onChange: function ( next ) { setDraft( 'canonical', next ); }
				} ),
				error ? el( 'p', { className: 'rk-error', role: 'alert' }, error ) : null,
				inherited
					? el( 'p', { className: 'description' }, __( 'Using the generated canonical.', 'rankkernel' ) )
					: el( 'p', { className: 'description' }, __( 'Custom canonical set.', 'rankkernel' ) )
			);
		}

		function imageRow( group, label, selectLabel ) {
			var current = meta[ group ];
			var prefix = 'rk-' + group + '-image';
			return el(
				'div',
				{ className: 'rk-meta-field' },
				el( 'span', { className: 'rk-meta-field-label', id: prefix + '-label' }, label ),
				current.image ? el( 'img', { className: 'rk-meta-thumb', src: current.image, alt: '' } ) : null,
				el(
					'div',
					{ className: 'rk-meta-row-actions', role: 'group', 'aria-labelledby': prefix + '-label' },
					el( 'button', {
						type: 'button',
						className: 'button button-small',
						'aria-label': ( current.image ? __( 'Change image', 'rankkernel' ) : selectLabel ) + ': ' + label,
						onClick: function () {
							pickImage( function ( src, id ) {
								var next = withMeta( meta );
								next[ group ].image = src;
								next[ group ].image_id = id;
								var payload = {};
								payload[ META_KEY ] = next;
								editPost( { meta: payload } );
							} );
						}
					}, current.image ? __( 'Change image', 'rankkernel' ) : selectLabel ),
					current.image ? el( 'button', {
						type: 'button',
						className: 'button button-small',
						'aria-label': __( 'Remove image', 'rankkernel' ) + ': ' + label,
						onClick: function () {
							var next = withMeta( meta );
							next[ group ].image = '';
							next[ group ].image_id = 0;
							var payload = {};
							payload[ META_KEY ] = next;
							editPost( { meta: payload } );
						}
					}, __( 'Remove', 'rankkernel' ) ) : null
				)
			);
		}

		function generalPanel() {
			var titleValue = display( 'title', meta.title );
			var descValue = display( 'description', meta.description );
			return el(
				'div',
				{ role: 'tabpanel', id: 'rk-panel-general', 'aria-labelledby': 'rk-tab-general', tabIndex: 0 },
				el( SerpPreview, { title: titleValue, description: descValue, device: device, onDevice: setDevice } ),
				textRow( { id: 'rk-title', path: 'title', label: __( 'SEO title', 'rankkernel' ), template: 'title', limit: TITLE_LIMIT, tokenizable: true, resettable: true } ),
				textRow( { id: 'rk-description', path: 'description', label: __( 'Meta description', 'rankkernel' ), template: 'description', limit: DESC_LIMIT, tokenizable: true, resettable: true, textarea: true, rows: 3 } ),
				// Extension seam: future focus keyword and content analysis
				// modules mount here. This container is intentionally empty;
				// it reserves the slot at the end of General without building
				// those modules now.
				el( 'div', { className: 'rk-ext-seam', id: 'rk-ext-analysis' } )
			);
		}

		function advancedPanel() {
			return el(
				'div',
				{ role: 'tabpanel', id: 'rk-panel-advanced', 'aria-labelledby': 'rk-tab-advanced', tabIndex: 0 },
				el( RadioPair, {
					legend: __( 'Search engine visibility', 'rankkernel' ),
					name: 'rk-index',
					value: meta.robots.index ? 'index' : 'noindex',
					hint: __( 'Noindex hides this post from search results.', 'rankkernel' ),
					options: [
						{ value: 'index', label: __( 'Index', 'rankkernel' ) },
						{ value: 'noindex', label: __( 'Noindex', 'rankkernel' ) }
					],
					onChange: function ( next ) {
						var updated = withMeta( meta );
						updated.robots.index = 'index' === next;
						var payload = {};
						payload[ META_KEY ] = updated;
						editPost( { meta: payload } );
					}
				} ),
				el( RadioPair, {
					legend: __( 'Link following', 'rankkernel' ),
					name: 'rk-follow',
					value: meta.robots.follow ? 'follow' : 'nofollow',
					hint: __( 'Nofollow tells crawlers not to follow links on this post.', 'rankkernel' ),
					options: [
						{ value: 'follow', label: __( 'Follow', 'rankkernel' ) },
						{ value: 'nofollow', label: __( 'Nofollow', 'rankkernel' ) }
					],
					onChange: function ( next ) {
						var updated = withMeta( meta );
						updated.robots.follow = 'follow' === next;
						var payload = {};
						payload[ META_KEY ] = updated;
						editPost( { meta: payload } );
					}
				} ),
				el( Collapsible, { title: __( 'Additional robots settings', 'rankkernel' ) },
					el(
						'div',
						null,
						CheckboxControl ? el( CheckboxControl, {
							label: __( 'No archive', 'rankkernel' ),
							checked: meta.robots.noarchive,
							onChange: function ( next ) {
								var updated = withMeta( meta );
								updated.robots.noarchive = true === next;
								var payload = {};
								payload[ META_KEY ] = updated;
								editPost( { meta: payload } );
							}
						} ) : null,
						CheckboxControl ? el( CheckboxControl, {
							label: __( 'No snippet', 'rankkernel' ),
							checked: meta.robots.nosnippet,
							onChange: function ( next ) {
								var updated = withMeta( meta );
								updated.robots.nosnippet = true === next;
								var payload = {};
								payload[ META_KEY ] = updated;
								editPost( { meta: payload } );
							}
						} ) : null,
						CheckboxControl ? el( CheckboxControl, {
							label: __( 'No image index', 'rankkernel' ),
							checked: meta.robots.noimageindex,
							onChange: function ( next ) {
								var updated = withMeta( meta );
								updated.robots.noimageindex = true === next;
								var payload = {};
								payload[ META_KEY ] = updated;
								editPost( { meta: payload } );
							}
						} ) : null,
						textRow( { id: 'rk-max-snippet', path: 'robots.max_snippet', label: __( 'Max snippet', 'rankkernel' ), help: __( 'Max characters for the snippet. Blank means unlimited.', 'rankkernel' ), number: true } ),
						SelectControl ? el( SelectControl, {
							id: 'rk-max-image-preview',
							label: __( 'Max image preview', 'rankkernel' ),
							value: meta.robots.max_image_preview,
							options: [
								{ label: __( 'Default', 'rankkernel' ), value: '' },
								{ label: __( 'None', 'rankkernel' ), value: 'none' },
								{ label: __( 'Standard', 'rankkernel' ), value: 'standard' },
								{ label: __( 'Large', 'rankkernel' ), value: 'large' }
							],
							onChange: function ( next ) {
								var updated = withMeta( meta );
								updated.robots.max_image_preview = next;
								var payload = {};
								payload[ META_KEY ] = updated;
								editPost( { meta: payload } );
							}
						} ) : null,
						textRow( { id: 'rk-max-video-preview', path: 'robots.max_video_preview', label: __( 'Max video preview', 'rankkernel' ), help: __( 'Max seconds for a video preview. Blank means unlimited.', 'rankkernel' ), number: true } )
					)
				),
				canonicalRow()
			);
		}

		function schemaPanel() {
			var schema = meta.schema;
			var selected = schemaTypeOf( schema );
			var disabled = Boolean( schemaObject( schema ).disabled );
			var autoType = 'post' === String( postType ) ? 'BlogPosting' : 'Article';
			var fields = schemaFieldsOf( schema );
			var custom = schemaCustomOf( schema );
			var customText = display( 'schema.customText', JSON.stringify( custom, null, 2 ) );
			var customError = errors[ 'schema.customText' ] || '';
			var messages = schemaValidation( schema );
			var visibleKeys = Object.keys( SCHEMA_FIELD_LABELS ).filter( function ( key ) {
				return schemaFieldVisible( key, selected );
			} );
			return el(
				'div',
				{ role: 'tabpanel', id: 'rk-panel-schema', 'aria-labelledby': 'rk-tab-schema', tabIndex: 0 },
				el(
					'div',
					{ className: 'rk-meta-field' },
					el(
						'p',
						{ className: 'rk-status', role: 'status' },
						disabled ? __( 'Schema output is disabled for this post.', 'rankkernel' ) : __( 'Schema output is enabled for this post.', 'rankkernel' )
					),
					CheckboxControl ? el( CheckboxControl, {
						label: __( 'Disable schema output for this post', 'rankkernel' ),
						checked: disabled,
						onChange: function ( next ) { setSchemaKey( 'disabled', true === next ); }
					} ) : null
				),
				SelectControl ? el(
					'div',
					{ className: 'rk-meta-field' },
					el( SelectControl, {
						id: 'rk-schema-type',
						label: __( 'Schema type', 'rankkernel' ),
						help: '' === selected
							? __( 'Automatic resolves to', 'rankkernel' ) + ' ' + autoType + '.'
							: __( 'Manual type selected. Clear it to return to Automatic.', 'rankkernel' ),
						value: selected,
						options: [ { label: __( 'Automatic', 'rankkernel' ) + ' (' + autoType + ')', value: '' } ].concat( SCHEMA_TYPES.map( function ( row ) {
							return { label: row[ 1 ], value: row[ 0 ] };
						} ) ),
						onChange: function ( next ) { setSchemaKey( 'type', next ); }
					} )
				) : null,
				el( Collapsible, { title: __( 'Manual overrides', 'rankkernel' ) },
					el(
						'div',
						null,
						el( 'p', { className: 'description' }, __( 'Only needed when a value must differ from the post itself. Only fields relevant to the chosen type are shown.', 'rankkernel' ) ),
						visibleKeys.map( function ( key ) {
							return textRow( { id: 'rk-schema-' + key, path: 'schema.fields.' + key, label: SCHEMA_FIELD_LABELS[ key ] } );
						} )
					)
				),
				el( Collapsible, { title: __( 'Advanced', 'rankkernel' ) },
					el(
						'div',
						null,
						TextareaControl ? el( TextareaControl, {
							id: 'rk-schema-custom',
							label: __( 'Custom JSON', 'rankkernel' ),
							help: __( 'Optional, for advanced use. A valid JSON object typed here is added to the schema output as is.', 'rankkernel' ),
							rows: 6,
							value: customText,
							onChange: function ( next ) {
								setDraft( 'schema.customText', next );
								var parsed = null;
								try {
									parsed = JSON.parse( next );
								} catch ( e ) {
									parsed = null;
								}
								if ( parsed && 'object' === typeof parsed && ! Array.isArray( parsed ) ) {
									setError( 'schema.customText', '' );
									setSchemaKey( 'custom', parsed );
								} else {
									setError( 'schema.customText', __( 'Custom JSON must be a valid JSON object. Nothing was saved.', 'rankkernel' ) );
								}
							}
						} ) : null,
						customError ? el( 'p', { className: 'rk-error', role: 'alert' }, customError ) : null,
						messages.length ? el(
							'div',
							{ className: 'rk-notice rk-is-warn', role: 'status' },
							el(
								'ul',
								null,
								messages.map( function ( message, index ) {
									return el( 'li', { key: String( index ) }, message );
								} )
							)
						) : el( 'p', { className: 'rk-notice rk-is-ok', role: 'status' }, '' === selected
							? __( 'Automatic type selected. Choose a type to validate its required fields.', 'rankkernel' )
							: __( 'All required fields are present.', 'rankkernel' ) ),
						el(
							'p',
							null,
							el( 'a', { href: 'https://search.google.com/test/rich-results?url=' + encodeURIComponent( cfg.permalink || cfg.homeUrl || '' ), target: '_blank', rel: 'noopener' }, __( 'Rich Results Test', 'rankkernel' ) ),
							' | ',
							el( 'a', { href: 'https://validator.schema.org/', target: '_blank', rel: 'noopener' }, __( 'Schema Validator', 'rankkernel' ) )
						),
						el(
							'div',
							{ className: 'rk-meta-row-actions' },
							el( 'button', {
								type: 'button',
								className: 'button button-small',
								onClick: function () {
									downloadJson( 'schema.json', schemaObject( schema ) );
								}
							}, __( 'Export JSON', 'rankkernel' ) ),
							el(
								'label',
								{ className: 'rk-import-label', htmlFor: 'rk-schema-import' },
								__( 'Import JSON', 'rankkernel' )
							),
							el( 'input', {
								type: 'file',
								id: 'rk-schema-import',
								accept: '.json,application/json',
								onChange: function ( event ) {
									var file = event.target && event.target.files ? event.target.files[ 0 ] : null;
									if ( ! file ) {
										return;
									}
									var reader = new FileReader();
									reader.onload = function () {
										var parsed = null;
										try {
											parsed = JSON.parse( String( reader.result || '' ) );
										} catch ( e ) {
											parsed = null;
										}
										if ( ! parsed || 'object' !== typeof parsed || Array.isArray( parsed ) ) {
											setError( 'schema.customText', __( 'Import file was invalid, nothing was saved.', 'rankkernel' ) );
											return;
										}
										setError( 'schema.customText', '' );
										clearDraft( 'schema.customText' );
										saveSchema( parsed );
									};
									reader.readAsText( file );
								}
							} )
						),
						el( 'p', { className: 'description' }, __( 'Import replaces the schema settings with the uploaded file.', 'rankkernel' ) )
					)
				)
			);
		}

		function socialPanel() {
			var titleValue = display( 'title', meta.title );
			var descValue = display( 'description', meta.description );
			return el(
				'div',
				{ role: 'tabpanel', id: 'rk-panel-social', 'aria-labelledby': 'rk-tab-social', tabIndex: 0 },
				el( Collapsible, { title: __( 'Open Graph', 'rankkernel' ) },
					el(
						'div',
						null,
						textRow( { id: 'rk-og-title', path: 'og.title', label: __( 'Open Graph title', 'rankkernel' ), template: 'title', tokenizable: true, resettable: true } ),
						textRow( { id: 'rk-og-description', path: 'og.description', label: __( 'Open Graph description', 'rankkernel' ), template: 'description', tokenizable: true, resettable: true, textarea: true, rows: 2 } ),
						imageRow( 'og', __( 'Open Graph image', 'rankkernel' ), __( 'Select image', 'rankkernel' ) ),
						textRow( { id: 'rk-og-type', path: 'og.type', label: __( 'Open Graph type', 'rankkernel' ), help: __( 'Leave blank to inherit (article for posts, website for pages).', 'rankkernel' ) } )
					)
				),
				el( Collapsible, { title: __( 'Twitter', 'rankkernel' ) },
					el(
						'div',
						null,
						SelectControl ? el( SelectControl, {
							id: 'rk-twitter-card',
							label: __( 'Twitter card', 'rankkernel' ),
							value: meta.twitter.card,
							options: [
								{ label: __( 'Summary with large image', 'rankkernel' ), value: 'summary_large_image' },
								{ label: __( 'Summary', 'rankkernel' ), value: 'summary' }
							],
							onChange: function ( next ) {
								var updated = withMeta( meta );
								updated.twitter.card = next;
								var payload = {};
								payload[ META_KEY ] = updated;
								editPost( { meta: payload } );
							}
						} ) : null,
						textRow( { id: 'rk-twitter-title', path: 'twitter.title', label: __( 'Twitter title', 'rankkernel' ), template: 'title', tokenizable: true, resettable: true } ),
						textRow( { id: 'rk-twitter-description', path: 'twitter.description', label: __( 'Twitter description', 'rankkernel' ), template: 'description', tokenizable: true, resettable: true, textarea: true, rows: 2 } ),
						imageRow( 'twitter', __( 'Twitter image', 'rankkernel' ), __( 'Select image', 'rankkernel' ) )
					)
				),
				el( SocialPreview, { meta: meta, title: titleValue, description: descValue } )
			);
		}

		var tabs = [
			{ id: 'general', label: __( 'General', 'rankkernel' ), icon: 'search' },
			{ id: 'advanced', label: __( 'Advanced', 'rankkernel' ), icon: 'admin-generic' },
			{ id: 'schema', label: __( 'Schema', 'rankkernel' ), icon: 'media-code' },
			{ id: 'social', label: __( 'Social', 'rankkernel' ), icon: 'share' }
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
			panel = generalPanel();
		} else if ( 'advanced' === activeTab ) {
			panel = advancedPanel();
		} else if ( 'schema' === activeTab ) {
			panel = schemaPanel();
		} else {
			panel = socialPanel();
		}

		return el(
			'div',
			{ className: 'rk-meta rk-side' },
			el(
				'div',
				{ role: 'tablist', 'aria-label': __( 'SEO settings sections', 'rankkernel' ), className: 'rk-meta-tabs' },
				tabs.map( function ( tab, index ) {
					return el(
						'div',
						{ key: tab.id, onKeyDown: function ( event ) { onTabKey( event, index ); } },
						el( TabButton, {
							id: 'rk-tab-' + tab.id,
							panelId: 'rk-panel-' + tab.id,
							label: tab.label,
							icon: tab.icon,
							selected: activeTab === tab.id,
							onSelect: function () { setActiveTab( tab.id ); }
						} )
					);
				} )
			),
			panel
		);
	}

	// Keep a reference for tests and for future reuse.
	window.rankkernelSeoSidebar = window.rankkernelSeoSidebar || {};
	window.rankkernelSeoSidebar.withMeta = withMeta;
	window.rankkernelSeoSidebar.usesSharedPreview = function () {
		return Boolean( window.rankkernelMetaEditorPreview && window.rankkernelMetaEditorPreview.effectiveValue );
	};

	registerPlugin( 'rankkernel-seo', {
		render: function () {
			return el(
				PluginSidebar,
				{ name: 'rankkernel-seo-sidebar', title: __( 'RankKernel SEO', 'rankkernel' ) },
				el( RankKernelSidebar, null )
			);
		}
	} );
} )();
