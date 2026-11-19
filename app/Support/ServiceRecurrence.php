<?php

namespace App\Support;

use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Approved immutable revisions; servicios remain the dated operational records. */
final class ServiceRecurrence
{
    public const MODES = ['diario'=>'Servicio diario','semanal'=>'Servicio semanal','personalizado'=>'Servicio personalizado','evento'=>'Evento especial'];
    public const DAYS = [1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo'];

    public static function ready(): bool
    {
        foreach ([
            'service_schedules'=>['id','id_sede','id_usuario','active_revision_id','created_at','updated_at'],
            'service_schedule_revisions'=>['id','schedule_id','id_usuario','estado','scope','effective_from','settings','reviewer_id','observaciones','reviewed_at','created_at','updated_at'],
            'servicios'=>['schedule_id','schedule_revision_id'],
        ] as $table=>$columns) {
            if (!Schema::hasTable($table) || !Schema::hasColumns($table,$columns)) return false;
        }
        return collect(Schema::getIndexes('servicios'))->contains(fn($index)=>$index['unique'] && $index['columns']===['schedule_id','fecha']);
    }

    public static function settings(object $revision): array { return json_decode($revision->settings,true,512,JSON_THROW_ON_ERROR); }

    public static function submit(int $site, int $user, array $settings, ?int $schedule = null, string $scope = 'future', ?string $from = null): int
    {
        abort_unless(self::ready(),503,'La programación recurrente requiere autorizar su migración.');
        return DB::transaction(function () use ($site,$user,$settings,$schedule,$scope,$from) {
            if ($schedule) {
                abort_unless(DB::table('service_schedules')->where('id',$schedule)->where('id_sede',$site)->lockForUpdate()->first(),404);
            } else {
                $schedule = DB::table('service_schedules')->insertGetId(['id_sede'=>$site,'id_usuario'=>$user,'created_at'=>now(),'updated_at'=>now()]);
            }
            if (DB::table('service_schedule_revisions')->where('schedule_id',$schedule)->where('estado','Pendiente')->exists()) {
                throw ValidationException::withMessages(['programacion'=>'Ya existe una propuesta pendiente; debe revisarse antes de enviar otra.']);
            }
            DB::table('service_schedule_revisions')->insert(['schedule_id'=>$schedule,'id_usuario'=>$user,'estado'=>'Pendiente','scope'=>$scope,'effective_from'=>$from ?: $settings['desde'],'settings'=>json_encode($settings,JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
            return $schedule;
        });
    }

    public static function review(int $site, int $reviewer, int $revisionId, bool $approve, ?string $reason): void
    {
        DB::transaction(function () use ($site,$reviewer,$revisionId,$approve,$reason) {
            $proposal=DB::table('service_schedule_revisions')->where('id',$revisionId)->first(); abort_unless($proposal,404);
            $schedule=DB::table('service_schedules')->where('id',$proposal->schedule_id)->where('id_sede',$site)->lockForUpdate()->first(); abort_unless($schedule,404);
            $revision=DB::table('service_schedule_revisions')->where('id',$revisionId)->lockForUpdate()->first();
            abort_unless($revision->estado==='Pendiente' && (int)$revision->id_usuario!==$reviewer,403);
            DB::table('service_schedule_revisions')->where('id',$revisionId)->update(['estado'=>$approve?'Aprobado':'Rechazado','reviewer_id'=>$reviewer,'observaciones'=>$reason,'reviewed_at'=>now(),'updated_at'=>now()]);
            $revision->estado=$approve?'Aprobado':'Rechazado';$revision->reviewer_id=$reviewer;$revision->reviewed_at=now();
            if(!$approve)Service::where('schedule_id',$schedule->id)->where('schedule_revision_id',$revisionId)->where('estado_aprobacion','Pendiente')->update(['estado_aprobacion'=>'Rechazado','id_usuario_revision'=>$reviewer,'revisado_en'=>now(),'observaciones_revision'=>$reason]);
            if ($approve && $revision->scope!=='one') DB::table('service_schedules')->where('id',$schedule->id)->update(['active_revision_id'=>$revisionId,'updated_at'=>now()]);
            if ($approve) {
                // Reconcile only future untouched records. Existing menus and operational relations stay attached.
                $rows=Service::where('schedule_id',$schedule->id)->whereDate('fecha','>=',max($revision->effective_from,now('America/Mexico_City')->toDateString()))->orderBy('fecha')->lockForUpdate()->get();
                foreach($rows as $row) {
                    if ($revision->scope==='one' && $row->fecha->toDateString()!==$revision->effective_from) continue;
                    if (!self::mutable($row)) continue;
                    self::apply($row,self::settings($revision),$revision,$row->fecha->toDateString());
                }
            }
        });
    }

    private static function mutable(Service $row): bool
    {
        if ($row->estado!=='Programado' && $row->estado!=='Cancelado') return false;
        foreach(['atenciones','grupos','zona_meseros'] as $table) if(Schema::hasTable($table) && Schema::hasColumn($table,'id_servicio') && DB::table($table)->where('id_servicio',$row->id_servicio)->exists()) return false;
        return true;
    }

    public static function hours(array $s, string $date): ?array
    {
        if ($date<$s['desde'] || (!empty($s['hasta']) && $date>$s['hasta']) || !empty($s['suspended'])) return null;
        $day=CarbonImmutable::parse($date)->dayOfWeekIso;
        if($s['mode']==='evento' && $date!==$s['desde']) return null;
        if($s['mode']!=='diario' && $s['mode']!=='evento' && !in_array($day,array_map('intval',$s['days']),true)) return null;
        return $s['mode']==='personalizado' ? ($s['hours'][$day] ?? null) : ['start'=>$s['start'],'end'=>$s['end']];
    }

    private static function apply(Service $row, array $s, object $revision, string $date): void
    {
        $hours=self::hours($s,$date);
        $data=['nombre'=>$s['nombre'],'capacidad'=>$s['capacidad'],'estado_aprobacion'=>$revision->estado,'schedule_revision_id'=>$revision->id,'id_usuario_revision'=>$revision->reviewer_id,'revisado_en'=>$revision->reviewed_at];
        if($hours) $data+=['inicio_hora'=>$hours['start'],'fin_hora'=>$hours['end'],'estado'=>'Programado'];
        else $data['estado']='Cancelado';
        if(Schema::hasColumn('servicios','cover_url'))$data['cover_url']=$s['cover_url'] ?? null;
        $row->forceFill($data)->save();
    }

    public static function ensure(int $site, string $from, string $until): void
    {
        if(!self::ready())return;
        $start=CarbonImmutable::parse($from);$end=CarbonImmutable::parse($until);
        abort_if($end->lt($start) || $start->diffInDays($end)>366,422,'El periodo máximo de generación es de un año.');
        foreach(DB::table('service_schedules')->where('id_sede',$site)->pluck('id') as $id) {
            DB::transaction(function () use ($id,$site,$start,$end) {
                // Serialize concurrent browser requests before looking up or inserting dates.
                DB::table('service_schedules')->where('id',$id)->lockForUpdate()->first();
                $revisions=DB::table('service_schedule_revisions')->where('schedule_id',$id)->where('estado','Aprobado')->orderBy('id')->get();
                if($revisions->isEmpty())$revisions=DB::table('service_schedule_revisions')->where('schedule_id',$id)->where('estado','Pendiente')->orderBy('id')->get();
                for($day=$start;$day->lte($end);$day=$day->addDay()) {
                    $date=$day->toDateString();
                    if($date<now('America/Mexico_City')->toDateString())continue;
                    $revision=$revisions->filter(fn($r)=>$r->effective_from<=$date && ($r->scope!=='one' || $r->effective_from===$date))->last();
                    if(!$revision)continue;
                    $settings=self::settings($revision);$hours=self::hours($settings,$date);if(!$hours)continue;
                    if(Service::where('schedule_id',$id)->whereDate('fecha',$date)->exists())continue;
                    $row=new Service();$row->forceFill(['schedule_id'=>$id,'id_sede'=>$site,'fecha'=>$date,'id_usuario_registro'=>$revision->id_usuario,'inicio_hora'=>$hours['start'],'fin_hora'=>$hours['end']]);
                    self::apply($row,$settings,$revision,$date);
                }
            });
        }
    }
}
