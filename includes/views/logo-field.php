<?php

defined( 'WPINC' ) || die();

/** @var string $id */
/** @var string $value */

?>

<div class="camptix-media">
	<div class="camptix-invoice-logo-preview-wrapper" data-imagewrapper>
		<?php
		if ( ! empty( $value ) ) {
			echo wp_get_attachment_image( $value, 'thumbnail' );
		}
		?>
	</div>

	<input data-set type="button" class="button button-secondary" value="<?php echo esc_attr__( 'Pick a logo', 'invoices-camptix' ); ?>" />
	<input
		data-unset
		type="button"
		class="button button-secondary"
		value="<?php echo esc_attr__( 'Remove logo', 'invoices-camptix' ); ?>"
		<?php
		if ( empty( $value ) ) {
			echo 'style="display:none;"';
		}
		?>
	/>
	<input type="hidden" name=camptix_options[<?php echo esc_attr( $id ); ?>] data-field="image_attachment" value="<?php echo esc_attr( $value ); ?>">
</div>
