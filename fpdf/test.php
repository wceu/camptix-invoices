<?php
require('facturePDF.php');

// #1 initialise les informations de base
//
// adresse de l'entreprise qui émet la facture
$adresse = "WPTech 18 rue du gardouet 44690 Maisdon-sur-Sèvre";
// adresse du client
$adresseClient = "Robert Meinard 3 place de Clichy 88154 Nancy le port";
// initialise l'objet facturePDF
$pdf = new facturePDF($adresse, $adresseClient, "CGV... Lorem ipsum dolor sit amet, consectetur adipisicing elit. Iure quisquam necessitatibus eligendi animi neque iste pariatur. Lorem ipsum dolor sit amet, consectetur adipisicing elit. Iure quisquam necessitatibus eligendi animi neque iste pariatur. Lorem ipsum dolor sit amet, consectetur adipisicing elit. Iure quisquam necessitatibus eligendi animi neque iste pariatur");
// défini le logo
$pdf->setLogo('logo-wptech.jpg');
// entete des produits
$pdf->productHeaderAddRow('Billets', 45, 'L');
$pdf->productHeaderAddRow('Quantité', 45, 'C');
$pdf->productHeaderAddRow('Prix unitaire', 45, 'C');
$pdf->productHeaderAddRow('Total', 45, 'C');
// entete des totaux
$pdf->totalHeaderAddRow(30, 'L');
$pdf->totalHeaderAddRow(30, 'C');
// element personnalisé
$pdf->elementAdd('', 'traitEnteteProduit', 'content');
$pdf->elementAdd('', 'traitBas', 'footer');

// #2 Créer une facture
//
// numéro de facture, date, texte avant le numéro de page
$pdf->initFacture("Facture n° ".mt_rand(1, 99999)."-".mt_rand(1, 99999), "", "");
// produit
$pdf->productAdd(array('Billet journée', '6', '30', '160'));

// ligne des totaux
$pdf->totalAdd(array('Total', '165 EUR'));

// #3 Importe le gabarit
//
require('gabarit'.intval($_GET['id']).'.php');

// #4 Finalisation
// construit le PDF
$pdf->buildPDF();
// télécharge le fichier
$pdf->Output('Facture.pdf', $_GET['download'] ? 'D':'I');
