<?php
/* =====================================================================
   SISGEDO — Responder primero, trabajar después
   ---------------------------------------------------------------------
   Para tareas lentas que el usuario no necesita esperar (enviar correos,
   copiarlos a "Enviados"...): se entrega la respuesta al navegador y la
   conexión se cierra; el script sigue ejecutándose en el servidor.

   Uso:
       sgd_respuesta_iniciar();          // al comienzo (guarda la salida)
       echo $numexp;                     // respuesta normal
       sgd_respuesta_terminar();         // el navegador ya la recibe
       enviar_correos();                 // el usuario ya no espera esto
   ===================================================================== */

if (!function_exists('sgd_respuesta_iniciar')) {

    /** Empieza a guardar la salida para poder cerrarla con su tamaño exacto. */
    function sgd_respuesta_iniciar()
    {
        ob_start();
    }

    /**
     * Entrega lo escrito hasta ahora y cierra la conexión con el navegador.
     * El script continúa (sin límite de tiempo corto y aunque el usuario
     * cierre la página).
     */
    function sgd_respuesta_terminar()
    {
        ignore_user_abort(true);
        @set_time_limit(180);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();          // no bloquear otras peticiones del mismo usuario
        }
        // PHP-FPM / LiteSpeed (lo habitual en cPanel): cierre nativo
        if (function_exists('fastcgi_finish_request')) {
            while (ob_get_level() > 0) ob_end_flush();
            fastcgi_finish_request();
            return;
        }
        if (function_exists('litespeed_finish_request')) {
            while (ob_get_level() > 0) ob_end_flush();
            litespeed_finish_request();
            return;
        }
        // Otros servidores: indicar el tamaño exacto y cerrar la conexión
        $contenido = '';
        while (ob_get_level() > 0) $contenido = ob_get_clean() . $contenido;
        if (!headers_sent()) {
            header('Content-Encoding: none');       // que no se comprima (cambiaría el tamaño)
            header('Content-Length: ' . strlen($contenido));
            header('Connection: close');
        }
        echo $contenido;
        @ob_flush();
        flush();
    }
}
