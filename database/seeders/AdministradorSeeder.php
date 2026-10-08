<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdministradorSeeder extends Seeder
{
    /**
     * Crea el usuario administrador del dashboard y la empresa inicial.
     * Es idempotente: se puede volver a ejecutar sin duplicar nada.
     */
    public function run(): void
    {
        $correo = env('ADMIN_EMAIL', 'admin@escaperoom.local');
        $clave = env('ADMIN_PASSWORD', 'escaperoom2026');

        User::updateOrCreate(
            ['email' => $correo],
            [
                'name' => 'Administrador',
                'password' => Hash::make($clave),
            ],
        );

        if (Empresa::count() === 0) {
            Empresa::create([
                'nombre' => config('escape_room.marca.cliente'),
                'logo_ruta' => null,   // sin logo propio todavía: usa el del config
                'activa' => true,
            ]);
        }

        $this->command?->info("Administrador: {$correo}");
        $this->command?->warn('Cambia la contraseña por defecto antes de publicar.');
    }
}
