<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SiteMedia extends Model { protected $table='site_media'; protected $fillable=['id_sede','path','name','mime','size']; public function getUrlAttribute(): string { return \Illuminate\Support\Facades\Storage::disk('public')->url($this->path); } }
