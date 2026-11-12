<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Atencion extends Model
{
    protected $table = 'atenciones';

    protected $primaryKey = 'id_atencion';

    public $timestamps = false;

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'id_servicio', 'id_servicio');
    }

    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa::class, 'id_mesa', 'id_mesa');
    }

    public function mesero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_mesero', 'id_usuario');
    }

    public function servicioOfrecido(): HasOne
    {
        return $this->hasOne(ServicioOfrecido::class, 'id_atencion', 'id_atencion');
    }
}
