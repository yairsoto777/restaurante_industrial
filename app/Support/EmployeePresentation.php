<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Optional presentation metadata; role authorization stays in usuario_sede. */
final class EmployeePresentation
{
    private function directory(Employee $employee): string
    {
        return 'employee-profiles/'.(int) $employee->id_sede.'/'.(int) $employee->getKey();
    }

    private function metadata(Employee $employee): array
    {
        $disk = Storage::disk('local');
        $path = $this->directory($employee).'/profile.json';
        if (! $disk->exists($path)) return [];
        $data = json_decode($disk->get($path), true);
        return is_array($data) ? $data : [];
    }

    public function profile(Employee $employee, array $roleIds = []): array
    {
        $data = $employee->exists ? $this->metadata($employee) : [];
        $roleIds = array_map('intval', $roleIds);
        $primary = (int) ($data['primary_role_id'] ?? 0);
        return [
            'primary_role_id' => in_array($primary, $roleIds, true) ? $primary : ($roleIds[0] ?? null),
            'has_photo' => $employee->exists && $this->photoPath($employee) !== null,
            'photo_url' => $employee->exists && $this->photoPath($employee) !== null
                ? route('manager.employees.photo', ['employee' => $employee->getKey(), 'v' => $data['photo'] ?? 'existing']) : null,
        ];
    }

    public function photoPath(Employee $employee): ?string
    {
        return $this->photoLocation($employee)['path'] ?? null;
    }

    public function photoLocation(Employee $employee): ?array
    {
        $data = $this->metadata($employee);
        $filename = $data['photo'] ?? null;
        if (! is_string($filename) || ! preg_match('/\A(?:photo|[a-f0-9-]{36})\.(?:jpg|jpeg|png|webp)\z/D', $filename)) return null;
        $path = $this->directory($employee).'/'.$filename;
        // Keep legacy private photographs readable; new uploads use the public disk.
        foreach (array_unique([$data['photo_disk'] ?? 'local', 'local', 'public']) as $disk) {
            if (in_array($disk, ['local', 'public'], true) && Storage::disk($disk)->exists($path)) return ['disk' => $disk, 'path' => $path];
        }
        return null;
    }

    public function save(Employee $employee, ?int $primaryRoleId, ?UploadedFile $photo): void
    {
        if ($primaryRoleId === null && $photo === null) return;
        $data = $this->metadata($employee);
        if ($primaryRoleId !== null) $data['primary_role_id'] = $primaryRoleId;
        if ($photo) {
            $filename = (string) \Illuminate\Support\Str::uuid().'.'.$photo->extension();
            if (! Storage::disk('public')->putFileAs($this->directory($employee), $photo, $filename)) {
                throw new \RuntimeException('No se pudo guardar la foto del empleado.');
            }
            $data['photo'] = $filename;
            $data['photo_disk'] = 'public';
        }
        if (! Storage::disk('local')->put($this->directory($employee).'/profile.json', json_encode($data, JSON_THROW_ON_ERROR))) {
            throw new \RuntimeException('No se pudo guardar la presentación del empleado.');
        }
    }
}
