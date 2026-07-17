<?php

require_once '../conexion/conexion.php';
require_once __DIR__ . '/seguridad.php';
class Read_main
{

    public function list_menu($usuario, $modulo, $complemento_direccion)
    {
        //        session_start();
        // Entrega central del token CSRF a todas las vistas (se envía en cada AJAX).
        echo '<script>(function(){var t="' . csrf_token() . '";var n=0;(function s(){if(window.jQuery){jQuery.ajaxSetup({headers:{"X-CSRF-Token":t}});jQuery(function(){jQuery("form").each(function(){if(!jQuery(this).find("input[name=_csrf]").length){jQuery("<input>",{type:"hidden",name:"_csrf",value:t}).appendTo(this);}});});}else if(n++<200){setTimeout(s,30);}})();})();</script>';
        // Oculta elementos decorativos/inutiles del tema en todas las vistas:
        //  - #sidebar-shortcuts: los 4 botones de colores del sidebar.
        //  - #nav-search: el buscador superior derecho que no tiene funcion.
        echo '<style>#sidebar-shortcuts{display:none !important;} #nav-search{display:none !important;}</style>';
        // Mitiga el parpadeo de "recuadro blanco" del tema al hacer scroll o volver a la pestana:
        // estabiliza la capa de pintado del contenedor principal (compositing en Chromium/Edge).
        // Solo backface-visibility (no usa transform, para no afectar z-index de desplegables).
        echo '<style>.main-content{backface-visibility:hidden;-webkit-backface-visibility:hidden;}</style>';
        try {
            $conexion = new Conexion();
            $conectar = $conexion->conectar();
            $sql = "select menu.nombre_menu,menu.clase_llamar,menu.direccion,menu.dependencia,menu.font_icon,menu.idmenu
                    from accesos_usuario
                    inner join personal on accesos_usuario.idusuario=personal.idpersonal
                    inner join menu on accesos_usuario.idmenu=menu.idmenu
                    where personal.idpersonal=? and accesos_usuario.access=true and menu.dependencia is null and menu.idmodulo=?";
            $result = $conectar->prepare($sql);
            $result->execute(array($usuario, $modulo));
            foreach ($result as $list_main) {
                echo '<li class="' . $list_main[1] . '" id="mm' . $list_main[5] . '">';
                echo '<a href="#" class="dropdown-toggle">';
                echo    '<i class="' . $list_main[4] . '"></i>';
                echo    '<span class="menu-text">';
                echo    $list_main[0];
                echo    '</span>';
                echo    '<b class="arrow fa fa-angle-down"></b>';
                echo    '<b class="arrow"></b>';
                echo  '</a>';
                echo '<b class="arrow"></b>';
                //verificando dependencias
                $subsql = "select menu.nombre_menu,menu.clase_llamar,menu.direccion,menu.dependencia,menu.font_icon,menu.idmenu
                    from accesos_usuario
                    inner join personal on accesos_usuario.idusuario=personal.idpersonal
                    inner join menu on accesos_usuario.idmenu=menu.idmenu
                    where personal.idpersonal=? and accesos_usuario.access=true and menu.dependencia=?
                    order by menu.nombre_menu asc";
                $subresult = $conectar->prepare($subsql);
                $subresult->execute([$usuario, $list_main[5]]);
                if ($subresult->rowCount() > 0) {
                    echo '<ul class="submenu">';
                    foreach ($subresult as $list_submain) {
                        echo '<li class="" id="mm' . $list_submain[5] . '">';
                        echo '<a href="' . $complemento_direccion . $list_submain[2] . '" >';
                        echo '<i class="menu-icon fa fa-caret-right"></i>';
                        echo $list_submain[0];
                        echo '<b class="arrow"></b>';
                        echo '</a>';
                        echo '<b class="arrow"></b>';
                        echo '</li>';
                    }
                    echo '</ul>';
                }
                echo '</li>';
            }
        } catch (Exception $exc) {
            header('location: read_main.php');
            error_log($exc->getMessage());
        }
    }
}
