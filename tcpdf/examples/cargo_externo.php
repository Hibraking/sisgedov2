<?php
//============================================================================
// SISGEDO — Cargo de recepción de documento EXTERNO (mesa de partes)
// Parámetro: ?exp=<nro_exp>
// Diseño clásico: DOS copias del cuadro de datos + cuadrícula 3x3 de sellos,
// con alturas calibradas para caber SIEMPRE en una sola hoja A4.
//============================================================================

require_once('tcpdf_include.php');
require_once '../../conexion/conexion.php';
require_once '../../clases/gestion_documental.php';

// Este script genera un PDF (salida binaria): NO debe imprimir avisos/notices,
// pues cualquier texto antes del PDF lo corrompe. Los errores van al log.
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

// Cabecera institucional
class MYPDF extends TCPDF {
    public function Header() {
        $image_file = K_PATH_IMAGES.SISGEDO_PDF_LOGO;
        $this->Image($image_file, 8, 8, 35, 10, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 15, SISGEDO_ENTIDAD_MAYUS, 0, false, 'C', 0, '', 0, false, 'C', 'M');
    }
    public function Footer() {
        $this->SetY(-13);
        $this->SetFont('helvetica', 'I', 7);
        $this->Cell(0, 8, 'SISGEDO - Sistema de Gestión Documentario · Cargo de recepción', 0, false, 'C');
    }
}

function esc_pdf($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** Recorta textos largos para que el cargo nunca pase de una hoja. */
function acotar_pdf($v, $max){
    $v = trim((string)$v);
    if (function_exists('mb_strlen')) {
        return mb_strlen($v, 'UTF-8') > $max ? mb_substr($v, 0, $max, 'UTF-8') . '…' : $v;
    }
    return strlen($v) > $max ? substr($v, 0, $max) . '…' : $v;
}

/** Cuadrícula clásica de recepciones: 3 filas x 3 columnas = 9 bloques. */
function cuadricula_recepcion(){
    $html = '<table border="1" cellpadding="3" cellspacing="0" width="100%"><tbody>';
    for ($fila = 0; $fila < 3; $fila++) {
        $html .= '<tr style="background-color:#F0F0F0;">';
        for ($c = 1; $c <= 3; $c++) {
            $n = $fila * 3 + $c;
            $html .= '<th colspan="2" width="33.33%" align="center"><b>Datos de Recepción '.$n.'</b></th>';
        }
        $html .= '</tr><tr>';
        for ($c = 0; $c < 3; $c++) { $html .= '<th width="9%" align="center"><b>N° EXP:</b></th><td width="24.33%">&nbsp;</td>'; }
        $html .= '</tr><tr>';
        for ($c = 0; $c < 3; $c++) { $html .= '<th width="9%" align="center"><b>Obs.:</b></th><td width="24.33%">&nbsp;</td>'; }
        $html .= '</tr><tr>';
        for ($c = 0; $c < 3; $c++) { $html .= '<td colspan="2" width="33.33%" height="58">&nbsp;</td>'; }
        $html .= '</tr><tr>';
        for ($c = 0; $c < 3; $c++) { $html .= '<td colspan="2" width="33.33%" align="center">SELLO O V° B°</td>'; }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

// Datos del expediente (externo: por número de expediente)
function lista_datos($numexp){
    try {
        $conexion = new Conexion();
        $conectado = $conexion->conectar();
        $sql = "select date_format(date_add(registro_documentos.fecha_registro, interval 0 hour), '%H:%i:%s') as hora,
                       date_format(date(registro_documentos.fecha_registro), '%d/%m/%Y') as fecha,
                       registro_documentos.nro_exp, registro_documentos.folios,
                       documentos.nombre_documento, registro_documentos.numero_docu,
                       tipo_tram.nombre, concat(remitente.apellidos, ' ', remitente.nombres) as ape_nom,
                       registro_documentos.asunto, oficinas.nombre, registro_documentos.observacion
                from registro_documentos
                left join documentos on registro_documentos.iddocumento = documentos.iddocumentos
                left join remitente on registro_documentos.idremitente = remitente.idremitente
                left join tipo_tram on registro_documentos.idtipo_tramite = tipo_tram.idtipo_tram
                left join oficinas on registro_documentos.idoficina = oficinas.idoficinas
                where registro_documentos.nro_exp = ?;";
        $result = $conectado->prepare($sql);
        $result->execute([$numexp]);

        $dato = null; $oficinas = array();
        foreach ($result as $filas) {
            if ($dato === null) { $dato = $filas; }
            if (!empty($filas[9])) { $oficinas[] = $filas[9]; }
        }
        if ($dato === null) {
            return '<br><br><p align="center"><b>No se encontró el expediente N° '.esc_pdf($numexp).'</b></p>';
        }
        $derivarA = count($oficinas) > 0 ? implode(' / ', array_unique($oficinas)) : 'Por asignar';
        $asunto   = acotar_pdf($dato[8], 200);
        $urlCons  = sisgedo_base_url('consulta_publico.php');
        // Código de verificación digital (MGD). Si la migración no se ejecutó, el cargo sale igual sin QR.
        $cvd = null;
        try { $cvd = sgd_obtener_cvd('EXPEDIENTE', $dato[2]); } catch (Exception $e) { $cvd = null; }

        // Cuadro de datos (se imprime DOS veces: una para el ciudadano y otra
        // para la entidad), como en el formato clásico.
        $tabla = '<table border="1" cellpadding="3" width="80%">
            <thead>
                <tr style="background-color:#F0F0F0;"><th colspan="4" align="center"><b>RECIBIDO MESA DE PARTES</b></th></tr>
            </thead>
            <tbody>
                <tr>
                    <td align="right" width="20%" style="background-color:#F0F0F0;"><b>FECHA:</b></td><td width="30%">'.esc_pdf($dato[1]).'</td>
                    <td align="right" width="20%" style="background-color:#F0F0F0;"><b>HORA:</b></td><td width="30%">'.esc_pdf($dato[0]).'</td>
                </tr>
                <tr>
                    <td align="right" style="background-color:#F0F0F0;"><b>EXPEDIENTE:</b></td><td><b>'.esc_pdf($dato[2]).'</b></td>
                    <td align="right" style="background-color:#F0F0F0;"><b>N° FOLIO:</b></td><td>'.esc_pdf($dato[3]).'</td>
                </tr>
                <tr>
                    <td align="right" style="background-color:#F0F0F0;"><b>TIPO DOCUMENTO:</b></td><td>'.esc_pdf($dato[4]).'</td>
                    <td align="right" style="background-color:#F0F0F0;"><b>NÚMERO:</b></td><td>'.esc_pdf($dato[5]).'</td>
                </tr>
                <tr>
                    <td align="right" style="background-color:#F0F0F0;"><b>TRÁMITE:</b></td><td>'.esc_pdf($dato[6]).'</td>
                    <td align="right" style="background-color:#F0F0F0;"><b>DERIVAR A:</b></td><td>'.esc_pdf($derivarA).'</td>
                </tr>
                <tr>
                    <td align="right" style="background-color:#F0F0F0;"><b>ASUNTO:</b></td><td colspan="3">'.esc_pdf($asunto).'</td>
                </tr>
            </tbody>
            <tfoot>
                <tr><td colspan="4" style="font-size:8px;">Verifique el estado en: <a href="'.$urlCons.'">'.$urlCons.'</a></td></tr>
                '.sgd_html_fila_verificacion($cvd).'
            </tfoot>
        </table>';

        // Se devuelven las piezas: el cuadro va DOS veces (ciudadano/entidad) con su QR al costado.
        return array('tabla' => $tabla, 'cvd' => $cvd);
    } catch (Exception $exc) {
        error_log('cargo_externo: ' . $exc->getMessage());
        return '<p>Error al generar el cargo.</p>';
    }
}

// ------------------------- Documento PDF -------------------------
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator('SISGEDO');
$pdf->SetTitle('Cargo de recepción');
$pdf->SetSubject('Sistema de Gestión Documentario');

$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->SetCellPadding(1);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
    require_once(dirname(__FILE__).'/lang/eng.php');
    $pdf->setLanguageArray($l);
}

$pdf->SetFont('times', '', 9);
$pdf->AddPage();

$res = lista_datos(isset($_GET['exp']) ? $_GET['exp'] : '');
if (is_array($res)) {
    for ($copia = 0; $copia < 2; $copia++) {
        $y0 = $pdf->GetY();
        $pdf->writeHTML($res['tabla'], true, false, true, false, '');
        sgd_pdf_qr($pdf, $y0, $res['cvd']);
        $pdf->Ln(1);
    }
    $pdf->writeHTML(cuadricula_recepcion(), true, false, true, false, '');
} else {
    $pdf->writeHTML($res, true, false, true, false, '');
}

$pdf->Output('Cargo.pdf', 'I');
