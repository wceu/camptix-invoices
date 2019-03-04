<?php

defined( 'WPINC' ) || die();

/** @var string $id */
/** @var string $value */
/** @var boolean $yearly */

?>

<p>
	<?php echo esc_html__( 'The next invoice number will be', 'invoices-camptix' ); ?>

	<?php
	if ( $yearly ) {
		echo esc_html( date( 'Y-' ) );
	}
	?>

	<input type="number" min="1" value="<?php echo esc_attr( $value ); ?>" name="camptix_options[<?php echo esc_html( $id ); ?>]" class="small-text">
</p>
