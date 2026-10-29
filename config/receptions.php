<?php

return [
    // Activar solo después de aprobar los estados y aplicar la migración propuesta.
    'capture_enabled' => false,
    'pending' => 'Pendiente de revisión',
    'counted_states' => ['Pendiente de revisión', 'Confirmada'],
    'released_states' => ['Anulada', 'Rechazada por almacén'],
    'order_states' => ['Programado'],
];
