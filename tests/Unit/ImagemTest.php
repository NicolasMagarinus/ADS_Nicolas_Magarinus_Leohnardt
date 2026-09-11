<?php

namespace Tests\Unit;

use App\Support\Imagem;
use PHPUnit\Framework\TestCase;

/**
 * As imagens vinham do Cloudinary em tamanho cheio — até 1024px — para
 * aparecer em cards de 200px. A transformação vai na própria URL, então o
 * ganho é só montar a URL certa.
 */
class ImagemTest extends TestCase
{
    private const ORIGINAL = 'https://res.cloudinary.com/dhffzvqtf/image/upload/v1763919598/bebidas/mojito.jpg';

    public function test_insere_a_transformacao_depois_de_upload(): void
    {
        $this->assertSame(
            'https://res.cloudinary.com/dhffzvqtf/image/upload/w_400,f_auto,q_auto/v1763919598/bebidas/mojito.jpg',
            Imagem::miniatura(self::ORIGINAL, 400)
        );
    }

    public function test_largura_pedida_e_respeitada(): void
    {
        $this->assertStringContainsString('w_800,f_auto,q_auto', Imagem::miniatura(self::ORIGINAL, 800));
    }

    public function test_url_vazia_cai_no_placeholder_ja_redimensionado(): void
    {
        foreach ([null, ''] as $vazio) {
            $resultado = Imagem::miniatura($vazio, 400);

            $this->assertStringContainsString('sem-imagem', $resultado);
            $this->assertStringContainsString('w_400,f_auto,q_auto', $resultado);
        }
    }

    public function test_url_de_fora_do_cloudinary_passa_intacta(): void
    {
        $externa = 'https://exemplo.test/fotos/drink.png';

        $this->assertSame($externa, Imagem::miniatura($externa, 400));
    }

    /**
     * O Cloudinary encadeia transformações, então aplicar de novo geraria uma
     * URL com dois segmentos e um redimensionamento sobre o outro.
     */
    public function test_url_ja_transformada_nao_recebe_outra_transformacao(): void
    {
        $jaTransformada = 'https://res.cloudinary.com/dhffzvqtf/image/upload/w_400,f_auto,q_auto/v1763919598/bebidas/mojito.jpg';

        $this->assertSame($jaTransformada, Imagem::miniatura($jaTransformada, 800));
    }

    public function test_transformacao_do_upload_original_e_preservada(): void
    {
        // O upload da bebida já grava com c_limit,w_1024; não é nossa
        // transformação, mas continua sendo uma, e não deve ganhar outra.
        $comCrop = 'https://res.cloudinary.com/dhffzvqtf/image/upload/c_limit,w_1024,q_auto/v1763919598/bebidas/mojito.jpg';

        $this->assertSame($comCrop, Imagem::miniatura($comCrop, 400));
    }

    /**
     * Sem versão na URL e com underline no nome do arquivo, um detector
     * ingênuo confundiria o arquivo com uma transformação e não redimensionaria
     * nada. Transformação do Cloudinary não tem extensão.
     */
    public function test_arquivo_com_underline_no_nome_nao_e_confundido(): void
    {
        $semVersao = 'https://res.cloudinary.com/dhffzvqtf/image/upload/sem_imagem.png';

        $this->assertSame(
            'https://res.cloudinary.com/dhffzvqtf/image/upload/w_400,f_auto,q_auto/sem_imagem.png',
            Imagem::miniatura($semVersao, 400)
        );
    }

    public function test_placeholder_e_exposto_para_quem_precisar_da_url_crua(): void
    {
        $this->assertStringContainsString('res.cloudinary.com', Imagem::PLACEHOLDER);
        $this->assertStringContainsString('sem-imagem', Imagem::PLACEHOLDER);
    }
}
