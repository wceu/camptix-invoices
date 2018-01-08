# Camptix invoices #

Camptix invoices est un plugin permettant de générer des factures automatiques pour chaque achat de ticket via camptix.

## À faire ##

* ~Créer une sous page de réglage pour définir la numérotation de facture~
* Lors de chaque commande, attacher une meta de numéro de facture autoincrémentée
* Utiliser la méthode [https://blog.niap3d.com/fr/4,10,news-8-Creer-un-fichier-PDF-avec-PHP.html](https://blog.niap3d.com/fr/4,10,news-8-Creer-un-fichier-PDF-avec-PHP.html) pour générer une facture
	- Sur un endpoint admin-post.php par exemple
	- Attacher la facture en tant que pièce jointe dans l’email de reçu
* placer des hooks pour injecter les informations relatives au marchand
* Permettre la cutomisation du modèle de la facture
* Ajouter un bouton en back pour regéner les factures (individuellement ou en bulk)