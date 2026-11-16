<?php
//============================================================================
// SISGEDO — Cargo de recepción de documento EXTERNO (mesa de partes)
// Parámetros: ?exp=<nro_exp> (personal con sesión) o ?c=<código de verificación> (ciudadano).
// Las direcciones antiguas tcpdf/examples/cargo_externo.php redirigen aquí.
//
// Diseño (una sola hoja A4, siempre):
//   1) Encabezado con logo y N° de expediente.
//   2) TALÓN DEL CIUDADANO: lo esencial + QR; se corta y se entrega.
//   3) EJEMPLAR DE LA ENTIDAD · HOJA DE RUTA: datos completos + QR.
//   4) RECORRIDO: casillas para el sello de recepción de cada área, que
//      ocupan exactamente el resto de la hoja (12 por defecto; 9 si se
//      define SISGEDO_CARGO_CASILLAS = 9 en conexion/institucion.php).
// Se dibuja con medidas en milímetros (no con HTML suelto) para que la
// hoja nunca se desborde a una segunda página.
//============================================================================

require_once __DIR__ . '/acceso.php';           // sesión o código de verificación + librería TCPDF

// Este script genera un PDF (salida binaria): NO debe imprimir avisos/notices,
// pues cualquier texto antes del PDF lo corrompe. Los errores van al log.
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

if (!defined('SISGEDO_CARGO_CASILLAS')) define('SISGEDO_CARGO_CASILLAS', 12);

class CargoPDF extends TCPDF {
    public function Header() {}            // el encabezado se dibuja en el cuerpo
    public function Footer() {
        $this->SetY(-10);
        $this->SetFont('helvetica', 'I', 6.5);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 5, 'SISGEDO - Sistema de Gestión Documentario · Cargo de recepción', 0, false, 'C');
    }
}

function esc_pdf($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** Recorta textos largos para que el cargo nunca pase de una hoja. */
function acotar_pdf($v, $max) {
    $v = trim(preg_replace('/\s+/u', ' ', (string)$v));
    return mb_strlen($v, 'UTF-8') > $max ? rtrim(mb_substr($v, 0, $max, 'UTF-8')) . '…' : $v;
}

/** Datos del expediente o null si no existe. */
function datos_cargo($numexp) {
    $c = (new Conexion())->conectar();
    $st = $c->prepare("SELECT date_format(rd.fecha_registro, '%d/%m/%Y'), date_format(rd.fecha_registro, '%H:%i:%s'),
                              rd.nro_exp, rd.folios, d.nombre_documento, rd.numero_docu, tt.nombre,
                              TRIM(CONCAT(COALESCE(r.apellidos,''), ' ', COALESCE(r.nombres,''))),
                              rd.asunto, o.nombre, COALESCE(rd.recepcion_web, 0), rd.idpersonal
                         FROM registro_documentos rd
                         LEFT JOIN documentos d  ON d.iddocumentos = rd.iddocumento
                         LEFT JOIN remitente r   ON r.idremitente = rd.idremitente
                         LEFT JOIN tipo_tram tt  ON tt.idtipo_tram = rd.idtipo_tramite
                         LEFT JOIN oficinas o    ON o.idoficinas = rd.idoficina
                        WHERE rd.nro_exp = ?
                        ORDER BY rd.idregistro_documentos");
    $st->execute(array($numexp));
    $filas = $st->fetchAll(PDO::FETCH_NUM);
    if (!$filas) return null;
    $d = $filas[0];
    $oficinas = array();
    foreach ($filas as $f) if (!empty($f[9])) $oficinas[] = $f[9];
    $oficinas = array_values(array_unique($oficinas));

    // Quién registró (ventanilla) o canal virtual
    $virtual = (int)$d[10] === 1;
    $registro = $virtual ? 'Ciudadano, por Mesa de Partes Virtual' : 'Mesa de Partes';
    if (!$virtual && !empty($d[11])) {
        $q = $c->prepare("SELECT TRIM(CONCAT(COALESCE(p.nombres,''),' ',COALESCE(p.apellidos,''))),
                                 (SELECT o.nombre FROM responsables rs JOIN oficinas o ON o.idoficinas = rs.idoficina
                                   WHERE rs.idpersonal = p.idpersonal ORDER BY rs.estado DESC, rs.idresponsables LIMIT 1)
                            FROM personal p WHERE p.idpersonal = ?");
        $q->execute(array((int)$d[11]));
        $p = $q->fetch(PDO::FETCH_NUM);
        if ($p && trim((string)$p[0]) !== '') {
            $registro = trim($p[0]) . (!empty($p[1]) ? ' · ' . $p[1] : '');
        }
    }
    $cvd = null;
    try { $cvd = sgd_obtener_cvd('EXPEDIENTE', $d[2]); } catch (Exception $e) { $cvd = null; }

    return array(
        'fecha' => $d[0], 'hora' => $d[1], 'exp' => (string)$d[2], 'folios' => (string)$d[3],
        'tipo' => (string)$d[4], 'numero' => trim((string)$d[5]), 'tramite' => (string)$d[6],
        'remitente' => (string)$d[7], 'asunto' => (string)$d[8],
        'derivar' => $oficinas ? implode(' / ', $oficinas) : 'Por asignar',
        'canal' => $virtual ? 'Mesa de Partes Virtual' : 'Ventanilla',
        'registro' => $registro, 'cvd' => $cvd,
    );
}

/* ---------------------------- Dibujo ---------------------------- */
const MX = 10;            // margen izquierdo/derecho (mm)
const ANCHO = 190;        // ancho útil (mm)
const QR = 24;            // tamaño del QR (mm)
$AZUL = array(30, 79, 156);

/** Tabla de datos (HTML) en la columna izquierda; devuelve la Y final. */
function tabla_datos($pdf, $y, $filas) {
    $borde = 'border:0.25px solid #B9C2CF;';
    $lab = 'style="' . $borde . 'background-color:#F1F4F8;font-size:6.6px;font-weight:bold;color:#333333;"';
    $val = 'style="' . $borde . 'font-size:8.2px;font-weight:normal;"';
    $h = '<table cellpadding="2.2" cellspacing="0">';
    foreach ($filas as $f) {
        $h .= '<tr>';
        if (count($f) === 4) {
            $h .= '<td width="17%" ' . $lab . '>' . esc_pdf($f[0]) . '</td><td width="33%" ' . $val . '>' . $f[1] . '</td>'
                . '<td width="17%" ' . $lab . '>' . esc_pdf($f[2]) . '</td><td width="33%" ' . $val . '>' . $f[3] . '</td>';
        } else {
            $h .= '<td width="17%" ' . $lab . '>' . esc_pdf($f[0]) . '</td><td width="83%" colspan="3" ' . $val . '>' . $f[1] . '</td>';
        }
        $h .= '</tr>';
    }
    $h .= '</table>';
    $pdf->SetDrawColor(185, 194, 207);
    $pdf->SetLineWidth(0.2);
    $pdf->SetFont('helvetica', '', 8.2);      // sin heredar la negrita del rótulo
    $pdf->writeHTMLCell(ANCHO - QR - 5, 0, MX, $y, $h, 0, 1, false, true, 'L');
    return $pdf->GetY();
}

/** Rótulo "CARGO DE RECEPCIÓN" + etiqueta azul a la derecha. */
function rotulo($pdf, $y, $etiqueta) {
    global $AZUL;
    $pdf->SetTextColor(20, 20, 20);
    $pdf->SetFont('helvetica', 'B', 8.5);
    $pdf->SetXY(MX, $y);
    $pdf->Cell(100, 4.5, 'CARGO DE RECEPCIÓN', 0, 0, 'L');
    $pdf->SetFont('helvetica', 'B', 6.5);
    $w = $pdf->GetStringWidth($etiqueta) + 5;
    $pdf->SetFillColor($AZUL[0], $AZUL[1], $AZUL[2]);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetXY(MX + ANCHO - $w, $y + 0.4);
    $pdf->Cell($w, 3.8, $etiqueta, 0, 0, 'C', true);
    $pdf->SetTextColor(20, 20, 20);
    return $y + 5.5;
}

/** QR de verificación con su código debajo, en la columna derecha. */
function qr($pdf, $y, $cvd, $leyenda) {
    if (!$cvd) return $y;
    $x = MX + ANCHO - QR - 1;
    $pdf->write2DBarcode(sgd_url_verificacion($cvd), 'QRCODE,M', $x, $y, QR, QR,
        array('border' => false, 'padding' => 0, 'fgcolor' => array(0, 0, 0), 'bgcolor' => false), 'N');
    $pdf->SetFont('courier', 'B', 8.5);
    $pdf->SetXY($x - 3, $y + QR + 0.5);
    $pdf->Cell(QR + 6, 3.5, sgd_formato_cvd($cvd), 0, 2, 'C');
    $pdf->SetFont('helvetica', '', 5.8);
    $pdf->SetTextColor(90, 90, 90);
    $pdf->Cell(QR + 6, 2.8, $leyenda, 0, 0, 'C');
    $pdf->SetTextColor(20, 20, 20);
    return $y + QR + 7;
}

/** Línea de pie de un bloque (texto a la izquierda y a la derecha). */
function pie_bloque($pdf, $y, $izq, $der) {
    $pdf->SetFont('helvetica', '', 6.6);
    $pdf->SetTextColor(70, 70, 70);
    $pdf->SetXY(MX, $y);
    $pdf->Cell(ANCHO / 2, 3.5, $izq, 0, 0, 'L');
    $pdf->Cell(ANCHO / 2, 3.5, $der, 0, 0, 'R');
    $pdf->SetTextColor(20, 20, 20);
    return $y + 4;
}

// ------------------------- Documento PDF -------------------------
$pdf = new CargoPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('SISGEDO');
$pdf->SetTitle('Cargo de recepción');
$pdf->SetSubject('Sistema de Gestión Documentario');
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);
$pdf->SetMargins(MX, 8, MX);
$pdf->SetAutoPageBreak(false);           // una sola hoja, siempre
$pdf->setCellPaddings(0, 0, 0, 0);
$pdf->AddPage();

$numexp = pdf_expediente_autorizado();          // con sesión: ?exp= ; sin sesión: solo ?c=código
$d = null;
try { $d = datos_cargo($numexp); } catch (Exception $e) { error_log('cargo_externo: ' . $e->getMessage()); }

if (!$d) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetXY(MX, 40);
    $pdf->Cell(ANCHO, 8, 'No se encontró el expediente N° ' . $numexp, 0, 0, 'C');
    $pdf->Output('Cargo.pdf', 'I');
    exit;
}

$sitio = preg_replace('~^https?://~', '', sisgedo_base_url());       // munihuaytara.gob.pe/sisgedov2
$documento = esc_pdf($d['tipo']) . ($d['numero'] !== '' ? ' <b>' . esc_pdf($d['numero']) . '</b>' : '');

// 1) Encabezado ---------------------------------------------------------
$pdf->Image(K_PATH_IMAGES . SISGEDO_PDF_LOGO, MX, 8, 0, 11, 'PNG', '', '', false, 300);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetXY(MX + 40, 8.5);
$pdf->Cell(100, 5, SISGEDO_ENTIDAD_MAYUS, 0, 2, 'L');
$pdf->SetFont('helvetica', '', 7.2);
$pdf->SetTextColor(90, 90, 90);
$pdf->Cell(100, 4, 'Sistema de Gestión Documentario · Mesa de Partes', 0, 0, 'L');
$pdf->SetFont('helvetica', 'B', 6.2);
$pdf->SetXY(MX + ANCHO - 60, 8);
$pdf->Cell(60, 3, 'EXPEDIENTE', 0, 2, 'R');
$pdf->SetFont('courier', 'B', 16);
$pdf->SetTextColor($AZUL[0], $AZUL[1], $AZUL[2]);
$pdf->Cell(60, 7, $d['exp'], 0, 0, 'R');
$pdf->SetTextColor(20, 20, 20);
$pdf->SetDrawColor($AZUL[0], $AZUL[1], $AZUL[2]);
$pdf->SetLineWidth(0.6);
$pdf->Line(MX, 21, MX + ANCHO, 21);

// 2) Talón del ciudadano ------------------------------------------------
$y = rotulo($pdf, 23, 'TALÓN DEL CIUDADANO');
$y0 = $y;
$yT = tabla_datos($pdf, $y, array(
    array('FECHA Y HORA', esc_pdf($d['fecha'] . ' · ' . $d['hora']), 'FOLIOS', esc_pdf($d['folios'])),
    array('DOCUMENTO', $documento),
    array('DERIVADO A', '<b>' . esc_pdf($d['derivar']) . '</b>', 'CANAL', esc_pdf($d['canal'])),
    array('ASUNTO', esc_pdf(acotar_pdf($d['asunto'], 150))),
));
$yQ = qr($pdf, $y0, $d['cvd'], 'Escanee para ver el estado');
$y = pie_bloque($pdf, max($yT, $yQ) + 0.8, 'Consulte su trámite con el N° de expediente en ' . $sitio, '');

// Línea de corte
$y += 2;
$pdf->SetDrawColor(140, 150, 165);
$pdf->SetLineStyle(array('width' => 0.3, 'dash' => '1.5,1.2', 'color' => array(140, 150, 165)));
$pdf->Line(MX, $y, MX + ANCHO, $y);
$pdf->SetLineStyle(array('width' => 0.2, 'dash' => 0));
$pdf->SetFont('helvetica', '', 6.2);
$pdf->SetTextColor(110, 120, 135);
$txt = 'CORTAR Y ENTREGAR AL CIUDADANO';
$tw = $pdf->GetStringWidth($txt) + 6;
$pdf->SetFillColor(255, 255, 255);
$pdf->SetXY(MX + (ANCHO - $tw) / 2, $y - 1.7);
$pdf->Cell($tw, 3.4, $txt, 0, 0, 'C', true);
$pdf->SetTextColor(20, 20, 20);

// 3) Ejemplar de la entidad · hoja de ruta ------------------------------
$y = rotulo($pdf, $y + 3, 'EJEMPLAR DE LA ENTIDAD · HOJA DE RUTA');
$y0 = $y;
$yT = tabla_datos($pdf, $y, array(
    array('FECHA Y HORA', esc_pdf($d['fecha'] . ' · ' . $d['hora']), 'FOLIOS', esc_pdf($d['folios'])),
    array('REMITENTE', esc_pdf($d['remitente'] !== '' ? $d['remitente'] : '—')),
    array('DOCUMENTO', $documento),
    array('TRÁMITE', esc_pdf($d['tramite']), 'DERIVADO A', '<b>' . esc_pdf($d['derivar']) . '</b>'),
    array('ASUNTO', esc_pdf(acotar_pdf($d['asunto'], 420))),
));
$yQ = qr($pdf, $y0, $d['cvd'], 'Verificar este cargo');
$y = pie_bloque($pdf, max($yT, $yQ) + 0.8, 'Registró: ' . acotar_pdf($d['registro'], 70), 'Verificación: ' . $sitio . '/verificar.php');

// 4) Recorrido: casillas de sello ---------------------------------------
$y += 2;
$pdf->SetDrawColor($AZUL[0], $AZUL[1], $AZUL[2]);
$pdf->SetLineWidth(0.5);
$pdf->Line(MX, $y, MX + ANCHO, $y);
$pdf->SetFont('helvetica', 'B', 8.5);
$pdf->SetXY(MX, $y + 1.2);
$pdf->Cell(120, 4.5, 'RECORRIDO · SELLO DE RECEPCIÓN DE CADA ÁREA', 0, 0, 'L');
$pdf->SetFont('helvetica', '', 6.4);
$pdf->SetTextColor(90, 90, 90);
$pdf->Cell(ANCHO - 120, 4.5, 'El sistema también registra cada derivación', 0, 0, 'R');
$pdf->SetTextColor(20, 20, 20);

$yIni = $y + 7;
$yFin = 297 - 12;                         // deja lugar al pie de página
$cols = 3;
$filas = (int)SISGEDO_CARGO_CASILLAS === 9 ? 3 : 4;
$cw = ANCHO / $cols;
$ch = ($yFin - $yIni) / $filas;

for ($i = 0; $i < $cols * $filas; $i++) {
    $cx = MX + ($i % $cols) * $cw;
    $cy = $yIni + floor($i / $cols) * $ch;
    // franja superior: N° de orden y expediente
    $pdf->SetFillColor(241, 244, 248);
    $pdf->SetDrawColor(195, 203, 214);
    $pdf->SetLineWidth(0.2);
    $pdf->Rect($cx, $cy, $cw, 4.2, 'DF');
    $pdf->SetFont('helvetica', 'B', 6.6);
    $pdf->SetXY($cx + 1.5, $cy + 0.4);
    $pdf->Cell(20, 3.4, 'N° ' . ($i + 1), 0, 0, 'L');
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->SetXY($cx + $cw - 31.5, $cy + 0.4);
    $pdf->Cell(30, 3.4, 'Exp. ' . $d['exp'], 0, 0, 'R');
    // espacio para el sello (con una marca de agua muy tenue)
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetTextColor(211, 217, 226);
    $pdf->SetXY($cx, $cy + 4.2 + ($ch - 4.2 - 8) / 2 - 1.5);
    $pdf->Cell($cw, 3, 'SELLO DE RECEPCIÓN', 0, 0, 'C');
    $pdf->SetTextColor(20, 20, 20);
    // renglones al pie de la casilla
    $yl = $cy + $ch - 8;
    $pdf->SetLineStyle(array('width' => 0.2, 'dash' => '0.6,0.6', 'color' => array(195, 203, 214)));
    $pdf->Line($cx, $yl, $cx + $cw, $yl);
    $pdf->SetLineStyle(array('width' => 0.2, 'dash' => 0, 'color' => array(205, 211, 220)));
    $pdf->SetFont('helvetica', '', 6.2);
    $pdf->SetTextColor(85, 85, 85);
    $pdf->SetXY($cx + 1.5, $yl + 0.6);
    $pdf->Cell(9, 3.3, 'Área:', 0, 0, 'L');
    $pdf->Line($cx + 9, $yl + 3.3, $cx + $cw - 1.5, $yl + 3.3);
    $pdf->SetXY($cx + 1.5, $yl + 4.2);
    $pdf->Cell(20, 3.3, 'Fecha / N° reg.:', 0, 0, 'L');
    $pdf->Line($cx + 19.5, $yl + 6.9, $cx + $cw - 1.5, $yl + 6.9);
    $pdf->SetTextColor(20, 20, 20);
    // borde de la casilla
    $pdf->SetDrawColor(195, 203, 214);
    $pdf->Rect($cx, $cy, $cw, $ch, 'D');
}
// marco exterior
$pdf->SetDrawColor(154, 165, 181);
$pdf->SetLineWidth(0.35);
$pdf->Rect(MX, $yIni, ANCHO, $yFin - $yIni, 'D');

$pdf->Output('Cargo.pdf', 'I');
