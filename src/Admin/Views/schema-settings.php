<?php
/**
 * Schema settings page view.
 *
 * Presentation only. SchemaSettingsPage prepares every variable used below,
 * and owns capability checks, nonce verification, request handling,
 * validation, and redirects.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var bool     $settingsUpdated     Whether the settings saved notice renders.
 * @var string   $represents          Selected site represents value.
 * @var string   $orgName             Organization name.
 * @var string   $orgLogo             Organization logo URL.
 * @var string[] $sameAsLines         Same As profile URLs.
 * @var bool     $websiteSearchAction Search Action checkbox state.
 * @var bool     $schemaBreadcrumbs   Breadcrumbs checkbox state.
 * @var bool     $schemaAuthor        Author checkbox state.
 * @var array<int, array{key: string, label: string, current: string}> $defaultRows Per post type default rows.
 * @var array<int, array{value: string, label: string}>                $schemaTypes Supported schema types.
 * @var string   $richResultsUrl      Rich Results Test URL.
 * @var string   $validatorUrl        Schema Validator URL.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( $settingsUpdated ) :
	?>
	<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Settings saved.', 'rankkernel' ); ?></p></div>
	<?php
endif;
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Schema Settings', 'rankkernel' ); ?></h1>

	<form method="post" action="">
		<?php wp_nonce_field( 'rankkernel_schema_settings' ); ?>

		<h2><?php echo esc_html__( 'Identity', 'rankkernel' ); ?></h2>
		<table class="form-table" role="presentation"><tbody>
			<tr>
				<th scope="row"><label for="rk-site-represents"><?php echo esc_html__( 'Site Represents', 'rankkernel' ); ?></label></th>
				<td>
					<select id="rk-site-represents" name="site_represents">
						<option value="organization"<?php echo selected( $represents, 'organization', false ); ?>><?php echo esc_html__( 'Organization', 'rankkernel' ); ?></option>
						<option value="person"<?php echo selected( $represents, 'person', false ); ?>><?php echo esc_html__( 'Person', 'rankkernel' ); ?></option>
					</select>
					<p class="description"><?php echo esc_html__( 'Choose Organization for a business or group site, Person for a personal site.', 'rankkernel' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rk-org-name"><?php echo esc_html__( 'Organization Name', 'rankkernel' ); ?></label></th>
				<td>
					<input type="text" id="rk-org-name" name="org_name" value="<?php echo esc_attr( $orgName ); ?>" class="regular-text" />
					<p class="description"><?php echo esc_html__( 'Shown as the site owner name in search results. Leave empty to use the site name.', 'rankkernel' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Organization Logo', 'rankkernel' ); ?></th>
				<td>
					<div id="rk-org-logo-wrap">
						<img id="rk-org-logo-preview" src="<?php echo esc_url( $orgLogo ); ?>" alt="" style="max-width:150px;height:auto;<?php echo '' === $orgLogo ? 'display:none;' : ''; ?>" />
						<input type="hidden" id="rk-org-logo" name="org_logo" value="<?php echo esc_attr( $orgLogo ); ?>" />
						<p>
							<button type="button" class="button" id="rk-org-logo-select"><?php echo esc_html__( 'Select image', 'rankkernel' ); ?></button>
							<button type="button" class="button" id="rk-org-logo-remove"<?php echo '' === $orgLogo ? ' style="display:none;"' : ''; ?>><?php echo esc_html__( 'Remove', 'rankkernel' ); ?></button>
						</p>
					</div>
					<p class="description"><?php echo esc_html__( 'Logo image shown with your site name in search results. Pick from the media library or upload a new image.', 'rankkernel' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="rk-org-sameas"><?php echo esc_html__( 'Same As', 'rankkernel' ); ?></label></th>
				<td>
					<textarea id="rk-org-sameas" name="org_sameas" rows="4" cols="50"><?php echo esc_textarea( implode( "\n", $sameAsLines ) ); ?></textarea>
					<p class="description"><?php echo esc_html__( 'One profile address per line, for example social profiles. Tells search engines which profiles are yours.', 'rankkernel' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Search Action', 'rankkernel' ); ?></th>
				<td>
					<label><input type="checkbox" name="website_search_action" value="1" <?php echo checked( $websiteSearchAction, true, false ); ?> /> <?php echo esc_html__( 'Adds a search box under your home page in search results. Only useful if your site has search.', 'rankkernel' ); ?></label>
				</td>
			</tr>
		</tbody></table>

		<h2><?php echo esc_html__( 'Defaults', 'rankkernel' ); ?></h2>
		<table class="form-table" role="presentation"><tbody>
			<?php foreach ( $defaultRows as $row ) : ?>
				<tr>
					<th scope="row"><label for="rk-<?php echo esc_attr( $row['key'] ); ?>"><?php echo esc_html( $row['label'] ); ?></label></th>
					<td>
						<select id="rk-<?php echo esc_attr( $row['key'] ); ?>" name="<?php echo esc_attr( $row['key'] ); ?>">
							<option value=""<?php echo selected( $row['current'], '', false ); ?>><?php echo esc_html__( 'Automatic', 'rankkernel' ); ?></option>
							<?php foreach ( $schemaTypes as $schemaType ) : ?>
								<option value="<?php echo esc_attr( $schemaType['value'] ); ?>"<?php echo selected( $row['current'], $schemaType['value'], false ); ?>><?php echo esc_html( $schemaType['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php echo esc_html__( 'Default schema type for this post type.', 'rankkernel' ); ?> <?php echo esc_html__( 'Automatic means posts use BlogPosting, other types use Article.', 'rankkernel' ); ?></p>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Breadcrumbs', 'rankkernel' ); ?></th>
				<td>
					<label><input type="checkbox" name="schema_breadcrumbs" value="1" <?php echo checked( $schemaBreadcrumbs, true, false ); ?> /> <?php echo esc_html__( 'Shows the page trail in search results. Turn off to hide it.', 'rankkernel' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php echo esc_html__( 'Author', 'rankkernel' ); ?></th>
				<td>
					<label><input type="checkbox" name="schema_author" value="1" <?php echo checked( $schemaAuthor, true, false ); ?> /> <?php echo esc_html__( 'Shows the article author in search results. Turn off to hide it.', 'rankkernel' ); ?></label>
				</td>
			</tr>
		</tbody></table>

		<h2><?php echo esc_html__( 'Tools', 'rankkernel' ); ?></h2>
		<p><a href="<?php echo esc_url( $richResultsUrl ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Rich Results Test', 'rankkernel' ); ?></a> | <a href="<?php echo esc_url( $validatorUrl ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Schema Validator', 'rankkernel' ); ?></a></p>
		<p class="description"><?php echo esc_html__( 'The Rich Results Test opens with your home URL prefilled.', 'rankkernel' ); ?></p>

		<?php submit_button( __( 'Save Schema Settings', 'rankkernel' ), 'primary', 'rankkernel_schema_save' ); ?>
	</form>
</div>
