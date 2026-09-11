<?php

namespace App\Support;

/**
 * Monta URLs de imagem do Cloudinary no tamanho em que elas serão exibidas.
 *
 * O catálogo guarda a imagem em até 1024px e as telas mostram cards de 200px,
 * então o navegador baixava muito mais byte do que usava — o que dói no
 * celular, que é o aparelho de quem está preparando um drink na bancada.
 *
 * A transformação vive na própria URL: basta inseri-la depois de /upload/.
 */
class Imagem
{
    /** Usada quando a bebida ou o ingrediente não tem imagem. */
    public const PLACEHOLDER = 'https://res.cloudinary.com/dhffzvqtf/image/upload/v1763919598/sem-imagem_br4i0i.png';

    /**
     * URL da imagem no tamanho pedido.
     *
     * URL de fora do Cloudinary passa intacta — não há o que transformar. URL
     * que já traz uma transformação também: o Cloudinary encadeia, e aplicar
     * de novo empilharia um redimensionamento sobre o outro.
     */
    public static function miniatura(?string $url, int $largura): string
    {
        $url = trim((string) $url) ?: self::PLACEHOLDER;

        $marcador = '/image/upload/';
        $posicao = strpos($url, $marcador);

        if ($posicao === false) {
            return $url;
        }

        $depois = substr($url, $posicao + strlen($marcador));

        if (self::jaTemTransformacao($depois)) {
            return $url;
        }

        return substr($url, 0, $posicao + strlen($marcador))."w_{$largura},f_auto,q_auto/".$depois;
    }

    /**
     * O primeiro segmento depois de /upload/ pode ser a versão (v1763919598),
     * o caminho do arquivo ou uma transformação.
     *
     * Transformação é uma lista de pares tipo w_400,f_auto e nunca tem
     * extensão — é isso que a separa de um arquivo como sem_imagem.png, que
     * também traz underline.
     */
    private static function jaTemTransformacao(string $depois): bool
    {
        $primeiro = strtok($depois, '/');

        if ($primeiro === false || str_contains($primeiro, '.')) {
            return false;
        }

        return (bool) preg_match('/^[a-z]+_[^,\/]+(,[a-z]+_[^,\/]+)*$/', $primeiro);
    }
}
