<?php

defined('WPINC') || die();

/**
 * @var array $camptix_opts
 * @var string $invoice_number
 * @var string $invoice_date
 * @var array $invoice_metas
 * @var array $invoice_order
 * @var string $logo
 */

?>

<html>
<head>
  <meta charset="UTF-8">
  <link href="http://fonts.googleapis.com/css?family=Open+Sans:300,600,700" rel="stylesheet" type="text/css"/>
  <style type="text/css">
    #camptix-invoice-page {
      margin: 5em;
      font-family: 'Open Sans', sans-serif;
      font-size: 14px;
      font-weight: 300;
      color: #222;
    }

    #camptix-invoice-page strong {
      font-weight: 700;
    }

    #camptix-invoice-page p {
      padding: 0;
      margin: 0;
      line-height: 1em;
    }

    #camptix-invoice-page .text-left {
      text-align: left;
    }

    #camptix-invoice-page .text-center {
      text-align: center;
    }

    #camptix-invoice-page .text-right {
      text-align: right;
    }

    #camptix-invoice-page .camptix-invoice-logo-holder {
      display: block;
      margin: 3em 0;
      padding-top: 3em;
    }

    #camptix-invoice-page .camptix-invoice-logo-holder img {
      max-width: 50%;
    }

    #camptix-invoice-page .camptix-inovice-header {
      padding-bottom: 1em;
      overflow: hidden;
      border-bottom: 1px solid #999;
    }

    #camptix-invoice-page .camptix-inovice-header .camptix-invoice-from-box {
      width: 48%;
      float: left;
    }

    #camptix-invoice-page .camptix-inovice-header .camptix-invoice-to-box {
      width: 48%;
      float: right;
    }

    #camptix-invoice-page .camptix-inovice-header .camptix-invoice-from-box p,
    #camptix-invoice-page .camptix-inovice-header .camptix-invoice-to-box p {
      line-height: 2em;
    }

    #camptix-invoice-page .camptix-inovice-header .camptix-invoice-to-box p {
      margin-bottom: 2em;
    }

    #camptix-invoice-page .camptix-invoice-data {
      padding: 2.5em 0;
    }

    #camptix-invoice-page .camptix-invoice-data p {
      line-height: 1.5em;
    }

    #camptix-invoice-page .camptix-invoice-order table,
    #camptix-invoice-page .camptix-invoice-order th,
    #camptix-invoice-page .camptix-invoice-order td {
      border: 1px solid #999;
    }

    #camptix-invoice-page .camptix-invoice-order th,
    #camptix-invoice-page .camptix-invoice-order td {
      line-height: 2.5em;
      padding: 0 0.5em;
    }

    #camptix-invoice-page .camptix-invoice-order table {
      width: 100%;
      border-collapse: collapse;
    }

    #camptix-invoice-page .camptix-invoice-order th {
      background: #BBBBBB;
    }

    #camptix-invoice-page .camptix-invoice-payment-status {
      padding: 3em 0;
    }

    #camptix-invoice-page .camptix-invoice-payment-status p {
      font-weight: 700;
    }

  </style>
</head>
<body>
<div id="camptix-invoice-page">
  <div class="camptix-invoice-logo-holder">
    <img src="<?php echo esc_url($logo); ?>">
  </div>
  <div class="camptix-inovice-header">
    <div class="camptix-invoice-from-box text-left">
      <strong><?php _e('From', 'invoices-camptix') ?>:</strong>
      <p class="text-left">
          <?php echo nl2br($camptix_opts['invoice-company']) ?>
      </p>
    </div>
    <div class="camptix-invoice-to-box text-right">
      <strong><?php _e('To', 'invoices-camptix') ?>:</strong>
      <p class="text-right">
          <?php echo $invoice_metas['name'] ?><br/>
          <?php echo $invoice_metas['address'] ?><br/>
      </p>
      <strong><?php _e('VAT no', 'invoices-camptix') ?>:</strong>
        <?php echo $invoice_metas['vat-number'] ?>
    </div>
  </div>
  <div class="camptix-invoice-data text-right">
    <p class="text-right">
      <strong><?php _e('Invoice no', 'invoices-camptix') ?>:</strong>
        <?php echo $invoice_number ?>
    </p>
    <p class="text-right">
      <strong><?php _e('Invoice Date', 'invoices-camptix') ?>:</strong>
        <?php echo $invoice_date ?>
    </p>
  </div>
  <div class="camptix-invoice-order">
    <table>
      <colgroup>
        <col style="width: 48%"/>
        <col style="width: 8%"/>
        <col style="width: 22%"/>
        <col style="width: 22%"/>
      </colgroup>
      <tr>
        <th class="text-left"><?php echo $camptix_opts['event_name'] ?></th>
        <th class="text-center"><?php _e('Qty', 'invoices-camptix') ?></th>
        <th class="text-right"><?php _e('Unit Price',
                'invoices-camptix') ?></th>
        <th class="text-right"><?php _e('Total Price',
                'invoices-camptix') ?></th>
      </tr>
        <?php foreach ($invoice_order['items'] as $item): ?>
          <tr>
            <td class="text-left"><?php echo $item['name'] ?></td>
            <td class="text-center"><?php echo $item['quantity'] ?></td>
            <td class="text-right">
                <?php echo CampTix_Addon_Invoices::format_currency(get_post_meta($item->id,
                    'tix_price', true), $camptix_opts['currency']) ?>
            </td>

            <td class="text-right">
                <?php echo CampTix_Addon_Invoices::format_currency($item['price'],
                    $camptix_opts['currency']) ?>
            </td>
          </tr>
        <?php endforeach ?>
      <tr>
        <td class="text-right"><?php _e('VAT', 'invoices-camptix') ?></td>
        <td></td>
        <td></td>
        <td class="text-right">
            <?php echo CampTix_Addon_Invoices::format_currency(0,
                $camptix_opts['currency']) ?>
        </td>
      </tr>
      <tr>
        <td class="text-right"><?php _e('TOTAL', 'invoices-camptix') ?></td>
        <td></td>
        <td></td>
        <td class="text-right">
            <?php echo CampTix_Addon_Invoices::format_currency($invoice_order['total'],
                $camptix_opts['currency']) ?>
        </td>
      </tr>
    </table>
  </div>
  <div class="camptix-invoice-payment-status">
    <p>
        <?php _e('Paid in full.', 'invoices-camptix') ?>
    </p>
  </div>
  <div class="camptix-invoice-note">
      <?php echo nl2br($camptix_opts['invoice-thankyou']) ?>
  </div>
</div>
</body>
</html>
