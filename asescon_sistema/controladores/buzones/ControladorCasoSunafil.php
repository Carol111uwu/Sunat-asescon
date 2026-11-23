<?php
declare(strict_types=1);

require_once __DIR__.'/../../modelos/buzones/RepositorioDocumentoBuzon.php';
require_once __DIR__.'/../../modelos/buzones/SeguimientoNotificacionesSunafil.php';
require_once __DIR__.'/../../servicios/archivos/ServicioArchivosPrivados.php';
require_once __DIR__.'/../../servicios/sunafil/ServicioColaBuzonSunafil.php';

final class ControladorCasoSunafil
{
    private SeguimientoNotificacionesSunafil $seguimiento;

    public function __construct(private mysqli $conexion)
    {
        $this->seguimiento = new SeguimientoNotificacionesSunafil($conexion);
    }

    public function preparar(int $id, ?array $post): array
    {
        asesconExigirPermiso($this->conexion, 'BUZON_SUNAFIL', 'ver');
        $filtro = asesconFiltroClientesSql($this->conexion, 'c');
        $doc = (new RepositorioDocumentoBuzon($this->conexion))->obtener('SUNAFIL', $id, $filtro);
        if (!$doc || (int)$doc['id_cliente'] < 1) {
            http_response_code(404);
            exit('Requerimiento no disponible para tu cuenta.');
        }

        $cliente = (int)$doc['id_cliente'];
        $actor = (int)asesconUsuarioActual($this->conexion)['id_usuario'];
        $editar = asesconTienePermiso($this->conexion, 'BUZON_SUNAFIL', 'editar');
        $gestionar = $editar && asesconEsPrincipal($this->conexion);
        $caso = $this->seguimiento->caso($id, $cliente);
        $verAvances = $gestionar || (int)($caso['id_responsable'] ?? 0) === $actor;
        $reportar = $editar && $verAvances && ($caso['estado'] ?? '') === 'ABIERTO';
        $archivo = (new ServicioArchivosPrivados())->resolver((string)($doc['archivo'] ?? ''), 'SUNAFIL');

        if ($post !== null) {
            if (!asesconValidarCsrf((string)($post['csrf_token'] ?? ''), 'csrf_buzon_sunafil')) {
                http_response_code(403);
                exit('La sesión del formulario venció.');
            }
            try {
                $mensaje = $this->procesar($post, $id, $doc, $actor, $editar, $gestionar, $reportar, $archivo);
            } catch (Throwable $error) {
                error_log('Caso SUNAFIL: '.get_class($error));
                $mensaje = $error instanceof DomainException ? $error->getMessage() : 'No se pudo completar la acción. Revisa los registros de ASESCON.';
            }
            $_SESSION['sunafil_caso_mensaje'][$id] = $mensaje;
            asesconRedirigir('vistas/buzones/caso_sunafil.php', ['id_documento'=>$id]);
        }

        $mensaje = $_SESSION['sunafil_caso_mensaje'][$id] ?? '';
        unset($_SESSION['sunafil_caso_mensaje'][$id]);
        $csrf = asesconCsrf('csrf_buzon_sunafil');
        $seguimientoDisponible = $this->seguimiento->disponible();
        $responsables = $seguimientoDisponible && $gestionar ? $this->seguimiento->responsables($cliente) : [];
        $avances = $seguimientoDisponible && $verAvances ? $this->seguimiento->avances($id, $cliente) : [];
        $estadoPdf = [];
        try {
            $estadoPdf = (new ServicioColaBuzonSunafil())->obtenerEstadoDocumento($cliente, $id);
        } catch (Throwable $error) {
            error_log('Estado SUNAFIL: '.get_class($error));
        }

        return compact('doc','cliente','editar','gestionar','reportar','verAvances','caso','archivo',
            'mensaje','csrf','seguimientoDisponible','responsables','avances','estadoPdf');
    }

    private function procesar(array $post, int $id, array $doc, int $actor, bool $editar, bool $gestionar,
        bool $reportar, ?string $archivo): string
    {
        $accion = (string)($post['accion'] ?? '');
        $cliente = (int)$doc['id_cliente'];

        if ($accion === 'pdf' && $editar) {
            if ($archivo === null) {
                (new ServicioColaBuzonSunafil())->solicitar($cliente, $actor, $id);
                return 'PDF solicitado a SUNAFIL. Actualiza esta pantalla cuando termine el proceso.';
            }
            return 'El PDF ya está disponible.';
        }
        if ($accion === 'correo' && $editar) {
            if ($archivo === null) {
                throw new DomainException('Primero solicita o vincula el PDF oficial.');
            }
            return $this->enviarCorreo($post, $doc, $id, $archivo);
        }
        if ($accion === 'habilitar' && $gestionar) {
            $this->seguimiento->habilitar();
            return 'Seguimiento habilitado.';
        }
        if (!$this->seguimiento->disponible()) {
            throw new DomainException('La cuenta principal debe habilitar el seguimiento SUNAFIL.');
        }
        if ($accion === 'asignar' && $gestionar) {
            $responsable = (int)($post['responsable'] ?? 0);
            $this->seguimiento->guardarAsignacion($id, $cliente, $responsable ?: null, $actor);
            return 'Responsable actualizado.';
        }
        if (in_array($accion, ['cerrar','reabrir'], true) && $gestionar) {
            $this->seguimiento->cambiarEstado($id, $cliente, $actor, $accion === 'cerrar');
            return $accion === 'cerrar' ? 'Caso cerrado.' : 'Caso abierto.';
        }
        if ($accion === 'nota' && $reportar) {
            $nota = trim((string)($post['nota'] ?? ''));
            if ($nota === '' || strlen($nota) > 20000) {
                throw new DomainException('Escribe un avance válido.');
            }
            $this->seguimiento->agregarAvance($id, $cliente, $actor, $nota, $gestionar);
            return 'Avance registrado.';
        }
        throw new DomainException('Tu cuenta no está autorizada para realizar esta acción.');
    }

    private function enviarCorreo(array $post, array $doc, int $id, string $archivo): string
    {
        $destino = trim((string)($post['destinatario'] ?? ''));
        $texto = trim((string)($post['mensaje'] ?? ''));
        if (!filter_var($destino, FILTER_VALIDATE_EMAIL) || $texto === '' || strlen($texto) > 20000) {
            throw new DomainException('Revisa el destinatario y el mensaje.');
        }
        require_once __DIR__.'/../../servicios/comunicaciones/ServicioCorreoSmtp.php';
        $texto .= "\n\nCliente: {$doc['razon_social']}\nRUC: {$doc['ruc']}\nAsunto: {$doc['asunto']}";
        $html = nl2br(htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        (new ServicioCorreoSmtp())->enviar($destino, 'Notificación SUNAFIL - '.$doc['ruc'], $html, 'ASESCON', '',
            [['ruta'=>$archivo,'nombre'=>"SUNAFIL_{$id}.pdf"]], $texto);
        return 'El servidor SMTP aceptó el correo con el PDF adjunto.';
    }
}
