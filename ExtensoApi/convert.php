<?php
header("Content-Type: application/json");

$method = $_SERVER["REQUEST_METHOD"];
$valor = null;

if ($method === "GET") {
    $valor = $_GET["valor"] ?? null;
} elseif ($method === "POST") {
    $input = json_decode(file_get_contents("php://input"), true);
    $valor = $input["valor"] ?? null;
} else {
    http_response_code(405);
    echo json_encode(["erro" => "Método não permitido, use GET ou POST"]);
    exit;
}

if (!is_numeric($valor) || $valor < 0 || $valor > 999999999999.99) {
    http_response_code(400);
    echo json_encode(["erro" => "Valor inválido (0 até 999.999.999.999,99)"]);
    exit;
}

$unidades = ["", "um", "dois", "três", "quatro", "cinco", "seis", "sete", "oito", "nove"];
$especiais = ["dez", "onze", "doze", "treze", "quatorze", "quinze", "dezesseis", "dezessete", "dezoito", "dezenove"];
$dezenas = ["", "", "vinte", "trinta", "quarenta", "cinquenta", "sessenta", "setenta", "oitenta", "noventa"];
$centenas = ["", "cento", "duzentos", "trezentos", "quatrocentos", "quinhentos", "seiscentos", "setecentos", "oitocentos", "novecentos"];

$qualificadoresSingular = ["", "mil", "milhão", "bilhão"];
$qualificadoresPlural = ["", "mil", "milhões", "bilhões"];

function numeroParaTexto($n) {
    global $unidades, $especiais, $dezenas, $centenas;
    if ($n === 0) return "";
    if ($n === 100) return "cem";

    $texto = "";
    $c = floor($n / 100);
    $d = floor(($n % 100) / 10);
    $u = $n % 10;

    if ($c) $texto .= $centenas[$c];
    if ($d < 2 && ($d * 10 + $u) >= 10) {
        $texto .= ($texto ? " e " : "") . $especiais[$d * 10 + $u - 10];
    } else {
        if ($d) $texto .= ($texto ? " e " : "") . $dezenas[$d];
        if ($u) $texto .= ($texto ? " e " : "") . $unidades[$u];
    }

    return $texto;
}

function escreverReais($numero) {
    global $qualificadoresSingular, $qualificadoresPlural;
    if ($numero === 0) return "zero reais";

    $partes = [];
    $n = $numero;
    $qualificador = 0;

    while ($n > 0) {
        $parte = $n % 1000;
        if ($parte) {
            $texto = numeroParaTexto($parte);
            $qualificadorTexto = ($parte === 1 && $qualificador > 0)
                ? $qualificadoresSingular[$qualificador]
                : $qualificadoresPlural[$qualificador];
            array_unshift($partes, trim("$texto $qualificadorTexto"));
        }
        $n = floor($n / 1000);
        $qualificador++;
    }

    return implode(" e ", $partes);
}

function escreverCentavos($centavos) {
    if ($centavos === 0) return "";
    $texto = numeroParaTexto($centavos);
    return $texto . " " . ($centavos === 1 ? "centavo" : "centavos");
}

$parteInteira = floor($valor);
$parteDecimal = round(($valor - $parteInteira) * 100);

$resultado = "";

if ($parteInteira > 0) {
    $resultado .= escreverReais($parteInteira);
    $resultado .= $parteInteira === 1 ? " real" : " reais";
}

if ($parteDecimal > 0) {
    if ($parteInteira > 0) $resultado .= " e ";
    $resultado .= escreverCentavos($parteDecimal);
}

echo json_encode([
    "valor" => $valor,
    "formatado" => "R$ " . number_format($valor, 2, ",", ""),
    "porExtenso" => $resultado
]);

