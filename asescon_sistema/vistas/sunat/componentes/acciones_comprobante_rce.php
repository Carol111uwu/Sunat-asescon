<?php
$iconosRce = [
    'ver' => '<circle cx="10.5" cy="10.5" r="5.5"/><path d="m15 15 4 4"/>',
    'pdf' => '<path d="M7 3h7l4 4v14H7zM14 3v5h4M12.5 11v6m-2-2 2 2 2-2"/>',
    'excel' => '<rect x="5" y="3" width="14" height="18" rx="1"/><path d="M5 9h14M5 15h14M12 9v12"/>',
    'correo' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
    'whatsapp' => '<path d="M5 17a8 8 0 1 1 3 2l-4 1z"/><path d="M9 8c-2 2 2 6 4 7 1 0 2-1 2-2l-2-1-1 1c-1-.4-2-1.5-2.5-2.5l1-1L9 8z"/>',
    'archivos' => '<path d="M3 7h7l2 2h9v10H3z"/>',
];
$iconoRce = static fn(string $clave): string => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $iconosRce[$clave] . '</svg>';
