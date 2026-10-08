<?php

use App\Support\TextosNuevos;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Misiones en sitio. El reto se resuelve físicamente en la sala: el
     * participante escanea el QR de la estación (eso arranca el cronómetro) y
     * escribe el código que obtiene al resolverlo.
     *
     * - `codigo_presencial`: el código que revela la estación.
     * - `token_qr`: secreto que va dentro del QR; sin él no se puede activar
     *   la misión a distancia adivinando la URL.
     */
    public function up(): void
    {
        Schema::table('misiones', function (Blueprint $t) {
            $t->string('codigo_presencial', 40)->nullable()->after('fragmento_codigo');
            $t->string('token_qr', 40)->nullable()->unique()->after('codigo_presencial');
        });

        foreach (DB::table('misiones')->pluck('id') as $id) {
            DB::table('misiones')->where('id', $id)->update(['token_qr' => Str::random(32)]);
        }

        TextosNuevos::instalar();
    }

    public function down(): void
    {
        Schema::table('misiones', function (Blueprint $t) {
            $t->dropUnique(['token_qr']);
            $t->dropColumn(['codigo_presencial', 'token_qr']);
        });

        DB::table('textos')->whereIn('clave', array_column(TextosNuevos::PRESENCIAL, 1))->delete();
        DB::table('ajustes')->where('clave', 'modalidad')->delete();
    }
};
