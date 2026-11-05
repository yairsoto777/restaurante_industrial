<?php

namespace App\Support;

use App\Models\CashSession;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/** Conservative checks for corrections to an opening, not financial settlement. */
final class CashOpeningSafety
{
    public function blockReason(CashSession $session, bool $lock = false): ?string
    {
        if ($session->estado !== CashSession::OPEN || $session->fecha_cierre !== null) {
            return 'Solo se puede corregir o anular una apertura que siga abierta y sin cierre registrado.';
        }

        try {
            // Do not filter by state, account validity or site: even an orphan payment blocks corrections.
            $payments = DB::table('pagos')->where('id_corte_caja', $session->getKey());
            if ($lock) {
                $payments->lockForUpdate();
            }
            if ($payments->first(['id_pago'])) {
                return 'La apertura tiene pagos asociados. No se permite modificar el fondo ni anularla, cualquiera que sea el estado del pago.';
            }

            // An operator's payment without a valid cut is ambiguous, including old rows.
            // Dates do not prove its assignment (legacy DATETIME values have no timezone).
            $unassigned = DB::table('pagos as p')->where('p.id_usuario', $session->id_usuario_cajero)
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')->from('cortes_caja as c')->whereColumn('c.id_corte_caja', 'p.id_corte_caja');
                });
            if ($lock) {
                $unassigned->lockForUpdate();
            }
            if ($unassigned->first(['p.id_pago'])) {
                return 'Hay pagos del responsable sin un corte válido. Es necesario conciliarlos antes de corregir o anular.';
            }

            foreach (['total_efectivo', 'total_tarjeta', 'total_transferencia', 'total_otros', 'total_sistema'] as $column) {
                if (CashAmount::cents((string) $session->$column) !== 0) {
                    return 'El turno conserva totales financieros. Solicita conciliación antes de modificar o anular la apertura.';
                }
            }
            if ($session->efectivo_contado !== null || $session->diferencia !== null
                || CashAmount::cents($session->fondo_inicial) < 0
                || CashAmount::cents($session->efectivo_esperado) !== CashAmount::cents($session->fondo_inicial)) {
                return 'El turno contiene datos de arqueo o importes inconsistentes. No se permite corregirlo como una apertura sin movimientos.';
            }

            foreach ($this->movementTables() as $table => $columns) {
                if (! in_array('id_corte_caja', $columns, true)) {
                    return 'Existen estructuras financieras adicionales cuya vinculación debe revisarse antes de corregir esta apertura.';
                }
                $movements = DB::table($table)->where('id_corte_caja', $session->getKey());
                if ($lock) {
                    $movements->lockForUpdate();
                }
                if ($movements->first()) {
                    return 'Hay otros movimientos relacionados con el turno. No se permite modificar ni anular su apertura.';
                }
            }
        } catch (QueryException|InvalidArgumentException $exception) {
            if ($lock && $exception instanceof QueryException) {
                // Let Laravel roll back/retry database deadlocks instead of swallowing them.
                throw $exception;
            }
            return 'No fue posible verificar que la apertura esté libre de movimientos. La operación permanece bloqueada.';
        }

        return null;
    }

    public function snapshot(CashSession $session): array
    {
        $data = $session->only([
            'id_sede', 'id_usuario_cajero', 'estado', 'fondo_inicial', 'efectivo_esperado',
            'total_efectivo', 'total_tarjeta', 'total_transferencia', 'total_otros', 'total_sistema',
            'efectivo_contado', 'diferencia', 'observaciones',
        ]);
        $data['fecha_apertura'] = $session->fecha_apertura?->format('Y-m-d H:i:s');
        $data['fecha_cierre'] = $session->fecha_cierre?->format('Y-m-d H:i:s');

        return $data;
    }

    public function revision(CashSession $session): string
    {
        // Include the last audit ID to prevent an A -> B -> A edit from reviving an old form.
        $auditId = DB::table('bitacora')->where('tabla_afectada', 'cortes_caja')
            ->where('id_registro', $session->getKey())->max('id_bitacora');

        return hash_hmac('sha256', json_encode([
            $session->getKey(), $this->snapshot($session), $auditId,
        ], JSON_THROW_ON_ERROR), (string) config('app.key'));
    }

    private function movementTables(): array
    {
        $tables = [];
        // Inspect the installed schema instead of assuming future movements do not exist.
        // Unknown financial structures fail closed until their contract is reviewed.
        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            if (in_array($name, ['cortes_caja', 'pagos', 'bitacora'], true)) {
                continue;
            }
            $columns = Schema::getColumnListing($name);
            $hasLink = array_intersect($columns, ['id_corte_caja', 'id_corte', 'id_caja', 'id_turno']);
            $financialName = preg_match('/(caja|corte|pago|retiro|deposito|egreso|ingreso|devolucion|reembolso)/i', $name);
            if ($hasLink || $financialName) {
                $tables[$name] = $columns;
            }
        }

        return $tables;
    }
}
