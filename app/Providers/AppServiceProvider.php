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
        $this->registrarDiretivaImagem();
    }

    /**
     * @js('chatbot.js') → <script src="/js/chatbot.js?v=1757..." defer></script>
     *
     * O ?v= vem do filemtime. O projeto não passa por build, então os arquivos
     * de public/ são servidos como estão e o navegador guardaria a versão
     * velha depois de cada deploy; o carimbo muda com o arquivo e invalida o
     * cache sem exigir um passo de build.
     */
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
