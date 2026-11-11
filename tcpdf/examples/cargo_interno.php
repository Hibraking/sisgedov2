<?php
//============================================================================
// SISGEDO — Cargo de recepción/derivación INTERNA (entre oficinas)
// Parámetro: ?exp=<idMovimientos>
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
        $this->Cell(0, 8, 'SISGEDO - Sistema de Gestión Documentario · Cargo interno', 0, false, 'C');
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

// Datos del movimiento (interno: por idMovimientos).
// NOTA: dos consultas separadas (no UNION) para evitar "Illegal mix of collations"
// entre docs_emitidos y registro_documentos tras la migración parcial a utf8mb4.
function lista_datos($idmov){
    try {
        $conexion = new Conexion();
        $conectado = $conexion->conectar();

        // a) Movimiento de un documento INTERNO (docs_emitidos)
        $sqlA = "select date_format(date_add(m.fecha_hora, interval 0 hour), '%d/%m/%Y') as fecha,
                        date_format(date_add(m.fecha_hora, interval 0 hour), '%H:%i:%s') as hora,
                        m.num_exp, m.folio, documentos.nombre_documento, de.numero,
                        'INTERNO', de.asunto, upper(o.nombre), 'EMITIDO', de.iddocs_emitidos
                 from movimientos m
                 inner join docs_emitidos de on m.iddocs_emitidos = de.iddocs_emitidos
                 inner join responsables r on m.idresponsable = r.idresponsables
                 inner join oficinas o on r.idoficina = o.idoficinas
                 left join documentos on de.iddocumento = documentos.iddocumentos
                 where m.idMovimientos = ?";
        $st = $conectado->prepare($sqlA);
        $st->execute([$idmov]);
        $dato = $st->fetch(PDO::FETCH_NUM);

        // b) Movimiento de un documento EXTERNO (registro_documentos)
        if (!$dato) {
            $sqlB = "select date_format(date_add(m.fecha_hora, interval 0 hour), '%d/%m/%Y') as fecha,
                            date_format(date_add(m.fecha_hora, interval 0 hour), '%H:%i:%s') as hora,
                            m.num_exp, m.folio, documentos.nombre_documento, rd.numero_docu,
                            coalesce(tipo_tram.nombre, 'EXTERNO'), rd.asunto, upper(o.nombre), 'EXPEDIENTE', rd.nro_exp
                     from movimientos m
                     inner join registro_documentos rd on m.idregdocs = rd.idregistro_documentos
                     inner join responsables r on m.idresponsable = r.idresponsables
                     inner join oficinas o on r.idoficina = o.idoficinas
                     left join documentos on rd.iddocumento = documentos.iddocumentos
                     left join tipo_tram on rd.idtipo_tramite = tipo_tram.idtipo_tram
                     where m.idMovimientos = ?";
            $st = $conectado->prepare($sqlB);
            $st->execute([$idmov]);
            $dato = $st->fetch(PDO::FETCH_NUM);
        }

        if (!$dato) {
            return '<br><br><p align="center"><b>No se encontró el movimiento solicitado.</b></p>';
        }

        $asunto = acotar_pdf($dato[7], 200);
        // Código de verificación digital (MGD) del documento que se mueve
        $cvd = null;
        try { $cvd = sgd_obtener_cvd($dato[9], $dato[10]); } catch (Exception $e) { $cvd = null; }

        // Cuadro de datos (se imprime DOS veces, como en el formato clásico).
        $tabla = '<table border="1" cellpadding="3" width="80%">
            <thead>
                <tr style="background-color:#F0F0F0;"><th colspan="4" align="center"><b>RECIBIDO EN '.esc_pdf($dato[8]).'</b></th></tr>
            </thead>
            <tbody>
                <tr>
                    <td align="right" width="20%" style="background-color:#F0F0F0;"><b>FECHA:</b></td><td width="30%">'.esc_pdf($dato[0]).'</td>
                    <td align="right" width="20%" style="background-color:#F0F0F0;"><b>HORA:</b></td><td width="30%">'.esc_pdf($dato[1]).'</td>
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
                    <td align="right" style="background-color:#F0F0F0;"><b>TRÁMITE:</b></td><td colspan="3">'.esc_pdf($dato[6]).'</td>
                </tr>
                <tr>
                    <td align="right" style="background-color:#F0F0F0;"><b>ASUNTO:</b></td><td colspan="3">'.esc_pdf($asunto).'</td>
                </tr>
            </tbody>
            <tfoot>'.sgd_html_fila_verificacion($cvd).'</tfoot>
        </table>';

        // Se devuelven las piezas: el cuadro va DOS veces (ciudadano/entidad) con su QR al costado.
        return array('tabla' => $tabla, 'cvd' => $cvd);
    } catch (Exception $exc) {
        error_log('cargo_interno: ' . $exc->getMessage());
        return '<p>Error al generar el cargo.</p>';
    }
}

// ------------------------- Documento PDF -------------------------
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator('SISGEDO');
$pdf->SetTitle('Cargo interno');
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
