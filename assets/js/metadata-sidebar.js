/**
 * RankKernel SEO sidebar (Gutenberg).
 *
 * Plain script, no build step. Uses wp globals directly with
 * wp.element.createElement, no JSX. Reads and writes the post meta key
 * _rankkernel_meta_data through wp.data only. Adds no REST route and
 * queries no Classic metabox DOM: every value flows through the editor
 * data store, so the hook contract test stays empty for this file.
 *
 * Render contract: registerPlugin slug is rankkernel-seo and the
 * PluginSidebar name is rankkernel-seo, so the open identifier is
 * rankkernel-seo/rankkernel-seo. Those two strings must stay identical;
 * renaming either one blanks the sidebar while the store still reports
 * it as open.
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

	if ( ! registerPlugin || ! PluginSidebar ) {
		return;
	}

	var components = window.wp.components || {};
	var __ = window.wp.i18n && window.wp.i18n.__ ? window.wp.i18n.__ : function ( text ) { return text; };

	var SIDEBAR_NAME = 'rankkernel-seo';

	var cfg = window.rankkernelMetaEditor || {};
	var templates = cfg.templates || {};
	var tokenLabels = cfg.tokenLabels || {};
	var limits = cfg.limits || {};
	var defaults = cfg.defaults || {};
	var META_KEY = '_rankkernel_meta_data';
	var TITLE_LIMIT = parseInt( limits.title, 10 ) || 60;
	var DESC_LIMIT = parseInt( limits.description, 10 ) || 160;
	var TITLE_PX = 580;
	var DESC_PX = 920;
	var DEBOUNCE_MS = 150;
	var SOCIAL_MIN_W = 600;
	var SOCIAL_MIN_H = 315;

	var shared = window.rankkernelMetaEditorPreview || {};

	// Last tokenizable field that held focus, so a token insert can hand
	// focus back to the field the user was editing.
	var lastTokenInputId = null;

	function rememberTokenFocus( inputId ) {
		lastTokenInputId = inputId;
	}

	function refocusTokenField( inputId ) {
		var id = inputId || lastTokenInputId;
		if ( ! id || ! document || ! document.getElementById ) {
			return;
		}
		try {
			var node = document.getElementById( id );
			if ( node && node.focus ) {
				node.focus();
			}
		} catch ( e ) {
			return;
		}
	}

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

	var tokenValues = cfg.tokens || null;

	// Single token resolver for the sidebar preview and the modal
	// preview. Both render through SerpPreview and effectiveValue below,
	// so no preview path can show a literal %%token%% string.
	function resolveTokens( text ) {
		if ( shared.resolveTokens ) {
			return shared.resolveTokens( text );
		}
		var str = String( text == null ? '' : text );
		if ( str.indexOf( '%%' ) < 0 ) {
			return str;
		}
		if ( ! tokenValues || 'object' !== typeof tokenValues ) {
			return str.replace( /%%[A-Za-z_]+%%/g, '' );
		}
		return str.replace( /%%([A-Za-z_]+)%%/g, function ( match, name ) {
			var value = tokenValues[ name ];
			return value == null ? '' : String( value );
		} );
	}

	// Effective value: the stored override when set, otherwise the
	// resolved template. Every token resolves through the token map
	// before render; without a map an empty override renders empty,
	// never the raw template.
	function effectiveValue( raw, templateKey ) {
		if ( shared.effectiveValue ) {
			return shared.effectiveValue( raw, templateKey );
		}
		var value = String( raw == null ? '' : raw ).trim();
		if ( '' !== value ) {
			return { text: resolveTokens( String( raw ) ), inherited: false };
		}
		if ( ! tokenValues || 'object' !== typeof tokenValues ) {
			return { text: '', inherited: true };
		}
		return { text: resolveTokens( String( templates[ templateKey ] || '' ) ), inherited: true };
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

	// Approximate rendered pixel width for the length readout. Wide
	// glyphs cost more, narrow glyphs less, the rest a middle weight.
	// A heuristic only, announced next to the character count.
	function estimatePixels( text ) {
		var str = String( text || '' );
		var px = 0;
		var i = 0;
		var ch = '';
		for ( i = 0; i < str.length; i++ ) {
			ch = str.charAt( i );
			if ( 'mwMW@%'.indexOf( ch ) >= 0 ) {
				px += 11;
			} else if ( 'il1t.,:;!|\' '.indexOf( ch ) >= 0 ) {
				px += 4;
			} else {
				px += 8;
			}
		}
		return px;
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

	// Visual grouping for the token picker. Tokens always come from
	// tokenLabels; this map only groups them, anything unlisted lands in
	// Other and nothing here is ever inserted on its own.
	var TOKEN_GROUPS = {
		'%%title%%': 'Post',
		'%%excerpt%%': 'Post',
		'%%category%%': 'Post',
		'%%author%%': 'Post',
		'%%date%%': 'Post',
		'%%page%%': 'Post',
		'%%sitename%%': 'Site',
		'%%sep%%': 'Site'
	};

	function tokenGroupName( token ) {
		return TOKEN_GROUPS[ token ] || 'Other';
	}

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
			focus_keywords: [],
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
		var focus_keywords = Array.isArray( input.focus_keywords ) ? input.focus_keywords : ( typeof input.focus_keywords === 'string' && input.focus_keywords ? input.focus_keywords.split( ',' ).map( function ( s ) { return s.trim(); } ).filter( Boolean ) : [] );
		// The schema subtree passes through untouched: fresh rows hold an
		// empty list, later saves normalize it to the object shape. Readers
		// stay defensive and never reshape it here.
		var schema = input.schema && 'object' === typeof input.schema ? input.schema : base.schema;
		// sanitize() reseeds omitted keys from defaults and runs on the
		// submitted value only, so flags must be sent or it resets them.
		var flags = input.flags && 'object' === typeof input.flags ? input.flags : {};
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
			focus_keywords: focus_keywords,
			schema: schema,
			flags: {
				pillar: true === flags.pillar,
				cornerstone: true === flags.cornerstone,
				breadcrumb_title: flags.breadcrumb_title == null ? '' : String( flags.breadcrumb_title )
			}
		};
	}

	// A robots budget of '' would be rejected by the REST schema before the
	// server sanitizer runs, so an empty or non numeric value must become
	// null at the point of assembly. Numbers stay numbers.
	function robotsIntOrNull( value ) {
		if ( value === null || value === undefined ) {
			return null;
		}
		var text = String( value ).trim();
		if ( '' === text || ! /^-?\d+$/.test( text ) ) {
			return null;
		}
		return parseInt( text, 10 );
	}

	// The empty option of the max-image-preview select maps to null, never
	// an empty string, and an unknown value falls back to null.
	function robotsPreviewOrNull( value ) {
		if ( value === null || value === undefined ) {
			return null;
		}
		var text = String( value ).trim();
		if ( '' === text ) {
			return null;
		}
		return [ 'none', 'standard', 'large' ].indexOf( text ) >= 0 ? text : null;
	}

	// Convert the UI shaped meta object into the REST payload shape. The
	// three max_* robots budgets are normalized here so no editor write can
	// hard block a save with an empty or malformed string.
	function toRestMeta( meta ) {
		var next = withMeta( meta );
		next.robots.max_snippet = robotsIntOrNull( next.robots.max_snippet );
		next.robots.max_video_preview = robotsIntOrNull( next.robots.max_video_preview );
		next.robots.max_image_preview = robotsPreviewOrNull( next.robots.max_image_preview );
		next.schema = schemaObject( next.schema );
		return next;
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

	// Tab button: icon only while inactive, icon plus text label while
	// active. The accessible name never depends on the visible label.
	function TabButton( props ) {
		return el(
			'button',
			{
				type: 'button',
				role: 'tab',
				id: props.id,
				'aria-selected': props.selected ? 'true' : 'false',
				'aria-controls': props.panelId,
				'aria-label': props.label,
				title: props.selected ? undefined : props.label,
				tabIndex: props.selected ? 0 : -1,
				className: 'rk-meta-tab' + ( props.selected ? ' is-active' : '' ),
				onClick: props.onSelect
			},
			el( 'span', { className: 'dashicons dashicons-' + props.icon, 'aria-hidden': 'true' } ),
			props.selected ? el( 'span', { className: 'rk-meta-tab-label', 'aria-hidden': 'true' }, props.label ) : null
		);
	}

	// State badge: text plus a shape marker, never colour alone.
	function StateBadge( props ) {
		var state = props.invalid ? 'invalid' : ( props.inherited ? 'inherited' : 'custom' );
		var text = props.invalid ? __( 'Invalid', 'rankkernel' ) : ( props.inherited ? __( 'Inherited', 'rankkernel' ) : __( 'Custom', 'rankkernel' ) );
		return el(
			'span',
			{
				className: 'rk-meta-badge' + ( props.invalid ? ' rk-is-invalid' : ( props.inherited ? ' rk-is-inherited' : ' rk-is-custom' ) ),
				'data-rk-state': state
			},
			text,
			props.modified ? el( 'span', { className: 'screen-reader-text' }, __( 'Modified from template', 'rankkernel' ) ) : null
		);
	}

	// Thin progress meter under the control.
	function FieldMeter( props ) {
		var status = counterStatus( String( props.text || '' ).length, props.limit );
		var chars = String( props.text || '' ).length;
		var pct = props.limit > 0 ? Math.min( 100, Math.round( ( chars / props.limit ) * 100 ) ) : 0;
		var tone = 'ok' === status ? 'rk-is-ok' : ( 'warn' === status ? 'rk-is-warn' : 'rk-is-over' );
		return el(
			'div',
			{ className: 'rk-count-bar', role: 'presentation' },
			el( 'div', {
				className: 'rk-count-fill ' + tone,
				'data-rk-state': status,
				style: { width: pct + '%' }
			} )
		);
	}

	function FieldReset( props ) {
		var disabled = true === props.disabled;
		return el(
			'button',
			{
				type: 'button',
				className: 'button button-small rk-reset rk-field-reset' + ( disabled ? ' is-disabled' : '' ),
				disabled: disabled,
				'aria-disabled': disabled ? 'true' : 'false',
				'aria-label': ( disabled
					? __( 'Using template value', 'rankkernel' )
					: __( 'Remove post-level override', 'rankkernel' ) ) + ': ' + props.label,
				title: disabled ? __( 'Using template value', 'rankkernel' ) : __( 'Remove post-level override', 'rankkernel' ),
				onClick: props.onReset
			},
			__( 'Reset', 'rankkernel' )
		);
	}

	// Collapsible section, collapsed by default. Uses PanelBody when the
	// components package provides it, otherwise a button disclosure with
	// aria-expanded so the state is always announced.
	function Collapsible( props ) {
		var PanelBody = components.PanelBody;
		if ( PanelBody ) {
			return el( PanelBody, { title: props.title, initialOpen: false }, el( 'div', { className: 'rk-collapsible-body' }, props.children ) );
		}
		var openState = useState( false );
		var open = openState[ 0 ];
		var setOpen = openState[ 1 ];
		var bodyId = props.bodyId || ( 'rk-collapsible-' + Math.random().toString( 36 ).slice( 2, 8 ) );
		return el(
			'div',
			{ className: 'rk-collapsible' },
			el(
				'button',
				{
					type: 'button',
					className: 'rk-collapsible-summary',
					'aria-expanded': open ? 'true' : 'false',
					'aria-controls': bodyId,
					onClick: function () { setOpen( ! open ); }
				},
				el( 'span', { className: open ? 'dashicons dashicons-arrow-down-alt2' : 'dashicons dashicons-arrow-right-alt2', 'aria-hidden': 'true' } ),
				props.title
			),
			open ? el( 'div', { className: 'rk-collapsible-body', id: bodyId }, props.children ) : null
		);
	}

	// Token picker: searchable, keyboard navigable, visually grouped, kept
	// inside the sidebar. Only lists tokens present in tokenLabels and
	// never invents a token value.
	function TokenPicker( props ) {
		var tokens = Object.keys( tokenLabels );
		var openState = useState( false );
		var open = openState[ 0 ];
		var setOpen = openState[ 1 ];
		var queryState = useState( '' );
		var query = queryState[ 0 ];
		var setQuery = queryState[ 1 ];
		var activeState = useState( 0 );
		var activeIndex = activeState[ 0 ];
		var setActiveIndex = activeState[ 1 ];
		var rootRef = useRef( null );

		var q = String( query || '' ).toLowerCase();
		var filtered = tokens.filter( function ( token ) {
			if ( '' === q ) {
				return true;
			}
			var label = String( tokenLabels[ token ] || '' ).toLowerCase();
			return token.toLowerCase().indexOf( q ) >= 0 || label.indexOf( q ) >= 0;
		} );

		var groups = {};
		var groupOrder = [];
		filtered.forEach( function ( token ) {
			var name = tokenGroupName( token );
			if ( ! groups[ name ] ) {
				groups[ name ] = [];
				groupOrder.push( name );
			}
			groups[ name ].push( token );
		} );

		function closeAndRefocus() {
			setOpen( false );
			setQuery( '' );
			setActiveIndex( 0 );
			refocusTokenField( props.inputId );
		}

		function insert( token ) {
			props.onInsert( token );
			closeAndRefocus();
		}

		useEffect( function () {
			if ( ! open ) {
				return undefined;
			}
			function onDown( event ) {
				if ( rootRef.current && rootRef.current.contains && rootRef.current.contains( event.target ) ) {
					return;
				}
				setOpen( false );
				setQuery( '' );
				setActiveIndex( 0 );
			}
			function onKey( event ) {
				if ( 'Escape' === event.key ) {
					setOpen( false );
					setQuery( '' );
					setActiveIndex( 0 );
				}
			}
			document.addEventListener( 'mousedown', onDown );
			document.addEventListener( 'keydown', onKey );
			return function () {
				document.removeEventListener( 'mousedown', onDown );
				document.removeEventListener( 'keydown', onKey );
			};
		}, [ open ] );

		if ( ! tokens.length ) {
			return el( 'p', { className: 'description' }, __( 'No tokens available for this post type.', 'rankkernel' ) );
		}

		function onListKey( event ) {
			if ( 'ArrowDown' === event.key ) {
				event.preventDefault();
				setActiveIndex( filtered.length ? ( activeIndex + 1 ) % filtered.length : 0 );
			} else if ( 'ArrowUp' === event.key ) {
				event.preventDefault();
				setActiveIndex( filtered.length ? ( activeIndex - 1 + filtered.length ) % filtered.length : 0 );
			} else if ( 'Enter' === event.key ) {
				event.preventDefault();
				if ( filtered[ activeIndex ] ) {
					insert( filtered[ activeIndex ] );
				}
			} else if ( 'Escape' === event.key ) {
				event.preventDefault();
				closeAndRefocus();
			}
		}

		var flatIndex = -1;

		return el(
			'div',
			{ className: 'rk-meta-tokens rk-field-tokens', ref: rootRef },
			el(
				'button',
				{
					type: 'button',
					className: 'button button-small rk-token-toggle',
					'aria-expanded': open ? 'true' : 'false',
					'aria-controls': props.listId,
					'aria-label': __( 'Insert token', 'rankkernel' ) + ': ' + props.fieldLabel,
					title: __( 'Insert token', 'rankkernel' ),
					onClick: function () {
						if ( open ) {
							closeAndRefocus();
							return;
						}
						setQuery( '' );
						setActiveIndex( 0 );
						setOpen( true );
					}
				},
				el( 'span', { className: 'dashicons dashicons-plus-alt', 'aria-hidden': 'true' } ),
				el( 'span', null, __( 'Token', 'rankkernel' ) )
			),
			open ? el(
				'div',
				{ className: 'rk-token-pop', role: 'group', id: props.listId, 'aria-label': __( 'Insert token', 'rankkernel' ) },
				el( 'input', {
					type: 'search',
					className: 'rk-token-search',
					placeholder: __( 'Search tokens…', 'rankkernel' ),
					'aria-label': __( 'Search tokens', 'rankkernel' ),
					value: query,
					onChange: function ( next ) {
						setQuery( next );
						setActiveIndex( 0 );
					},
					onKeyDown: onListKey
				} ),
				el( 'p', { className: 'description rk-token-help' }, __( 'Tokens insert at the cursor and update the preview.', 'rankkernel' ) ),
				filtered.length ? groupOrder.map( function ( name ) {
					return el(
						'div',
						{ className: 'rk-token-group', key: name },
						el( 'p', { className: 'rk-token-group-label', 'aria-hidden': 'true' }, name ),
						groups[ name ].map( function ( token ) {
							flatIndex++;
							var mine = flatIndex;
							var readable = tokenLabels[ token ] || token;
							return el(
								'button',
								{
									key: token,
									type: 'button',
									className: 'button button-small rk-token-row' + ( mine === activeIndex ? ' rk-token-active' : '' ),
									'aria-current': mine === activeIndex ? 'true' : 'false',
									title: token + ' ' + readable,
									'aria-label': __( 'Insert token', 'rankkernel' ) + ' ' + token + ', ' + readable,
									onMouseEnter: function () { setActiveIndex( mine ); },
									onFocus: function () { setActiveIndex( mine ); },
									onKeyDown: onListKey,
									onClick: function () {
										insert( token );
									}
								},
								el( 'span', { className: 'rk-token-name' }, token ),
								el( 'span', { className: 'rk-token-desc', 'aria-hidden': 'true' }, readable )
							);
						} )
					);
				} ) : el( 'p', { className: 'description' }, __( 'No tokens match your search.', 'rankkernel' ) ),
				el( 'button', {
					type: 'button',
					className: 'button button-small rk-token-close',
					'aria-label': __( 'Close token picker', 'rankkernel' ),
					onClick: closeAndRefocus
				}, __( 'Close', 'rankkernel' ) )
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

	function DeviceSwitch( props ) {
		var desktopRef = useRef( null );
		var mobileRef = useRef( null );
		function focusDevice( device ) {
			var node = 'mobile' === device ? mobileRef.current : desktopRef.current;
			if ( node && node.focus ) {
				node.focus();
			}
		}
		function onSwitchKey( event ) {
			if ( 'ArrowRight' === event.key || 'ArrowLeft' === event.key ) {
				event.preventDefault();
				var next = 'mobile' === props.device ? 'desktop' : 'mobile';
				props.onDevice( next );
				focusDevice( next );
			}
		}
		return el(
			'div',
			{ className: 'rk-meta-serp-tools', role: 'group', 'aria-label': props.label || __( 'Preview width', 'rankkernel' ) },
			el( 'button', {
				type: 'button',
				ref: desktopRef,
				className: 'button button-small' + ( 'desktop' === props.device ? ' is-active' : '' ),
				'aria-pressed': 'desktop' === props.device ? 'true' : 'false',
				tabIndex: 'desktop' === props.device ? 0 : -1,
				onClick: function () { props.onDevice( 'desktop' ); },
				onKeyDown: onSwitchKey
			}, __( 'Desktop', 'rankkernel' ) ),
			el( 'button', {
				type: 'button',
				ref: mobileRef,
				className: 'button button-small' + ( 'mobile' === props.device ? ' is-active' : '' ),
				'aria-pressed': 'mobile' === props.device ? 'true' : 'false',
				tabIndex: 'mobile' === props.device ? 0 : -1,
				onClick: function () { props.onDevice( 'mobile' ); },
				onKeyDown: onSwitchKey
			}, __( 'Mobile', 'rankkernel' ) )
		);
	}

	// One coherent search preview: header row with the title and the
	// device switch, the preview surface, then a footer row with the
	// Edit Snippet action. Compact for a roughly 280px sidebar.
	function SerpPreview( props ) {
		var titleEff = effectiveValue( props.title, 'title' );
		var descEff = effectiveValue( props.description, 'description' );
		var isMobile = 'mobile' === props.device;
		return el(
			'section',
			{ className: 'rk-serp-block', 'aria-labelledby': props.headingId },
			el(
				'div',
				{ className: 'rk-serp-head' },
				el( 'h3', { className: 'rk-serp-heading', id: props.headingId }, __( 'Search Preview', 'rankkernel' ) ),
				el( DeviceSwitch, { device: props.device, onDevice: props.onDevice } )
			),
			el(
				'div',
				{ className: 'rk-meta-serp rk-serp' + ( isMobile ? ' rk-is-mobile' : ' rk-is-desktop' ) },
				el(
					'div',
					{ className: 'rk-serp-row' },
					siteMark(),
					el(
						'div',
						{ className: 'rk-serp-id' },
						el( 'p', { className: 'rk-meta-serp-site' }, cfg.siteName || cfg.siteUrl || '' ),
						el( 'p', { className: 'rk-meta-serp-url' }, shortUrl( props.url || cfg.permalink || cfg.homeUrl || '' ) )
					)
				),
				el( 'p', { className: 'rk-meta-serp-title' }, titleEff.text || __( 'Untitled', 'rankkernel' ) ),
				el( 'p', { className: 'rk-meta-serp-desc' }, descEff.text || '' ),
				props.noindex ? el( 'p', { className: 'rk-serp-noindex', role: 'status' }, __( 'Noindex is on: this post is hidden from search results.', 'rankkernel' ) ) : null
			),
			el( 'p', { className: 'description rk-serp-note' }, __( 'Preview is approximate, not exact search rendering.', 'rankkernel' ) ),
			props.onEdit ? el(
				'div',
				{ className: 'rk-serp-foot' },
				el(
					'button',
					{
						type: 'button',
						className: 'button button-secondary rk-preview-open',
						'aria-label': __( 'Edit Snippet', 'rankkernel' ),
						onClick: props.onEdit
					},
					el( 'span', { className: 'dashicons dashicons-edit', 'aria-hidden': 'true' } ),
					el( 'span', null, __( 'Edit Snippet', 'rankkernel' ) )
				)
			) : null
		);
	}

	// Social preview card, driven by the active network the caller picks.
	// Each platform keeps its own chrome: Facebook shows a page source
	// line above the image, title, description and URL; Twitter shows a
	// profile row plus the card size, then image, title, description.
	function SocialPreview( props ) {
		var titleEff = effectiveValue( props.title, 'title' );
		var descEff = effectiveValue( props.description, 'description' );
		var isTwitter = 'twitter' === props.network;
		var primary = isTwitter ? props.meta.twitter : props.meta.og;
		var fallback = isTwitter ? props.meta.og : props.meta.twitter;
		var title = ! isEmpty( primary.title ) ? primary.title : ( ! isEmpty( fallback.title ) ? fallback.title : titleEff.text );
		var desc = ! isEmpty( primary.description ) ? primary.description : ( ! isEmpty( fallback.description ) ? fallback.description : descEff.text );
		var image = ! isEmpty( primary.image ) ? primary.image : ( ! isEmpty( fallback.image ) ? fallback.image : String( defaults.ogImage || '' ) );
		var card = 'summary' === props.meta.twitter.card ? 'summary' : 'summary_large_image';
		var compact = isTwitter && 'summary' === card;
		var site = cfg.siteName || cfg.siteUrl || '';
		var link = shortUrl( props.url || cfg.permalink || cfg.homeUrl || '' );
		if ( isTwitter ) {
			return el(
				'div',
				{ className: 'rk-meta-social rk-social rk-social-twitter' + ( compact ? ' rk-is-compact' : '' ) },
				el(
					'div',
					{ className: 'rk-social-profile' },
					siteMark(),
					el(
						'div',
						{ className: 'rk-social-id' },
						el( 'p', { className: 'rk-social-name' }, site || __( 'Site', 'rankkernel' ) ),
						el( 'p', { className: 'description rk-social-card' }, 'summary' === card ? __( 'Small image card', 'rankkernel' ) : __( 'Large image card', 'rankkernel' ) )
					)
				),
				image ? el( 'img', { className: 'rk-meta-social-image', src: image, alt: '' } ) : el( 'p', { className: 'description rk-social-noimage' }, __( 'No image', 'rankkernel' ) ),
				el(
					'div',
					{ className: 'rk-meta-social-body' },
					el( 'p', { className: 'rk-meta-social-title' }, title || __( 'Untitled', 'rankkernel' ) ),
					el( 'p', { className: 'rk-meta-social-desc' }, desc || '' ),
					el( 'p', { className: 'rk-meta-social-site' }, link )
				)
			);
		}
		return el(
			'div',
			{ className: 'rk-meta-social rk-social rk-social-facebook' },
			el( 'p', { className: 'rk-social-source' }, site || __( 'Site', 'rankkernel' ) ),
			image ? el( 'img', { className: 'rk-meta-social-image', src: image, alt: '' } ) : el( 'p', { className: 'description rk-social-noimage' }, __( 'No image', 'rankkernel' ) ),
			el(
				'div',
				{ className: 'rk-meta-social-body' },
				el( 'p', { className: 'rk-meta-social-title' }, title || __( 'Untitled', 'rankkernel' ) ),
				el( 'p', { className: 'rk-meta-social-desc' }, desc || '' ),
				el( 'p', { className: 'rk-meta-social-site' }, link )
			)
		);
	}

	// Social image control: thumbnail, select/replace/remove through
	// wp.media, checking state while dimensions resolve, a too-small
	// warning under the minimum, and an invalid warning when the file
	// cannot load. Guidance carries the recommended and minimum sizes.
	function SocialImageControl( props ) {
		var Spinner = components.Spinner;
		var Notice = components.Notice;
		var current = props.current || { image: '', image_id: 0 };
		var checkState = useState( { status: 'idle', width: 0, height: 0 } );
		var check = checkState[ 0 ];
		var setCheck = checkState[ 1 ];
		var failedState = useState( '' );
		var failed = failedState[ 0 ];
		var setFailed = failedState[ 1 ];

		useEffect( function () {
			if ( isEmpty( current.image ) ) {
				setCheck( { status: 'idle', width: 0, height: 0 } );
				return undefined;
			}
			var cancelled = false;
			setCheck( { status: 'checking', width: 0, height: 0 } );
			var probe = new Image();
			probe.onload = function () {
				if ( cancelled ) {
					return;
				}
				var w = probe.naturalWidth || 0;
				var h = probe.naturalHeight || 0;
				if ( w > 0 && ( w < SOCIAL_MIN_W || h < SOCIAL_MIN_H ) ) {
					setCheck( { status: 'small', width: w, height: h } );
					return;
				}
				setCheck( { status: 'ok', width: w, height: h } );
			};
			probe.onerror = function () {
				if ( cancelled ) {
					return;
				}
				setCheck( { status: 'error', width: 0, height: 0 } );
			};
			probe.src = current.image;
			return function () {
				cancelled = true;
			};
		}, [ current.image ] );

		var labelId = props.idPrefix + '-label';
		var checking = 'checking' === check.status;
		var tooSmall = 'small' === check.status;
		var invalid = 'error' === check.status;
		var empty = isEmpty( current.image );
		var smallNotice = __( 'This image is smaller than the minimum', 'rankkernel' ) + ' ' + SOCIAL_MIN_W + 'x' + SOCIAL_MIN_H + ' (' + check.width + 'x' + check.height + ').';

		return el(
			'div',
			{ className: 'rk-meta-field rk-field rk-image-field' + ( invalid ? ' is-invalid' : '' ) },
			el(
				'div',
				{ className: 'rk-meta-field-head rk-field-head' },
				el( 'span', { className: 'rk-meta-field-label rk-field-label', id: labelId }, props.label ),
				current.image && ! checking && ! invalid ? el( StateBadge, { inherited: false } ) : null
			),
			empty ? el(
				'div',
				{ className: 'rk-thumb-empty', role: 'presentation' },
				el( 'span', { className: 'dashicons dashicons-format-image', 'aria-hidden': 'true' } ),
				el( 'span', null, __( 'No image', 'rankkernel' ) )
			) : el(
				'div',
				{ className: 'rk-thumb-wrap' + ( checking ? ' rk-is-checking' : '' ) },
				el( 'img', { className: 'rk-meta-thumb rk-thumb', src: current.image, alt: '' } ),
				checking ? el(
					'p',
					{ className: 'description rk-thumb-loading', role: 'status' },
					Spinner ? el( Spinner, null ) : null,
					el( 'span', null, __( 'Checking image…', 'rankkernel' ) )
				) : null
			),
			invalid ? el( 'p', { className: 'rk-error', role: 'alert' }, __( 'This image cannot be loaded. Select a different file.', 'rankkernel' ) ) : null,
			tooSmall ? ( Notice ? el(
				Notice,
				{ status: 'warning', isDismissible: false, className: 'rk-image-notice' },
				smallNotice
			) : el(
				'p',
				{ className: 'rk-warn', role: 'status' },
				smallNotice
			) ) : null,
			failed ? el( 'p', { className: 'rk-error', role: 'alert' }, failed ) : null,
			el(
				'div',
				{ className: 'rk-meta-row-actions', role: 'group', 'aria-labelledby': labelId },
				el( 'button', {
					type: 'button',
					className: 'button button-small',
					disabled: ! props.mediaAvailable,
					'aria-label': ( current.image ? __( 'Replace image', 'rankkernel' ) : props.selectLabel ) + ': ' + props.label,
					onClick: function () {
						setFailed( '' );
						var opened = false;
						try {
							opened = pickImage( props.onPick, function () {
								setFailed( __( 'Could not load the selected image. Try a different file.', 'rankkernel' ) );
							} );
						} catch ( e ) {
							opened = false;
						}
						if ( ! opened ) {
							setFailed( __( 'Could not open the media library. Try again.', 'rankkernel' ) );
						}
					}
				}, current.image ? __( 'Replace image', 'rankkernel' ) : props.selectLabel ),
				current.image ? el( 'button', {
					type: 'button',
					className: 'button button-small',
					'aria-label': __( 'Remove image', 'rankkernel' ) + ': ' + props.label,
					onClick: props.onRemove
				}, __( 'Remove', 'rankkernel' ) ) : null
			),
			props.mediaAvailable ? null : el( 'p', { className: 'description' }, __( 'The media library is unavailable here, image selection is disabled.', 'rankkernel' ) ),
			el( 'p', { className: 'description' }, __( 'Recommended 1200x630, minimum 600x315.', 'rankkernel' ) )
		);
	}

	function RadioPair( props ) {
		return el(
			'fieldset',
			{ className: 'rk-meta-field rk-field rk-radio-pair' },
			el( 'legend', null, props.legend ),
			props.options.map( function ( option ) {
				var id = props.name + '-' + option.value;
				var checked = props.value === option.value;
				return el(
					'label',
					{ key: option.value, className: 'rk-radio' + ( checked ? ' rk-is-checked' : '' ), htmlFor: id },
					el( 'input', {
						type: 'radio',
						id: id,
						name: props.name,
						value: option.value,
						checked: checked,
						onChange: function () { props.onChange( option.value ); }
					} ),
					option.label
				);
			} ),
			props.hint ? el( 'p', { className: 'description' }, props.hint ) : null
		);
	}

	function pickImage( onPick, onFail ) {
		try {
			if ( ! window.wp || ! window.wp.media ) {
				return false;
			}
			var frame = window.wp.media( {
				title: __( 'Select preview image', 'rankkernel' ),
				button: { text: __( 'Use this image', 'rankkernel' ) },
				multiple: false
			} );
			frame.on( 'select', function () {
				try {
					var attachment = frame.state().get( 'selection' ).first();
					if ( ! attachment ) {
						return;
					}
					var json = attachment.toJSON();
					var src = ( json.sizes && json.sizes.full && json.sizes.full.url ) || json.url || '';
					if ( '' === String( src ).trim() ) {
						if ( onFail ) {
							onFail();
						}
						return;
					}
					onPick( src, json.id || 0 );
				} catch ( e ) {
					if ( onFail ) {
						onFail();
					}
				}
			} );
			frame.open();
			return true;
		} catch ( e ) {
			if ( onFail ) {
				onFail();
			}
			return false;
		}
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

	// Preview snippet editor modal: an alternate editing surface over the
	// SAME metadata state the sidebar writes, never a second data model.
	// The title, description and reset controls below are the shared field
	// component bound to the same store paths, so every keystroke updates
	// the sidebar preview immediately.
	function PreviewModal( props ) {
		var Modal = components.Modal;
		var modalTabState = useState( props.initialTab || 'general' );
		var activeModalTab = modalTabState[ 0 ];
		var setActiveModalTab = modalTabState[ 1 ];
		var titleText = __( 'Edit Snippet', 'rankkernel' );
		var previewUrl = props.url || shortUrl( cfg.permalink || cfg.homeUrl || '' );

		var modalTabs = [
			{ id: 'general', label: __( 'General', 'rankkernel' ), icon: 'search' },
			{ id: 'social', label: __( 'Social', 'rankkernel' ), icon: 'share' }
		];

		function onModalTabKey( event, index ) {
			if ( 'ArrowRight' === event.key || 'ArrowDown' === event.key ) {
				event.preventDefault();
				setActiveModalTab( modalTabs[ ( index + 1 ) % modalTabs.length ].id );
			} else if ( 'ArrowLeft' === event.key || 'ArrowUp' === event.key ) {
				event.preventDefault();
				setActiveModalTab( modalTabs[ ( index - 1 + modalTabs.length ) % modalTabs.length ].id );
			} else if ( 'Home' === event.key ) {
				event.preventDefault();
				setActiveModalTab( modalTabs[ 0 ].id );
			} else if ( 'End' === event.key ) {
				event.preventDefault();
				setActiveModalTab( modalTabs[ modalTabs.length - 1 ].id );
			}
		}

		var modalTabNav = el(
			'div',
			{ className: 'rk-modal-tabs', role: 'tablist', 'aria-label': __( 'Snippet Editor Sections', 'rankkernel' ) },
			modalTabs.map( function ( tab, index ) {
				var selected = activeModalTab === tab.id;
				return el(
					'button',
					{
						key: tab.id,
						type: 'button',
						role: 'tab',
						id: 'rk-modal-tab-' + tab.id,
						'aria-selected': selected ? 'true' : 'false',
						'aria-controls': 'rk-modal-panel-' + tab.id,
						'aria-label': tab.label,
						title: selected ? undefined : tab.label,
						tabIndex: selected ? 0 : -1,
						className: 'rk-modal-tab' + ( selected ? ' is-active' : '' ),
						onClick: function () { setActiveModalTab( tab.id ); },
						onKeyDown: function ( event ) { onModalTabKey( event, index ); }
					},
					el( 'span', { className: 'dashicons dashicons-' + tab.icon, 'aria-hidden': 'true' } ),
					selected ? el( 'span', { className: 'rk-modal-tab-label', 'aria-hidden': 'true' }, tab.label ) : null
				);
			} )
		);

		var modalContent = null;
		if ( 'general' === activeModalTab ) {
			modalContent = el(
				'div',
				{ className: 'rk-modal-tab-panel', role: 'tabpanel', id: 'rk-modal-panel-general', 'aria-labelledby': 'rk-modal-tab-general', tabIndex: 0 },
				el( SerpPreview, {
					headingId: 'rk-modal-serp-heading',
					title: props.titleValue,
					description: props.descValue,
					device: props.device,
					onDevice: props.onDevice,
					noindex: props.noindex,
					url: props.url
				} ),
				el(
					'div',
					{ className: 'rk-modal-permalink rk-field' },
					el( 'label', { className: 'rk-field-label', htmlFor: 'rk-modal-permalink' }, __( 'Permalink', 'rankkernel' ) ),
					el( 'input', {
						type: 'text',
						id: 'rk-modal-permalink',
						className: 'rk-modal-permalink-input',
						value: previewUrl,
						readOnly: true,
						'aria-readonly': 'true'
					} ),
					el( 'p', { className: 'description' }, __( 'The address shown in the preview. Editing is not supported here.', 'rankkernel' ) )
				),
				props.titleField,
				props.descField
			);
		} else {
			modalContent = el(
				'div',
				{ className: 'rk-modal-tab-panel', role: 'tabpanel', id: 'rk-modal-panel-social', 'aria-labelledby': 'rk-modal-tab-social', tabIndex: 0 },
				props.socialContent
			);
		}

		var body = el(
			'div',
			{ className: 'rk-modal-body rk-meta' },
			modalTabNav,
			modalContent
		);

		if ( Modal ) {
			return el(
				Modal,
				{ title: titleText, onRequestClose: props.onClose, className: 'rk-preview-modal' },
				body
			);
		}
		return el(
			'div',
			{ className: 'rk-modal-fallback-veil', role: 'presentation', onClick: props.onClose },
			el(
				'div',
				{
					className: 'rk-modal-fallback',
					role: 'dialog',
					'aria-modal': 'true',
					'aria-label': titleText,
					onClick: function ( event ) { event.stopPropagation(); }
				},
				el(
					'div',
					{ className: 'rk-modal-fallback-head' },
					el( 'h2', null, titleText ),
					el( 'button', { type: 'button', className: 'button button-small', onClick: props.onClose }, __( 'Close', 'rankkernel' ) )
				),
				body
			)
		);
	}

	function RankKernelSidebar() {
		var postId = useSelect( function ( select ) {
			try {
				var editor = select( 'core/editor' );
				if ( editor && editor.getCurrentPostId ) {
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
		var modalTabState = useState( null );
		var modalTab = modalTabState[ 0 ];
		var setModalTab = modalTabState[ 1 ];
		var socialState = useState( 'facebook' );
		var socialNetwork = socialState[ 0 ];
		var setSocialNetwork = socialState[ 1 ];

		var stored = useSelect( function ( select ) {
			try {
				var editor = select( 'core/editor' );
				if ( ! editor || ! editor.getEditedPostAttribute ) {
					return null;
				}
				var edited = editor.getEditedPostAttribute( 'meta' );
				return edited && edited[ META_KEY ] ? edited[ META_KEY ] : null;
			} catch ( e ) {
				return null;
			}
		}, [] );
		var postType = useSelect( function ( select ) {
			try {
				var editor = select( 'core/editor' );
				if ( editor && editor.getEditedPostAttribute ) {
					return editor.getEditedPostAttribute( 'type' ) || '';
				}
			} catch ( e ) {
				return '';
			}
			return '';
		}, [] );
		var dispatchers = useDispatch( 'core/editor' ) || {};
		var editPost = dispatchers.editPost || null;
		var meta = withMeta( stored );

		// Local drafts keep typing instant; the store write trails by ~150ms.
		var draftsState = useState( {} );
		var drafts = draftsState[ 0 ];
		var setDrafts = draftsState[ 1 ];
		var errorsState = useState( {} );
		var errors = errorsState[ 0 ];
		var setErrors = errorsState[ 1 ];
		var timersRef = useRef( {} );
		var timers = timersRef.current || {};

		// Every store write funnels through here so the REST payload shape is
		// normalized in exactly one place.
		function writeMeta( next ) {
			if ( ! editPost ) {
				return;
			}
			var payload = {};
			payload[ META_KEY ] = toRestMeta( next );
			editPost( { meta: payload } );
		}

		function pushValue( path, value ) {
			if ( ! editPost ) {
				return;
			}
			var next = withMeta( meta );
			if ( 'title' === path || 'description' === path ) {
				next[ path ] = value;
			} else if ( 'focus_keywords' === path ) {
				next.focus_keywords = Array.isArray( value ) ? value : ( typeof value === 'string' ? value.split( ',' ).map( function ( s ) { return s.trim(); } ).filter( Boolean ) : [] );
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
			writeMeta( next );
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

	function appendToken( path, token, inputId ) {
		var raw = display( path, pathValue( path ) );
		var base = String( raw == null ? '' : raw );
		var node = null;
		try {
			node = inputId && document && document.getElementById ? document.getElementById( inputId ) : null;
		} catch ( e ) {
			node = null;
		}
		// Insert at the caret when the field is available so the token
		// lands where the user was typing; otherwise append. Either way
		// the write goes through the same draft/store path, so counters,
		// badges and both previews recompute from one state.
		if ( node && ( 'TEXTAREA' === node.tagName || 'INPUT' === node.tagName ) && null !== node.selectionStart ) {
			var start = node.selectionStart;
			var end = null !== node.selectionEnd ? node.selectionEnd : start;
			var next = base.slice( 0, start ) + token + base.slice( end );
			setDraft( path, next );
			var caret = start + String( token ).length;
			setTimeout( function () {
				try {
					var live = document.getElementById( inputId );
					if ( live && live.focus ) {
						live.focus();
						if ( null !== live.selectionStart && live.setSelectionRange ) {
							live.setSelectionRange( caret, caret );
						}
					}
				} catch ( e ) {
					return;
				}
			}, 0 );
			return;
		}
		setDraft( path, base + token );
	}

		function pathValue( path ) {
			if ( 'title' === path || 'description' === path || 'canonical' === path || 'focus_keywords' === path ) {
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
			if ( ! editPost ) {
				return;
			}
			var next = withMeta( meta );
			next.schema = nextSchema;
			writeMeta( next );
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

		function tokenGroup( path, listId, fieldLabel, inputId ) {
			return el( TokenPicker, {
				listId: listId,
				fieldLabel: fieldLabel,
				inputId: inputId,
				onInsert: function ( token ) { appendToken( path, token, inputId ); }
			} );
		}

		// The one shared field component. Header row carries the visible
		// label plus the state badge; the control follows; a thin meter
		// follows that; a single supporting row carries the counter, the
		// status word, and the token plus reset actions. State travels as
		// text and shape as well as colour: inherited versus custom badges,
		// an error or warning notice with role alert or status, a status
		// pill with its own glyph, and a reset that stays hidden until
		// there is an override to remove.
		function metaField( opts ) {
			var path = opts.path;
			var value = display( path, pathValue( path ) );
			var templateKey = opts.template || null;
			var eff = templateKey ? effectiveValue( value, templateKey ) : null;
			var inherited = isEmpty( value );
			var modified = ! inherited;
			var error = opts.error || errors[ path ] || '';
			var warning = opts.warning || '';
			var disabled = true === opts.disabled;
			var loading = true === opts.loading;
			var inputId = opts.id;
			var useTextarea = true === opts.textarea;
			var Control = useTextarea ? TextareaControl : TextControl;
			if ( ! Control ) {
				return null;
			}
			var limit = opts.limit || 0;
			var pixelLimit = opts.pixelLimit || 0;
			var counterText = eff ? eff.text : value;
			var chars = String( counterText || '' ).length;
			var px = estimatePixels( counterText );
			var status = limit > 0 ? counterStatus( chars, limit ) : 'ok';
			if ( pixelLimit > 0 && px > pixelLimit && 'over' !== status ) {
				status = 'over';
			}
			var invalid = '' !== error;
			var controlProps = {
				id: inputId,
				label: opts.label,
				hideLabelFromVision: true,
				value: loading ? '' : value,
				disabled: disabled || loading,
				placeholder: eff && eff.inherited ? eff.text : ( opts.placeholder || '' ),
				help: '',
				onFocus: function () {
					if ( opts.tokenizable ) {
						rememberTokenFocus( inputId );
					}
				},
				onChange: function ( next ) {
					setError( path, '' );
					if ( opts.validate ) {
						opts.validate( next );
					}
					setDraft( path, next );
				}
			};
			if ( opts.rows ) {
				controlProps.rows = opts.rows;
			}
			var resettable = true === opts.resettable && ! disabled && ! loading;
			var showReset = resettable && ! inherited;
			return el(
				'div',
				{ className: 'rk-meta-field rk-field' + ( invalid ? ' is-invalid' : '' ) + ( disabled ? ' is-disabled' : '' ) + ( loading ? ' is-loading' : '' ) },
				el(
					'div',
					{ className: 'rk-meta-field-head rk-field-head' },
					el( 'label', { className: 'rk-meta-field-label rk-field-label', htmlFor: inputId }, opts.label ),
					eff ? el( StateBadge, { inherited: inherited, modified: modified, invalid: invalid } ) : null
				),
				el( Control, controlProps ),
				invalid ? el( 'p', { className: 'rk-error', role: 'alert' }, error ) : null,
				( '' !== warning && ! invalid ) ? el( 'p', { className: 'rk-warn', role: 'status' }, warning ) : null,
				opts.help ? el( 'p', { className: 'description' }, opts.help ) : null,
				loading ? el( 'p', { className: 'description', role: 'status' }, __( 'Loading…', 'rankkernel' ) ) : null,
				limit > 0 ? el( FieldMeter, { text: counterText, limit: limit } ) : null,
				el(
					'div',
					{ className: 'rk-field-foot rk-field-support' },
					el(
						'div',
						{ className: 'rk-field-count-group' },
						limit > 0 ? el(
							'p',
							{ className: 'rk-meta-count rk-count-text', role: 'status' },
							pixelLimit > 0
								? chars + ' / ' + limit + ' (' + px + 'px / ' + pixelLimit + 'px)'
								: chars + ' / ' + limit + ' ' + __( 'chars', 'rankkernel' )
						) : null,
						limit > 0 ? el(
							'span',
							{ className: 'rk-field-status', 'data-rk-state': status },
							el( 'span', { className: 'screen-reader-text' }, __( 'Length status', 'rankkernel' ) + ': ' ),
							statusWord( status )
						) : null
					),
					( opts.tokenizable || resettable ) ? el(
						'div',
						{ className: 'rk-field-actions' },
						opts.tokenizable ? tokenGroup( path, opts.id + '-tokens', opts.label, inputId ) : null,
						resettable && showReset ? el( FieldReset, {
							label: opts.label,
							disabled: false,
							onReset: function () { resetField( path ); }
						} ) : null
					) : null
				)
			);
		}

		// Canonical validation: invalid input shows an error and is never
		// written to the store.
		useEffect( function () {
			var pending = drafts.canonical;
			if ( pending === undefined ) {
				return undefined;
			}
			if ( '' === String( pending ).trim() ) {
				if ( errors.canonical ) {
					setError( 'canonical', '' );
				}
				return undefined;
			}
			if ( ! isValidHttpUrl( pending ) ) {
				if ( timers.canonical ) {
					clearTimeout( timers.canonical );
				}
				var invalidMsg = __( 'Enter a full URL starting with http:// or https://. Invalid input is not saved.', 'rankkernel' );
				if ( errors.canonical !== invalidMsg ) {
					setError( 'canonical', invalidMsg );
				}
				return undefined;
			}
			if ( errors.canonical ) {
				setError( 'canonical', '' );
			}
			return undefined;
		}, [ drafts.canonical ] );

		function canonicalRow() {
			var value = display( 'canonical', meta.canonical );
			var inherited = isEmpty( value );
			return el(
				'div',
				null,
				metaField( {
					id: 'rk-canonical',
					path: 'canonical',
					label: __( 'Canonical URL', 'rankkernel' ),
					placeholder: cfg.permalink || cfg.homeUrl || '',
					help: __( 'Leave blank to use the generated canonical. A full URL is required when set.', 'rankkernel' )
				} ),
				inherited
					? el( 'p', { className: 'description' }, __( 'Using the generated canonical.', 'rankkernel' ) )
					: el( 'p', { className: 'description' }, __( 'Custom canonical set.', 'rankkernel' ) )
			);
		}

		function socialImageRow( group, label ) {
			var current = meta[ group ] || { image: '', image_id: 0 };
			var mediaAvailable = Boolean( window.wp && window.wp.media );
			return el( SocialImageControl, {
				idPrefix: 'rk-' + group + '-image',
				label: label,
				selectLabel: __( 'Select image', 'rankkernel' ),
				current: current,
				mediaAvailable: mediaAvailable,
				onPick: function ( src, id ) {
					if ( ! editPost ) {
						return;
					}
					var next = withMeta( meta );
					next[ group ].image = src;
					next[ group ].image_id = id;
					writeMeta( next );
				},
				onRemove: function () {
					if ( ! editPost ) {
						return;
					}
					var next = withMeta( meta );
					next[ group ].image = '';
					next[ group ].image_id = 0;
					writeMeta( next );
				}
			} );
		}

		function FocusKeywordsInput( props ) {
			var keywords = Array.isArray( props.keywords ) ? props.keywords : [];
			var inputState = useState( '' );
			var inputValue = inputState[ 0 ];
			var setInputValue = inputState[ 1 ];

			function addKeyword( kw ) {
				var clean = String( kw || '' ).trim();
				if ( ! clean ) {
					return;
				}
				if ( keywords.indexOf( clean ) >= 0 ) {
					setInputValue( '' );
					return;
				}
				var next = keywords.concat( [ clean ] );
				props.onChange( next );
				setInputValue( '' );
			}

			function removeKeyword( index ) {
				var next = keywords.filter( function ( _, i ) { return i !== index; } );
				props.onChange( next );
			}

			function handleKeyDown( event ) {
				if ( 'Enter' === event.key || ',' === event.key ) {
					event.preventDefault();
					addKeyword( inputValue );
				} else if ( 'Backspace' === event.key && ! inputValue && keywords.length > 0 ) {
					removeKeyword( keywords.length - 1 );
				}
			}

			return el(
				'div',
				{ className: 'rk-meta-field rk-field rk-focus-keywords-field' },
				el(
					'div',
					{ className: 'rk-meta-field-head rk-field-head' },
					el( 'label', { className: 'rk-meta-field-label rk-field-label', htmlFor: 'rk-focus-kw-input' }, __( 'Focus Keyword', 'rankkernel' ) ),
					el( 'span', { className: 'dashicons dashicons-editor-help rk-help-icon', title: __( 'Insert keywords you want to rank for.', 'rankkernel' ) } )
				),
				el(
					'div',
					{ className: 'rk-keywords-tagify' },
					keywords.map( function ( kw, idx ) {
						return el(
							'span',
							{ key: idx, className: 'rk-keyword-tag' },
							el( 'span', { className: 'rk-keyword-tag-text' }, kw ),
							el(
								'button',
								{
									type: 'button',
									className: 'rk-keyword-tag-remove',
									'aria-label': __( 'Remove keyword', 'rankkernel' ) + ': ' + kw,
									onClick: function () { removeKeyword( idx ); }
								},
								'×'
							)
						);
					} ),
					el( 'input', {
						id: 'rk-focus-kw-input',
						type: 'text',
						className: 'rk-keywords-input',
						placeholder: keywords.length === 0 ? __( 'e.g. SEO plugin, WordPress', 'rankkernel' ) : '',
						value: inputValue,
						onChange: function ( e ) { setInputValue( e.target.value ); },
						onKeyDown: handleKeyDown,
						onBlur: function () { addKeyword( inputValue ); }
					} )
				),
				el( 'p', { className: 'description' }, __( 'Press Enter or comma to add focus keywords.', 'rankkernel' ) )
			);
		}

		function ContentAnalysisChecklist() {
			var path = cfg.analysis && cfg.analysis.path ? cfg.analysis.path : '';
			var nonce = cfg.analysis && cfg.analysis.nonce ? cfg.analysis.nonce : '';
			var keywords = Array.isArray( meta.focus_keywords ) ? meta.focus_keywords : [];
			var keywordKey = keywords.join( '|' );

			// Subscribed, not read once, so editing the content re-runs the analysis.
			var draft = useSelect( function ( select ) {
				try {
					var editor = select( 'core/editor' );
					if ( ! editor || ! editor.getEditedPostAttribute ) {
						return null;
					}
					return {
						title: editor.getEditedPostAttribute( 'title' ) || '',
						slug: editor.getEditedPostAttribute( 'slug' ) || '',
						content: editor.getEditedPostAttribute( 'content' ) || ''
					};
				} catch ( e ) {
					return null;
				}
			}, [] );

			var resultState = useState( null );
			var result = resultState[ 0 ];
			var setResult = resultState[ 1 ];
			var errorState = useState( '' );
			var error = errorState[ 0 ];
			var setError = errorState[ 1 ];

			var title = draft ? draft.title : '';
			var content = draft ? draft.content : '';
			var slug = draft ? draft.slug : '';
			var description = meta.description || '';

			useEffect( function () {
				if ( ! path || '' === keywordKey ) {
					setResult( null );
					setError( '' );
					return undefined;
				}

				// A slower earlier reply must never overwrite a newer one, and an
				// unmount must not set state, so every run cancels on cleanup.
				var cancelled = false;
				var controller = 'function' === typeof window.AbortController ? new window.AbortController() : null;

				var timer = window.setTimeout( function () {
					window.fetch( path, {
						method: 'POST',
						credentials: 'same-origin',
						signal: controller ? controller.signal : undefined,
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': nonce
						},
						body: JSON.stringify( {
							post_id: cfg.postId,
							title: title,
							description: description,
							slug: slug,
							content: content,
							keywords: keywords
						} )
					} ).then( function ( response ) {
						var status = response.status;
						return response.json().then(
							function ( data ) {
								return { ok: response.ok, status: status, data: data };
							},
							function () {
								return { ok: false, status: status, data: null };
							}
						);
					} ).then( function ( reply ) {
						if ( cancelled ) {
							return;
						}

						if ( ! reply.ok || ! reply.data ) {
							setResult( null );
							setError( 403 === reply.status ? __( 'Save the post once, then run the analysis.', 'rankkernel' ) : __( 'The analysis could not be run. Try again.', 'rankkernel' ) );
							return;
						}

						setError( '' );
						setResult( reply.data );
					} ).catch( function () {
						if ( cancelled ) {
							return;
						}

						setResult( null );
						setError( __( 'The analysis could not be run. Try again.', 'rankkernel' ) );
					} );
				}, 700 );

				return function () {
					cancelled = true;
					if ( controller ) {
						controller.abort();
					}
					window.clearTimeout( timer );
				};
			}, [ path, nonce, keywordKey, title, content, slug, description ] );

			if ( ! path ) {
				return null;
			}

			if ( '' === keywordKey ) {
				return el( 'p', { className: 'description' }, __( 'Add a focus keyword to run the content analysis.', 'rankkernel' ) );
			}

			if ( '' !== error ) {
				return el( 'p', { className: 'description rk-analysis-error', role: 'status' }, error );
			}

			if ( ! result ) {
				return el( 'p', { className: 'description', role: 'status' }, __( 'Analysing the current draft…', 'rankkernel' ) );
			}

			var checks = [];
			( result.checks || [] ).forEach( function ( check ) {
				if ( 'na' === check.status ) {
					return;
				}
				checks.push( {
					id: check.id,
					ok: 'pass' === check.status,
					status: check.status,
					label: check.message
				} );
			} );

			var failCount = checks.filter( function ( c ) { return ! c.ok; } ).length;
			var score = typeof result.score === 'number' ? result.score : 0;

			return el(
				Collapsible,
				{
					title: el(
						'span',
						{ className: 'rk-checklist-head-inner' },
					__( 'Content analysis', 'rankkernel' ),
					el( 'span', { className: 'rk-checklist-badge ' + ( failCount === 0 ? 'rk-badge-ok' : 'rk-badge-warn' ) }, score + ' / 100' )
					),
					bodyId: 'rk-seo-checklist-body'
				},
				el(
					'ul',
					{ className: 'rk-checklist-items' },
					checks.map( function ( check ) {
						return el(
							'li',
							{ key: check.id, className: 'rk-checklist-item ' + ( check.ok ? 'is-ok' : 'is-fail' ) },
							el( 'span', { className: 'dashicons ' + ( check.ok ? 'dashicons-yes-alt' : 'dashicons-dismiss' ), 'aria-hidden': 'true' } ),
							el( 'span', { className: 'screen-reader-text' }, 'pass' === check.status ? __( 'Pass:', 'rankkernel' ) : __( 'Needs work:', 'rankkernel' ) ),
							el( 'span', { className: 'rk-checklist-label' }, check.label )
						);
					} )
				)
			);
		}

		function renderSocialContent() {
			var titleValue = display( 'title', meta.title );
			var descValue = display( 'description', meta.description );
			var isTwitter = 'twitter' === socialNetwork;
			var group = isTwitter ? 'twitter' : 'og';
			var groupLabel = isTwitter ? __( 'Twitter', 'rankkernel' ) : __( 'Facebook', 'rankkernel' );
			return el(
				'div',
				{ className: 'rk-social-modal-inner' },
				el(
					'div',
					{ className: 'rk-social-switch', role: 'group', 'aria-label': __( 'Social network', 'rankkernel' ) },
					el( 'button', {
						type: 'button',
						className: 'button button-small' + ( ! isTwitter ? ' is-active' : '' ),
						'aria-pressed': ! isTwitter ? 'true' : 'false',
						onClick: function () { setSocialNetwork( 'facebook' ); }
					}, __( 'Facebook', 'rankkernel' ) ),
					el( 'button', {
						type: 'button',
						className: 'button button-small' + ( isTwitter ? ' is-active' : '' ),
						'aria-pressed': isTwitter ? 'true' : 'false',
						onClick: function () { setSocialNetwork( 'twitter' ); }
					}, __( 'Twitter', 'rankkernel' ) )
				),
				el( SocialPreview, { meta: meta, title: titleValue, description: descValue, network: socialNetwork } ),
				socialImageRow( group, groupLabel + ' ' + __( 'image', 'rankkernel' ) ),
				metaField( {
					id: 'rk-social-title',
					path: group + '.title',
					label: groupLabel + ' ' + __( 'title', 'rankkernel' ),
					template: 'title',
					tokenizable: true,
					resettable: true
				} ),
				metaField( {
					id: 'rk-social-description',
					path: group + '.description',
					label: groupLabel + ' ' + __( 'description', 'rankkernel' ),
					template: 'description',
					tokenizable: true,
					resettable: true,
					textarea: true,
					rows: 2
				} ),
				el( Collapsible, { title: __( 'Network settings', 'rankkernel' ), bodyId: 'rk-social-settings' },
					el(
						'div',
						null,
						metaField( {
							id: 'rk-og-type',
							path: 'og.type',
							label: __( 'Open Graph type', 'rankkernel' ),
							help: __( 'Leave blank to inherit (article for posts, website for pages).', 'rankkernel' )
						} ),
						SelectControl ? el( SelectControl, {
							id: 'rk-twitter-card',
							label: __( 'Twitter card', 'rankkernel' ),
							value: meta.twitter.card,
							options: [
								{ label: __( 'Summary with large image', 'rankkernel' ), value: 'summary_large_image' },
								{ label: __( 'Summary', 'rankkernel' ), value: 'summary' }
							],
							onChange: function ( next ) {
								if ( ! editPost ) {
									return;
								}
								var updated = withMeta( meta );
								updated.twitter.card = next;
								writeMeta( updated );
							}
						} ) : null
					)
				)
			);
		}

		function generalPanel() {
			var titleValue = display( 'title', meta.title );
			var descValue = display( 'description', meta.description );
			var permalink = cfg.permalink || cfg.homeUrl || '';

			// A disabled analysis module registers no route, so the contract
			// carries no path and both controls stay out of the panel.
			var analysisReady = !!( cfg.analysis && cfg.analysis.path );

			return el(
				'div',
				{ role: 'tabpanel', id: 'rk-panel-general', 'aria-labelledby': 'rk-tab-general', tabIndex: 0 },
			el( SerpPreview, {
				headingId: 'rk-serp-heading',
				title: titleValue,
				description: descValue,
				device: device,
				onDevice: setDevice,
				noindex: ! meta.robots.index,
				url: permalink,
				onEdit: function () { setModalTab( 'general' ); }
			} ),
				el( 'p', { className: 'description rk-serp-edit-note' }, __( 'Titles and descriptions are edited in the snippet editor. The preview reflects the current draft values.', 'rankkernel' ) ),
				analysisReady ? el( FocusKeywordsInput, {
					keywords: meta.focus_keywords,
					onChange: function ( next ) { pushValue( 'focus_keywords', next ); }
				} ) : null,
				analysisReady ? el( ContentAnalysisChecklist, {} ) : null
			);
		}

		// Rendered here, not inside generalPanel(), so the modal host stays
		// mounted while another tab panel is displayed.
		function previewModal() {
			if ( ! modalTab ) {
				return null;
			}

			return el( PreviewModal, {
				initialTab: modalTab,
				titleValue: display( 'title', meta.title ),
				descValue: display( 'description', meta.description ),
				device: device,
				onDevice: setDevice,
				noindex: ! meta.robots.index,
				url: cfg.permalink || cfg.homeUrl || '',
				onClose: function () { setModalTab( null ); },
				titleField: metaField( { id: 'rk-modal-title', path: 'title', label: __( 'SEO title', 'rankkernel' ), template: 'title', limit: TITLE_LIMIT, pixelLimit: TITLE_PX, tokenizable: true, resettable: true, help: __( 'Shown as the first line of the search result. Blank uses the template.', 'rankkernel' ) } ),
				descField: metaField( { id: 'rk-modal-description', path: 'description', label: __( 'Meta description', 'rankkernel' ), template: 'description', limit: DESC_LIMIT, pixelLimit: DESC_PX, tokenizable: true, resettable: true, textarea: true, rows: 4, help: __( 'Shown under the title in the search result. Blank uses the template.', 'rankkernel' ) } ),
				socialContent: renderSocialContent()
			} );
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
						if ( ! editPost ) {
							return;
						}
						var updated = withMeta( meta );
						updated.robots.index = 'index' === next;
						writeMeta( updated );
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
						if ( ! editPost ) {
							return;
						}
						var updated = withMeta( meta );
						updated.robots.follow = 'follow' === next;
						writeMeta( updated );
					}
				} ),
				el( Collapsible, { title: __( 'Additional robots settings', 'rankkernel' ), bodyId: 'rk-robots-extra' },
					el(
						'div',
						null,
						CheckboxControl ? el( CheckboxControl, {
							label: __( 'No archive', 'rankkernel' ),
							checked: meta.robots.noarchive,
							onChange: function ( next ) {
								if ( ! editPost ) {
									return;
								}
								var updated = withMeta( meta );
								updated.robots.noarchive = true === next;
								writeMeta( updated );
							}
						} ) : null,
						CheckboxControl ? el( CheckboxControl, {
							label: __( 'No snippet', 'rankkernel' ),
							checked: meta.robots.nosnippet,
							onChange: function ( next ) {
								if ( ! editPost ) {
									return;
								}
								var updated = withMeta( meta );
								updated.robots.nosnippet = true === next;
								writeMeta( updated );
							}
						} ) : null,
						CheckboxControl ? el( CheckboxControl, {
							label: __( 'No image index', 'rankkernel' ),
							checked: meta.robots.noimageindex,
							onChange: function ( next ) {
								if ( ! editPost ) {
									return;
								}
								var updated = withMeta( meta );
								updated.robots.noimageindex = true === next;
								writeMeta( updated );
							}
						} ) : null,
						metaField( { id: 'rk-max-snippet', path: 'robots.max_snippet', label: __( 'Max snippet', 'rankkernel' ), help: __( 'Max characters for the snippet. Blank means unlimited.', 'rankkernel' ) } ),
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
								if ( ! editPost ) {
									return;
								}
								var updated = withMeta( meta );
								updated.robots.max_image_preview = next;
								writeMeta( updated );
							}
						} ) : null,
						metaField( { id: 'rk-max-video-preview', path: 'robots.max_video_preview', label: __( 'Max video preview', 'rankkernel' ), help: __( 'Max seconds for a video preview. Blank means unlimited.', 'rankkernel' ) } )
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
					{ className: 'rk-meta-field rk-field' },
					el(
						'p',
						{ className: 'rk-status rk-schema-accent', role: 'status' },
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
					{ className: 'rk-meta-field rk-field' },
					el( SelectControl, {
						id: 'rk-schema-type',
						label: __( 'Schema type', 'rankkernel' ),
						disabled: disabled,
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
				el( Collapsible, { title: __( 'Manual overrides', 'rankkernel' ), bodyId: 'rk-schema-manual' },
					el(
						'div',
						null,
						el( 'p', { className: 'description' }, __( 'Only needed when a value must differ from the post itself. Only fields relevant to the chosen type are shown.', 'rankkernel' ) ),
						visibleKeys.map( function ( key ) {
							return metaField( { key: key, id: 'rk-schema-' + key, path: 'schema.fields.' + key, label: SCHEMA_FIELD_LABELS[ key ], disabled: disabled } );
						} )
					)
				),
				el( Collapsible, { title: __( 'Advanced', 'rankkernel' ), bodyId: 'rk-schema-advanced' },
					el(
						'div',
						null,
						TextareaControl ? el( TextareaControl, {
							id: 'rk-schema-custom',
							label: __( 'Custom JSON', 'rankkernel' ),
							help: __( 'Optional, for advanced use. A valid JSON object typed here is added to the schema output as is.', 'rankkernel' ),
							rows: 6,
							disabled: disabled,
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
								disabled: disabled,
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
								disabled: disabled,
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
						el( 'p', { className: 'description' }, __( 'Import replaces the schema settings with the uploaded file.', 'rankkernel' ) ),
						( 'FAQPage' === selected || 'HowTo' === selected ) ? el(
							'p',
							{ className: 'description' },
							__( 'Questions and steps live in the FAQ and How-To blocks; this tab validates them but does not duplicate their editors.', 'rankkernel' )
						) : null
					)
				)
			);
		}

		function socialPanel() {
			var titleValue = display( 'title', meta.title );
			var descValue = display( 'description', meta.description );
			var isTwitter = 'twitter' === socialNetwork;
			return el(
				'div',
				{ role: 'tabpanel', id: 'rk-panel-social', 'aria-labelledby': 'rk-tab-social', tabIndex: 0 },
				el(
					'div',
					{ className: 'rk-social-notice-card rk-field' },
					el( 'h4', { className: 'rk-social-notice-title' }, __( 'Social Media Preview', 'rankkernel' ) ),
					el( 'p', { className: 'description rk-social-notice-desc' }, __( 'Here you can view and edit the thumbnail, title and description that will be displayed when your site is shared on social media.', 'rankkernel' ) ),
					el( 'p', { className: 'description rk-social-notice-sub' }, __( 'Click on the button below to view and edit the preview.', 'rankkernel' ) ),
					el(
						'button',
						{
							type: 'button',
							className: 'button button-primary rk-edit-snippet-btn',
							onClick: function () { setModalTab( 'social' ); }
						},
						el( 'span', { className: 'dashicons dashicons-edit', 'aria-hidden': 'true' } ),
						el( 'span', null, __( 'Edit Snippet', 'rankkernel' ) )
					)
				),
				el(
					'div',
					{ className: 'rk-social-switch', role: 'group', 'aria-label': __( 'Social network', 'rankkernel' ) },
					el( 'button', {
						type: 'button',
						className: 'button button-small' + ( ! isTwitter ? ' is-active' : '' ),
						'aria-pressed': ! isTwitter ? 'true' : 'false',
						onClick: function () { setSocialNetwork( 'facebook' ); }
					}, __( 'Facebook', 'rankkernel' ) ),
					el( 'button', {
						type: 'button',
						className: 'button button-small' + ( isTwitter ? ' is-active' : '' ),
						'aria-pressed': isTwitter ? 'true' : 'false',
						onClick: function () { setSocialNetwork( 'twitter' ); }
					}, __( 'Twitter', 'rankkernel' ) )
				),
				el( SocialPreview, { meta: meta, title: titleValue, description: descValue, network: socialNetwork } )
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
						{ key: tab.id, className: 'rk-meta-tab-wrap', onKeyDown: function ( event ) { onTabKey( event, index ); } },
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
			panel,
			previewModal()
		);
	}

	// Keep a reference for tests and for future reuse.
	window.rankkernelSeoSidebar = window.rankkernelSeoSidebar || {};
	window.rankkernelSeoSidebar.withMeta = withMeta;
	window.rankkernelSeoSidebar.sidebarName = SIDEBAR_NAME;
	window.rankkernelSeoSidebar.usesSharedPreview = function () {
		return Boolean( window.rankkernelMetaEditorPreview && window.rankkernelMetaEditorPreview.effectiveValue );
	};

	registerPlugin( 'rankkernel-seo', {
		render: function () {
			return el(
				PluginSidebar,
				{ name: SIDEBAR_NAME, title: __( 'RankKernel SEO', 'rankkernel' ) },
				el( RankKernelSidebar, null )
			);
		}
	} );
} )();
