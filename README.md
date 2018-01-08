# Camptix invoices #

Camptix invoices est un plugin permettant de générer des factures automatiques pour chaque achat de ticket via camptix.

## À faire ##

- ~~Créer une sous page de réglage pour définir la numérotation de facture~~
- Lors de chaque commande, attacher une meta de numéro de facture autoincrémentée
- Utiliser la méthode [https://blog.niap3d.com/fr/4,10,news-8-Creer-un-fichier-PDF-avec-PHP.html](https://blog.niap3d.com/fr/4,10,news-8-Creer-un-fichier-PDF-avec-PHP.html) pour générer une facture
	- Sur un endpoint admin-post.php par exemple
	- Attacher la facture en tant que pièce jointe dans l’email de reçu
- placer des hooks pour injecter les informations relatives au marchand
- Permettre la cutomisation du modèle de la facture
- Ajouter un bouton en back pour regéner les factures (individuellement ou en bulk)

## Retour paiement ##

Il faut trouver un moyen de composer une facture alors que l’on a très peu d’info sur la transaction
Juste ce qu'il y a dans la meta `tix_order` du `tix_receipt_email`…

```
array(2) {
  ["transaction_id"]=>
  string(17) "7KA97229BP454623U"
  ["transaction_details"]=>
  array(1) {
    ["raw"]=>
    array(26) {
      ["TOKEN"]=>
      string(20) "EC-92F54162V8235432F"
      ["SUCCESSPAGEREDIRECTREQUESTED"]=>
      string(5) "false"
      ["TIMESTAMP"]=>
      string(20) "2018-01-08T23:30:29Z"
      ["CORRELATIONID"]=>
      string(13) "1f724acebe2a7"
      ["ACK"]=>
      string(7) "Success"
      ["VERSION"]=>
      string(4) "88.0"
      ["BUILD"]=>
      string(8) "42157829"
      ["INSURANCEOPTIONSELECTED"]=>
      string(5) "false"
      ["SHIPPINGOPTIONISDEFAULT"]=>
      string(5) "false"
      ["PAYMENTINFO_0_TRANSACTIONID"]=>
      string(17) "7KA97229BP454623U"
      ["PAYMENTINFO_0_TRANSACTIONTYPE"]=>
      string(4) "cart"
      ["PAYMENTINFO_0_PAYMENTTYPE"]=>
      string(7) "instant"
      ["PAYMENTINFO_0_ORDERTIME"]=>
      string(20) "2018-01-08T23:30:29Z"
      ["PAYMENTINFO_0_AMT"]=>
      string(4) "5.00"
      ["PAYMENTINFO_0_FEEAMT"]=>
      string(4) "0.42"
      ["PAYMENTINFO_0_TAXAMT"]=>
      string(4) "0.00"
      ["PAYMENTINFO_0_CURRENCYCODE"]=>
      string(3) "EUR"
      ["PAYMENTINFO_0_PAYMENTSTATUS"]=>
      string(9) "Completed"
      ["PAYMENTINFO_0_PENDINGREASON"]=>
      string(4) "None"
      ["PAYMENTINFO_0_REASONCODE"]=>
      string(4) "None"
      ["PAYMENTINFO_0_PROTECTIONELIGIBILITY"]=>
      string(8) "Eligible"
      ["PAYMENTINFO_0_PROTECTIONELIGIBILITYTYPE"]=>
      string(51) "ItemNotReceivedEligible,UnauthorizedPaymentEligible"
      ["PAYMENTINFO_0_SELLERPAYPALACCOUNTID"]=>
      string(28) "w.bahuaud-marchant@gmail.com"
      ["PAYMENTINFO_0_SECUREMERCHANTACCOUNTID"]=>
      string(13) "WLE8QSQ7MY7N8"
      ["PAYMENTINFO_0_ERRORCODE"]=>
      string(1) "0"
      ["PAYMENTINFO_0_ACK"]=>
      string(7) "Success"
    }
  }
}
int(2)
string(32) "7ca4b3f3d1fd5a697e1ee89efd1bab2e"
```