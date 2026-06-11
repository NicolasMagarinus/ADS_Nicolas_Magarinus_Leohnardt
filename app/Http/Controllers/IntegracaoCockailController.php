<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class IntegracaoCockailController extends Controller
{
    public static function obterBebidas()
    {
        $alphabet = range('a', 'z');

        foreach ($alphabet as $letter) {
            $url = "https://www.thecocktaildb.com/api/json/v1/1/search.php?f={$letter}";
            $response = Http::get($url);

            if ($response->successful() && !empty($response->json()["drinks"])) {
                foreach ($response->json()["drinks"] as $drink) {
                    $dsPreparo = self::traduzirTexto($drink["strInstructions"]);
                    $nmBebida  = self::traduzirTexto($drink["strDrink"]);

                    DB::table("bebida")->updateOrInsert(
                    [
                        "id_externo" => $drink["idDrink"]
                    ],
                    [
                        "nm_bebida" => $nmBebida,
                        "ds_preparo" => $dsPreparo,
                        "id_tipo" => $drink["strAlcoholic"] === "Alcoholic" ? 1 : 2,
                        "ds_imagem" => $drink["strDrinkThumb"] ?? null,
                        "created_at" => now(),
                        "updated_at" => now()
                    ]);
                }
            }
        }
    }

    public static function obterIngredientes()
    {
        $url = "https://www.thecocktaildb.com/api/json/v1/1/list.php?i=list";
        $response = Http::get($url);
        if ($response->successful() && !empty($response->json()["drinks"])) {
            foreach ($response->json()["drinks"] as $ingrediente) {
                $urlIngrediente = "www.thecocktaildb.com/api/json/v1/1/search.php?i={$ingrediente['strIngredient1']}";
                $ingredientResponse = Http::get($urlIngrediente);

                $objIngrediente = $ingredientResponse->json()["ingredients"][0];

                $nmIngrediente = self::traduzirTexto($objIngrediente["strIngredient"]);
                $idExterno     = $objIngrediente["idIngredient"];

                DB::table("ingrediente")->updateOrInsert(
                    ["id_externo" => $idExterno],
                    ["nm_ingrediente" => $nmIngrediente]
                );
            }
        }
    }

    public static function obterBebidaIngrediente()
    {
        try {
            DB::beginTransaction();
            $arrDrink = DB::table("bebida")->pluck("id_externo", "cd_bebida");

            foreach ($arrDrink as $cdBebida => $idExternoBebida) {
                $url = "https://www.thecocktaildb.com/api/json/v1/1/lookup.php?i={$idExternoBebida}";

                $response = Http::timeout(30)->get($url);
                $objBebida = $response->json()["drinks"] ?? null;

                if ($response->successful() && !empty($objBebida)) {
                    for ($i = 1; $i < 15; $i++) {
                        $ingredienteStr = $objBebida[0]["strIngredient{$i}"] ?? null;

                        if (empty($ingredienteStr)) {
                            continue;
                        }

                        $nmIngrediente = self::traduzirTexto(json_encode($ingredienteStr, JSON_UNESCAPED_UNICODE));

                        $objIngrediente = DB::table("ingrediente")
                            ->where("nm_ingrediente", $nmIngrediente)
                            ->first();

                        if (!$objIngrediente) {
                            $urlIngrediente = "https://www.thecocktaildb.com/api/json/v1/1/search.php?i=" . urlencode($ingredienteStr);
                            $ingredientResponse = Http::timeout(30)->get($urlIngrediente);

                            if ($ingredientResponse->successful()) {
                                $ingredienteData = $ingredientResponse->json()["ingredients"][0] ?? null;

                                if ($ingredienteData) {
                                    $idIngrediente = DB::table("ingrediente")->insertGetId([
                                        "nm_ingrediente" => $nmIngrediente,
                                        "id_externo"     => $ingredienteData["idIngredient"] ?? null,
                                        "created_at"     => now(),
                                        "updated_at"     => now(),
                                    ], 'cd_ingrediente');
                                }
                            }
                        } else {
                            $idIngrediente = $objIngrediente->cd_ingrediente;
                        }

                        if (empty($idIngrediente)) {
                            continue;
                        }

                        $objBebidaIngrediente = DB::table("bebida_ingrediente")
                            ->where("cd_bebida", $cdBebida)
                            ->where("cd_ingrediente", $idIngrediente)
                            ->exists();

                        if (!$objBebidaIngrediente) {
                            DB::table("bebida_ingrediente")->insert([
                                "cd_bebida"      => $cdBebida,
                                "cd_ingrediente" => $idIngrediente,
                                "ds_medida"      => $objBebida[0]["strMeasure{$i}"] ?? 0,
                                "created_at"     => now(),
                                "updated_at"     => now(),
                            ]);
                        }
                    }
                }
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            error_log("ERRO: " . $e->getMessage());
        }
    }

    static function traduzirTexto($text, $from = 'en', $to = 'pt-BR') {
        $response = Http::post('http://localhost:5000/translate', [
            'q' => $text,
            'source' => $from,
            'target' => $to,
            'format' => 'text'
        ]);

        if ($response->successful()) {
            return $response->json()['translatedText'];
        }

        return $text;
    }
}
