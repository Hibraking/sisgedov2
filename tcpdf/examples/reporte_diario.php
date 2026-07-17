<?php
//============================================================================
// SISGEDO — Reporte diario de documentos EXTERNOS registrados (mesa de partes)
// Parámetros: ?f_inicio=YYYY-MM-DD&f_fin=YYYY-MM-DD
//============================================================================

require_once('tcpdf_include.php');
require_once '../../conexion/conexion.php';

// PDF (salida binaria): ocultar avisos en pantalla para no corromper la salida.
// Los errores se siguen registrando en el log.
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

session_start();
                
                    if (!isset($_SESSION['usuario'])) {
                        header("Location: ../../login.php");
                        return;
                }
// Extend the TCPDF class to create custom Header and Footer
class MYPDF extends TCPDF {

	//Page header
	public function Header() {
		// Logo
		$image_file = K_PATH_IMAGES.'logo huaytara.png';
		$this->Image($image_file, 8, 8, 35,10, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
		// Set font
		$this->SetFont('helvetica', 'B', 12);
		// Title
		$this->Cell(0, 15, 'MUNICIPALIDAD PROVINCIAL DE HUAYTARA', 0, false, 'C', 0, '', 0, false, 'C', 'M');
                
	}

	// Page footer
	public function Footer() {
		// Position at 15 mm from bottom
		$this->SetY(-15);
		// Set font
		$this->SetFont('helvetica', 'I', 8);
		// Page number
		$this->Cell(0, 10, 'Página '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
	}
           
        
}
//function for get data
function lista_datos($inicio,$fin){
         try {
             $conexion=new Conexion();
                $conectado=$conexion->conectar();
                $sql="select registro_documentos.idregistro_documentos as idregd,registro_documentos.nro_exp,concat(remitente.apellidos,', ',remitente.nombres) as ape_nom,
                      tipo_tram.nombre as tipo_tram,documentos.nombre_documento as tipo_documento,
                      registro_documentos.numero_docu,registro_documentos.asunto,registro_documentos.parasu,
                      registro_documentos.observacion,registro_documentos.folios,
                      date_format(date_add(registro_documentos.fecha_registro, INTERVAL 0 hour),'%d/%m/%y %h:%i %p') as fecha_registro,
                      oficinas.nombre as derivar,
                      (select date_format(date_add(min(movimientos.fecha_hora), INTERVAL 0 hour),'%d/%m/%y %h:%i %p')
                       from movimientos where idregdocs=idregd) as fecha_recepcion
                      from registro_documentos
                      left join documentos on registro_documentos.iddocumento=documentos.iddocumentos
                      inner join remitente on registro_documentos.idremitente=remitente.idremitente
                      inner join tipo_tram on registro_documentos.idtipo_tramite=tipo_tram.idtipo_tram
                      inner join oficinas on registro_documentos.idoficina=oficinas.idoficinas
                      where date(registro_documentos.fecha_registro) between ? and ?
                      order by registro_documentos.nro_exp asc;";
                $result=$conectado->prepare($sql);
                $result->execute([$inicio,$fin]);
                $resultado="<table border=\"1\" >"
                        . "<thead>"
                        . "<tr style=\"background-color:#B4764B;\">"
                        . "<th width=\"45\" align=\"center\"><b>Exp.</b></th>"
                        . "<th width=\"160\" align=\"center\"><b>Remitente</b></th>"
                        
                        . "<th width=\"80\" align=\"center\"><b>Tipo Doc.</b></th>"
                        . "<th width=\"40\" align=\"center\"><b>N° Doc.</b></th>"
                        . "<th width=\"200\" align=\"center\"><b>Asunto</b></th>"
                        . "<th width=\"100\" align=\"center\"><b>Trámite</b></th>"
                        . "<th width=\"90\" align=\"center\"><b>Observación</b></th>"
                        . "<th width=\"35\" align=\"center\"><b>Folios</b></th>"
                        . "<th width=\"50\" align=\"center\"><b>Fecha Registro</b></th>"
                        . "<th width=\"120\" align=\"center\"><b>Oficina de atención</b></th>"
                        . "<th width=\"50\" align=\"center\"><b>Fecha Recepción</b></th>"
                        . "</tr>"
                        . "</thead>";
                foreach ($result as $filas) {
                       $resultado.="<tr>";
                        $resultado.="<td width=\"45\" align=\"center\">".$filas[1]."</td>";
                        $resultado.="<td width=\"160\" align=\"left\">".$filas[2]."</td>";
                        
                        $resultado.="<td width=\"80\" align=\"center\">".$filas[4]."</td>";
                        $resultado.="<td width=\"40\" align=\"center\">".$filas[5]."</td>";
                        $resultado.="<td width=\"200\" align=\"justify\">".$filas[6]."</td>";
                        $resultado.="<td width=\"100\" align=\"left\">".$filas[3]."</td>";
                        $resultado.="<td width=\"90\" align=\"center\">".$filas[8]."</td>";
                        $resultado.="<td width=\"35\" align=\"center\">".$filas[9]."</td>";
                        $resultado.="<td width=\"50\" align=\"center\">".$filas[10]."</td>";
                        $resultado.="<td width=\"120\" align=\"center\">".$filas[11]."</td>";
                        $resultado.="<td width=\"50\" align=\"center\">".$filas[12]."</td>";
                       $resultado.="</tr>";
                }
                $resultado.="</table>";
             return $resultado;   
         } catch (Exception $exc) {
             echo $exc->getTraceAsString();
             die();
         }
        }
// create new PDF document
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Municipalidad Provincial de Huaytará');
$pdf->SetTitle('Reporte diario');
$pdf->SetSubject('SISTEMA DE TRAMITE DOCUMENTARIO');
$pdf->SetKeywords('SISGEDO, Trámite Documentario, Huaytará');

// set default header data
$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

// set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// set some language-dependent strings (optional)
if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
	require_once(dirname(__FILE__).'/lang/eng.php');
	$pdf->setLanguageArray($l);
}

// ---------------------------------------------------------

// set font

$pdf->SetFont('times', 'B', 12);
// add a page
$pdf->AddPage('L', 'A4');
$pdf->Write(0, 'REGISTRO DE DOCUMENTOS', '', 0, 'C', true, 0, false, false, 0);
$pdf->SetFont('times', '', 8);

// writeHTML($html, $ln=true, $fill=false, $reseth=false, $cell=false, $align='')
// writeHTMLCell($w, $h, $x, $y, $html='', $border=0, $ln=0, $fill=0, $reseth=true, $align='', $autopadding=true)

// create some HTML content
$inicio=$_GET['f_inicio'];
$fin=$_GET['f_fin'];
        $html= lista_datos($inicio,$fin);
//echo $_GET['exp'];
// output the HTML content
$pdf->writeHTML($html, true, false, true, false, '');

// ---------------------------------------------------------

//Close and output PDF document
$pdf->Output('Cargo.pdf', 'I');

//============================================================+
// END OF FILE
//============================================================+
                                                                                                                                                                                                                                                                                                                                                                                                                                                                