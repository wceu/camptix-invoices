jQuery(document).ready(function ($) {
    /**
     * Compose a form to receive client information
     */
    var $afterMarker = $('.tix_tickets_table.tix-attendee-form');
    if ($afterMarker.length) {
        var invoiceForm = '<div style="margin-bottom:2rem;"><label><input type="checkbox" value="1" name="camptix-need-invoice"/> Je souhaite une facture</label></div>';
        $afterMarker.eq(-1).after(invoiceForm);
    }
});