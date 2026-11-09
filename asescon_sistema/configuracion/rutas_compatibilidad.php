<?php
declare(strict_types=1);

                                                                        
function asesconNormalizarRuta(string $ruta): string
{
    $ruta = ltrim($ruta, "/");
    $corte = strcspn($ruta, "?#");
    $sufijo = substr($ruta, $corte);
    $ruta = substr($ruta, 0, $corte);
    $equivalencias = [
        'vistas/clientes/recordatorio_whatsapp.php' => 'vistas/clientes/comunicacion_informes.php?seccion=recordatorios',
        'vistas/clientes/informes_entregables.php' => 'vistas/clientes/comunicacion_informes.php?seccion=informes',
        'vistas/comunicaciones/envio_entregables.php' => 'vistas/clientes/comunicacion_informes.php?seccion=entregables',
        'vistas/clientes/planes_servicio.php' => 'vistas/clientes/servicios_cobros.php?seccion=planes',
        'vistas/clientes/cotizaciones.php' => 'vistas/clientes/servicios_cobros.php?seccion=cotizaciones',
        'vistas/clientes/honorarios_pendientes.php' => 'vistas/clientes/servicios_cobros.php?seccion=honorarios',
        'api/sunat/consultar_declaraciones.php' => 'api/sunat/declaraciones_pagos/consultar_declaraciones_y_pagos.php',
        'vistas/sunat/acceso_portal.php' => 'vistas/clientes/gestion_clientes.php',
        'views/sunat/panel_operaciones_sire.php' => 'vistas/sunat/panel_sire.php',
        'vistas/sunat/panel_operaciones_sire.php' => 'vistas/sunat/panel_sire.php',
        'api/clientes/buscar.php' => 'api/clientes/buscar_clientes.php',
        'api/sunat/ficha_ruc.php' => 'api/sunat/consulta_ficha_ruc.php',
        'api/sunat/sire.php' => 'api/sunat/operaciones_sire.php',
        'controllers/afp/AfpController.php' => 'controladores/afp/ControladorAFP.php',
        'controllers/autenticacion/CambiarCuentaController.php' => 'controladores/autenticacion/ControladorCambioCuenta.php',
        'controllers/autenticacion/LoginController.php' => 'controladores/autenticacion/ControladorInicioSesion.php',
        'controllers/buzones/BuzonSolController.php' => 'controladores/buzones/ControladorBuzonSol.php',
        'controllers/buzones/BuzonSunafilController.php' => 'controladores/buzones/ControladorBuzonSunafil.php',
        'controllers/clientes/ClienteController.php' => 'controladores/clientes/ControladorCliente.php',
        'controllers/clientes/CotizacionesController.php' => 'controladores/clientes/ControladorCotizaciones.php',
        'controllers/clientes/HonorariosController.php' => 'controladores/clientes/ControladorHonorarios.php',
        'controllers/clientes/InformesController.php' => 'controladores/clientes/ControladorInformes.php',
        'controllers/clientes/PlanesController.php' => 'controladores/clientes/ControladorPlanes.php',
        'controllers/clientes/WhatsAppController.php' => 'controladores/clientes/ControladorWhatsApp.php',
        'controllers/dashboard/ControladorDashboard.php' => 'controladores/panel/ControladorPanelPrincipal.php',
        'controllers/equipo/EquipoController.php' => 'controladores/equipo/ControladorEquipo.php',
        'controllers/seguridad/PerfilCuentaController.php' => 'controladores/seguridad/ControladorPerfilCuenta.php',
        'controllers/seguridad/RecordatorioContrasenaController.php' => 'controladores/seguridad/ControladorRecordatorioContrasena.php',
        'controllers/sunat/AlertaSunatController.php' => 'controladores/sunat/ControladorAlertaSunat.php',
        'controllers/sunat/ComprobanteNoDomiciliadoController.php' => 'controladores/sunat/ControladorComprobanteNoDomiciliado.php',
        'controllers/sunat/ComprobanteSunatController.php' => 'controladores/sunat/ControladorComprobanteSunat.php',
        'controllers/sunat/CpeController.php' => 'controladores/sunat/ControladorCpe.php',
        'controllers/sunat/DeclaracionController.php' => 'controladores/sunat/ControladorDeclaracion.php',
        'controllers/sunat/DetraccionController.php' => 'controladores/sunat/ControladorDetraccion.php',
        'controllers/sunat/LiquidacionController.php' => 'controladores/sunat/ControladorLiquidacion.php',
        'controllers/sunat/Pdt0621Controller.php' => 'controladores/sunat/ControladorPdt0621.php',
        'controllers/sunat/Plame0601Controller.php' => 'controladores/sunat/ControladorPlame0601.php',
        'controllers/sunat/ReciboHonorarioController.php' => 'controladores/sunat/ControladorReciboHonorario.php',
        'controllers/sunat/TipoCambioController.php' => 'controladores/sunat/ControladorTipoCambio.php',
        'controllers/sunat/VencimientoSunatController.php' => 'controladores/sunat/ControladorVencimientoSunat.php',
        'controllers/tareas/TareaController.php' => 'controladores/tareas/ControladorTarea.php',
        'public/css/base/dashboard.css' => 'publico/css/base/estructura_panel.css',
        'public/css/base/responsive.css' => 'publico/css/base/diseno_adaptable.css',
        'public/js/sunat/dashboard_sire.js' => 'publico/js/sunat/panel_sire.js',
        'views/dashboard.php' => 'vistas/panel_principal.php',
        'views/login.php' => 'vistas/inicio_sesion.php',
        'views/logout.php' => 'vistas/cerrar_sesion.php',
        'views/partials/sidebar.php' => 'vistas/componentes/menu_lateral.php',
        'views/partials/topbar.php' => 'vistas/componentes/barra_superior.php',
        'views/sunat/dashboard_sire.php' => 'vistas/sunat/panel_sire.php',
        'controllers/autenticacion/RestablecerContrasenaController.php' => 'controladores/autenticacion/ControladorRestablecimientoContrasena.php',
        'controllers/autenticacion/SolicitarRecuperacionContrasenaController.php' => 'controladores/autenticacion/ControladorSolicitudRecuperacionContrasena.php',
        'views/partials/alerta_cambio_contrasena.php' => 'vistas/componentes/alerta_cambio_contrasena.php',
        'public/templates/comprobantes_no_domiciliados.csv' => 'publico/plantillas/comprobantes_no_domiciliados.csv',
    ];
    if (isset($equivalencias[$ruta])) {
        return $equivalencias[$ruta] . (str_contains($equivalencias[$ruta], '?') && str_starts_with($sufijo, '?') ? '&'.substr($sufijo,1) : $sufijo);
    }
    foreach (["views/" => "vistas/", "controllers/" => "controladores/", "models/" => "modelos/", "services/" => "servicios/", "public/" => "publico/"] as $anterior => $actual) {
        if (str_starts_with($ruta, $anterior)) {
            return $actual . substr($ruta, strlen($anterior)) . $sufijo;
        }
    }
    return $ruta . $sufijo;
}
