<?php

defined( 'WPINC' ) || die();

/** @var string $invoice_number */
/** @var string $auth */
/** @var Post $post */

?>

<div class="misc-pub-section">
	<p>
		<?php echo esc_html__( 'Invoice number', 'invoices-camptix' ); ?> <strong><?php echo esc_html( $invoice_number ); ?></strong>
	</p>
	<a
		href="<?php echo esc_attr( admin_url( 'admin-post.php?action=camptix-invoice.get&invoice_id=' . $post->ID . '&invoice_auth=' . $auth ) ); ?>"
		class="button button-secondary"
		target="_blank"
	>
		<?php echo esc_html__( 'Download invoice', 'invoices-camptix' ); ?>
	</a>
</div>
