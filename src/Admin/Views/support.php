<?php
/**
 * Support request screen.
 *
 * Presentation only. SupportPage prepares every variable used below.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var string                                  $notice      Notice key, 'sent', 'invalid' or ''.
 * @var array<string, string>                   $errors      Field errors keyed by field name.
 * @var array<string, string>                   $values      Redisplayed values keyed by field name.
 * @var array<string, string>                   $categories  Category value to label.
 */

defined( 'ABSPATH' ) || exit;

$rkNotice = $notice;
$rkErrors = $errors;
$rkValues = $values;
$rkCats   = $categories;

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
 * Prints the error text and the aria wiring for a field.
 *
 * @param string $key Field name.
 * @return void
 */
$rk_error_markup = static function ( string $key ) use ( $rk_error ): void {
	$message = $rk_error( $key );

	if ( '' === $message ) {
		echo ' aria-invalid="false"';

		return;
	}

	printf(
		' aria-invalid="true" aria-describedby="rk-support-%1$s-error"',
		esc_attr( $key )
	);
	echo '<p class="rk-support-error" id="rk-support-' . esc_attr( $key ) . '-error">';
	echo esc_html( $message );
	echo '</p>';
};

?>
<div class="wrap rk-support">
	<div class="rk-ui-page-header">
		<div class="rk-ui-page-header-text">
			<h2 class="rk-ui-page-title"><?php esc_html_e( 'Support', 'rankkernel' ); ?></h2>
			<p class="rk-support-lede"><?php esc_html_e( 'Report a problem, ask a question or send an idea. It reaches the plugin author directly, and you will get a reply at the address you give below.', 'rankkernel' ); ?></p>
		</div>
	</div>

	<?php if ( 'sent' === $rkNotice ) : ?>
		<div class="rk-ui-notice rk-ui-notice-success" role="status">
			<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">check_circle</span>
			<div class="rk-ui-notice-body">
				<p class="rk-ui-notice-text"><?php esc_html_e( 'Thank you. Your message is on its way and we will reply to the address you gave.', 'rankkernel' ); ?></p>
			</div>
		</div>
	<?php elseif ( 'invalid' === $rkNotice ) : ?>
		<div class="rk-ui-notice rk-ui-notice-error" role="alert">
			<span class="rk-icon rk-ui-notice-icon" aria-hidden="true">error</span>
			<div class="rk-ui-notice-body">
				<p class="rk-ui-notice-text"><?php esc_html_e( 'Your request was not sent. Please correct the highlighted fields and try again.', 'rankkernel' ); ?></p>
				<?php if ( '' !== $rk_error( 'form' ) ) : ?>
					<p class="rk-ui-notice-text"><?php echo esc_html( $rk_error( 'form' ) ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

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

		<div class="rk-support-card rk-ui-card">
			<div class="rk-ui-card-body">
				<div class="rk-ui-form-row">
					<label class="rk-ui-form-label" for="rk-support-category"><?php esc_html_e( 'What is this about?', 'rankkernel' ); ?></label>
					<div class="rk-ui-select-wrap">
						<select
							id="rk-support-category"
							name="category"
							class="rk-ui-select"
							data-rk-support-field="category"
							aria-describedby="rk-support-category-hint"
							<?php $rk_error_markup( 'category' ); ?>
						>
							<option value=""><?php esc_html_e( 'Choose one', 'rankkernel' ); ?></option>
							<?php foreach ( $rkCats as $rkValue => $rkLabel ) : ?>
								<option
									value="<?php echo esc_attr( (string) $rkValue ); ?>"
									<?php selected( $rk_value( 'category' ), (string) $rkValue ); ?>
								><?php echo esc_html( $rkLabel ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<p class="rk-ui-hint" id="rk-support-category-hint"><?php esc_html_e( 'This decides who picks the request up.', 'rankkernel' ); ?></p>
				</div>

				<div class="rk-ui-form-row">
					<label class="rk-ui-form-label" for="rk-support-subject"><?php esc_html_e( 'Subject', 'rankkernel' ); ?></label>
					<input
						type="text"
						id="rk-support-subject"
						name="subject"
						class="rk-support-input"
						value="<?php echo esc_attr( $rk_value( 'subject' ) ); ?>"
						maxlength="150"
						autocomplete="off"
						data-rk-support-field="subject"
						data-rk-support-count="rk-support-subject-count"
						aria-describedby="rk-support-subject-hint rk-support-subject-count"
						<?php $rk_error_markup( 'subject' ); ?>
					/>
					<p class="rk-ui-hint" id="rk-support-subject-hint"><?php esc_html_e( 'One line that says what the request is about.', 'rankkernel' ); ?></p>
					<p class="rk-support-count" id="rk-support-subject-count" aria-live="polite"></p>
				</div>

				<div class="rk-ui-form-row">
					<label class="rk-ui-form-label" for="rk-support-message"><?php esc_html_e( 'Message', 'rankkernel' ); ?></label>
					<textarea
						id="rk-support-message"
						name="message"
						rows="10"
						class="rk-support-textarea"
						maxlength="5000"
						data-rk-support-field="message"
						data-rk-support-count="rk-support-message-count"
						aria-describedby="rk-support-message-hint rk-support-message-count"
						<?php $rk_error_markup( 'message' ); ?>
					><?php echo esc_textarea( $rk_value( 'message' ) ); ?></textarea>
					<p class="rk-ui-hint" id="rk-support-message-hint"><?php esc_html_e( 'For a problem, say what you did, what you expected and what happened instead. A link to the page helps.', 'rankkernel' ); ?></p>
					<p class="rk-support-count" id="rk-support-message-count" aria-live="polite"></p>
				</div>

				<div class="rk-ui-form-row">
					<label class="rk-ui-form-label" for="rk-support-email"><?php esc_html_e( 'Your email address', 'rankkernel' ); ?></label>
					<input
						type="email"
						id="rk-support-email"
						name="email"
						class="rk-support-input"
						value="<?php echo esc_attr( $rk_value( 'email' ) ); ?>"
						maxlength="254"
						autocomplete="email"
						data-rk-support-field="email"
						aria-describedby="rk-support-email-hint"
						<?php $rk_error_markup( 'email' ); ?>
					/>
					<p class="rk-ui-hint" id="rk-support-email-hint"><?php esc_html_e( 'Only used to reply to this request.', 'rankkernel' ); ?></p>
				</div>

				<div class="rk-ui-form-row">
					<label class="rk-ui-form-label" for="rk-support-screenshot"><?php esc_html_e( 'Screenshot', 'rankkernel' ); ?></label>
					<input
						type="file"
						id="rk-support-screenshot"
						name="rankkernel_support_screenshot"
						class="rk-support-file"
						accept="image/png,image/jpeg,image/gif,image/webp"
						data-rk-support-field="screenshot"
						aria-describedby="rk-support-screenshot-hint"
						<?php $rk_error_markup( 'screenshot' ); ?>
					/>
					<p class="rk-ui-hint" id="rk-support-screenshot-hint"><?php esc_html_e( 'Optional. A PNG, JPEG, GIF or WebP image, up to 2 MB. A picture often says more than a paragraph.', 'rankkernel' ); ?></p>
				</div>

				<div class="rk-ui-form-row rk-support-switch-row">
					<label class="rk-ui-switch" for="rk-support-diagnostics">
						<input
							type="checkbox"
							id="rk-support-diagnostics"
							name="rankkernel_support_diagnostics"
							value="1"
							<?php checked( '1', $rk_value( 'diagnostics' ) ); ?>
							data-rk-support-field="diagnostics"
							aria-label="<?php echo esc_attr__( 'Include site details with this request', 'rankkernel' ); ?>"
							aria-describedby="rk-support-diagnostics-hint"
						/>
						<span class="rk-ui-switch-track" aria-hidden="true"><span class="rk-ui-switch-knob"></span></span>
					</label>
					<p class="rk-ui-hint" id="rk-support-diagnostics-hint"><?php esc_html_e( 'Adds the plugin version, WordPress version, PHP version, active theme and site address to the email. This is usually what makes a report fixable in one round.', 'rankkernel' ); ?></p>
				</div>

				<div class="rk-ui-form-row">
					<label class="rk-support-consent" for="rk-support-consent">
						<input
							type="checkbox"
							id="rk-support-consent"
							name="rankkernel_support_consent"
							value="1"
							class="rk-support-checkbox"
							<?php checked( '1', $rk_value( 'consent' ) ); ?>
							data-rk-support-field="consent"
							aria-describedby="rk-support-consent-hint"
							<?php $rk_error_markup( 'consent' ); ?>
						/>
						<span class="rk-support-consent-text"><?php esc_html_e( 'I understand this message and any site details are emailed to the plugin author.', 'rankkernel' ); ?></span>
					</label>
					<p class="rk-ui-hint" id="rk-support-consent-hint"><?php esc_html_e( 'Your request is emailed, never posted anywhere and never shared with anyone else.', 'rankkernel' ); ?></p>
				</div>
			</div>
		</div>

		<?php // Inside the form, so a bot that fills every field posts it too. ?>
		<div class="rk-support-honeypot" aria-hidden="true">
			<label for="rk-support-website"><?php esc_html_e( 'Leave this field empty', 'rankkernel' ); ?></label>
			<input type="text" id="rk-support-website" name="rankkernel_support_website" value="" tabindex="-1" autocomplete="off" />
		</div>

		<div class="rk-support-actions">
			<button type="submit" class="rk-ui-btn rk-ui-btn-primary"><?php esc_html_e( 'Send request', 'rankkernel' ); ?></button>
		</div>
	</form>
</div>