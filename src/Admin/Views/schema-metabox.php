<?php
/**
 * Schema metabox view.
 *
 * Presentation only. SchemaMetabox prepares every variable used below, and
 * owns capability checks, nonce verification, request handling, saving and
 * validation.
 *
 * @package RankKernel
 * @license GPL-2.0-or-later
 *
 * @var int      $postId             Current post id.
 * @var string   $noticeMessage      Sanitized notice key from the redirect query arg.
 * @var bool     $disabled           Whether schema output is disabled for this post.
 * @var string   $postType           Current post type slug.
 * @var string   $selected           Selected schema type, empty for automatic.
 * @var string   $autoLabel          Resolved label shown for the automatic option.
 * @var array<int, array{value: string, label: string}> $typeOptions Supported schema type options.
 * @var array<int, array{id: string, name: string, label: string, value: string, types: string, hide: string}> $fieldRows Manual field override rows.
 * @var string   $customJson         Custom JSON textarea value.
 * @var string[] $validationMessages Validation warnings for the selected type.
 * @var string   $validationLabel    Label used in the validation success message.
 * @var string   $richResultsUrl     Rich Results Test URL.
 * @var string   $validatorUrl       Schema Validator URL.
 * @var string   $exportUrl          Schema export URL.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( 'saved' === $noticeMessage ) :
	?>
	<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Schema saved.', 'rankkernel' ); ?></p></div>
	<?php
elseif ( 'invalid-json' === $noticeMessage ) :
	?>
	<div class="notice notice-error is-dismissible"><p><?php echo esc_html__( 'Custom JSON was invalid, it was cleared and nothing else changed.', 'rankkernel' ); ?></p></div>
	<?php
elseif ( 'invalid-import' === $noticeMessage ) :
	?>
	<div class="notice notice-error is-dismissible"><p><?php echo esc_html__( 'Import file was invalid, nothing was saved.', 'rankkernel' ); ?></p></div>
	<?php
endif;
?>
<?php wp_nonce_field( 'rankkernel_schema_save', 'rankkernel_schema_nonce' ); ?>
<p><label><input type="checkbox" name="rankkernel_schema_disabled" value="1" <?php echo checked( $disabled, true, false ); ?> /> <?php echo esc_html__( 'Disable schema output for this post', 'rankkernel' ); ?></label><br /><span class="description"><?php echo esc_html__( 'No structured data prints on this post while checked.', 'rankkernel' ); ?></span></p>

<h3><?php echo esc_html__( 'Schema type', 'rankkernel' ); ?></h3>
<p><label for="rankkernel-schema-type"><?php echo esc_html__( 'Type', 'rankkernel' ); ?></label> <select name="rankkernel_schema_type" id="rankkernel-schema-type">
	<?php $auto = '' === $selected ? ' selected="selected"' : ''; ?>
	<option value=""<?php echo $auto; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value is empty or a hardcoded selected attribute fragment. ?>><?php echo esc_html( $autoLabel ); ?></option>
	<?php foreach ( $typeOptions as $typeOption ) : ?>
		<?php $mark = $typeOption['value'] === $selected ? ' selected="selected"' : ''; ?>
		<option value="<?php echo esc_attr( $typeOption['value'] ); ?>"<?php echo $mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value is empty or a hardcoded selected attribute fragment. ?>><?php echo esc_html( $typeOption['label'] ); ?></option>
	<?php endforeach; ?>
</select></p>
<?php if ( '' !== $postType ) : ?>
	<p class="description"><?php echo esc_html__( 'Automatic uses the default type set for this post type in RankKernel Schema settings.', 'rankkernel' ); ?></p>
<?php endif; ?>

<details><summary><?php echo esc_html__( 'Manual field overrides (optional)', 'rankkernel' ); ?></summary>
	<p class="description"><?php echo esc_html__( 'Only needed when a value must differ from the post itself. Rows unrelated to the chosen type stay hidden.', 'rankkernel' ); ?></p>
	<table class="form-table" role="presentation"><tbody>
		<?php foreach ( $fieldRows as $fieldRow ) : ?>
			<tr data-rankkernel-field-types="<?php echo esc_attr( $fieldRow['types'] ); ?>"<?php echo $fieldRow['hide']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value is empty or a hardcoded style attribute fragment. ?>>
				<th scope="row"><label for="<?php echo esc_attr( $fieldRow['id'] ); ?>"><?php echo esc_html( $fieldRow['label'] ); ?></label></th>
				<td><input type="text" id="<?php echo esc_attr( $fieldRow['id'] ); ?>" class="regular-text" name="<?php echo esc_attr( $fieldRow['name'] ); ?>" value="<?php echo esc_attr( $fieldRow['value'] ); ?>" /></td>
			</tr>
		<?php endforeach; ?>
	</tbody></table>
</details>

<details><summary><?php echo esc_html__( 'Advanced: custom JSON, import, export', 'rankkernel' ); ?></summary>
	<h3><?php echo esc_html__( 'Custom JSON', 'rankkernel' ); ?></h3>
	<p><label for="rankkernel-schema-custom"><?php echo esc_html__( 'Extra schema properties', 'rankkernel' ); ?></label></p>
	<textarea id="rankkernel-schema-custom" class="large-text code" rows="6" name="rankkernel_schema_custom"><?php echo esc_textarea( $customJson ); ?></textarea>
	<p class="description"><?php echo esc_html__( 'Optional, for advanced use. A valid JSON object typed here is added to the schema output as is.', 'rankkernel' ); ?></p>

	<h3><?php echo esc_html__( 'Validation', 'rankkernel' ); ?></h3>
	<?php if ( [] === $validationMessages ) : ?>
		<div class="notice notice-success inline"><p><?php echo esc_html( sprintf( /* translators: %s: schema type name */ __( 'All required fields for %s are present.', 'rankkernel' ), $validationLabel ) ); ?></p></div>
	<?php else : ?>
		<div class="notice notice-warning inline"><ul>
			<?php foreach ( $validationMessages as $validationMessage ) : ?>
				<li><?php echo esc_html( $validationMessage ); ?></li>
			<?php endforeach; ?>
		</ul></div>
	<?php endif; ?>

	<h3><?php echo esc_html__( 'Test this page', 'rankkernel' ); ?></h3>
	<p><a href="<?php echo esc_url( $richResultsUrl ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Rich Results Test', 'rankkernel' ); ?></a> | <a href="<?php echo esc_url( $validatorUrl ); ?>" target="_blank" rel="noopener"><?php echo esc_html__( 'Schema Validator', 'rankkernel' ); ?></a></p>

	<h3><?php echo esc_html__( 'Import and export', 'rankkernel' ); ?></h3>
	<p><a class="button" href="<?php echo esc_url( $exportUrl ); ?>"><?php echo esc_html__( 'Export JSON', 'rankkernel' ); ?></a></p>
	<p><label for="rankkernel-schema-import"><?php echo esc_html__( 'Import JSON', 'rankkernel' ); ?></label> <input type="file" id="rankkernel-schema-import" name="rankkernel_schema_import" accept=".json,application/json" /></p>
	<p class="description"><?php echo esc_html__( 'Upload a file previously exported with the Export JSON button above.', 'rankkernel' ); ?></p>
</details>
