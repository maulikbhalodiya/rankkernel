<?php
/**
 * Sitemap settings page view.
 *
 * Presentation only. SitemapSettingsPage prepares every variable used below,
 * and owns capability checks, nonce verification, request handling,
 * validation and redirects.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var bool   $settingsUpdated       Whether the settings saved notice renders.
 * @var array<int, array{url: string, class: string, current: bool, label: string}> $tabItems Tab links.
 * @var bool   $showGeneral           Whether the General tab section renders.
 * @var bool   $showPostTypes         Whether the Post Types tab section renders.
 * @var bool   $showTaxonomies        Whether the Taxonomies tab section renders.
 * @var bool   $showAuthors           Whether the Authors tab section renders.
 * @var string $indexUrl              Sitemap index URL.
 * @var string $itemsPerPage          Links per sitemap value.
 * @var array<int, array<string, mixed>> $generalRows General tab rows.
 * @var array<int, array{key: string, label: string, enabled: bool, url: string}> $postTypeRows Post type rows.
 * @var array<int, array{key: string, label: string, enabled: bool, url: string}> $taxonomyRows Taxonomy rows.
 * @var array<int, array{name: string, title: string, checked: bool, hint: string}> $authorsRows Authors checkbox rows.
 * @var bool   $hasEditableRoles      Whether editable roles exist.
 * @var array<int, array{slug: string, name: string, excluded: bool}> $roleRows Editable role rows.
 * @var array{name: string, title: string, value: string, hint: string} $authorsExcludeUsers Exclude users row.
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
	<h1><?php echo esc_html__( 'Sitemap Settings', 'rankkernel' ); ?></h1>

	<nav class="nav-tab-wrapper" aria-label="<?php echo esc_attr( __( 'Sitemap settings tabs', 'rankkernel' ) ); ?>">
		<?php foreach ( $tabItems as $tabItem ) : ?>
			<a href="<?php echo esc_url( $tabItem['url'] ); ?>" class="<?php echo esc_attr( $tabItem['class'] ); ?>"<?php echo ! empty( $tabItem['current'] ) ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $tabItem['label'] ); ?></a>
		<?php endforeach; ?>
	</nav>

	<form method="post" action="">
		<?php wp_nonce_field( 'rankkernel_sitemap_settings' ); ?>

		<?php if ( $showGeneral ) : ?>
			<h2><?php echo esc_html__( 'General', 'rankkernel' ); ?></h2>
			<p>
				<?php echo esc_html__( 'Your sitemap index can be found here: ', 'rankkernel' ); ?>
				<a href="<?php echo esc_url( $indexUrl ); ?>"><?php echo esc_html( $indexUrl ); ?></a>
			</p>
			<table class="form-table" role="presentation"><tbody>
				<tr>
					<th scope="row"><label for="rk-items-per-page"><?php echo esc_html__( 'Links Per Sitemap', 'rankkernel' ); ?></label></th>
					<td>
						<input type="number" id="rk-items-per-page" name="items_per_page" value="<?php echo esc_attr( $itemsPerPage ); ?>" class="small-text" min="1" max="50000" />
						<p class="description"><?php echo esc_html__( 'Max number of links on each sitemap page.', 'rankkernel' ); ?></p>
					</td>
				</tr>
				<?php foreach ( $generalRows as $generalRow ) : ?>
					<?php if ( 'checkbox' === $generalRow['kind'] ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $generalRow['title'] ); ?></th>
							<td>
								<label><input type="checkbox" name="<?php echo esc_attr( $generalRow['name'] ); ?>" value="1" <?php echo checked( $generalRow['checked'], true, false ); ?> /> <?php echo esc_html( $generalRow['hint'] ); ?></label>
							</td>
						</tr>
					<?php else : ?>
						<tr>
							<th scope="row"><label for="rk-<?php echo esc_attr( $generalRow['name'] ); ?>"><?php echo esc_html( $generalRow['title'] ); ?></label></th>
							<td>
								<textarea id="rk-<?php echo esc_attr( $generalRow['name'] ); ?>" name="<?php echo esc_attr( $generalRow['name'] ); ?>" rows="2" cols="40"><?php echo esc_textarea( $generalRow['value'] ); ?></textarea>
								<p class="description"><?php echo esc_html( $generalRow['hint'] ); ?></p>
							</td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody></table>
		<?php elseif ( $showPostTypes ) : ?>
			<h2><?php echo esc_html__( 'Post Types', 'rankkernel' ); ?></h2>
			<table class="form-table" role="presentation"><tbody>
				<?php foreach ( $postTypeRows as $postTypeRow ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $postTypeRow['label'] ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $postTypeRow['key'] ); ?>" value="1" <?php echo checked( $postTypeRow['enabled'], true, false ); ?> /> <?php echo esc_html__( 'Include in Sitemap', 'rankkernel' ); ?></label>
							<p class="description"><?php echo esc_html__( 'Include archive pages for posts of this type in the XML sitemap.', 'rankkernel' ); ?></p>
							<p class="description"><?php echo esc_html__( 'Sitemap URL:', 'rankkernel' ); ?> <?php echo esc_url( $postTypeRow['url'] ); ?></p>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody></table>
		<?php elseif ( $showTaxonomies ) : ?>
			<h2><?php echo esc_html__( 'Taxonomies', 'rankkernel' ); ?></h2>
			<table class="form-table" role="presentation"><tbody>
				<?php foreach ( $taxonomyRows as $taxonomyRow ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $taxonomyRow['label'] ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $taxonomyRow['key'] ); ?>" value="1" <?php echo checked( $taxonomyRow['enabled'], true, false ); ?> /> <?php echo esc_html__( 'Include in Sitemap', 'rankkernel' ); ?></label>
							<p class="description"><?php echo esc_html__( 'Include archive pages for terms of this taxonomy in the XML sitemap.', 'rankkernel' ); ?></p>
							<p class="description"><?php echo esc_html__( 'Sitemap URL:', 'rankkernel' ); ?> <?php echo esc_url( $taxonomyRow['url'] ); ?></p>
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<td colspan="2">
						<p class="description"><?php echo esc_html__( 'Empty terms are listed only when the general include empty terms setting is on.', 'rankkernel' ); ?></p>
					</td>
				</tr>
			</tbody></table>
		<?php elseif ( $showAuthors ) : ?>
			<h2><?php echo esc_html__( 'Authors', 'rankkernel' ); ?></h2>
			<table class="form-table" role="presentation"><tbody>
				<?php foreach ( $authorsRows as $authorsRow ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $authorsRow['title'] ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $authorsRow['name'] ); ?>" value="1" <?php echo checked( $authorsRow['checked'], true, false ); ?> /> <?php echo esc_html( $authorsRow['hint'] ); ?></label>
						</td>
					</tr>
				<?php endforeach; ?>
				<tr>
					<th scope="row"><?php echo esc_html__( 'Exclude roles', 'rankkernel' ); ?></th>
					<td>
						<?php if ( ! $hasEditableRoles ) : ?>
							<p class="description"><?php echo esc_html__( 'No editable roles found.', 'rankkernel' ); ?></p>
						<?php else : ?>
							<?php foreach ( $roleRows as $roleRow ) : ?>
								<label><input type="checkbox" name="authors_exclude_roles[]" value="<?php echo esc_attr( $roleRow['slug'] ); ?>" <?php echo checked( $roleRow['excluded'], true, false ); ?> /> <?php echo esc_html( $roleRow['name'] ); ?></label><br />
							<?php endforeach; ?>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rk-<?php echo esc_attr( $authorsExcludeUsers['name'] ); ?>"><?php echo esc_html( $authorsExcludeUsers['title'] ); ?></label></th>
					<td>
						<textarea id="rk-<?php echo esc_attr( $authorsExcludeUsers['name'] ); ?>" name="<?php echo esc_attr( $authorsExcludeUsers['name'] ); ?>" rows="2" cols="40"><?php echo esc_textarea( $authorsExcludeUsers['value'] ); ?></textarea>
						<p class="description"><?php echo esc_html( $authorsExcludeUsers['hint'] ); ?></p>
					</td>
				</tr>
			</tbody></table>
		<?php endif; ?>

		<?php submit_button( __( 'Save Sitemap Settings', 'rankkernel' ), 'primary', 'rankkernel_sitemap_save' ); ?>
	</form>
</div>
