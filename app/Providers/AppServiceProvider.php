<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Pagination\Paginator::useBootstrapFive();

        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        $this->registrarDiretivaImagem();
    }

    /**
     * @imagem($bebida->ds_imagem, 400) → a URL do Cloudinary já no tamanho em
     * que a imagem vai aparecer, ou o placeholder quando não houver imagem.
     *
     * Não use em og:image: WhatsApp e Facebook querem a imagem grande no card
     * da prévia.
     */
    private function registrarDiretivaImagem(): void
    {
        Blade::directive('imagem', function ($expression) {
            return "<?php echo e(\App\Support\Imagem::miniatura({$expression})); ?>";
        });
    }
}
