<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre', 'logo_ruta', 'activa'])]
class Empresa extends Model
{
    protected $table = 'empresas';

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    /**
     * URL pública del logo, o null si la empresa no tiene uno cargado. Pasa por
     * la ruta archivos.publicos (no por el symlink /storage) y usa el dominio de
     * la petición, no APP_URL. El parámetro v evita que el navegador muestre
     * un logo viejo desde caché tras reemplazarlo.
     */
    public function logoUrl(): ?string
    {
        if (! $this->logo_ruta) {
            return null;
        }

        return route('archivos.publicos', [
            'ruta' => $this->logo_ruta,
            'v' => $this->updated_at?->timestamp,
        ]);
    }
}
