jQuery( document ).ready( function ($) {
	/**
	 * Compose a form to receive client information
	 */
	var $afterMarker = $( '.tix-has-dynamic-receipts .tix_tickets_table.tix-attendee-form' );
	if ( $afterMarker.length ) {
		$.ajax({
			url: camptixInvoicesVars.invoiceDetailsForm,
			method: 'GET',
			dataType: 'json',
			success:function (data) {
				if ( 'undefined' != data.form ) {
					var invoiceForm = data.form;
					$afterMarker.eq(-1).after(invoiceForm);
				}
			}
		});

		$(document).on( 'change', '#camptix-need-invoice', toggleInvoiceDetailsForm );
		function toggleInvoiceDetailsForm() {
			var $camptixInvoiceDetailsForm = $( '.camptix-invoice-details' );
			$camptixInvoiceDetailsForm.toggle();
			var $camptixInvoiceDetailsFormFields = $camptixInvoiceDetailsForm.find( 'input,textarea,select' );
			var required = $camptixInvoiceDetailsFormFields.eq(0).prop( 'required' );
			$camptixInvoiceDetailsFormFields.prop( 'required', ! required );
		}
	}
});
