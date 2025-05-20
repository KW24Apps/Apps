<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'BitrixHelper.php';
require_once 'Utils.php';

$log = [];
$log[] = "Inicio do test.php";

$data = [
    'iddeal' => '1462',
    'spa' => '1054',
];

$dealId = $data['iddeal'];
$spa = $data['spa'];

$bitrix = new BitrixHelper('https://gnapp.bitrix24.com.br/rest/21/348n8xqhp06wp41c/');
if ($spa === 'crm.deal') {
    $resposta = $bitrix->getDeal($dealId);
} else {
    $resposta = $bitrix->getSpaItem($spa, $dealId);
}
$campos = $resposta['result']['item'] ?? [];
$log[] = "Campos obtidos: " . json_encode($campos);

$camposSignatarios = [
    'UF_CRM_41_1727793339', // contratante
    'UF_CRM_41_1747700402', // contratada
    'UF_CRM_41_1747700459'  // testemunha
];

$signatarios = [
    'contratante' => [],
    'contratada' => [],
    'testemunha' => []
];

foreach ($camposSignatarios as $campo) {
    $log[] = "Verificando campo $campo: " . json_encode($campos[$campo] ?? null);
    if (!empty($campos[$campo])) {
        $ids = is_array($campos[$campo]) ? $campos[$campo] : [$campos[$campo]];
        foreach ($ids as $idContato) {
            $log[] = "Forçando chamada para ID $idContato";
            $contato = $bitrix->call('crm.contact.get', ['id' => $idContato]);
            $log[] = "Contato bruto ID $idContato: " . json_encode($contato);
            if (!empty($contato['result'])) {
                $c = $contato['result'];
                $info = [
                    'nome' => $c['NAME'] ?? '',
                    'sobrenome' => $c['LAST_NAME'] ?? '',
                    'email' => $c['EMAIL'][0]['VALUE'] ?? ''
                ];
                if ($campo === 'UF_CRM_41_1727793339') {
                    $signatarios['contratante'][] = $info;
                } elseif ($campo === 'UF_CRM_41_1747700402') {
                    $signatarios['contratada'][] = $info;
                } elseif ($campo === 'UF_CRM_41_1747700459') {
                    $signatarios['testemunha'][] = $info;
                }
                $log[] = "Contato ID $idContato: " . json_encode($info);
            } else {
                $log[] = "Erro ao obter contato ID $idContato";
            }
        }
    }
}

$log[] = "Signatarios finais: " . json_encode($signatarios);
file_put_contents('log_test.txt', implode(PHP_EOL, $log));

echo "OK - Verifique o log_test.txt";
