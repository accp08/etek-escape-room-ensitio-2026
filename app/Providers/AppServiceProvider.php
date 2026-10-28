<?php

namespace App\Providers;

use App\Support\Marca;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($publicPath = config('vite.public_path')) {
            $this->app->usePublicPath($publicPath);
        }

        Vite::useBuildDirectory(config('vite.build_directory'));

        // Paginador propio con los estilos del dashboard (el de Laravel no se ve en este tema).
        Paginator::defaultView('partials.paginacion');

        // La marca de la empresa queda disponible como $marca en todas las vistas.
        // Se resuelve una sola vez por petición (Marca cachea en memoria).
        View::composer('*', function ($vista) {
            $vista->with('marca', Marca::actual());
        });
    }
}
