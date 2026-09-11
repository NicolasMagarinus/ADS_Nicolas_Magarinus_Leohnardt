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

        $this->registrarDiretivaJs();
    }

    /**
     * @js('chatbot.js') → <script src="/js/chatbot.js?v=1757..." defer></script>
     *
     * O ?v= vem do filemtime. O projeto não passa por build, então os arquivos
     * de public/ são servidos como estão e o navegador guardaria a versão
     * velha depois de cada deploy; o carimbo muda com o arquivo e invalida o
     * cache sem exigir um passo de build.
     */
    private function registrarDiretivaJs(): void
    {
        Blade::directive('js', function ($expression) {
            return "<?php
                \$caminho = {$expression};
                \$arquivo = public_path('js/'.\$caminho);
                \$versao = is_file(\$arquivo) ? filemtime(\$arquivo) : '0';
                echo '<script src=\"'.e(asset('js/'.\$caminho)).'?v='.\$versao.'\" defer></script>';
            ?>";
        });
    }
}
