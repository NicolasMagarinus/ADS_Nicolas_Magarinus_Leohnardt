<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OpenAI\Laravel\Facades\OpenAI;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Exception;

class GerarBebidasAI extends Command
{
    protected $signature = 'app:gerar-bebidas-ai {quantidade=5}';
    protected $description = 'Gera receitas originais de bebidas e salva no banco usando GPT-4o-mini';

    public function handle()
    {
        $qtd = (int) $this->argument('quantidade');

        $prompt = "
        Gere {$qtd} receitas originais de bebidas (tipo 1 = alcoólicas, tipo 2 = não alcoólicas), com nome criativo, descrição, ingredientes (com medidas), e modo de preparo.
        todas em português, e retorne em JSON no formato de um array, por exemplo:
        [
          {
            \"nome\": \"Caipirinha de Maracujá\",
            \"descricao\": \"Uma variação tropical da clássica caipirinha.\",
            \"ingredientes\": [
              {\"nome\": \"Cachaça\", \"medida\": \"50 ml\"},
              {\"nome\": \"Maracujá\", \"medida\": \"1 unidade\"},
              {\"nome\": \"Açúcar\", \"medida\": \"2 colheres de chá\"},
              {\"nome\": \"Gelo\", \"medida\": \"a gosto\"}
            ],
            \"preparo\": \"Macere o maracujá com o açúcar, adicione a cachaça e o gelo, e misture bem.\",
            \"tipo\": 1
          }
        ]
        ";

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Você é um bartender especialista em criar receitas de bebidas.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.9,
                'max_tokens' => 2000,
            ]);

            $content = $response['choices'][0]['message']['content'] ?? null;
            if (!$content) {
                $this->error('Nenhum conteúdo retornado pela IA.');
                return;
            }

            $json = $this->extrairJson($content);
            $bebidas = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($bebidas)) {
                $this->error('Erro ao decodificar JSON retornado: ' . json_last_error_msg());
                return;
            }

            foreach ($bebidas as $bebida) {
                $this->salvarBebida($bebida);
            }

            $this->info("{$qtd} bebidas geradas e salvas com sucesso!");

        } catch (\OpenAI\Exceptions\RateLimitException $e) {
            $this->warn('Limite de requisições atingido. Tente novamente mais tarde.');
        } catch (Exception $e) {
            $this->error('Erro: ' . $e->getMessage());
        }
    }

    private function extrairJson(string $texto): string
    {
        $inicio = strpos($texto, '[');
        $fim = strrpos($texto, ']');
        if ($inicio === false || $fim === false) {
            return $texto;
        }
        return substr($texto, $inicio, $fim - $inicio + 1);
    }

    private function salvarBebida(array $bebida)
    {
        DB::beginTransaction();

        try {
            $cd_bebida = DB::table('bebida')->insertGetId([
                'nm_bebida' => $bebida['nome'] ?? 'Sem nome',
                'ds_preparo' => $bebida['preparo'] ?? '',
                'id_tipo' => $bebida['tipo'] ?? 1,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'cd_bebida');

            if (!empty($bebida['ingredientes'])) {
                foreach ($bebida['ingredientes'] as $ing) {
                    $nomeIng = trim($ing['nome'] ?? '');
                    $medida = $ing['medida'] ?? '';

                    if (!$nomeIng) continue;

                    $cd_ingrediente = DB::table('ingrediente')
                        ->where('nm_ingrediente', 'ilike', $nomeIng)
                        ->value('cd_ingrediente');

                    if (!$cd_ingrediente) {
                        $cd_ingrediente = DB::table('ingrediente')->insertGetId([
                            'nm_ingrediente' => $nomeIng,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ], 'cd_ingrediente');
                    }

                    DB::table('bebida_ingrediente')->insert([
                        'cd_bebida' => $cd_bebida,
                        'cd_ingrediente' => $cd_ingrediente,
                        'ds_medida' => $medida,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();

            $this->line("Inserida bebida: {$bebida['nome']}");

            $this->gerarImagemBebida($cd_bebida, $bebida);

        } catch (Exception $e) {
            DB::rollBack();
            $this->error('Erro ao salvar bebida: ' . $e->getMessage());
        }
    }

    private function gerarImagemBebida(int $cd_bebida, array $bebida)
    {
        try {
            $promptImagem = "Fotografia realista de um coquetel chamado {$bebida['nome']}, " .
                            "feito com " . implode(', ', array_column($bebida['ingredientes'] ?? [], 'nome')) .
                            ". Fundo neutro, copo bonito, iluminação de estúdio, estilo de foto profissional.";

            $this->line("Gerando imagem para: {$bebida['nome']}...");

            $result = OpenAI::images()->create([
                'model'           => 'dall-e-3',
                'prompt'          => $promptImagem,
                'size'            => '1024x1024',
                'response_format' => "b64_json",
                'n' => 1
            ]);

            $imageBase64 = $result['data'][0]['b64_json'] ?? null;

            if (!$imageBase64) {
                $this->warn("Falha ao gerar imagem para {$bebida['nome']}");
                return;
            }

            $dataUri = "data:image/png;base64," . $imageBase64;

            $upload = Cloudinary::upload($dataUri, [
                'folder' => 'bebidas'
            ]);

            $url = $upload->getSecurePath();

            DB::table('bebida')
                ->where('cd_bebida', $cd_bebida)
                ->update(['ds_imagem' => $url]);

            $this->line("Imagem enviada ao Cloudinary: {$url}");

        } catch (\OpenAI\Exceptions\RateLimitException $e) {
            $this->warn('Limite de requisições de imagem atingido, pulando esta bebida.');
        } catch (Exception $e) {
            $this->warn('Erro ao gerar imagem: ' . $e->getMessage());
        }
    }
}
