<?php
  require('../fpdf.php');

  class PDF extends FPDF
  {

    protected $B = 0;
    protected $I = 0;
    protected $U = 0;
    protected $HREF = '';

    function WriteHTML($html)
    {
      // Parseur HTML
      $html = str_replace("\n",' ',$html);
      $a = preg_split('/<(.*)>/U',$html,-1,PREG_SPLIT_DELIM_CAPTURE);
      foreach($a as $i=>$e)
      {
        if($i%2==0)
        {
          // Texte
          if($this->HREF)
            $this->PutLink($this->HREF,$e);
          else
            $this->Write(5,$e);
        }
        else
        {
          // Balise
          if($e[0]=='/')
            $this->CloseTag(strtoupper(substr($e,1)));
          else
          {
            // Extraction des attributs
            $a2 = explode(' ',$e);
            $tag = strtoupper(array_shift($a2));
            $attr = array();
            foreach($a2 as $v)
            {
              if(preg_match('/([^=]*)=["\']?([^"\']*)/',$v,$a3))
                $attr[strtoupper($a3[1])] = $a3[2];
            }
            $this->OpenTag($tag,$attr);
          }
        }
      }
    }

    function OpenTag($tag, $attr)
    {
      // Balise ouvrante
      if($tag=='B' || $tag=='I' || $tag=='U')
        $this->SetStyle($tag,true);
      if($tag=='A')
        $this->HREF = $attr['HREF'];
      if($tag=='BR')
        $this->Ln(5);
    }

    function CloseTag($tag)
    {
      // Balise fermante
      if($tag=='B' || $tag=='I' || $tag=='U')
        $this->SetStyle($tag,false);
      if($tag=='A')
        $this->HREF = '';
    }

    function SetStyle($tag, $enable)
    {
      // Modifie le style et sélectionne la police correspondante
      $this->$tag += ($enable ? 1 : -1);
      $style = '';
      foreach(array('B', 'I', 'U') as $s)
      {
        if($this->$s>0)
          $style .= $s;
      }
      $this->SetFont('',$style);
    }

    function PutLink($URL, $txt)
    {
      // Place un hyperlien
      $this->SetTextColor(0,0,255);
      $this->SetStyle('U',true);
      $this->Write(5,$txt,$URL);
      $this->SetStyle('U',false);
      $this->SetTextColor(0);
    }

    // En-tête
    function Header()
    {
      // Logo
      $this->Image('../images/logo-wptech.jpg',10,6,30);
      // Décalage à droite
      $this->Cell(80);
      // Police Arial gras 15
      $this->SetFont('Arial','B',15);
      // Titre
      $this->Cell(30,10,utf8_decode('Facture 01-20180101'),0,0,'C');
      // Saut de ligne
      $this->Ln(30);
       $this->SetFont('Arial','',10);
      // Organisme
      $this->WriteHTML(utf8_decode('<b>WPTech</b><br>18 rue du gardouet<br>44690 Maisdon-sur-Sèvre'));
      $this->Ln(15);
      $this->WriteHTML(utf8_decode('<b>Adressé à :</b><br>Nom de facturation<br>adresse de facturation'));
      $this->Ln(30);
    }

    // Pied de page
    function Footer()
    {
      // Positionnement à 1,5 cm du bas
      $this->SetY(-25);
      // Police Arial italique 10
      $this->SetFont('Arial','I',10);
      // Texte
      $this->MultiCell(200,5,utf8_decode('CGV... Lorem ipsum dolor sit amet, consectetur adipisicing elit. Iure quisquam necessitatibus eligendi animi neque iste pariatur. Lorem ipsum dolor sit amet, consectetur adipisicing elit. Iure quisquam necessitatibus eligendi animi neque iste pariatur. Lorem ipsum dolor sit amet, consectetur adipisicing elit. Iure quisquam necessitatibus eligendi animi neque iste pariatur'),0,'L',false);
      // Saut de ligne
      //$this->Ln(5);
      // Numéro de page
      //$this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
    }

  }

  // Instanciation de la classe dérivée
  $pdf = new PDF();
  $pdf->AliasNbPages();
  $pdf->AddPage();

  // Titres
  $pdf->SetFont('Arial','B',12);
  $w = array(45, 45, 45, 45);
  $pdf->Cell($w[0],10,utf8_decode(''),0,0,'C',false);
  $pdf->Cell($w[1],10,utf8_decode('Quatité'),0,0,'C',false);
  $pdf->Cell($w[2],10,utf8_decode('Prix unitaire'),0,0,'C',false);
  $pdf->Cell($w[3],10,utf8_decode('Total'),0,0,'C',false);
  $pdf->Ln(10);

  // Billet journée
  $pdf->SetFont('Arial','',12);
  $w = array(45, 45, 45, 45);
  $pdf->Cell($w[0],10,utf8_decode('Billet journée'),1,0,'C',false);
  $pdf->Cell($w[1],10,utf8_decode('6'),1,0,'C',false);
  $pdf->Cell($w[2],10,utf8_decode('30'),1,0,'C',false);
  $pdf->Cell($w[3],10,utf8_decode('160'),1,0,'C',false);
  $pdf->Ln(10);

  // Billet after
  $w = array(45, 45, 45, 45);
  $pdf->Cell($w[0],10,utf8_decode('Billet after'),1,0,'C',false);
  $pdf->Cell($w[1],10,utf8_decode('3'),1,0,'C',false);
  $pdf->Cell($w[2],10,utf8_decode('40'),1,0,'C',false);
  $pdf->Cell($w[3],10,utf8_decode('120'),1,0,'C',false);
  $pdf->Ln(20);

  // Total
  $pdf->SetFont('Arial','B',12);
  $w = array(45, 45, 45, 45);
  $pdf->Cell($w[0],10,utf8_decode(''),0,0,'C',false);
  $pdf->Cell($w[1],10,utf8_decode(''),0,0,'C',false);
  $pdf->Cell($w[2],10,utf8_decode('Total à payer :'),0,0,'C',false);
  $pdf->Cell($w[3],10,utf8_decode('280'),0,0,'C',false);
  $pdf->Ln(30);

  // Mot
  $pdf->SetFont('Arial','',12);
  $pdf->MultiCell(190,5,utf8_decode('Mot de remerciement.... Lorem ipsum dolor sit amet, consectetur adipisicing elit. Iure quisquam necessitatibus eligendi animi neque iste pariatur. Lorem ipsum dolor sit amet, consectetur adipisicing elit. Iure quisquam necessitatibus eligendi animi neque iste pariatur. Lorem ipsum dolor sit amet, consectetur adipisicing elit. Iure quisquam necessitatibus eligendi animi neque iste pariatur'),0,'L',false);
  $pdf->Output();
?>