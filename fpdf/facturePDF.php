<?php
// 15/08/13 - Correction ligne 30
// v1.0 du 12/12/13 - Patrice Kuntz - blog.niap3d.com
require('fpdf.php');
class facturePDF extends FPDF{
// contenu
private $elementLst;
// logo
private $logoUrl;
private $logoPosX;
private $logoPosY;
private $logoWidth;
// produit
private $productHead = array();
private $productWidth;
private $productLst = array();
// totaux
private $totalHead = array();
private $totalWidth;
private $totalLst = array();
// gabarit
public $template;
// - - - - - - - - - - - - - - - - - - - - - - - - -
// constructeur
//
// $atextAdr1 : adresse de l'entreprise
// $atextAdr2 : adresse du client
// $aFooter : texte du pied de page
function facturePDF($atextAdr1='', $atextAdr2='', $aFooter=''){
	parent::__construct();
	$this->SetMargins(0, 0, 0);
	$this->SetFont('Helvetica', '', 11);
	$this->SetCreator('CReSeL CMS, module Boutique en ligne');
	$this->SetAuthor('CReSeL');
	$this->AliasNbPages();
	$this->template = array();
	$this->template['productHead'] = $this->templateArrayInit();
	$this->template['product'] = $this->templateArrayInit();
	$this->template['totalHead'] = $this->templateArrayInit();
	$this->template['total'] = $this->templateArrayInit();
	$this->elementLst = array('header'=> array(), 'content'=> array(), 'footer'=>array());
	$this->elementAdd($atextAdr1, 'header', 'header');
	$this->elementAdd($atextAdr2, 'client', 'header');
	$this->elementAdd($aFooter, 'footer', 'footer');
}
// - - - - - - - - - - - - - - - - - - - - - - - - -
// initialise un gabarit
//
function templateArrayInit(){
	$r = array();
	$r['lineHeight'] = 6;
	$r['fontSize'] = 12;
	$r['fontFace'] = '';
	$r['color'] = array('r'=>0, 'g'=>0, 'b'=>0);
	$r['backgroundColor'] = array('r'=>255, 'g'=>255, 'b'=>255);
	$r['align'] = 'L';
	$r['margin'] = array(0, 0, 0, 0);
	$r['padding'] = array(0, 0, 0, 0);
	return $r;
}
// - - - - - - - - - - - - - - - - - - - - - - - - -
// initialise le logo
//
// $aUrl : adresse du fichier PNG, JPEG ou GIF
// $aWidth : largeur à l'affichage
// $aX : position depuis le bord gauche
// $aY : position depuis le bord haut
public function setLogo($aUrl='', $aWidth=30, $aX=10, $aY=6){
	$this->logoUrl = $aUrl;
	$this->logoPosX = $aX;
	$this->logoPosY = $aY;
	$this->logoWidth = $aWidth;
}
// - - - - - - - - - - - - - - - - - - - - - - - - -
// initialise le numéro de facture
//
// $aFacture : numéro de facture
// $aPage : texte à afiche devant le numéro de page
// $aDate : date d'émission de la facture
public function initFacture($aFacture='', $aDate='', $aPage=''){
	$this->elementAdd($aFacture, 'infoFacture', 'header');
	$this->elementAdd($aDate, 'infoDate', 'header');
	$this->elementAdd($aPage, 'infoPage', 'header');
	$this->SetSubject($aFacture, true);
}
// - - - - - - - - - - - - - - - - - - - - - - - - -
// liste de contenu
//
// $aTxt : texte à afficher dans le bloc
// $aid : identifiant qui sert à relier l'élément au gabarit
public function elementAdd($aTxt, $aId, $aZone='content'){
	if(empty($aId)) return 0;
	switch($aZone){
	case 'header' :
	case 'content' :
	case 'footer' :
		$this->elementLst[$aZone][] = array('text'=>$aTxt, 'id'=>$aId);
		$this->template[$aId] = $this->templateArrayInit();
		break;
	}
}
// - - - - - - - - - - - - - - - - - - - - - - - - -
// entete du tableau des produits
//
// $aStr : nom de la colonne
// $aWidth : largeur de la colonne
// $aHeaderALign : alignement de la cellule d'entête
// $aContentAlign : alignement des celules de contenu
public function productHeaderAddRow($aStr, $aWidth='30', $aAlign='C'){
	if(empty($aStr)) return 0;
	$this->productHead[] = array('text'=>$aStr, 'width'=>$aWidth, 'align'=>$aAlign);
	$this->productWidth += intval($aWidth);
}
// - - - - - - - - - - - - - - - - - - - - - - - - -
// tableau de produit
//
// $aLst est une array qui contient les infos à afficher
public function productAdd($aLst=''){
	if(!empty($aLst)) $this->productLst[] = $aLst;
}
// - - - - - - - - - - - - - - - - - - - - - - - - -
// entete du tableau des totaux
//
// $aVal : valeur
// $aContentAlign : alignement des celules de contenu
public function totalHeaderAddRow($aWidth='30', $aAlign='C'){
	$this->totalHead[] = array('width'=>$aWidth, 'align'=>$aAlign);
	$this->totalWidth += intval($aWidth);
}
// - - - - - - - - - - - - - - - - - - - - - - - - -
// tableau de totaux
//
// $aLst est une array qui contient les infos à afficher
public function totalAdd($aLst=''){
	if(!empty($aLst)) $this->totalLst[] = $aLst;
}
// - - - - - - - - - - - - - - - - - - - - - - - - -
// prépare le contenu du PDF
//
public function buildPDF(){
	// ajoute une nouvelle page (avec entête et pied de page)
	$this->AddPage();
	$yMax = $this->GetY();
	foreach($this->elementLst['content'] as $v){
		$yMax = max($yMax, $this->prepareLine($v['text'], $this->template[$v['id']]));
	}
	$this->SetY($yMax);
	// affiche les produits
	if(!empty($this->productLst)){
		$tplt = $this->template['product'];
		// initialise les marges
		$this->lMargin = $tplt['padding'][3]+$tplt['margin'][3];
		$this->rMargin = $tplt['padding'][1]+$tplt['margin'][1];
		$this->SetFont('', '', $tplt['fontSize']);
		$nb = 0;
		$fillRect = 1;
		foreach($this->productLst as $r){
			$nb++;
			$posMaxY = 0;
			$nbLineMax = 0;
			// cherche la cellule qui a le plus de contenu
			foreach($r as $k=>$v){
				$nbLineMax = max($nbLineMax, $this->NbLines($this->productHead[$k]['width'], $v));
			}
			$cellHeightMax = $nbLineMax*$tplt['lineHeight'];
			// vérifie si on a la place pour ajouter la ligne. sinon créer une nouvelle page
			if($this->GetY()+$cellHeightMax>$this->PageBreakTrigger) $this->AddPage();
			// change les couleurs
			if($nb%2!=0){
				$bg = $tplt['backgroundColor'];
				$fg = $tplt['color'];
			}else{
				$bg = $tplt['backgroundColor2'];
				$fg = $tplt['color2'];
			}
			$tplt = $this->template['product'];
			$this->buildLine($this->productWidth, $cellHeightMax, $this->productHead, $r, $tplt, $fg, $bg);
		}
	}
	// affiche le total
	if(!empty($this->totalLst)){
		$this->prepareLine('', $this->template['totalHead'], 0);
		$tplt = $this->template['total'];
		foreach($this->totalLst as $r){
			$this->buildLine($this->totalWidth, $tplt['lineHeight'], $this->totalHead, $r, $tplt, $tplt['color'], $tplt['backgroundColor']);
		}
	}
}
// - - - - - - - - - - - - - - - - - - - - - - - - -
// prépare l'affichage d'une ligne simple
//
// $aText : texte à afficher
// $aTplt : gabarit à utiliser
private function prepareLine($aText, $aTplt, $aInitPos=1){
	// initialise la position 
	if($aInitPos) $this->SetXY(0, 0);
	// calcul la largeur (dimension du fichier - marge gauche et droite - padding gauche et droit)
	$w = $this->w-$aTplt['margin'][1]-$aTplt['margin'][3]-$aTplt['padding'][1]-$aTplt['padding'][3];
	// définit la typo pour le calcul du nombre de ligne
	$this->SetFont('', '', $aTplt['fontSize']);
	// calcul le nombre de ligne (à peu près)
	$nbLineMax = $this->NbLines($w, $aText);
	// calcul la hauteur (nombre de ligne x hauteur de ligne du gabarit)
	$h = $nbLineMax*$aTplt['lineHeight'];
	// créer la ligne
	$this->buildLine($w, $h, array(0=>array('width'=>$w, 'align'=>$aTplt['align'])), array(0=>$aText), $aTplt, $aTplt['color'], $aTplt['backgroundColor']);
	// renvoi la position actuelle
	return $this->GetY();
}
// - - - - - - - - - - - - - - - - - - - - - - - - -
// ajoute une ligne avec couleur de fond
//
// $aW : largeur
// $aH : hauteur
// $aHeader : entete
// $aContent : contenu
// $aTplt : gabarit
// $aColorF : couleur de la police
// $aColorBk : couleur de fond
private function buildLine($aW, $aH, $aHeader='', $aContent='', $aTplt='', $aColorF=array('r'=>0, 'g'=>0, 'b'=>0), $aColorBk=array('r'=>255, 'g'=>255, 'b'=>255)){
	if(empty($aHeader) || empty($aTplt)) return 0;
	if(empty($aContent)) $aContent = $aHeader;
	// initialise la typo et les couleurs
	$this->SetFont('', $aTplt['fontFace'], $aTplt['fontSize']);
	$this->SetTextColor($aColorF['r'], $aColorF['g'], $aColorF['b']);
	// dessine le fond
	if(!($aColorBk['r']==255 && $aColorBk['g']==255 && $aColorBk['b']==255)){
		$this->SetFillColor($aColorBk['r'], $aColorBk['g'], $aColorBk['b']);
		$this->Rect($aTplt['margin'][3], $this->GetY()+$aTplt['margin'][0], $aW+$aTplt['padding'][1]+$aTplt['padding'][3], $aH+$aTplt['padding'][0]+$aTplt['padding'][2], 'F');
	}
	// initialise et sauvegarde la position
	$this->SetXY($aTplt['margin'][3]+$aTplt['padding'][3], $this->GetY()+$aTplt['margin'][0]+$aTplt['padding'][0]);
	$posX = $this->GetX();
	$posY = $this->GetY();
	$posMaxY = 0;
	// affiche la ligne
	foreach($aContent as $k=>$v){
		$this->MultiCell($aHeader[$k]['width'], $aTplt['lineHeight'], utf8_decode(is_array($v) ? $v['text']:$v), 0, $aHeader[$k]['align']);
		// enregistre la hauteur max
		if($this->GetY()>$posMaxY) $posMaxY = $this->GetY();
		// calcul la nouvelle position X en rajoutant la largeur de colonne
		$posX += $aHeader[$k]['width'];
		// MultiCell crée un renvoi de ligne. Il faut se replacer
		$this->SetXY($posX, $posY);
	}
	// se replace à la marge gauche et sous la dernière ligne crée
	$this->SetXY($this->lMargin, $posMaxY+$aTplt['padding'][2]+$aTplt['margin'][2]);
}
// - - - - - - - - - - - - - - - - - - - - - - - - -
// Tableau avec MultiCells : http://www.fpdf.org/fr/script/script3.php
private function NbLines($w, $txt){
	//Calcule le nombre de lignes qu'occupe un MultiCell de largeur w
	$cw = $this->CurrentFont['cw'];
	if($w==0) $w = $this->w-$this->rMargin-$this->x;
	$wmax = ($w-2*$this->cMargin)*1000/$this->FontSize;
	$s = str_replace("\r",'',$txt);
	$nb = strlen($s);
	if($nb>0 and $s[$nb-1]=="\n") $nb--;
	$sep = -1;
	$i = 0;
	$j = 0;
	$l = 0;
	$nl = 1;
	while($i<$nb){
		$c=$s[$i];
		if($c=="\n"){
			$i++;
			$sep = -1;
			$j = $i;
			$l = 0;
			$nl++;
			continue;
		}
		if($c==' ') $sep = $i;
		$l += $cw[$c];
		if($l>$wmax){
			if($sep==-1){
				if($i==$j)
					$i++;
			}else $i = $sep+1;
			$sep = -1;
			$j = $i;
			$l = 0;
			$nl++;
		}else $i++;
	}
	return $nl;
}
// - - - - - - - - - - - - - - - - - - - - - - - - -
// entete
//
function Header(){
	if(!empty($this->logoUrl)){
		$this->Image($this->logoUrl, $this->logoPosX, $this->logoPosY, $this->logoWidth);
		$this->Ln(12);
	}
	// elements d'entete
	foreach($this->elementLst['header'] as $v){
		$yMax = max($yMax, $this->prepareLine($v['text'], $this->template[$v['id']]));
	}
	// entete du tableau
	if(!empty($this->productHead)){
		$this->SetY($yMax);
		$tplt = $this->template['productHead'];
		$this->buildLine($this->productWidth, $tplt['lineHeight'], $this->productHead, $this->productHead, $tplt, $tplt['color'], $tplt['backgroundColor']);
	}
}
// - - - - - - - - - - - - - - - - - - - - - - - - -
// pied de page
//
function Footer(){
	foreach($this->elementLst['footer'] as $v){
		$this->prepareLine($v['text'], $this->template[$v['id']]);
	}
}
// - - - - - - - - - - - - - - - - - - - - - - - - -
}
?>