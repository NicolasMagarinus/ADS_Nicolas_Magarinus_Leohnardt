import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // app.* vai em toda página, pelo layout. Os outros três são de tela
            // e entram na view que os usa — juntá-los no app mandaria as 691
            // linhas de chatbot+meubar+coleção para telas que não usam nada
            // disso. O que for comum entre eles o Rollup extrai sozinho.
            input: [
                'resources/css/app.css',
                'resources/css/auth.css',
                'resources/js/app.js',
                'resources/js/chatbot.js',
                'resources/js/meubar.js',
                'resources/js/colecao.js',
                'resources/js/cadastro-bebida.js',
            ],
            refresh: true,
        }),
    ],
});
