@props(['value' => null, 'physical' => false])
@php
    $key = mb_strtolower(trim((string) $value));
    $tone = $physical ? ($key === 'disponible' ? 'info' : 'neutral') : match ($key) {
        'disponible', 'pagado', 'pagada', 'confirmado', 'confirmada', 'listo', 'lista', 'entregado', 'entregada', 'completado', 'completada', 'cerrada', 'cerrado', 'finalizado', 'finalizada', 'resuelta', 'resuelto', 'aprobado' => 'success',
        'pendiente', 'abierta', 'abierto', 'en atención', 'en curso', 'en preparación', 'preparando', 'ocupada', 'ocupado', 'pendiente de pago', 'pendiente de cobro', 'por cobrar', 'en operación' => 'warning',
        'error', 'urgente', 'crítica', 'crítico', 'rechazado', 'rechazada' => 'danger',
        'programado', 'programada', 'registrado', 'registrada' => 'info',
        default => 'neutral',
    };
@endphp
<span {{ $attributes->class(['ops-status', 'ops-status-'.$tone]) }}><span aria-hidden="true"></span>{{ $value ?: 'Sin estado' }}</span>
