<?php
// dump_publicacoes.php
// Uso: php dump_publicacoes.php
// Gera: retorno_2026-01-14.md (data fixa – arquivo de teste)

date_default_timezone_set('America/Sao_Paulo');

// DATA FIXA (TESTE)
$data = '2026-01-14';

// Ajuste aqui
$hashCliente = 'e6e973a473050bebc1fbd9f02ed62f6e';

$url = "https://www.publicacoesonline.com.br/index_pe.php"
     . "?hashCliente=" . urlencode($hashCliente)
     . "&data=" . urlencode($data)
     . "&retorno=JSON";

// --- cURL ---
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

$body = curl_exec($ch);
$err  = curl_error($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// --- Erro de transporte ---
if ($body === false) {
    $filename = __DIR__ . "/retorno_{$data}.md";
    file_put_contents($filename, "# Erro cURL\n\n{$err}\n");
    echo "ERRO cURL: {$err}\n";
    exit(1);
}

// --- Formata JSON ---
$pretty = $body;
$decoded = json_decode($body, true);
if (json_last_error() === JSON_ERROR_NONE) {
    $pretty = json_encode(
        $decoded,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

// --- Monta Markdown ---
$md  = "# Retorno Publicações Online (TESTE)\n\n";
$md .= "- Data consultada: {$data}\n";
$md .= "- HTTP Status: {$http}\n";
$md .= "- URL: {$url}\n\n";
$md .= "```json\n{$pretty}\n```\n";

// --- Salva no diretório do script ---
$filename = __DIR__ . "/retorno_{$data}.md";
$bytes = @file_put_contents($filename, $md);

// --- Validação ---
if ($bytes === false) {
    $error = error_get_last();
    echo "ERRO ao gravar arquivo: " . ($error['message'] ?? 'desconhecido') . PHP_EOL;
    exit(1);
}

echo "OK: arquivo gerado\n";
echo "Arquivo: {$filename}\n";
echo "Tamanho: {$bytes} bytes\n";
