<?php

use App\Support\RecorteLogo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Los logos nuevos se recortan al subirlos; este quita el margen vacío
     * de los que ya estaban cargados.
     */
    public function up(): void
    {
        DB::table('empresas')->whereNotNull('logo_ruta')->get(['id', 'logo_ruta'])
            ->each(function ($empresa) {
                if (RecorteLogo::recortar($empresa->logo_ruta)) {
                    // Cambia la versión de la URL para que el navegador no muestre el anterior.
                    DB::table('empresas')->where('id', $empresa->id)->update(['updated_at' => now()]);
                }
            });
    }

    public function down(): void
    {
        // El recorte no se puede deshacer: el margen quitado no se guarda.
    }
};
