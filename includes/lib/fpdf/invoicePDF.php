<?php

// v1.0 du 12/12/13 - Patrice Kuntz - blog.niap3d.com
require('fpdf.php');
class invoicePDF extends FPDF{
// Content.
private $elementLst;
// Logo.
private $logoUrl;
private $logoPosX;
private $logoPosY;
private $logoWidth;
// Product.
private $productHead = array();
private $productWidth;
private $productLst = array();
// Totals.
private $totalHead = array();
private $totalWidth;
private $totalLst = array();
private $vatTotalHead = array();
private $vatTotalWidth;
private $vatTotalLst = array();

private $afterContent = array();
// Template.
public $template;

/**
 * Constructor.
 *
 * @param string $atextAdr1   Organization's address.
 * @param string $atextAdr2   Customer's address.
 * @param string $aFooter     Footer text.
 */
function __construct($atextAdr1='', $atextAdr2='', $aFooter='') {
	parent::__construct();
	$this->SetMargins(0, 0, 0);
	$this->SetFont('Helvetica', '', 10);
	$this->SetCreator('CampTix Invoices');
	$this->SetAuthor('CampTix Invoices');
	$this->AliasNbPages();
	$this->template = array();
	$this->template['productHead'] = $this->templateArrayInit();
	$this->template['product'] = $this->templateArrayInit();
	$this->template['afterContent'] = $this->templateArrayInit();
	$this->template['totalHead'] = $this->templateArrayInit();
	$this->template['total'] = $this->templateArrayInit();
	$this->template['vatTotalHead'] = $this->templateArrayInit();
	$this->template['vatTotal'] = $this->templateArrayInit();
	$this->elementLst = array('header'=> array(), 'content'=> array(), 'footer'=>array());
	$this->elementAdd($atextAdr1, 'header', 'header');
	$this->elementAdd($atextAdr2, 'client', 'header');
	$this->elementAdd($aFooter, 'footer', 'footer');
}

/**
 * Initializes a template.
 */
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
/**
 * Initializes the logo.
 *
 * $aUrl : Image file URL (PNG, JPEG or GIF).
 * $aWidth : Image display width.
 * $aX : Position from the left edge.
 * $aY : Position from the top edge.
 */
public function setLogo($aUrl='', $aWidth=30, $aX=20, $aY=20){
	$this->logoUrl = $aUrl;
	$this->logoPosX = $aX;
	$this->logoPosY = $aY;
	$this->logoWidth = $aWidth;
}
/**
 * Initializes the invoice number.
 *
 * @param string $aFacture   Invoice number.
 * @param string $aPage      String to display in front of the page number.
 * @param string $aDate      Invoice date.
 */
public function initFacture($aFacture='', $aDate='', $aPage=''){
	$this->elementAdd($aFacture, 'infoFacture', 'header');
	$this->elementAdd($aDate, 'infoDate', 'header');
	$this->elementAdd($aPage, 'infoPage', 'header');
	$this->SetSubject($aFacture, true);
}
/**
 * Content list.
 *
 * @param string $aTxt   Text content of the current block.
 * @param int $aid       Unique ID to link this element to the template.
 */
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
/**
 * Products table header.
 *
 * @param string $aStr     Column name.
 * @param string $aWidth   Column width.
 * @param string $aAlign   Text alignment ('C' for center).
 */
public function productHeaderAddRow($aStr, $aWidth='30', $aAlign='C'){
	if(empty($aStr)) return 0;
	$this->productHead[] = array('text'=>$aStr, 'width'=>$aWidth, 'align'=>$aAlign);
	$this->productWidth += intval($aWidth);
}
/**
 * Products table.
 *
 * @param array $aLst   List of info to display.
*/
public function productAdd($aLst=''){
	if(!empty($aLst)) $this->productLst[] = $aLst;
}
/**
 * Totals table header.
 *
 * @param string $aWidth   Column width.
 * @param string $aAlign   Text alignment ('C' for center).
 */
public function totalHeaderAddRow($aWidth='30', $aAlign='C'){
	$this->totalHead[] = array('width'=>$aWidth, 'align'=>$aAlign);
	$this->totalWidth += intval($aWidth);
}
public function vatTotalHeaderAddRow($aWidth='30', $aAlign='C'){
	$this->vatTotalHead[] = array('width'=>$aWidth, 'align'=>$aAlign);
	$this->vatTotalWidth += intval($aWidth);
}

/**
 * Totals table.
 *
 * @param array $aLst   List of info to display.
 */
public function totalAdd($aLst=''){
	if(!empty($aLst)) $this->totalLst[] = $aLst;
}
public function vatTotalAdd($aLst=''){
	if(!empty($aLst)) $this->vatTotalLst[] = $aLst;
}

public function afterContentAdd($aLst='') {
	if(!empty($aLst)) $this->afterContent = $aLst;
}
/**
 * Prepares the PDF content.
 */
public function buildPDF() {
	// Adds a new page (including header and footer)
	$this->AddPage();
	$yMax = $this->GetY();
	foreach($this->elementLst['content'] as $v){
		$yMax = max($yMax, $this->prepareLine($v['text'], $this->template[$v['id']]));
	}
	$this->SetY($yMax);
	// Displays products.
	if(!empty($this->productLst)){
		$tplt = $this->template['product'];
		// Initializes margins.
		$this->lMargin = $tplt['padding'][3]+$tplt['margin'][3];
		$this->rMargin = $tplt['padding'][1]+$tplt['margin'][1];
		$this->SetFont('', '', $tplt['fontSize']);
		$nb = 0;
		$fillRect = 1;
		foreach($this->productLst as $r){
			$nb++;
			$posMaxY = 0;
			$nbLineMax = 0;
			// Looks for the cell with the largest content.
			foreach($r as $k=>$v){
				$nbLineMax = max($nbLineMax, $this->NbLines($this->productHead[$k]['width'], $v));
			}
			$cellHeightMax = $nbLineMax*$tplt['lineHeight'];
			// Makes sure there is enough room for a new line. Creates a new page is there is not.
			if($this->GetY()+$cellHeightMax>$this->PageBreakTrigger) $this->AddPage();
			// Alternates colors on each row.
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
	// Displays total.
	if(!empty($this->vatTotalLst)){
		$this->prepareLine('', $this->template['vatTotalHead'], 0);
		$tplt = $this->template['vatTotal'];
		foreach($this->vatTotalLst as $r){
			$this->buildLine($this->vatTotalWidth, $tplt['lineHeight'], $this->vatTotalHead, $r, $tplt, $tplt['color'], $tplt['backgroundColor']);
		}
	}
	if(!empty($this->totalLst)){
		$this->prepareLine('', $this->template['totalHead'], 0);
		$tplt = $this->template['total'];
		foreach($this->totalLst as $r){
			$this->buildLine($this->totalWidth, $tplt['lineHeight'], $this->totalHead, $r, $tplt, $tplt['color'], $tplt['backgroundColor']);
		}
	}

	if(!empty($this->afterContent)){
		foreach($this->afterContent as $r){
			$this->prepareLine($r, $this->template['afterContent'], 0);
		}
	}

}
/**
 * Prepares a single line for display.
 *
 * @param string $aText   Text to display.
 * @param array $aTplt    Template.
 */
private function prepareLine($aText, $aTplt, $aInitPos=1){
	// Initialize the position.
	if($aInitPos) $this->SetXY(0, 0);
	// Computes the width. (file width - left and right margins - left and right padding)
	$w = $this->w-$aTplt['margin'][1]-$aTplt['margin'][3]-$aTplt['padding'][1]-$aTplt['padding'][3];
	// Sets the font to compute the number of lines.
	$this->SetFont('', '', $aTplt['fontSize']);
	// Computes the number of line (approximately)
	$nbLineMax = $this->NbLines($w, $aText);
	// Computes the height (number of lines * line height of the template)
	$h = $nbLineMax*$aTplt['lineHeight'];
	// Creates the line.
	$this->buildLine($w, $h, array(0=>array('width'=>$w, 'align'=>$aTplt['align'])), array(0=>$aText), $aTplt, $aTplt['color'], $aTplt['backgroundColor']);
	// Returns the current Y position.
	return $this->GetY();
}
/**
 * Adds a line with a background color.
 *
 * @param int $aW            Width.
 * @param int $aH            Height.
 * @param string $aHeader    Header.
 * @param string $aContent   Content.
 * @param array $aTplt       Template.
 * @param array $aColorF     Text color.
 * @param array $aColorBk    Background color.
 */
private function buildLine($aW, $aH, $aHeader='', $aContent='', $aTplt='', $aColorF=array('r'=>0, 'g'=>0, 'b'=>0), $aColorBk=array('r'=>255, 'g'=>255, 'b'=>255)){
	if(empty($aHeader) || empty($aTplt)) return 0;
	if(empty($aContent)) $aContent = $aHeader;
	// Initializes the font and color.
	$this->SetFont('', $aTplt['fontFace'], $aTplt['fontSize']);
	$this->SetTextColor($aColorF['r'], $aColorF['g'], $aColorF['b']);
	// Draws the background.
	if(!($aColorBk['r']==255 && $aColorBk['g']==255 && $aColorBk['b']==255)){
		$this->SetFillColor($aColorBk['r'], $aColorBk['g'], $aColorBk['b']);
		$this->Rect($aTplt['margin'][3], $this->GetY()+$aTplt['margin'][0], $aW+$aTplt['padding'][1]+$aTplt['padding'][3], $aH+$aTplt['padding'][0]+$aTplt['padding'][2], 'F');
	}
	// Initializes and saves the position.
	$this->SetXY($aTplt['margin'][3]+$aTplt['padding'][3], $this->GetY()+$aTplt['margin'][0]+$aTplt['padding'][0]);
	$posX = $this->GetX();
	$posY = $this->GetY();
	$posMaxY = 0;
	// Displays the line.
	foreach($aContent as $k=>$v){
		$this->MultiCell($aHeader[$k]['width'], $aTplt['lineHeight'], utf8_decode(is_array($v) ? $v['text']:$v), 0, $aHeader[$k]['align']);
		// Saves the maximum height.
		if($this->GetY()>$posMaxY) $posMaxY = $this->GetY();
		// Computes the new X position by accounting for the column's width.
		$posX += $aHeader[$k]['width'];
		// MultiCell adds a line break ; the position needs to be reset.
		$this->SetXY($posX, $posY);
	}
	// Reset the position below the last created line, at [left margin] from the left edge.
	$this->SetXY($this->lMargin, $posMaxY+$aTplt['padding'][2]+$aTplt['margin'][2]);
}
/**
 * Table with MultiCells.
 *
 * @link http://www.fpdf.org/en/script/script3.php
 */
private function NbLines($w, $txt){
	// Computes how many lines a MultiCell span if its width is w.
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
/**
 * Header.
 */
function Header(){
	if(!empty($this->logoUrl)){
		$this->Image($this->logoUrl, $this->logoPosX, $this->logoPosY, $this->logoWidth);
		$this->Ln(12);
	}
	// Header elements.
	foreach($this->elementLst['header'] as $v){
		$yMax = ( isset( $yMax ) ) ? $yMax : 0;
		$yMax = max($yMax, $this->prepareLine($v['text'], $this->template[$v['id']]));
	}
	// Table header.
	if(!empty($this->productHead)){
		$this->SetY($yMax);
		$tplt = $this->template['productHead'];
		$this->buildLine($this->productWidth, $tplt['lineHeight'], $this->productHead, $this->productHead, $tplt, $tplt['color'], $tplt['backgroundColor']);
	}
}
/**
 * Footer.
 */
function Footer(){
	foreach($this->elementLst['footer'] as $v){
		$this->prepareLine($v['text'], $this->template[$v['id']]);
	}
}
}
