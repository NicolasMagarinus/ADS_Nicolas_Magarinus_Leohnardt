<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OpenAI\Laravel\Facades\OpenAI;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Exception;

class GerarIngredientesAI extends Command
{
    protected $signature = 'app:gerar-ingredientes-ai {--qt=50 : Quantidade de ingredientes a listar}';
    
    protected $description = 'Gera (ou atualiza imagens de) ingredientes mais usados em drinks usando gpt-3.5 e DALL·E';

    public function handle()
    {
        $qt = (int) $this->option('qt') ?: 50;

        $prompt = "
        Gere uma lista com os {$qt} ingredientes mais utilizados em drinks (coquetéis e não alcoólicos), em português.
        Retorne apenas um JSON no formato de array de strings, por exemplo:
        [\"Limão\", \"Açúcar\", \"Hortelã\"]
        ";

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'Você é um assistente que lista ingredientes de bebidas.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ]);

            $content = $response['choices'][0]['message']['content'] ?? null;
            if (!$content) {
                $this->error('Nenhum conteúdo retornado pela IA.');
                return;
            }

            $json = $this->extrairJson($content);
            $ingredientes = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($ingredientes)) {
                $this->error('Erro ao decodificar JSON retornado: ' . json_last_error_msg());
                return;
            }

            foreach ($ingredientes as $nome) {
                $nome = trim((string)$nome);
                if ($nome === '') continue;

                DB::beginTransaction();
                try {
                    $cd_ingrediente = DB::table('ingrediente')
                        ->where('nm_ingrediente', 'ilike', $nome)
                        ->value('cd_ingrediente');

                    if (!$cd_ingrediente) {
                        $cd_ingrediente = DB::table('ingrediente')->insertGetId([
                            'nm_ingrediente' => $nome,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ], 'cd_ingrediente');

                        $this->line("Ingrediente criado: {$nome} (id: {$cd_ingrediente})");
                    } else {
                        $this->line("Ingrediente já existe: {$nome} (id: {$cd_ingrediente}) — adicionando/atualizando imagem");
                    }

                    // Gera imagem e atualiza registro (tanto para novos quanto para existentes)
                    $this->gerarImagemIngrediente($cd_ingrediente, $nome);

                    DB::commit();
                } catch (Exception $e) {
                    DB::rollBack();
                    $this->error("Erro ao processar ingrediente '{$nome}': " . $e->getMessage());
                }
            }

            $this->info("Processamento finalizado para {$qt} ingredientes solicitados.");

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

    private function gerarImagemIngrediente(int $cd_ingrediente, string $nome)
    {
        try {
            $promptImagem = "Fotografia realista de alta qualidade do ingrediente '{$nome}' usado em drinks. " .
                            "Foco no item, fundo neutro (branco ou cinza suave), iluminação de estúdio, detalhes nítidos, estilo profissional.";

            $this->line("Gerando imagem para ingrediente: {$nome}...");

            $result = OpenAI::images()->create([
                'model'           => 'dall-e-3',
                'prompt'          => $promptImagem,
                'size'            => '1024x1024',
                'response_format' => 'b64_json',
                'n' => 1
            ]);

            $imageBase64 = $result['data'][0]['b64_json'] ?? null;
            if (!$imageBase64) {
                $this->warn("Falha ao gerar imagem para {$nome}");
                return;
            }

            $dataUri = "data:image/png;base64," . $imageBase64;

            $upload = Cloudinary::upload($dataUri, [
                'folder' => 'ingredientes'
            ]);

            $url = $upload->getSecurePath();

            DB::table('ingrediente')
                ->where('cd_ingrediente', $cd_ingrediente)
                ->update(['ds_imagem' => $url, 'updated_at' => now()]);

            $this->line("Imagem enviada ao Cloudinary para {$nome}: {$url}");

        } catch (\OpenAI\Exceptions\RateLimitException $e) {
            $this->warn('Limite de requisições de imagem atingido, pulando este ingrediente.');
        } catch (Exception $e) {
            $this->warn('Erro ao gerar/enviar imagem: ' . $e->getMessage());
        }
    }
}
