<?php
require_once __DIR__ . '/conexion/conexion.php';
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (isset($_SESSION['usuario'])) {
  header('Location: ./index.php');
  exit;
}

$loginError = isset($_GET['e']) ? (int) $_GET['e'] : 0;
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/png" href="images/icono sistema.jpg" />
  <title>SISGEDO — Ingresar al sistema</title>
  <link rel="stylesheet" href="css/sisgedo-theme.css" />
  <link rel="stylesheet" href="css/login.css" />
  <script src="js/login.js" defer></script>
</head>

<body data-login-error="<?php echo $loginError; ?>">
  <div class="page">
    <div class="shell">

      <section class="left">
        <div class="brand">
          <svg class="leaf" viewBox="0 0 24 24" fill="none">
            <path d="M4 12c0-5 4-9 15-9 0 11-4 16-11 16-2 0-4-1-4-1S4 15 4 12Z" fill="#22c55e" />
            <path d="M8.5 16.5C11 12 14 10 17 9" stroke="#0e2140" stroke-width="1.4" stroke-linecap="round" />
          </svg>
          <b>SISGEDO</b>
        </div>

        <div class="kicker">SISTEMA DE</div>
        <h1>Gestión de Trámite<br />Documentario</h1>
        <div class="rule"></div>
        <h2>Municipalidad Provincial de Huaytará</h2>
        <p class="desc">Plataforma digital para la gestión, recepción, derivación y seguimiento de documentos de manera eficiente y segura.</p>

        <div class="building-wrap">
          <img class="building" src="images/edificio_svg_trans.svg" alt="Municipalidad Provincial de Huaytará" />
        </div>
        <svg class="building-fallback" viewBox="0 0 1780 880" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-label="Municipalidad Provincial de Huaytará">
          <circle cx="986" cy="104" r="5" />
          <path d="M986 250 V100" />
          <path d="M986 116 C1020 128 1055 104 1092 120 L1092 158 C1055 142 1020 166 986 152 Z" />
          <path d="M992 132 C1020 140 1050 124 1084 136" />
          <path d="M898 292 L986 248 L1074 292" />
          <path d="M890 292 H1082" />
          <path d="M898 302 H1074" />
          <path d="M916 302 V424 M1056 302 V424" />
          <path d="M948 308 V420 M986 308 V420 M1024 308 V420" />
          <circle cx="986" cy="364" r="22" />
          <path d="M892 424 H1080" />
          <path d="M876 446 H1096" />
          <path d="M876 424 V446 M1096 424 V446" />
          <path d="M604 470 H1176 M604 530 H1176 M604 470 V530 M1176 470 V530" />
          <path d="M690 486 H1092 V520 H690 Z" />
          <text x="891" y="509" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="17" letter-spacing="1" fill="#ffffff" stroke="none">MUNICIPALIDAD PROVINCIAL DE HUAYTARÁ</text>
          <path d="M632 636 H1150" />
          <path d="M640 540 H672 M644 548 V748 M668 548 V748 M652 552 V744 M660 552 V744 M638 752 H674 M636 762 H676" />
          <path d="M756 540 H788 M760 548 V748 M784 548 V748 M768 552 V744 M776 552 V744 M754 752 H790 M752 762 H792" />
          <path d="M992 540 H1024 M996 548 V748 M1020 548 V748 M1004 552 V744 M1012 552 V744 M990 752 H1026 M988 762 H1028" />
          <path d="M1108 540 H1140 M1112 548 V748 M1136 548 V748 M1120 552 V744 M1128 552 V744 M1106 752 H1142 M1104 762 H1144" />
          <path d="M792 636 A98 98 0 0 1 988 636" />
          <path d="M846 636 V748 H934 V636 M890 636 V748" />
          <path d="M676 636 A40 40 0 0 1 756 636 M676 636 V742 M756 636 V742" />
          <path d="M1024 636 A40 40 0 0 1 1104 636 M1024 636 V742 M1104 636 V742" />
          <path d="M612 752 H1168 M596 764 H1184" />
          <path d="M252 458 H620 M252 470 H620 M252 470 V770 M252 770 H620" />
          <path d="M278 458 V408 M306 458 V408 M272 408 H312 M292 388 V376" />
          <path d="M278 408 Q292 386 306 408" />
          <path d="M352 458 L452 396 L556 458" />
          <path d="M310 600 V560 A20 20 0 0 1 350 560 V600 Z" />
          <path d="M410 600 V560 A20 20 0 0 1 450 560 V600 Z" />
          <path d="M510 600 V560 A20 20 0 0 1 550 560 V600 Z" />
          <path d="M310 718 V678 A20 20 0 0 1 350 678 V718 Z" />
          <path d="M410 718 V678 A20 20 0 0 1 450 678 V718 Z" />
          <path d="M510 718 V678 A20 20 0 0 1 550 678 V718 Z" />
          <path d="M1160 458 H1528 M1160 470 H1528 M1528 470 V770 M1160 770 H1528" />
          <path d="M1200 458 V446 H1230 V458 M1290 458 V446 H1320 V458 M1380 458 V446 H1410 V458 M1470 458 V446 H1500 V458" />
          <path d="M1230 600 V560 A20 20 0 0 1 1270 560 V600 Z" />
          <path d="M1330 600 V560 A20 20 0 0 1 1370 560 V600 Z" />
          <path d="M1430 600 V560 A20 20 0 0 1 1470 560 V600 Z" />
          <path d="M1230 718 V678 A20 20 0 0 1 1270 678 V718 Z" />
          <path d="M1330 718 V678 A20 20 0 0 1 1370 678 V718 Z" />
          <path d="M1430 718 V678 A20 20 0 0 1 1470 678 V718 Z" />
          <path d="M240 770 H1540 M212 782 H1568 M44 795 H1740" />
          <path d="M128 792 V650 M150 792 V650" />
          <path d="M139 508 c50 0 92 36 92 88 c0 46 -38 82 -92 82 c-54 0 -96 -36 -96 -82 c0 -52 46 -88 96 -88 Z" />
          <path d="M139 640 V520 M139 566 l-30 -22 M139 580 l34 -26 M139 606 l-26 -20" />
          <path d="M1616 792 V470" />
          <path d="M1616 470 C1656 520 1658 720 1622 792 C1586 720 1584 520 1616 470 Z" />
          <path d="M1692 792 V430" />
          <path d="M1692 430 C1730 490 1732 726 1696 792 C1658 726 1656 490 1692 430 Z" />
        </svg>

        <div class="features">
          <div class="feature">
            <span class="ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2">
                <path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3Z" />
                <path d="M9.5 12l1.8 1.8L15 10" stroke-linecap="round" stroke-linejoin="round" />
              </svg></span>
            <div>
              <div class="ttl">Seguro</div>
              <div class="sub">Tus datos están<br />protegidos</div>
            </div>
          </div>
          <div class="feature">
            <span class="ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2">
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7v5l3 2" stroke-linecap="round" stroke-linejoin="round" />
              </svg></span>
            <div>
              <div class="ttl">Eficiente</div>
              <div class="sub">Gestión rápida<br />y ordenada</div>
            </div>
          </div>
          <div class="feature">
            <span class="ic"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2">
                <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z" />
                <path d="M14 3v5h5M9 13h6M9 17h6" stroke-linecap="round" />
              </svg></span>
            <div>
              <div class="ttl">Confiable</div>
              <div class="sub">Información<br />siempre disponible</div>
            </div>
          </div>
        </div>
      </section>

      <section class="right" id="card">
        <div class="theme">
          <a id="th-light" href="#" class="active" data-theme="light"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="4" />
              <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" stroke-linecap="round" />
            </svg> Claro</a>
          <span class="sep">|</span>
          <a id="th-dark" href="#" data-theme="dark"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z" stroke-linejoin="round" />
            </svg> Oscuro</a>
        </div>

        <div class="logo-circle">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
            <path d="M4 12c0-5 4-9 15-9 0 11-4 16-11 16-2 0-4-1-4-1S4 15 4 12Z" fill="#22c55e" />
            <path d="M8.5 16.5C11 12 14 10 17 9" stroke="#ffffff" stroke-width="1.4" stroke-linecap="round" />
          </svg>
        </div>

        <div class="welcome">
          <h3>Bienvenido de vuelta</h3>
          <p>Ingresa tus credenciales para continuar</p>
        </div>

        <div id="login-msg" class="alert-error" aria-live="polite"></div>

        <form class="login" action="clases/login.php" method="post" autocomplete="off">
          <div class="field">
            <label class="lbl" for="usuario">Usuario</label>
            <div class="input">
              <svg class="lead" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="8" r="4" />
                <path d="M4 20c0-4 4-6 8-6s8 2 8 6" stroke-linecap="round" />
              </svg>
              <input id="usuario" type="text" name="usuario" placeholder="Usuario" required autofocus />
            </div>
          </div>

          <div class="field">
            <label class="lbl" for="pass">Contraseña</label>
            <div class="input">
              <svg class="lead" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="4" y="10" width="16" height="10" rx="2" />
                <path d="M8 10V7a4 4 0 0 1 8 0v3" stroke-linecap="round" />
              </svg>
              <input type="password" name="password" id="pass" placeholder="Contraseña" required />
              <button type="button" class="eye" id="togglePass" aria-label="Mostrar contraseña" title="Mostrar/ocultar">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
                  <circle cx="12" cy="12" r="3" />
                </svg>
              </button>
            </div>
          </div>

          <div class="row-between">
            <label class="remember"><input type="checkbox" name="recordar" checked /> Recordarme en este dispositivo</label>
            <a href="#" class="forgot" id="forgotLink">¿Olvidaste tu contraseña?</a>
          </div>

          <button type="submit" class="btn-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2">
              <rect x="4" y="10" width="16" height="10" rx="2" />
              <path d="M8 10V7a4 4 0 0 1 8 0v3" stroke-linecap="round" />
            </svg>
            Ingresar al sistema
          </button>
        </form>

        <div class="or">O ingresa con</div>

        <div class="socials">
          <button type="button" class="social js-disabled-access">
            <svg width="17" height="17" viewBox="0 0 24 24">
              <path fill="#4285F4" d="M22.5 12.2c0-.7-.1-1.4-.2-2H12v3.8h5.9c-.3 1.4-1 2.5-2.2 3.3v2.8h3.6c2.1-2 3.2-4.9 3.2-7.9Z" />
              <path fill="#34A853" d="M12 23c2.9 0 5.4-1 7.2-2.6l-3.6-2.8c-1 .7-2.3 1.1-3.6 1.1-2.8 0-5.1-1.9-6-4.4H2.3v2.9C4.1 20.8 7.8 23 12 23Z" />
              <path fill="#FBBC05" d="M6 14.3c-.2-.7-.4-1.4-.4-2.3s.1-1.6.4-2.3V6.8H2.3C1.5 8.4 1 10.1 1 12s.5 3.6 1.3 5.2L6 14.3Z" />
              <path fill="#EA4335" d="M12 5.3c1.6 0 3 .6 4.1 1.6l3.1-3.1C17.4 2 14.9 1 12 1 7.8 1 4.1 3.2 2.3 6.8L6 9.7c.9-2.5 3.2-4.4 6-4.4Z" />
            </svg>
            Google
          </button>
          <button type="button" class="social js-disabled-access">
            <svg width="16" height="16" viewBox="0 0 24 24">
              <rect x="2" y="2" width="9.2" height="9.2" fill="#F25022" />
              <rect x="12.8" y="2" width="9.2" height="9.2" fill="#7FBA00" />
              <rect x="2" y="12.8" width="9.2" height="9.2" fill="#00A4EF" />
              <rect x="12.8" y="12.8" width="9.2" height="9.2" fill="#FFB900" />
            </svg>
            Microsoft
          </button>
          <button type="button" class="social js-disabled-access">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2">
              <circle cx="12" cy="8" r="4" />
              <path d="M4 20c0-4 4-6 8-6s8 2 8 6" stroke-linecap="round" />
            </svg>
            SSO Institucional
          </button>
        </div>

        <div class="notice">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6l7-3Z" />
            <path d="M12 8v4M12 15.5v.5" stroke-linecap="round" />
          </svg>
          <div>
            <div class="nt">Acceso autorizado</div>
            <div class="nb">El uso no autorizado de este sistema está prohibido y puede ser sancionado de acuerdo con las leyes vigentes.</div>
          </div>
        </div>
      </section>

    </div>
  </div>

  <div class="footer">
    &copy; 2025 Municipalidad Provincial de Huaytará. Todos los derechos reservados.
    <span class="sep">|</span> Versión 2.0.0
  </div>
</body>

</html>