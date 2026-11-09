<?php
declare(strict_types=1);
function iconoAccionBuzonSol(string $accion): string
{
    $figuras = [
        'ver' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'descargar' => '<path d="M12 3v12m-5-5 5 5 5-5M4 17v4h16v-4"/>',
        'correo' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'whatsapp' => '<path d="M20.5 11.5a8.5 8.5 0 0 1-12.3 7.6L3 21l1.9-5.2A8.5 8.5 0 1 1 20.5 11.5Z"/><path d="M9 8.5c.5 3 3 5.5 6 6l1.5-1.5"/>',
        'revisar' => '<path d="m4 12 5 5L20 6"/>',
        'cerrar' => '<rect x="5" y="5" width="14" height="14" rx="2"/><path d="m9 9 6 6M15 9l-6 6"/>',
        'reabrir' => '<path d="M3 11a9 9 0 1 1 3 7M3 4v7h7"/>',
        'notas' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
        'asignar' => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c.3-3.3 2.8-5.5 7-5.5 2 0 3.6.5 4.8 1.4M19 8v8M15 12h8"/>',
    ];
    $figura = $figuras[$accion] ?? '';
    return '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">'.$figura.'</svg>';
}
