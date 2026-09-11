<?php

namespace Database\Seeders;

use App\Enums\TipoBebida;
use App\Models\Bebida;
use App\Models\BebidaIngrediente;
use App\Models\Ingrediente;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo mínimo para desenvolvimento e teste.
 *
 * Depois de um migrate:fresh o catálogo só voltava chamando a OpenAI pelo
 * app:gerar-bebidas-ai, que custa dinheiro e cota a cada execução. Estas
 * receitas são fixas: rodar o seeder não gasta nada.
 *
 * Os ingredientes passam pelo Ingrediente::normalizar(), o caminho único de
 * escrita do projeto — gravar direto recriaria as duplicatas que a migração de
 * fusão já teve de limpar uma vez.
 */
class BebidaSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->receitas() as $receita) {
            DB::transaction(function () use ($receita) {
                // Idempotente por nome: rodar duas vezes não duplica nada.
                $bebida = Bebida::firstOrCreate(
                    ['nm_bebida' => $receita['nome']],
                    [
                        'ds_preparo' => $receita['preparo'],
                        'id_tipo' => $receita['tipo'],
                        'ds_bebida' => $receita['descricao'],
                    ]
                );

                foreach ($receita['ingredientes'] as $nome => $medida) {
                    $ingrediente = Ingrediente::normalizar($nome);

                    BebidaIngrediente::updateOrCreate(
                        [
                            'cd_bebida' => $bebida->cd_bebida,
                            'cd_ingrediente' => $ingrediente->cd_ingrediente,
                        ],
                        ['ds_medida' => $medida]
                    );
                }
            });
        }
    }

    /** @return array<int, array{nome: string, tipo: TipoBebida, descricao: string, preparo: string, ingredientes: array<string, string>}> */
    private function receitas(): array
    {
        return [
            [
                'nome' => 'Caipirinha',
                'tipo' => TipoBebida::Alcoolica,
                'descricao' => 'O drink brasileiro mais conhecido do mundo, feito com cachaça, limão e açúcar.',
                'preparo' => 'Corte o limão em rodelas e retire o miolo branco. Macere com o açúcar no fundo do copo. Complete com gelo e cachaça e misture bem.',
                'ingredientes' => ['Cachaça' => '60 ml', 'Limão' => '1 unidade', 'Açúcar' => '2 colheres de chá', 'Gelo' => 'a gosto'],
            ],
            [
                'nome' => 'Mojito',
                'tipo' => TipoBebida::Alcoolica,
                'descricao' => 'Clássico cubano, refrescante, com hortelã e água com gás.',
                'preparo' => 'Macere levemente as folhas de hortelã com o açúcar e o suco de limão. Acrescente o rum e o gelo. Complete com água com gás e misture.',
                'ingredientes' => ['Rum branco' => '50 ml', 'Hortelã' => '8 folhas', 'Limão' => 'meia unidade', 'Açúcar' => '2 colheres de chá', 'Água com gás' => '100 ml'],
            ],
            [
                'nome' => 'Negroni',
                'tipo' => TipoBebida::Alcoolica,
                'descricao' => 'Aperitivo italiano de partes iguais, amargo e encorpado.',
                'preparo' => 'Coloque gelo no copo. Acrescente as três partes iguais e mexa por vinte segundos. Finalize com uma casca de laranja.',
                'ingredientes' => ['Gim' => '30 ml', 'Vermute tinto' => '30 ml', 'Campari' => '30 ml', 'Laranja' => '1 casca'],
            ],
            [
                'nome' => 'Daiquiri',
                'tipo' => TipoBebida::Alcoolica,
                'descricao' => 'Três ingredientes, equilíbrio exato entre doce e ácido.',
                'preparo' => 'Bata o rum, o suco de limão e o xarope com gelo na coqueteleira por quinze segundos. Coe para uma taça gelada.',
                'ingredientes' => ['Rum branco' => '60 ml', 'Limão' => '25 ml de suco', 'Xarope de açúcar' => '15 ml'],
            ],
            [
                'nome' => 'Margarita',
                'tipo' => TipoBebida::Alcoolica,
                'descricao' => 'Mexicano, servido com a borda da taça no sal.',
                'preparo' => 'Passe limão na borda da taça e mergulhe no sal. Bata a tequila, o licor e o suco de limão com gelo. Coe para a taça.',
                'ingredientes' => ['Tequila' => '50 ml', 'Licor de laranja' => '20 ml', 'Limão' => '20 ml de suco', 'Sal' => 'para a borda'],
            ],
            [
                'nome' => 'Caipiroska',
                'tipo' => TipoBebida::Alcoolica,
                'descricao' => 'A caipirinha feita com vodca, mais suave que a original.',
                'preparo' => 'Macere o limão com o açúcar. Complete com gelo e vodca. Misture bem antes de servir.',
                'ingredientes' => ['Vodca' => '60 ml', 'Limão' => '1 unidade', 'Açúcar' => '2 colheres de chá', 'Gelo' => 'a gosto'],
            ],
            [
                'nome' => 'Gin-tônica',
                'tipo' => TipoBebida::Alcoolica,
                'descricao' => 'Simples de montar e difícil de errar, com muito gelo.',
                'preparo' => 'Encha a taça de gelo. Acrescente o gim e complete com a água tônica. Finalize com rodelas de limão siciliano.',
                'ingredientes' => ['Gim' => '50 ml', 'Água tônica' => '150 ml', 'Limão siciliano' => '2 rodelas', 'Gelo' => 'a gosto'],
            ],
            [
                'nome' => 'Batida de coco',
                'tipo' => TipoBebida::Alcoolica,
                'descricao' => 'Cremosa e doce, clássica das festas brasileiras.',
                'preparo' => 'Bata todos os ingredientes no liquidificador com gelo até ficar homogêneo. Sirva bem gelado.',
                'ingredientes' => ['Cachaça' => '100 ml', 'Leite condensado' => '200 ml', 'Leite de coco' => '200 ml', 'Gelo' => '1 xícara'],
            ],
            [
                'nome' => 'Aperol Spritz',
                'tipo' => TipoBebida::Alcoolica,
                'descricao' => 'Leve e alaranjado, o aperitivo do verão italiano.',
                'preparo' => 'Encha a taça de gelo. Acrescente o Aperol e o espumante e complete com a água com gás. Finalize com uma fatia de laranja.',
                'ingredientes' => ['Aperol' => '60 ml', 'Espumante' => '90 ml', 'Água com gás' => '30 ml', 'Laranja' => '1 fatia'],
            ],
            [
                'nome' => 'Cuba Libre',
                'tipo' => TipoBebida::Alcoolica,
                'descricao' => 'Rum com cola e limão, servido alto e muito gelado.',
                'preparo' => 'Encha o copo de gelo. Acrescente o rum e o suco de limão. Complete com a cola e misture uma vez.',
                'ingredientes' => ['Rum branco' => '50 ml', 'Refrigerante de cola' => '150 ml', 'Limão' => 'meia unidade', 'Gelo' => 'a gosto'],
            ],
            [
                'nome' => 'Limonada suíça',
                'tipo' => TipoBebida::NaoAlcoolica,
                'descricao' => 'Limão batido com casca e leite condensado, cremosa e gelada.',
                'preparo' => 'Corte os limões em quatro, sem descascar. Bata rapidamente com a água gelada e coe. Volte ao liquidificador com o leite condensado e o gelo.',
                'ingredientes' => ['Limão' => '2 unidades', 'Água' => '500 ml', 'Leite condensado' => '3 colheres de sopa', 'Gelo' => '1 xícara'],
            ],
            [
                'nome' => 'Mojito sem álcool',
                'tipo' => TipoBebida::NaoAlcoolica,
                'descricao' => 'A versão sem álcool do clássico cubano, igualmente refrescante.',
                'preparo' => 'Macere a hortelã com o açúcar e o suco de limão. Acrescente gelo e complete com água com gás.',
                'ingredientes' => ['Hortelã' => '8 folhas', 'Limão' => 'meia unidade', 'Açúcar' => '2 colheres de chá', 'Água com gás' => '200 ml', 'Gelo' => 'a gosto'],
            ],
            [
                'nome' => 'Suco de abacaxi com hortelã',
                'tipo' => TipoBebida::NaoAlcoolica,
                'descricao' => 'Doce e herbal, funciona bem no calor.',
                'preparo' => 'Bata o abacaxi com a água e o gelo. Acrescente a hortelã e bata mais alguns segundos. Coe se preferir sem fibras.',
                'ingredientes' => ['Abacaxi' => '4 fatias', 'Hortelã' => '6 folhas', 'Água' => '300 ml', 'Gelo' => '1 xícara'],
            ],
            [
                'nome' => 'Chá gelado de pêssego',
                'tipo' => TipoBebida::NaoAlcoolica,
                'descricao' => 'Preparado em casa, sem o açúcar dos industrializados.',
                'preparo' => 'Prepare o chá preto e deixe esfriar. Misture com o pêssego batido e o suco de limão. Sirva com bastante gelo.',
                'ingredientes' => ['Chá preto' => '500 ml', 'Pêssego' => '2 unidades', 'Limão' => '1 colher de sopa de suco', 'Gelo' => 'a gosto'],
            ],
            [
                'nome' => 'Água saborizada de morango',
                'tipo' => TipoBebida::NaoAlcoolica,
                'descricao' => 'Leve, sem açúcar, para beber ao longo do dia.',
                'preparo' => 'Corte os morangos ao meio e coloque na jarra com a água e as folhas de manjericão. Deixe na geladeira por duas horas antes de servir.',
                'ingredientes' => ['Morango' => '6 unidades', 'Água' => '1 litro', 'Manjericão' => '4 folhas', 'Gelo' => 'a gosto'],
            ],
            [
                'nome' => 'Refresco de maracujá',
                'tipo' => TipoBebida::NaoAlcoolica,
                'descricao' => 'Ácido e aromático, some rápido num dia quente.',
                'preparo' => 'Bata a polpa do maracujá com a água e o açúcar. Coe para tirar as sementes e sirva com gelo.',
                'ingredientes' => ['Maracujá' => '2 unidades', 'Água' => '500 ml', 'Açúcar' => '2 colheres de sopa', 'Gelo' => 'a gosto'],
            ],
        ];
    }
}
