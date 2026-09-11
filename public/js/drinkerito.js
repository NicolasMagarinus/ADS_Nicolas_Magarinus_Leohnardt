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

    return { escapeHtml: escapeHtml };
})();
