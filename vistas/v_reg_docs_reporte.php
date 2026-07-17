<?php
session_start();
require_once '../clases/guardia.php';
$_SESSION["modulo"] = "1";
if (!isset($_SESSION['usuario'])) {
	header("Location: ../login.php");
}

?>
<?php include __DIR__ . '/../partes/cabecera_full.php'; ?>
<div class="breadcrumbs ace-save-state" id="breadcrumbs">
	<ul class="breadcrumb">
		<li>
			<i class="ace-icon fa fa-home home-icon"></i>
			<a href="../index.php">Inicio</a>
		</li>
		<li>
			<i class="ace-icon fa fa-desktop"></i>
			<a href="#">Operaciones</a>
		</li>
		<li class="active">Recepcionar Documentos</li>
	</ul><!-- /.breadcrumb -->

	<div class="nav-search" id="nav-search">
		<form class="form-search">
			<span class="input-icon">
				<input type="text" placeholder="Search ..." class="nav-search-input" id="nav-search-input" autocomplete="off" />
				<i class="ace-icon fa fa-search nav-search-icon"></i>
			</span>
		</form>
	</div><!-- /.nav-search -->
</div>

<div class="page-content">
	<div class="ace-settings-container" id="ace-settings-container">
		<div class="btn btn-app btn-xs btn-warning ace-settings-btn" id="ace-settings-btn">
			<i class="ace-icon fa fa-cog bigger-130"></i>
		</div>

		<div class="ace-settings-box clearfix" id="ace-settings-box">
			<div class="pull-left width-50">
				<div class="ace-settings-item">
					<div class="pull-left">
						<select id="skin-colorpicker" class="hide">
							<option data-skin="no-skin" value="#438EB9">#438EB9</option>
							<option data-skin="skin-1" value="#222A2D">#222A2D</option>
							<option data-skin="skin-2" value="#C6487E">#C6487E</option>
							<option data-skin="skin-3" value="#D0D0D0">#D0D0D0</option>
						</select>
					</div>
					<span>&nbsp; Choose Skin</span>
				</div>

				<div class="ace-settings-item">
					<input type="checkbox" class="ace ace-checkbox-2 ace-save-state" id="ace-settings-navbar" autocomplete="off" />
					<label class="lbl" for="ace-settings-navbar"> Fixed Navbar</label>
				</div>

				<div class="ace-settings-item">
					<input type="checkbox" class="ace ace-checkbox-2 ace-save-state" id="ace-settings-sidebar" autocomplete="off" />
					<label class="lbl" for="ace-settings-sidebar"> Fixed Sidebar</label>
				</div>

				<div class="ace-settings-item">
					<input type="checkbox" class="ace ace-checkbox-2 ace-save-state" id="ace-settings-breadcrumbs" autocomplete="off" />
					<label class="lbl" for="ace-settings-breadcrumbs"> Fixed Breadcrumbs</label>
				</div>

				<div class="ace-settings-item">
					<input type="checkbox" class="ace ace-checkbox-2" id="ace-settings-rtl" autocomplete="off" />
					<label class="lbl" for="ace-settings-rtl"> Right To Left (rtl)</label>
				</div>

				<div class="ace-settings-item">
					<input type="checkbox" class="ace ace-checkbox-2 ace-save-state" id="ace-settings-add-container" autocomplete="off" />
					<label class="lbl" for="ace-settings-add-container">
						Inside
						<b>.container</b>
					</label>
				</div>
			</div><!-- /.pull-left -->

			<div class="pull-left width-50">
				<div class="ace-settings-item">
					<input type="checkbox" class="ace ace-checkbox-2" id="ace-settings-hover" autocomplete="off" />
					<label class="lbl" for="ace-settings-hover"> Submenu on Hover</label>
				</div>

				<div class="ace-settings-item">
					<input type="checkbox" class="ace ace-checkbox-2" id="ace-settings-compact" autocomplete="off" />
					<label class="lbl" for="ace-settings-compact"> Compact Sidebar</label>
				</div>

				<div class="ace-settings-item">
					<input type="checkbox" class="ace ace-checkbox-2" id="ace-settings-highlight" autocomplete="off" />
					<label class="lbl" for="ace-settings-highlight"> Alt. Active Item</label>
				</div>
			</div><!-- /.pull-left -->
		</div><!-- /.ace-settings-box -->
	</div><!-- /.ace-settings-container -->

	<div class="page-header">
		<h1>
			Recepcionar Documentos
		</h1>
	</div><!-- /.page-header -->

	<div class="row">
		<div class="col-xs-12">
			<!-- PAGE CONTENT BEGINS -->


			<!-- /.row aqui todo el contenido de la pagina web-->
			<div class="col-xs-12 col-sm-10">
				<div class="widget-box">
					<div class="widget-header">
						<h4 class="widget-title">Datos.../</h4>

						<span class="widget-toolbar">
							<a href="#" data-action="reload">
								<i class="ace-icon fa fa-refresh" id="reload"></i>
							</a>

							<a href="#" data-action="collapse">
								<i class="ace-icon fa fa-chevron-up"></i>
							</a>

						</span>
					</div>
					<div class="widget-body">
						<div class="widget-main">
							<div class="widget-body">
								<div class="widget-main">

									<form action="" method="post" enctype="multipart/form-data" onsubmit="return false;">

										<div>
											<div class="row">
												<div class="col-sm-6">

													<label class="control-label bolder blue col-sm-12">Documento a Recepcionar</label>
													<span class="col-sm-4"></span>
													<div class="radio col-sm-8 ">
														<label>
															<input name="form-field-radio" type="radio" class="ace" id="r_interno" />
															<span class="lbl">Interno</span>
														</label>
														<label>
															<input name="form-field-radio" type="radio" class="ace" id="r_exrterno" />
															<span class="lbl">Externo</span>
														</label>
														<label>
															<input name="form-field-radio" type="radio" class="ace" id="r_derivado" />
															<span class="lbl">Derivado</span>
														</label>
													</div>
													<label for="Documento" class="bolder blue">Documento:</label>
													<select required="" class="chosen-select form-control" data-placeholder="Escoja un Documento" name="Documento" id="Documento">
														<option value=""> </option>

													</select>
													<label for="Accion" class="bolder blue">Acción Documento:</label>
													<select required="" class="chosen-select form-control" data-placeholder="Escoja una Acción del Documento..." name="Accion" id="Accion">
														<option value=""> </option>
													</select>


												</div>

												<div class="col-sm-6">
													<label for="Detalles" class="bolder blue">Detalles:</label>
													<textarea name="Detalles" class="form-control" rows="6" cols="20" id="Detalles"></textarea>
													<br>
													<span class="float-left"></span>
													<button type="submit" class="btn btn-info btn-md float-right" id="btnguardar">
														<i class="ace-icon fa fa-save bigger-120"></i>Guardar
													</button>
												</div>
											</div>


										</div>

									</form>
								</div>
							</div>

						</div>
					</div>
				</div>
			</div><!-- /.span -->


			<!-- /.row aqui todo el contenido de la pagina web-->

			<div class="hr hr32 hr-dotted"></div>

			<div class="row">
				<div class="col-xs-12">


					<div class="clearfix">
						<div class="pull-right tableTools-container"></div>
					</div>
					<div class="table-header">
						Documentos Recepcionados
					</div>

					<!-- div.table-responsive -->

					<!-- div.dataTables_borderWrap -->
					<div>
						<table id="dynamic-table" class="table table-striped table-bordered table-hover">
							<thead>
								<tr>
									<th colspan="7" class="green center">Origen</th>
									<th colspan="6" class="green center">Detalles</th>
								</tr>
								<tr>
									<th class="center">
										<label class="pos-rel">
											<input type="checkbox" class="ace" />
											<span class="lbl"></span>
										</label>
									</th>
									<th class="hidden">Código</th>
									<th>Num. Exp.</th>
									<th>Of. Origen</th>
									<th>Trámite</th>
									<th>Asunto</th>
									<th>Tipo Doc.</th>
									<th>Núm. Doc.</th>
									<th>Num. Exp.</th>
									<th>Detalles</th>
									<th>Acción Doc.</th>
									<th>Estado</th>
									<th>Fecha</th>
									<th>Acciones</th>
								</tr>
							</thead>

							<tbody>
								<?php
								require_once '../clases/recepcion_proveido.php';
								$dml_recep = new Recepcion_proveido();
								$dml_recep->lista_recepcionadas($_SESSION['idoficina']);
								?>
							</tbody>
						</table>
					</div>
				</div>
			</div>

			<!-- PAGE CONTENT ENDS -->
			<div id="modal-form" class="modal" tabindex="-1">
				<div class="modal-dialog">
					<div class="modal-content">
						<div class="modal-header">
							<button type="button" class="close" data-dismiss="modal">&times;</button>
							<h4 class="blue bigger">Por favor Ingrese los Datos Solicitados</h4>
						</div>

						<div class="modal-body">
							<div class="row">
								<div class="col-xs-12 col-sm-5">
									<div class="space"></div>

									<input type="file" />
								</div>

								<div class="col-xs-12 col-sm-7">
									<div class="form-group">

										<label for="Oficina_modal" class="bolder blue">Oficina</label>
										<div>
											<select class="chosen-select" required="" name="Oficina_modal" id="Oficina_modal" data-placeholder="Seleccione una Acción...">
												<option value=""></option>
											</select>
										</div>
										<label for="Accion_modal" class="bolder blue">Acción</label>
										<div>
											<select class="chosen-select" required="" name="Accion_modal" id="Accion_modal" data-placeholder="Seleccione una Acción...">
												<option value=""></option>
											</select>
										</div>
									</div>

									<div class="space-4"></div>

									<div class="form-group ">
										<label for="det_modal" class="bolder blue">Detalles:</label>
										<textarea name="det_modal" class="form-control" rows="3" cols="20" id="det_modal"></textarea>
										<label id="status" class="bolder blue center"></label>
									</div>
								</div>
							</div>
						</div>

						<div class="modal-footer">
							<button class="btn btn-sm" data-dismiss="modal">
								<i class="ace-icon fa fa-times"></i>
								Cancelar
							</button>

							<button class="btn btn-sm btn-primary" id="MGuardar">
								<i class="ace-icon fa fa-save"></i>
								Guardar
							</button>
						</div>
					</div>
				</div>
			</div>

		</div><!-- /.col -->
	</div><!-- /.row -->
</div><!-- /.page-content -->
</div>
</div><!-- /.main-content -->

<?php include __DIR__ . '/../partes/pie.php'; ?>
<script src="./pruebas.js"></script>

<!-- inline scripts related to this page -->
<script type="text/javascript">
	var cod_movimiento, idregdocs, iddoc_emitidos, insert_deriv_resp_dev, idestadodoc;
	jQuery(function($) {
		//initiate dataTables plugin
		var myTable =
			$('#dynamic-table')
			//.wrap("<div class='dataTables_borderWrap' />")   //if you are applying horizontal scrolling (sScrollX)
			.DataTable({
				bAutoWidth: false,
				"aoColumns": [{
						"bSortable": false
					},
					null, null, null, null, null, null, null, null, null, null, null, null,
					{
						"bSortable": false
					}
				],
				"aaSorting": [],


				//"bProcessing": true,
				//"bServerSide": true,
				//"sAjaxSource": "http://127.0.0.1/table.php"	,

				//,
				//"sScrollY": "200px",
				//"bPaginate": false,

				//"sScrollX": "100%",
				//"sScrollXInner": "120%",
				//"bScrollCollapse": true,
				//Note: if you are applying horizontal scrolling (sScrollX) on a ".table-bordered"
				//you may want to wrap the table inside a "div.dataTables_borderWrap" element

				//"iDisplayLength": 50


				select: {
					style: 'multi'
				}
			});



		$.fn.dataTable.Buttons.defaults.dom.container.className = 'dt-buttons btn-overlap btn-group btn-overlap';

		new $.fn.dataTable.Buttons(myTable, {
			buttons: [{
					"extend": "colvis",
					"text": "<i class='fa fa-search bigger-110 blue'></i> <span class='hidden'>Mostrar/Ocultar Columnas</span>",
					"className": "btn btn-white btn-primary btn-bold",
					columns: ':not(:first):not(:last)'
				},
				{
					"extend": "copy",
					"text": "<i class='fa fa-copy bigger-110 pink'></i> <span class='hidden'>Copiar al portapapeles</span>",
					"className": "btn btn-white btn-primary btn-bold"
				},
				{
					"extend": "csv",
					"text": "<i class='fa fa-database bigger-110 orange'></i> <span class='hidden'>Exportar a CSV</span>",
					"className": "btn btn-white btn-primary btn-bold"
				},
				{
					"extend": "excel",
					"text": "<i class='fa fa-database bigger-110 green'></i> <span class='hidden'>Exportar a Excel</span>",
					"className": "btn btn-white btn-primary btn-bold"
				},
				{
					"extend": "pdf",
					"text": "<i class='fa fa-file-pdf-o bigger-110 red'></i> <span class='hidden'>Export to PDF</span>",
					"className": "btn btn-white btn-primary btn-bold"
				},
				{
					"extend": "print",
					"text": "<i class='fa fa-print bigger-110 grey'></i> <span class='hidden'>Imprimir</span>",
					"className": "btn btn-white btn-primary btn-bold",
					autoPrint: false,
					message: 'This print was produced using the Print button for DataTables'
				}
			]
		});
		myTable.buttons().container().appendTo($('.tableTools-container'));

		//style the message box
		var defaultCopyAction = myTable.button(1).action();
		myTable.button(1).action(function(e, dt, button, config) {
			defaultCopyAction(e, dt, button, config);
			$('.dt-button-info').addClass('gritter-item-wrapper gritter-info gritter-center white');
		});


		var defaultColvisAction = myTable.button(0).action();
		myTable.button(0).action(function(e, dt, button, config) {

			defaultColvisAction(e, dt, button, config);


			if ($('.dt-button-collection > .dropdown-menu').length == 0) {
				$('.dt-button-collection')
					.wrapInner('<ul class="dropdown-menu dropdown-light dropdown-caret dropdown-caret" />')
					.find('a').attr('href', '#').wrap("<li />")
			}
			$('.dt-button-collection').appendTo('.tableTools-container .dt-buttons')
		});

		////

		setTimeout(function() {
			$($('.tableTools-container')).find('a.dt-button').each(function() {
				var div = $(this).find(' > div').first();
				if (div.length == 1) div.tooltip({
					container: 'body',
					title: div.parent().text()
				});
				else $(this).tooltip({
					container: 'body',
					title: $(this).text()
				});
			});
		}, 500);





		myTable.on('select', function(e, dt, type, index) {
			if (type === 'row') {
				$(myTable.row(index).node()).find('input:checkbox').prop('checked', true);
			}
		});
		myTable.on('deselect', function(e, dt, type, index) {
			if (type === 'row') {
				$(myTable.row(index).node()).find('input:checkbox').prop('checked', false);
			}
		});




		/////////////////////////////////
		//table checkboxes
		$('th input[type=checkbox], td input[type=checkbox]').prop('checked', false);

		//select/deselect all rows according to table header checkbox
		$('#dynamic-table > thead > tr > th input[type=checkbox], #dynamic-table_wrapper input[type=checkbox]').eq(0).on('click', function() {
			var th_checked = this.checked; //checkbox inside "TH" table header

			$('#dynamic-table').find('tbody > tr').each(function() {
				var row = this;
				if (th_checked) myTable.row(row).select();
				else myTable.row(row).deselect();
			});
		});

		//select/deselect a row when the checkbox is checked/unchecked
		$('#dynamic-table').on('click', 'td input[type=checkbox]', function() {
			var row = $(this).closest('tr').get(0);
			if (this.checked) myTable.row(row).deselect();
			else myTable.row(row).select();
		});



		$(document).on('click', '#dynamic-table .dropdown-toggle', function(e) {
			e.stopImmediatePropagation();
			e.stopPropagation();
			e.preventDefault();
		});



		//And for the first simple table, which doesn't have TableTools or dataTables
		//select/deselect all rows according to table header checkbox
		var active_class = 'active';
		$('#simple-table > thead > tr > th input[type=checkbox]').eq(0).on('click', function() {
			var th_checked = this.checked; //checkbox inside "TH" table header

			$(this).closest('table').find('tbody > tr').each(function() {
				var row = this;
				if (th_checked) $(row).addClass(active_class).find('input[type=checkbox]').eq(0).prop('checked', true);
				else $(row).removeClass(active_class).find('input[type=checkbox]').eq(0).prop('checked', false);
			});
		});

		//select/deselect a row when the checkbox is checked/unchecked
		$('#simple-table').on('click', 'td input[type=checkbox]', function() {
			var $row = $(this).closest('tr');
			if ($row.is('.detail-row ')) return;
			if (this.checked) $row.addClass(active_class);
			else $row.removeClass(active_class);
		});



		/********************************/
		//add tooltip for small view action buttons in dropdown menu
		$('[data-rel="tooltip"]').tooltip({
			placement: tooltip_placement
		});

		//tooltip placement on right or left
		function tooltip_placement(context, source) {
			var $source = $(source);
			var $parent = $source.closest('table')
			var off1 = $parent.offset();
			var w1 = $parent.width();

			var off2 = $source.offset();
			//var w2 = $source.width();

			if (parseInt(off2.left) < parseInt(off1.left) + parseInt(w1 / 2)) return 'right';
			return 'left';
		}




		/***************/
		$('.show-details-btn').on('click', function(e) {
			e.preventDefault();
			$(this).closest('tr').next().toggleClass('open');
			$(this).find(ace.vars['.icon']).toggleClass('fa-angle-double-down').toggleClass('fa-angle-double-up');
		});
		/***************/




		//obteniendo el ancho de 
		ancho = document.getElementById('dynamic-table').offsetWidth;

		//add horizontal scrollbars to a simple table
		//				$('#dynamic-table').css({'width':'2000px', 'max-width': 'none'}).wrap('<div style="width:'+ancho+' ;" />').parent().ace_scroll(
		//				  {
		//					horizontal: true,
		//					styleClass: 'scroll-top scroll-dark scroll-visible',//show the scrollbars on top(default is bottom)
		//					size: 2000,
		//					mouseWheelLock: true
		//				  }
		//				).css('padding-top', '12px');


		if (!ace.vars['touch']) {
			$('.chosen-select').chosen({
				allow_single_deselect: true
			});
			//resize the chosen on window resize

			$(window)
				.off('resize.chosen')
				.on('resize.chosen', function() {
					$('.chosen-select').each(function() {
						var $this = $(this);
						$this.next().css({
							'width': $this.parent().width()
						});
					})
				}).trigger('resize.chosen');
			//resize chosen on sidebar collapse/expand
			$(document).on('settings.ace.chosen', function(e, event_name, event_val) {
				if (event_name != 'sidebar_collapsed') return;
				$('.chosen-select').each(function() {
					var $this = $(this);
					$this.next().css({
						'width': $this.parent().width()
					});
				})
			});


			$('#chosen-multiple-style .btn').on('click', function(e) {
				var target = $(this).find('input[type=radio]');
				var which = parseInt(target.val());
				if (which == 2) $('#form-field-select-4').addClass('tag-input-style');
				else $('#form-field-select-4').removeClass('tag-input-style');
			});
		}
		$('#id-input-file-3').ace_file_input({
			style: 'well',
			btn_choose: 'Suelte los archivos aquí o haga click',
			btn_change: null,
			no_icon: 'ace-icon fa fa-cloud-upload',
			droppable: true,
			thumbnail: 'small' //large | fit
				//,icon_remove:null//set null, to hide remove/reset button
				/**,before_change:function(files, dropped) {
					//Check an example below
					//or examples/file-upload.html
					return true;
				}*/
				/**,before_remove : function() {
					return true;
				}*/
				,
			preview_error: function(filename, error_code) {
				//name of the file that failed
				//error_code values
				//1 = 'FILE_LOAD_FAILED',
				//2 = 'IMAGE_LOAD_FAILED',
				//3 = 'THUMBNAIL_FAILED'
				//alert(error_code);
			}

		}).on('change', function() {
			//console.log($(this).data('ace_input_files'));
			//console.log($(this).data('ace_input_method'));
		});


		$(".knob").knob();


		var tag_input = $('#form-field-tags');
		try {
			tag_input.tag({
				placeholder: tag_input.attr('placeholder'),
				//enable typeahead by specifying the source array
				source: ace.vars['US_STATES'], //defined in ace.js >> ace.enable_search_ahead
				/**
				//or fetch data from database, fetch those that match "query"
				source: function(query, process) {
				  $.ajax({url: 'remote_source.php?q='+encodeURIComponent(query)})
				  .done(function(result_items){
					process(result_items);
				  });
				}
				*/
			});

			//programmatically add/remove a tag
			var $tag_obj = $('#form-field-tags').data('tag');
			$tag_obj.add('Programmatically Added');

			var index = $tag_obj.inValues('some tag');
			$tag_obj.remove(index);
		} catch (e) {
			//display a textarea for old IE, because it doesn't support this plugin or another one I tried!
			tag_input.after('<textarea id="' + tag_input.attr('id') + '" name="' + tag_input.attr('name') + '" rows="3">' + tag_input.val() + '</textarea>').remove();
			//autosize($('#form-field-tags'));
		}


		/////////
		$('#modal-form input[type=file]').ace_file_input({
			style: 'well',
			btn_choose: 'Suelte los archivos aquí o haga click',
			btn_change: null,
			no_icon: 'ace-icon fa fa-cloud-upload',
			droppable: true,
			thumbnail: 'large'
		});

		//chosen plugin inside a modal will have a zero width because the select element is originally hidden
		//and its width cannot be determined.
		//so we set the width after modal is show
		$('#modal-form').on('shown.bs.modal', function() {
			if (!ace.vars['touch']) {
				$(this).find('.chosen-container').each(function() {
					$(this).find('a:first-child').css('width', '300px');
					$(this).find('.chosen-drop').css('width', '300px');
					$(this).find('.chosen-search input').css('width', '290px');

				});

				//cargar arcciones
				var parametros = {
					"carga_aciones": '-'
				};
				ajax_data_select('json', parametros, '../clases/recepcion_proveido.php', 'post', '#Accion_modal');
				//cargar oficinas
				parametros = {
					"carga_oficinas": '-'
				};
				ajax_data_select('json', parametros, '../clases/recepcion_proveido.php', 'post', '#Oficina_modal');
			}
		});
		/**
		//or you can activate the chosen plugin after modal is shown
		//this way select element becomes visible with dimensions and chosen works as expected
		$('#modal-form').on('shown', function () {
			$(this).find('.modal-chosen').chosen();
		})
		*/
	});

	//funcion que busca en los select
	//var buscar=document.getElementById("buscar").value;
	//variable globales a recuperar


	function select_choosen(obejto_choosen, valorbuscado) {
		//                  alert(valorbuscado);
		var idxdato = 0;
		for (var i = 0, max = obejto_choosen.length; i < max; i++) {
			if (obejto_choosen.options[i].text == valorbuscado) {
				idxdato = obejto_choosen.options[i].value;
				$(obejto_choosen).val(idxdato);
				$(obejto_choosen).trigger("chosen:updated");
				break;
			}
		}
	}

	function ajax_data_select(datatype, parametros, url, type, objeto) {
		$(objeto)
			.empty()
			.append('<option value=""> </option>');
		$.ajax({
			dataType: datatype,
			data: parametros,
			url: url,
			type: type,
			beforeSend: function() {
				//$("#resultado").html("Procesando, espere por favor...");
			},
			success: function(response) {
				for (var i = 0, max = response.length; i < max; i++) {
					$(objeto).append(new Option(response[i][1], response[i][0], true, false));
				}
				$(objeto).trigger("chosen:updated");
			},
			error: function(xhr, status, error) {
				alert(xhr.responseText);
			}
		});
	}
	//                  function ajax_get_single_data(datatype,parametros,url,type){
	//                      var single_data=new Array();
	//                                 
	//                                return single_data;
	//                  }

	//capturando el boton para enviar datos a ser editados


	$(".chosen-select").chosen({
		no_results_text: "Oops, No encontramos Resultados"
	});
	$('.chosen-select').chosen();

	$(".eliminar").click(function() {

		var valores = new Array();
		i = 0;

		$(this).parents("tr").find("td").each(function() {
			valores[i] = $(this).html();
			i++;
		});


		var respuesta = confirm("Ud. está a punto de eliminar un registro ¿Desea Continuar?");
		if (respuesta === true) {
			var parametros = {
				"Eliminar": 'Eliminar',
				"Codigo": valores[1]
			};
			//                                alert(valores[1]);
			$.ajax({
				data: parametros,
				url: '../clases/recepcion_proveido.php',
				type: 'post',
				beforeSend: function() {
					//$("#resultado").html("Procesando, espere por favor...");
				},
				success: function(response) {
					alert("Eliminado Correctamente");
					location.reload();
				},
				error: function(xhr, status, error) {
					alert(xhr.responseText);
				}
			});
		}

	});
	//acciones que se relizan con la ventana modal que se envia datos
	$('.Derivar').on('click', function() {
		var estado_envio = 'Derivar';
		var valores = new Array();
		i = 0;
		$(this).parents("tr").find("td").each(function() {
			valores[i] = $(this).html();
			i++;
		});
		cod_movimiento = valores[1];
		idestadodoc = 2;
		//parametros de obtencion
		var parametros = {
			"Obtener": '-',
			"Codigo": cod_movimiento
		};
		$.ajax({
			dataType: 'json',
			data: parametros,
			url: '../clases/recepcion_proveido.php',
			type: 'post',
			beforeSend: function() {
				//$("#resultado").html("Procesando, espere por favor...");
			},
			success: function(response) {
				idregdocs = response[0][0];
				iddoc_emitidos = response[0][1];
			},
			error: function(xhr, status, error) {
				alert(xhr.responseText);
			}
		});




	});
	$('.Devolver').on('click', function() {
		var estado_envio = 'Devolver';
		var valores = new Array();
		i = 0;
		$(this).parents("tr").find("td").each(function() {
			valores[i] = $(this).html();
			i++;
		});
		cod_movimiento = valores[1];
		idestadodoc = 3;
		//parametros de obtencion
		var parametros = {
			"Obtener": '-',
			"Codigo": cod_movimiento
		};
		$.ajax({
			dataType: 'json',
			data: parametros,
			url: '../clases/recepcion_proveido.php',
			type: 'post',
			beforeSend: function() {
				//$("#resultado").html("Procesando, espere por favor...");
			},
			success: function(response) {
				idregdocs = response[0][0];
				iddoc_emitidos = response[0][1];
				//                                             alert(idregdocs+iddoc_emitidos);
			},
			error: function(xhr, status, error) {
				alert(xhr.responseText);
			}
		});
	});
	$('.Responder').on('click', function() {
		var estado_envio = 'Responder';
		var valores = new Array();
		i = 0;
		$(this).parents("tr").find("td").each(function() {
			valores[i] = $(this).html();
			i++;
		});
		cod_movimiento = valores[1];
		idestadodoc = 4;
		//parametros de obtencion
		var parametros = {
			"Obtener": '-',
			"Codigo": cod_movimiento
		};
		$.ajax({
			dataType: 'json',
			data: parametros,
			url: '../clases/recepcion_proveido.php',
			type: 'post',
			beforeSend: function() {
				//$("#resultado").html("Procesando, espere por favor...");
			},
			success: function(response) {
				idregdocs = response[0][0];
				iddoc_emitidos = response[0][1];
				//                                             alert(idregdocs+iddoc_emitidos);
			},
			error: function(xhr, status, error) {
				alert(xhr.responseText);
			}
		});
	});
	//cambio de estados
	$('.Estados').change(function() {
		var ID = $(this).attr("id");
		var codigo = $(this).val();
		//parametros de obtencion
		var parametros = {
			"update_status": '-',
			"id": ID,
			"codigo": codigo
		};
		$.ajax({
			data: parametros,
			url: '../clases/recepcion_proveido.php',
			type: 'post',
			beforeSend: function() {
				//$("#resultado").html("Procesando, espere por favor...");
			},
			success: function() {
				alert('Estado Cambiado Correctamente');
			},
			error: function(xhr, status, error) {
				location.reload();
			}
		});
	});
	//cuando el documento se encuentra listo se cargan mas opciones
	$(document).ready(function() {
		var idoficina = "<?php echo $_SESSION['idoficina']; ?>";
		$('#mm3').addClass("active open");
		$('#mm21').addClass("active");
		//cargar arcciones
		var parametros = {
			"carga_aciones": '-'
		};
		ajax_data_select('json', parametros, '../clases/recepcion_proveido.php', 'post', '#Accion');
		//carga de datos a documentos a recepcionar
		$('#r_interno').on('click', function() {

			var parametros = {
				"carga_docs_emitidos": '-',
				"idoficina": idoficina
			};
			ajax_data_select('json', parametros, '../clases/recepcion_proveido.php', 'post', '#Documento');
		});
		$('#r_exrterno').on('click', function() {
			$('#Documento')
				.empty()
				.append('<option value=""> </option>');
			var parametros = {
				"carga_externos": '-',
				"idoficina": idoficina
			};
			ajax_data_select('json', parametros, '../clases/recepcion_proveido.php', 'post', '#Documento');


		});
		$('#r_derivado').on('click', function() {
			$('#Documento')
				.empty()
				.append('<option value=""> </option>');
			var parametros = {
				"carga_derivados": '-',
				"idoficina": idoficina
			};
			ajax_data_select('json', parametros, '../clases/recepcion_proveido.php', 'post', '#Documento');
		});
		$('#btnguardar').on('click', function() {
			var externo = document.getElementById('r_exrterno');
			var interno = document.getElementById('r_interno');
			var derivado = document.getElementById('r_derivado');

			var idregdocs = document.getElementById('Documento').value;
			var iddocs_emitidos = document.getElementById('Documento').value;
			var idacciondocs = document.getElementById('Accion').value;
			var idresponsable = "<?php echo $_SESSION['idresponsable']; ?>";
			var detalles = document.getElementById('Detalles').value;
			if (interno.checked === true) {
				idregdocs = null;
				var parametros = {
					"insertar": '-',
					"idregdocs": idregdocs,
					"iddocs_emitidos": iddocs_emitidos,
					"idacciondocs": idacciondocs,
					"idresponsable": idresponsable,
					"detalles": detalles,
					"idoficina": idoficina
				};
			} else if (externo.checked === true) {
				iddocs_emitidos = null;
				var parametros = {
					"insertar": '-',
					"idregdocs": idregdocs,
					"iddocs_emitidos": iddocs_emitidos,
					"idacciondocs": idacciondocs,
					"idresponsable": idresponsable,
					"detalles": detalles,
					"idoficina": idoficina
				};
			} else if (derivado.checked === true) {
				var parametros = {
					"recibir_derivado": '-',
					"idresponsable": idresponsable,
					"detalles": detalles,
					"codigo": iddocs_emitidos,
					"idoficina": idoficina
				};
			} else {
				alert('Por favor Seleccione una Opción');
				return;
			}
			//ajax de envío
			//                        alert('hola');
			$.ajax({

				data: parametros,
				url: '../clases/recepcion_proveido.php',
				type: 'post',
				beforeSend: function() {
					//$("#resultado").html("Procesando, espere por favor...");
				},
				success: function() {
					location.reload();
				},
				error: function(xhr, status, error) {
					alert(xhr.responseText);
				}
			});

		});
		$('#MGuardar').on('click', function() {

			var idoficina_derivar = document.getElementById("Oficina_modal").value;
			var id_accion = document.getElementById("Accion_modal").value;
			var detalles = document.getElementById("det_modal").value;
			var parametros = {
				"insert_deriv_resp_dev": '-',
				"idregdocs": idregdocs,
				"iddocs_emitidos": iddoc_emitidos,
				"idacciondocs": id_accion,
				"idestadodocs": idestadodoc,
				"idoficina_derivar": idoficina_derivar,
				"detalles": detalles,
				"Codigo": cod_movimiento,
				"NuevoEstado": idestadodoc
			};
			$.ajax({
				data: parametros,
				url: '../clases/recepcion_proveido.php',
				type: 'post',
				beforeSend: function(response) {
					document.getElementById('status').innerHTML = 'Enviando....';
				},
				success: function() {
					$("#modal-form").modal("hide");
					alert('Exito se ha enviado Correctamente');
					$('#' + cod_movimiento).val(idestadodoc);

				},
				error: function(xhr, status, error) {
					alert(xhr.responseText + 'errorrrr');
				}
			});
		});

	});
</script>

</body>

</html>