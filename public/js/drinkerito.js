/**
 * Utilitários compartilhados pelas telas do Drinkerito.
 *
 * Carregado pelo layout, antes dos scripts de cada página. Existe porque o
 * escapeHtml vivia duplicado dentro de duas views, e foi justamente a cópia
 * que faltava no Meu Bar que abriu o XSS.
 */
window.Drinkerito = (function () {
    'use strict';

    /**
     * Escapa texto para interpolar em HTML.
     *
     * Inclui a aspa simples, que faltava nas duas cópias antigas: sem ela, um
     * valor interpolado num atributo delimitado por aspa simples escaparia do
     * atributo.
     */
    function escapeHtml(str) {
        if (!str) return '';

        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    const PLACEHOLDER = 'https://res.cloudinary.com/dhffzvqtf/image/upload/v1763919598/sem-imagem_br4i0i.png';

    /**
     * URL da imagem do Cloudinary já no tamanho em que ela vai aparecer.
     *
     * Espelha App\Support\Imagem::miniatura(), do lado PHP: as telas montadas
     * em JavaScript baixavam a imagem em tamanho cheio pelo mesmo motivo que
     * as views baixavam.
     */
    function imagem(url, largura) {
        url = (url || '').trim() || PLACEHOLDER;

        const marcador = '/image/upload/';
        const posicao = url.indexOf(marcador);

        if (posicao === -1) return url;

        const depois = url.slice(posicao + marcador.length);
        const primeiro = depois.split('/')[0];

        // Transformação é uma lista de pares tipo w_400,f_auto e nunca tem
        // extensão — é o que a separa de um arquivo como sem_imagem.png.
        const jaTransformada = !primeiro.includes('.')
            && /^[a-z]+_[^,/]+(,[a-z]+_[^,/]+)*$/.test(primeiro);

        if (jaTransformada) return url;

        return url.slice(0, posicao + marcador.length) + 'w_' + largura + ',f_auto,q_auto/' + depois;
    }

    return { escapeHtml: escapeHtml, imagem: imagem, PLACEHOLDER: PLACEHOLDER };
})();
