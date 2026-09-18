<?php
/**
 * Crawl Signals settings view.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * Presentation only. CrawlSettingsPage prepares every variable used below.
 *
 * @var bool   $settingsUpdated     Whether the saved notice renders.
 * @var string $notice              Write status notice key.
 * @var bool   $robotsPhysical      Whether a physical robots.txt exists.
 * @var bool   $llmsPhysicalExists  Whether a physical llms.txt exists.
 * @var string[] $consistency       Consistency warnings.
 * @var array<int, array{url: string, class: string, label: string}> $tabItems Tab links.
 * @var string $tab                 Current tab id.
 * @var string $robotsMode          robots.txt mode.
 * @var string $robotsCustom        Custom robots.txt block.
 * @var array<int, array{slug: string, label: string, enabled: bool}> $presetDefs Preset rows.
 * @var string $sitemapUrl          Optional sitemap URL.
 * @var array{errors: string[], warnings: string[]} $robotsValidation Validation result.
 * @var string $robotsPreview       Preview output.
 * @var bool   $llmsEnabled         Whether llms.txt is on.
 * @var string $llmsSummary         llms.txt summary.
 * @var array<int, array{slug: string, label: string, enabled: bool}> $llmsPostTypes Post type rows.
 * @var array<int, array{slug: string, label: string, enabled: bool}> $llmsTaxonomies Taxonomy rows.
 * @var string $llmsLimit           Item cap.
 * @var string $llmsExcerpt         Excerpt length.
 * @var string $llmsExclude         Excluded ids text.
 * @var bool   $llmsPhysical        Physical write toggle.
 * @var string $llmsPreview         llms.txt preview.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap rankkernel-crawl">
	<h1><?php echo esc_html__( 'Crawl Signals', 'rankkernel' ); ?></h1>

	<?php if ( $settingsUpdated ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Settings saved.', 'rankkernel' ); ?></p></div>
	<?php endif; ?>

	<?php if ( 'written' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'A physical llms.txt was written.', 'rankkernel' ); ?></p></div>
	<?php elseif ( 'exists' === $notice ) : ?>
		<div class="notice notice-warning is-dismissible"><p><?php echo esc_html__( 'A physical llms.txt already exists, so RankKernel did not overwrite it.', 'rankkernel' ); ?></p></div>
	<?php elseif ( 'failed' === $notice ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html__( 'The physical llms.txt could not be written.', 'rankkernel' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $robotsPhysical ) : ?>
		<div class="notice notice-warning"><p><?php echo esc_html__( 'A physical robots.txt exists in the site root, so the editor below has no effect. RankKernel will not delete it.', 'rankkernel' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $llmsPhysicalExists ) : ?>
		<div class="notice notice-warning"><p><?php echo esc_html__( 'A physical llms.txt exists in the site root, so the virtual route is not used. RankKernel will not overwrite it.', 'rankkernel' ); ?></p></div>
	<?php endif; ?>

	<?php foreach ( $consistency as $warning ) : ?>
		<div class="notice notice-warning"><p><?php echo esc_html( $warning ); ?></p></div>
	<?php endforeach; ?>

	<h2 class="nav-tab-wrapper">
		<?php foreach ( $tabItems as $item ) : ?>
			<a href="<?php echo esc_url( $item['url'] ); ?>" class="<?php echo esc_attr( $item['class'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
		<?php endforeach; ?>
	</h2>

	<?php if ( 'robots' === $tab ) : ?>
		<p><?php echo esc_html__( 'robots.txt is served virtually from the WordPress filter. No file is ever written.', 'rankkernel' ); ?></p>

		<form method="post" action="">
			<?php wp_nonce_field( 'rankkernel_crawl_settings' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php echo esc_html__( 'Mode', 'rankkernel' ); ?></th>
					<td>
						<label><input type="radio" name="mode" value="default" <?php checked( 'default', $robotsMode ); ?> /> <?php echo esc_html__( 'Default (keep WordPress output and add the settings below)', 'rankkernel' ); ?></label><br />
						<label><input type="radio" name="mode" value="custom" <?php checked( 'custom', $robotsMode ); ?> /> <?php echo esc_html__( 'Custom (replace the WordPress output with the block below)', 'rankkernel' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rk-robots-custom"><?php echo esc_html__( 'Custom directives', 'rankkernel' ); ?></label></th>
					<td>
						<textarea id="rk-robots-custom" name="custom" rows="8" cols="60" class="large-text code"><?php echo esc_textarea( $robotsCustom ); ?></textarea>
						<p class="description"><?php echo esc_html__( 'One directive per line. Allowed: User-agent, Allow, Disallow, Sitemap, Crawl-delay. Comments start with #.', 'rankkernel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'AI crawlers', 'rankkernel' ); ?></th>
					<td>
						<?php foreach ( $presetDefs as $preset ) : ?>
							<label style="display:block;">
								<input type="checkbox" name="presets[]" value="<?php echo esc_attr( $preset['slug'] ); ?>" <?php checked( true, $preset['enabled'] ); ?> />
								<?php echo esc_html( $preset['label'] ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description"><?php echo esc_html__( 'Blocked crawlers are written above the wildcard group as User-agent plus Disallow: /.', 'rankkernel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rk-robots-sitemap"><?php echo esc_html__( 'Sitemap URL', 'rankkernel' ); ?></label></th>
					<td>
						<input type="text" id="rk-robots-sitemap" name="sitemap_url" class="regular-text code" value="<?php echo esc_attr( $sitemapUrl ); ?>" />
						<p class="description"><?php echo esc_html__( 'Optional. Absolute http(s) URL. Leave empty to keep the sitemap directive already emitted by the Sitemaps module.', 'rankkernel' ); ?></p>
					</td>
				</tr>
			</table>

			<?php if ( [] !== $robotsValidation['errors'] ) : ?>
				<div class="notice notice-error inline">
					<?php foreach ( $robotsValidation['errors'] as $validationError ) : ?>
						<p><?php echo esc_html( $validationError ); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( [] !== $robotsValidation['warnings'] ) : ?>
				<div class="notice notice-warning inline">
					<?php foreach ( $robotsValidation['warnings'] as $warning ) : ?>
						<p><?php echo esc_html( $warning ); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<h2><?php echo esc_html__( 'Preview', 'rankkernel' ); ?></h2>
			<pre class="code" style="padding:12px;background:#fff;border:1px solid #c3c4c7;overflow:auto;"><?php echo esc_html( $robotsPreview ); ?></pre>

			<?php submit_button( __( 'Save robots.txt', 'rankkernel' ), 'primary', 'rankkernel_crawl_save' ); ?>
		</form>
	<?php else : ?>
		<p><?php echo esc_html__( 'llms.txt is served virtually as Markdown with an X-Robots-Tag noindex header. Google Search ignores llms.txt, as it states in its own documentation, so treat this as a proposal for AI tools only.', 'rankkernel' ); ?></p>

		<form method="post" action="">
			<?php wp_nonce_field( 'rankkernel_crawl_settings' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php echo esc_html__( 'Enable', 'rankkernel' ); ?></th>
					<td>
						<label><input type="checkbox" name="llms_enabled" value="1" <?php checked( true, $llmsEnabled ); ?> /> <?php echo esc_html__( 'Serve the virtual llms.txt route', 'rankkernel' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rk-llms-summary"><?php echo esc_html__( 'Summary', 'rankkernel' ); ?></label></th>
					<td>
						<textarea id="rk-llms-summary" name="llms_summary" rows="3" cols="60" class="large-text"><?php echo esc_textarea( $llmsSummary ); ?></textarea>
						<p class="description"><?php echo esc_html__( 'Rendered as a blockquote under the site name.', 'rankkernel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Post types', 'rankkernel' ); ?></th>
					<td>
						<?php foreach ( $llmsPostTypes as $row ) : ?>
							<label style="display:block;">
								<input type="checkbox" name="llms_post_types[]" value="<?php echo esc_attr( $row['slug'] ); ?>" <?php checked( true, $row['enabled'] ); ?> />
								<?php echo esc_html( $row['label'] ); ?>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Taxonomies', 'rankkernel' ); ?></th>
					<td>
						<?php if ( [] === $llmsTaxonomies ) : ?>
							<p class="description"><?php echo esc_html__( 'No public taxonomies are registered.', 'rankkernel' ); ?></p>
						<?php else : ?>
							<?php foreach ( $llmsTaxonomies as $row ) : ?>
								<label style="display:block;">
									<input type="checkbox" name="llms_taxonomies[]" value="<?php echo esc_attr( $row['slug'] ); ?>" <?php checked( true, $row['enabled'] ); ?> />
									<?php echo esc_html( $row['label'] ); ?>
								</label>
							<?php endforeach; ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rk-llms-limit"><?php echo esc_html__( 'Items per section', 'rankkernel' ); ?></label></th>
					<td>
						<input type="number" id="rk-llms-limit" name="llms_limit" min="1" max="500" value="<?php echo esc_attr( $llmsLimit ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rk-llms-excerpt"><?php echo esc_html__( 'Excerpt length', 'rankkernel' ); ?></label></th>
					<td>
						<input type="number" id="rk-llms-excerpt" name="llms_excerpt_length" min="20" max="400" value="<?php echo esc_attr( $llmsExcerpt ); ?>" />
						<p class="description"><?php echo esc_html__( 'Characters, trimmed at a word boundary.', 'rankkernel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rk-llms-exclude"><?php echo esc_html__( 'Exclude posts', 'rankkernel' ); ?></label></th>
					<td>
						<input type="text" id="rk-llms-exclude" name="llms_exclude_ids" class="regular-text code" value="<?php echo esc_attr( $llmsExclude ); ?>" />
						<p class="description"><?php echo esc_html__( 'Comma separated post IDs to leave out.', 'rankkernel' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Physical file', 'rankkernel' ); ?></th>
					<td>
						<label><input type="checkbox" name="llms_physical" value="1" <?php checked( true, $llmsPhysical ); ?> /> <?php echo esc_html__( 'Allow writing a physical llms.txt', 'rankkernel' ); ?></label>
						<p class="description"><?php echo esc_html__( 'The virtual route stays the default. Writing never overwrites an existing file.', 'rankkernel' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Save llms.txt', 'rankkernel' ), 'primary', 'rankkernel_crawl_save' ); ?>
		</form>

		<?php if ( $llmsEnabled ) : ?>
			<h2><?php echo esc_html__( 'Preview', 'rankkernel' ); ?></h2>
			<pre class="code" style="padding:12px;background:#fff;border:1px solid #c3c4c7;max-height:400px;overflow:auto;"><?php echo esc_html( $llmsPreview ); ?></pre>

			<form method="post" action="">
				<?php wp_nonce_field( 'rankkernel_crawl_settings' ); ?>
				<p>
					<button type="submit" class="button" name="rankkernel_llms_write" value="1"><?php echo esc_html__( 'Write physical llms.txt', 'rankkernel' ); ?></button>
				</p>
			</form>
		<?php endif; ?>
	<?php endif; ?>
</div>
