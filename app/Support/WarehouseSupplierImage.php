<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class WarehouseSupplierImage
{
    public static function column(): ?string
    {
        foreach (['logo_url', 'imagen_url'] as $column) {
            if (Schema::hasColumn('proveedores', $column)) return $column;
        }
        return null;
    }

    public static function coverColumn(): ?string
    {
        return Schema::hasColumn('proveedores', 'cover_url') ? 'cover_url' : null;
    }

    public static function coverUrl(?object $supplier): string
    {
        return self::pathUrl($supplier->cover_url ?? null) ?: asset('img/ui/warehouse-logistics.jpg');
    }

    public static function url(?object $supplier): ?string
    {
        return self::pathUrl($supplier->logo_url ?? $supplier->imagen_url ?? null);
    }

    private static function pathUrl(?string $path): ?string
    {
        if (!is_string($path) || trim($path) === '') return null;
        if (preg_match('~^https?://~i', $path) || str_starts_with($path, '/')) return $path;
        if (str_contains($path, ':')) return null;
        // The local public disk URL uses APP_URL, which may omit the installation
        // subdirectory. Build it from the current web request, as other assets do.
        return config('filesystems.disks.public.driver') === 'local'
            ? asset('storage/'.ltrim($path, '/'))
            : Storage::disk('public')->url($path);
    }

    public static function initials(?string $name): string
    {
        $words = preg_split('/\s+/u', trim($name ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        return $words ? mb_strtoupper(implode('', array_map(fn ($word) => mb_substr($word, 0, 1), array_slice($words, 0, 2)))) : 'NP';
    }

    public static function rules(?string $column): array
    {
        return $column ? ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'dimensions:min_width=1,min_height=1', 'max:4096'] : ['prohibited'];
    }

    // Keep files out of retried transactions and remove the new file if saving fails.
    public static function save(Request $request, ?string $column, callable $persist): void
    {
        $paths = [];
        $values = [];
        try {
            foreach (['imagen' => $column, 'portada' => self::coverColumn()] as $field => $targetColumn) {
                if (!$targetColumn || !$request->hasFile($field)) continue;
                $path = $request->file($field)->store($field === 'portada' ? 'proveedores/portadas' : 'proveedores', 'public');
                if (!$path) throw ValidationException::withMessages([$field => 'No se pudo guardar la imagen. Inténtalo de nuevo.']);
                $paths[] = $path;
                $values[$targetColumn] = $path;
            }
            $persist($values);
        } catch (\Throwable $exception) {
            if ($paths) Storage::disk('public')->delete($paths);
            throw $exception;
        }
    }
}
