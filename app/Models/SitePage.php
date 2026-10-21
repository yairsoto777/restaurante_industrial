<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SitePage extends Model
{
    protected $table = 'site_pages';
    protected $primaryKey = 'id_pagina';
    protected $fillable = ['id_sede', 'slug', 'template', 'draft', 'published', 'status', 'published_at'];
    protected $casts = ['draft' => 'array', 'published' => 'array', 'published_at' => 'datetime'];
    public function site(): BelongsTo { return $this->belongsTo(Site::class, 'id_sede', 'id_sede'); }
}
