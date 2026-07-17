<?php /* Cabecera compartida COMPLETA (incluye CSS de plugins: datepicker, chosen, colorpicker, etc.): doctype, <head>, navbar, sidebar (menu) y apertura de main-content.
   Incluir DESPUES de session_start()/guardia y del login-check de cada vista. */ ?>
<!DOCTYPE html>
<html lang="es">

<head>
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
	<meta charset="utf-8" />
	<title>SGD-SYSFL</title>

	<meta name="description" content="overview &amp; stats" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />

	<!-- bootstrap & fontawesome -->
	<link rel="stylesheet" href="../assets/css/bootstrap.min.css" />
	<link rel="stylesheet" href="../assets/font-awesome/4.5.0/css/font-awesome.min.css" />

	<!-- page specific plugin styles -->
	<link rel="stylesheet" href="../assets/css/bootstrap-colorpicker.min.css" />
	<link rel="stylesheet" href="../assets/css/bootstrap-datepicker3.min.css" />
	<link rel="stylesheet" href="../assets/css/bootstrap-datetimepicker.min.css" />
	<link rel="stylesheet" href="../assets/css/bootstrap-timepicker.min.css" />
	<link rel="stylesheet" href="../assets/css/chosen.min.css" />
	<link rel="stylesheet" href="../assets/css/daterangepicker.min.css" />
	<link rel="stylesheet" href="../assets/css/jquery-ui.custom.min.css" />
	<link rel="stylesheet" href="../assets/css/jquery-ui.min.css" />

	<!-- text fonts -->
	<link rel="stylesheet" href="../assets/css/fonts.googleapis.com.css" />

	<!-- ace styles -->
	<link rel="stylesheet" href="../assets/css/ace.min.css" class="ace-main-stylesheet" id="main-ace-style" />

	<!--[if lte IE 9]>
			<link rel="stylesheet" href="../assets/css/ace-part2.min.css" class="ace-main-stylesheet" />
		<![endif]-->
	<link rel="stylesheet" href="../assets/css/ace-skins.min.css" />
	<link rel="stylesheet" href="../assets/css/ace-rtl.min.css" />

	<!--[if lte IE 9]>
		  <link rel="stylesheet" href="../assets/css/ace-ie.min.css" />
		<![endif]-->

	<!-- inline styles related to this page -->

	<!-- ace settings handler -->
	<script src="../assets/js/ace-extra.min.js"></script>

	<!-- HTML5shiv and Respond.js for IE8 to support HTML5 elements and media queries -->

</head>

<body class="no-skin">

	<div id="navbar" class="navbar navbar-default          ace-save-state">
		<div class="navbar-container ace-save-state" id="navbar-container">
			<button type="button" class="navbar-toggle menu-toggler pull-left" id="menu-toggler" data-target="#sidebar">
				<span class="sr-only">MENÚ</span>

				<span class="icon-bar"></span>

				<span class="icon-bar"></span>

				<span class="icon-bar"></span>
			</button>

			<div class="navbar-header pull-left">
				<a href="index.html" class="navbar-brand">
					<small>
						<i class="fa fa-leaf"></i>
						SISGEDO - SISTEMA DE GESTIÓN DOCUMENTARIO
					</small>
				</a>
			</div>

			<div class="navbar-buttons navbar-header pull-right" role="navigation">
				<ul class="nav ace-nav">


					<li class="light-blue dropdown-modal">
						<a data-toggle="dropdown" href="#" class="dropdown-toggle">
							<img class="nav-user-photo" src="../assets/images/avatars/user.jpg" alt="Jason's Photo" />
							<span class="user-info">
								<small>Bienvenido,</small>
								<?php
								echo isset($_SESSION['alias']) ? $_SESSION['alias'] : (isset($_SESSION['usuario']) ? $_SESSION['usuario'] : '');
								?>
							</span>

							<i class="ace-icon fa fa-caret-down"></i>
						</a>

						<ul class="user-menu dropdown-menu-right dropdown-menu dropdown-yellow dropdown-caret dropdown-close">
							<li>
								<a href="#">
									<i class="ace-icon fa fa-cog"></i>
									Configurar
								</a>
							</li>

							<li>
								<a href="profile.html">
									<i class="ace-icon fa fa-user"></i>
									Perfil
								</a>
							</li>

							<li class="divider"></li>

							<li>
								<a href="../clases/cerrarsesion.php">
									<i class="ace-icon fa fa-power-off"></i>
									Salir


								</a>
							</li>
						</ul>
					</li>
				</ul>
			</div>
		</div><!-- /.navbar-container -->
	</div>

	<div class="main-container ace-save-state" id="main-container">
		<script type="text/javascript">
			try {
				ace.settings.loadState('main-container')
			} catch (e) {}
		</script>

		<div id="sidebar" class="sidebar                  responsive                    ace-save-state">
			<script type="text/javascript">
				try {
					ace.settings.loadState('sidebar')
				} catch (e) {}
			</script>

			<div class="sidebar-shortcuts" id="sidebar-shortcuts">
				<div class="sidebar-shortcuts-large" id="sidebar-shortcuts-large">
					<button class="btn btn-success">
						<i class="ace-icon fa fa-signal"></i>
					</button>

					<button class="btn btn-info">
						<i class="ace-icon fa fa-pencil"></i>
					</button>

					<button class="btn btn-warning">
						<i class="ace-icon fa fa-users"></i>
					</button>

					<button class="btn btn-danger">
						<i class="ace-icon fa fa-cogs"></i>
					</button>
				</div>

				<div class="sidebar-shortcuts-mini" id="sidebar-shortcuts-mini">
					<span class="btn btn-success"></span>

					<span class="btn btn-info"></span>

					<span class="btn btn-warning"></span>

					<span class="btn btn-danger"></span>
				</div>
			</div><!-- /.sidebar-shortcuts -->

			<ul class="nav nav-list">
				<li class="">
					<a href="../index.php">
						<i class="menu-icon fa fa-tachometer"></i>
						<span class="menu-text"> Tablero </span>
					</a>

					<b class="arrow"></b>
				</li>

				<?php
				require_once '../clases/read_main.php';
				$list_main = new Read_main();
				$list_main->list_menu($_SESSION['usuario'], '1', '../');
				?>

			</ul><!-- /.nav-list -->

			<div class="sidebar-toggle sidebar-collapse" id="sidebar-collapse">
				<i id="sidebar-toggle-icon" class="ace-icon fa fa-angle-double-left ace-save-state" data-icon1="ace-icon fa fa-angle-double-left" data-icon2="ace-icon fa fa-angle-double-right"></i>
			</div>
		</div>

		<div class="main-content">
			<div class="main-content-inner">