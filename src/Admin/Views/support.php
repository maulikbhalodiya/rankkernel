<?php
/**
 * Support request screen.
 *
 * Presentation only. SupportPage prepares every variable used below.
 * Layout follows the Stitch support mock: breadcrumb header, stepped form
 * card, diagnostics preview chips, knowledge base cards, footer.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var string                $notice         Notice key, 'sent', 'invalid' or ''.
 * @var array<string, string> $errors         Field errors keyed by field name.
 * @var array<string, string> $values         Redisplayed values keyed by field name.
 * @var array<string, string> $categories     Category value to label.
 * @var string                $version        Plugin version, empty when unknown.
 * @var array<string, string> $diagnosticRows Diagnostic label to value.
 */

defined( 'ABSPATH' ) || exit;

$rkNotice  = $notice;
$rkErrors  = $errors;
$rkValues  = $values;
$rkCats    = $categories;
$rkVersion = $version;
$rkDiag    = $diagnosticRows;

/**
 * Returns the stored value for a field.
 *
 * @param string $key Field name.
 * @return string Stored value.
 */
$rk_value = static function ( string $key ) use ( $rkValues ): string {
	return (string) ( $rkValues[ $key ] ?? '' );
};

/**
 * Returns the error for a field, or an empty string.
 *
 * @param string $key Field name.
 * @return string Error text.
 */
$rk_error = static function ( string $key ) use ( $rkErrors ): string {
	return (string) ( $rkErrors[ $key ] ?? '' );
};

/**
 * Prints the validity wiring for a field, for use inside its start tag.
 *
 * @param string $key Field name.
 * @return void
 */
$rk_error_attrs = static function ( string $key ) use ( $rk_error ): void {
	if ( '' === $rk_error( $key ) ) {
		echo ' aria-invalid="false"';

		return;
	}

	echo ' aria-invalid="true"';
};

/**
 * Builds the describedby value for a field: its hints plus the live error id
 * when one is showing.
 *
 * @param string $key      Field name.
 * @param string $hint_ids Space separated hint element ids.
 * @return string Describedby value.
 */
$rk_describedby = static function ( string $key, string $hint_ids ) use ( $rk_error ): string {
	if ( '' === $rk_error( $key ) ) {
		return $hint_ids;
	}

	return trim( $hint_ids . ' rk-support-' . $key . '-error' );
};

/**
 * Prints the server side error block for a field, for use after its control.
 *
 * @param string $key Field name.
 * @return void
 */
$rk_error_block = static function ( string $key ) use ( $rk_error ): void {
	$message = $rk_error( $key );

	if ( '' === $message ) {
		return;
	}

	echo '<p class="rk-support-error" id="rk-support-' . esc_attr( $key ) . '-error">';
	echo esc_html( $message );
	echo '</p>';
};

?>
<div class="wrap rk-support rk-ui">
	<h1 class="screen-reader-text"><?php esc_html_e( 'Support', 'rankkernel' ); ?></h1>
	<div class="rk-support-top">
		<header class="rk-ui-card rk-ui-page-header">
			<div class="rk-ui-page-header-text">
				<div class="rk-ui-page-header-title-row">
					<h2 class="rk-ui-page-title"><?php esc_html_e( 'Support', 'rankkernel' ); ?></h2>
					<?php if ( '' !== $rkVersion ) : ?>
						<span class="rk-ui-pill rk-ui-pill-info"><?php echo esc_html( sprintf( /* translators: %s: the plugin version number. */ __( 'v%s', 'rankkernel' ), $rkVersion ) ); ?></span>
					<?php endif; ?>
				</div>
				<p class="rk-support-status"><span class="rk-support-status-dot" aria-hidden="true"></span><?php esc_html_e( 'Direct Core Team Queue', 'rankkernel' ); ?></p>
				<p class="rk-ui-sub"><?php esc_html_e( 'Have a question, found something that is not working, or have an idea for RankKernel? Send a message and we will take a look.', 'rankkernel' ); ?></p>
			</div>
		</header>
	</div>

	<?php if ( 'sent' === $rkNotice ) : ?>
		<div class="rk-ui-notice rk-ui-notice-success" role="status" data-rk-support-notice>
			<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">check_circle</span>
			<div class="rk-ui-notice-body">
				<p class="rk-ui-notice-text"><strong><?php esc_html_e( 'Request sent successfully', 'rankkernel' ); ?></strong></p>
				<p class="rk-ui-notice-text"><?php esc_html_e( 'Thank you. Your message is on its way and we will reply to the address you gave.', 'rankkernel' ); ?></p>
			</div>
			<button type="button" class="rk-ui-notice-dismiss" aria-label="<?php echo esc_attr__( 'Dismiss this notice', 'rankkernel' ); ?>"><span class="rk-icon" aria-hidden="true">close</span></button>
		</div>
	<?php elseif ( 'invalid' === $rkNotice ) : ?>
		<div class="rk-ui-notice rk-ui-notice-error" role="alert" data-rk-support-notice>
			<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">error</span>
			<div class="rk-ui-notice-body">
				<p class="rk-ui-notice-text"><strong><?php esc_html_e( 'Attention required', 'rankkernel' ); ?></strong></p>
				<p class="rk-ui-notice-text"><?php esc_html_e( 'Please fix the highlighted fields below before submitting your inquiry.', 'rankkernel' ); ?></p>
				<?php if ( '' !== $rk_error( 'form' ) ) : ?>
					<p class="rk-ui-notice-text"><?php echo esc_html( $rk_error( 'form' ) ); ?></p>
				<?php endif; ?>
			</div>
			<button type="button" class="rk-ui-notice-dismiss" aria-label="<?php echo esc_attr__( 'Dismiss this notice', 'rankkernel' ); ?>"><span class="rk-icon" aria-hidden="true">close</span></button>
		</div>
	<?php endif; ?>

	<div class="rk-ui-card rk-support-card">
		<div class="rk-ui-card-body">
			<form
				class="rk-support-form"
				method="post"
				enctype="multipart/form-data"
				action="<?php echo esc_url( admin_url( 'admin.php?page=rankkernel-support' ) ); ?>"
				novalidate
				data-rk-support-form
			>
				<?php wp_nonce_field( 'rankkernel_support_submit' ); ?>
				<input type="hidden" name="rankkernel_support_submit" value="1" />

				<div class="rk-ui-form-row rk-support-step">
					<div class="rk-support-step-head">
						<label class="rk-ui-form-label rk-support-step-label" for="rk-support-category"><?php esc_html_e( 'What is this about?', 'rankkernel' ); ?> <span class="rk-support-required" aria-hidden="true">*</span></label>
						<span class="rk-support-step-count"><?php esc_html_e( 'Step 1 of 4', 'rankkernel' ); ?></span>
					</div>
					<div class="rk-ui-select-wrap">
						<select
							id="rk-support-category"
							name="category"
							class="rk-ui-select"
							data-rk-support-field="category"
							aria-describedby="<?php echo esc_attr( $rk_describedby( 'category', 'rk-support-category-hint' ) ); ?>"
							<?php $rk_error_attrs( 'category' ); ?>
						>
							<option value=""><?php esc_html_e( 'Choose one', 'rankkernel' ); ?></option>
							<?php foreach ( $rkCats as $rkValue => $rkLabel ) : ?>
								<option
									value="<?php echo esc_attr( (string) $rkValue ); ?>"
									<?php selected( $rk_value( 'category' ), (string) $rkValue ); ?>
								><?php echo esc_html( $rkLabel ); ?></option>
							<?php endforeach; ?>
						</select>
						<span class="rk-icon rk-ui-select-chevron" aria-hidden="true">expand_more</span>
					</div>
					<?php $rk_error_block( 'category' ); ?>
					<p class="rk-ui-hint" id="rk-support-category-hint"><?php esc_html_e( 'This decides who picks the request up.', 'rankkernel' ); ?></p>
				</div>

				<div class="rk-ui-form-row rk-support-step">
					<div class="rk-support-step-head">
						<label class="rk-ui-form-label rk-support-step-label" for="rk-support-subject"><?php esc_html_e( 'Subject', 'rankkernel' ); ?> <span class="rk-support-required" aria-hidden="true">*</span></label>
						<span class="rk-support-step-count"><?php esc_html_e( 'Step 2 of 4', 'rankkernel' ); ?></span>
					</div>
					<div class="rk-support-label-row">
						<span class="rk-ui-hint" id="rk-support-subject-hint"><?php esc_html_e( 'One line that says what the request is about.', 'rankkernel' ); ?></span>
						<p class="rk-support-count" id="rk-support-subject-count" aria-live="polite"></p>
					</div>
					<input
						type="text"
						id="rk-support-subject"
						name="subject"
						class="rk-support-input"
						value="<?php echo esc_attr( $rk_value( 'subject' ) ); ?>"
						maxlength="150"
						autocomplete="off"
						placeholder="<?php echo esc_attr__( 'e.g. Sitemap returning 404 after enabling RankKernel', 'rankkernel' ); ?>"
						data-rk-support-field="subject"
						data-rk-support-count="rk-support-subject-count"
						aria-describedby="<?php echo esc_attr( $rk_describedby( 'subject', 'rk-support-subject-hint rk-support-subject-count' ) ); ?>"
						<?php $rk_error_attrs( 'subject' ); ?>
					/>
					<?php $rk_error_block( 'subject' ); ?>
					<div class="rk-support-label-row rk-support-message-head">
						<label class="rk-ui-form-label" for="rk-support-message"><?php esc_html_e( 'Message', 'rankkernel' ); ?> <span class="rk-support-required" aria-hidden="true">*</span></label>
						<p class="rk-support-count" id="rk-support-message-count" aria-live="polite"></p>
					</div>
					<textarea
						id="rk-support-message"
						name="message"
						rows="6"
						class="rk-support-textarea"
						maxlength="5000"
						placeholder="<?php echo esc_attr__( 'For a problem, say what you did, what you expected and what happened instead. A link to the page helps.', 'rankkernel' ); ?>"
						data-rk-support-field="message"
						data-rk-support-count="rk-support-message-count"
						aria-describedby="<?php echo esc_attr( $rk_describedby( 'message', 'rk-support-message-hint rk-support-message-count' ) ); ?>"
						<?php $rk_error_attrs( 'message' ); ?>
					><?php echo esc_textarea( $rk_value( 'message' ) ); ?></textarea>
					<?php $rk_error_block( 'message' ); ?>
					<p class="rk-ui-hint" id="rk-support-message-hint"><?php esc_html_e( 'The more precise the steps, the faster the fix.', 'rankkernel' ); ?></p>
				</div>

				<div class="rk-ui-form-row rk-support-step">
					<div class="rk-support-step-head">
						<label class="rk-ui-form-label rk-support-step-label" for="rk-support-email"><?php esc_html_e( 'Your email address', 'rankkernel' ); ?> <span class="rk-support-required" aria-hidden="true">*</span></label>
						<span class="rk-support-step-count"><?php esc_html_e( 'Step 3 of 4', 'rankkernel' ); ?></span>
					</div>
					<div class="rk-support-icon-field">
						<span class="rk-icon rk-support-icon-field-icon" aria-hidden="true">inbox</span>
						<input
							type="email"
							id="rk-support-email"
							name="email"
							class="rk-support-input"
							value="<?php echo esc_attr( $rk_value( 'email' ) ); ?>"
							maxlength="254"
							autocomplete="email"
							placeholder="<?php echo esc_attr__( 'admin@example.com', 'rankkernel' ); ?>"
							data-rk-support-field="email"
							aria-describedby="<?php echo esc_attr( $rk_describedby( 'email', 'rk-support-email-hint' ) ); ?>"
							<?php $rk_error_attrs( 'email' ); ?>
						/>
					</div>
					<?php $rk_error_block( 'email' ); ?>
					<p class="rk-ui-hint" id="rk-support-email-hint"><?php esc_html_e( 'Only used to reply to this request. We never send promotional emails or share contacts.', 'rankkernel' ); ?></p>
				</div>

				<div class="rk-ui-form-row rk-support-step">
					<div class="rk-support-step-head">
						<span class="rk-ui-form-label rk-support-step-label" id="rk-support-context-label"><?php esc_html_e( 'Helpful context', 'rankkernel' ); ?></span>
						<span class="rk-support-step-count"><?php esc_html_e( 'Step 4 of 4', 'rankkernel' ); ?></span>
					</div>
					<p class="rk-ui-hint"><?php esc_html_e( 'Provide additional diagnostics to help our engineers reproduce anomalies quickly.', 'rankkernel' ); ?></p>
					<div class="rk-support-upload" role="group" aria-labelledby="rk-support-context-label">
						<div class="rk-support-upload-info">
							<span class="rk-support-upload-icon" aria-hidden="true"><span class="rk-icon" aria-hidden="true">cloud_upload</span></span>
							<div class="rk-support-upload-text">
								<span class="rk-support-upload-name" id="rk-support-file-name" data-rk-support-empty="<?php echo esc_attr__( 'No file selected', 'rankkernel' ); ?>"><?php esc_html_e( 'No file selected', 'rankkernel' ); ?></span>
								<span class="rk-ui-hint"><?php esc_html_e( 'PNG, JPEG, GIF or WebP up to 2 MB', 'rankkernel' ); ?></span>
							</div>
						</div>
						<input
							type="file"
							id="rk-support-screenshot"
							name="rankkernel_support_screenshot[]"
							class="rk-support-sr-only"
							accept="image/png,image/jpeg,image/gif,image/webp"
							multiple
							data-rk-support-field="screenshot"
							data-rk-support-max="5"
							aria-describedby="<?php echo esc_attr( $rk_describedby( 'screenshot', 'rk-support-screenshot-hint' ) ); ?>"
							<?php $rk_error_attrs( 'screenshot' ); ?>
						/>
						<label class="rk-ui-btn rk-ui-btn-secondary rk-support-browse" for="rk-support-screenshot"><span class="rk-icon" aria-hidden="true">download</span><?php esc_html_e( 'Browse files', 'rankkernel' ); ?></label>
					</div>
					<?php $rk_error_block( 'screenshot' ); ?>
					<ul class="rk-support-file-list" data-rk-support-file-list hidden></ul>
					<p class="rk-ui-hint" id="rk-support-screenshot-hint"><?php esc_html_e( 'Optional. Up to 5 pictures, PNG, JPEG, GIF or WebP, 2 MB each. A picture often says more than a paragraph.', 'rankkernel' ); ?></p>
					<div class="rk-support-diagnostics">
						<div class="rk-support-diagnostics-head">
							<div class="rk-support-diagnostics-text">
								<p class="rk-ui-form-label rk-support-diagnostics-label"><?php esc_html_e( 'Site details sent with this request', 'rankkernel' ); ?></p>
								<p class="rk-ui-hint" id="rk-support-diagnostics-hint"><?php esc_html_e( 'Your RankKernel and WordPress versions, PHP version, active theme and site address travel with the message, so the issue can be reproduced in the same environment.', 'rankkernel' ); ?></p>
							</div>
						</div>
						<div class="rk-support-chips" data-rk-support-diagnostics>
							<?php foreach ( $rkDiag as $rkDiagLabel => $rkDiagValue ) : ?>
								<?php if ( '' !== (string) $rkDiagValue ) : ?>
									<span class="rk-support-chip"><span class="rk-support-chip-label"><?php echo esc_html( (string) $rkDiagLabel ); ?>:</span> <span class="rk-support-chip-value"><?php echo esc_html( (string) $rkDiagValue ); ?></span></span>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<div class="rk-ui-form-row rk-support-consent-row">
					<label class="rk-support-consent" for="rk-support-consent">
						<input
							type="checkbox"
							id="rk-support-consent"
							name="rankkernel_support_consent"
							value="1"
							class="rk-support-checkbox"
							<?php checked( '1', $rk_value( 'consent' ) ); ?>
							data-rk-support-field="consent"
							aria-describedby="<?php echo esc_attr( $rk_describedby( 'consent', 'rk-support-consent-hint' ) ); ?>"
							<?php $rk_error_attrs( 'consent' ); ?>
						/>
						<span class="rk-support-consent-text"><?php esc_html_e( 'I understand this message and any site details are emailed to the plugin author.', 'rankkernel' ); ?></span>
					</label>
					<?php $rk_error_block( 'consent' ); ?>
					<p class="rk-ui-hint" id="rk-support-consent-hint"><?php esc_html_e( 'Your request is emailed, never posted anywhere and never shared with anyone else.', 'rankkernel' ); ?></p>
				</div>

				<div class="rk-support-honeypot" aria-hidden="true">
					<label for="rk-support-website"><?php esc_html_e( 'Leave this field empty', 'rankkernel' ); ?></label>
					<input type="text" id="rk-support-website" name="rankkernel_support_website" value="" tabindex="-1" autocomplete="off" />
				</div>

				<div class="rk-support-actions">
					<p class="rk-ui-hint rk-support-assurance"><span class="rk-icon" aria-hidden="true">shield</span><?php esc_html_e( 'Your request is emailed directly to the RankKernel core engineering team.', 'rankkernel' ); ?></p>
					<button type="submit" class="rk-ui-btn rk-ui-btn-primary"><span class="rk-icon" aria-hidden="true">send</span><?php esc_html_e( 'Send request', 'rankkernel' ); ?></button>
				</div>
			</form>
		</div>
	</div>

	<div class="rk-support-kb" aria-label="<?php echo esc_attr__( 'Support resources', 'rankkernel' ); ?>">
		<div class="rk-ui-card rk-support-kb-card">
			<div class="rk-ui-card-body">
				<p class="rk-support-kb-title"><span class="rk-icon" aria-hidden="true">info</span><?php esc_html_e( 'Online documentation', 'rankkernel' ); ?></p>
				<p class="rk-ui-hint"><?php esc_html_e( 'Guides for robots.txt, canonical paths, and schema models.', 'rankkernel' ); ?></p>
			</div>
		</div>
		<div class="rk-ui-card rk-support-kb-card">
			<div class="rk-ui-card-body">
				<p class="rk-support-kb-title"><span class="rk-icon" aria-hidden="true">tune</span><?php esc_html_e( 'Instant Indexing API', 'rankkernel' ); ?></p>
				<p class="rk-ui-hint"><?php esc_html_e( 'Service accounts and IndexNow validation handshakes.', 'rankkernel' ); ?></p>
			</div>
		</div>
		<div class="rk-ui-card rk-support-kb-card">
			<div class="rk-ui-card-body">
				<p class="rk-support-kb-title"><span class="rk-icon" aria-hidden="true">assessment</span><?php esc_html_e( 'Changelog and releases', 'rankkernel' ); ?></p>
				<p class="rk-ui-hint"><?php esc_html_e( 'Update manifests, bug fixes, and release revisions.', 'rankkernel' ); ?></p>
			</div>
		</div>
	</div>

	<p class="rk-ui-hint rk-support-footer">
		<?php
		if ( '' !== $rkVersion ) {
			echo esc_html( sprintf( /* translators: %s: the plugin version number. */ __( 'RankKernel SEO Suite, Support Module v%s', 'rankkernel' ), $rkVersion ) );
		} else {
			esc_html_e( 'RankKernel SEO Suite, Support Module', 'rankkernel' );
		}
		?>
	</p>
</div>
