<?php
namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixHelper.php';

use Helpers\BitrixDealHelper;
use Helpers\BitrixHelper;
use DateTime;

class SchedulerController
{
    public function executar()
    {
        // 1. Pega parâmetros básicos
        $spa = $_GET['spa'] ?? null;
        $dealId = $_GET['deal'] ?? $_GET['id'] ?? null;

        if (!$spa || !$dealId) {
            header('Content-Type: application/json');
            echo json_encode(['erro' => 'Parâmetros spa e deal/id são obrigatórios']);
            return;
        }

        // 2. Busca campos da config_extra (via acesso autenticado)
        $configExtra = $GLOBALS['ACESSO_AUTENTICADO']['config_extra'] ?? null;
        if (!$configExtra) {
            header('Content-Type: application/json');
            echo json_encode(['erro' => 'Configuração extra não encontrada']);
            return;
        }

        $configJson = json_decode($configExtra, true);
        $spaKey = 'SPA_' . $spa;

        if (!isset($configJson[$spaKey]['campos'])) {
            header('Content-Type: application/json');
            echo json_encode(['erro' => 'SPA não encontrada no config_extra']);
            return;
        }

        // 3. Monta lista dos campos UF_CRM_* do grupo da SPA
        $campos = $configJson[$spaKey]['campos'];
        $ufCampos = array_column($campos, 'uf');
        // Formata os campos no padrão do retorno da API
        $ufCamposFormatados = BitrixHelper::formatarCampos(array_fill_keys($ufCampos, null));
        // Só as chaves
        $listaCampos = array_keys($ufCamposFormatados);

        // 4. Consulta o deal
        $resultado = BitrixDealHelper::consultarDeal($spa, $dealId, implode(',', $ufCampos));

        // 5. Consulta os fields da SPA (pega definição dos campos, inclusive os de lista)
        $fields = BitrixHelper::consultarCamposSpa($spa);

        // 6. Mapeia os valores dos campos lista de ID para texto
        $itemRetornado = $resultado['result']['item'] ?? [];
        $itemConvertido = BitrixHelper::mapearValoresEnumerados($itemRetornado, $fields);

        // Inicializa o retorno com os campos nome amigável
        $retorno = [];
        foreach ($campos as $campo) {
            $campoFormatado = array_key_first(BitrixHelper::formatarCampos([$campo['uf'] => null]));
            $retorno[$campo['nome']] = $itemConvertido[$campoFormatado] ?? null;
        }
        $retorno['id'] = $itemConvertido['id'] ?? null;

        // 7. Calcula próxima data
        $periodo = $retorno['Período'] ?? null;
        $dataAtual = $retorno['RETORNO DATA'] ?? null;
        if (!$dataAtual) {
            // Primeira execução: usar Data de início
            $dataAtual = $retorno['Data de início'] ?? (new DateTime())->format('c');
            $dt = new DateTime($dataAtual);
            $dt->setTime(6, 0, 0);
            $dataAtual = $dt->format('c');
        } else {
            // Ajusta horário para 6h mesmo na data de retorno existente
            $dt = new DateTime($dataAtual);
            $dt->setTime(6, 0, 0);
            $dataAtual = $dt->format('c');
        }
        $proximaData = null;

        if ($periodo === 'Semanal') {
            $diasSemana = $retorno['Dias da semana'] ?? [];
            $proximaData = $this->calcularProximaDataSemanal($diasSemana, $dataAtual);
        } elseif ($periodo === 'Mensal') {
            $diaMes = (int)($retorno['Dias de criação de tarefas (Mês)'] ?? 1);
            $proximaData = $this->calcularProximaDataMensal($diaMes, $dataAtual);
        } elseif ($periodo === 'Intervalo de tempo') {
            $intervaloDias = (int)($retorno['Intervalo de tempo'] ?? 0);
            $proximaData = $this->calcularProximaDataIntervalo($intervaloDias, $dataAtual);
        }

        $retorno['Proxima Data'] = $proximaData;

        header('Content-Type: application/json');
        echo json_encode(['result' => ['item' => $retorno]]);
    }

    private function calcularProximaDataSemanal(array $diasSemana, string $dataAtual): string
    {
        $diasSemana = array_map('intval', $diasSemana);
        sort($diasSemana);

        $dataAtualObj = new DateTime($dataAtual);
        $hojeNum = (int)$dataAtualObj->format('N');

        $diasDaSemanaMap = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday'
        ];

        foreach ($diasSemana as $dia) {
            if ($dia > $hojeNum) {
                $dataAtualObj->modify('next ' . $diasDaSemanaMap[$dia]);
                $dataAtualObj->setTime(6, 0, 0);
                return $dataAtualObj->format('c');
            }
        }

        $primeiroDia = $diasSemana[0];
        $dataAtualObj->modify('next ' . $diasDaSemanaMap[$primeiroDia]);
        $dataAtualObj->setTime(6, 0, 0);
        return $dataAtualObj->format('c');
    }

    private function calcularProximaDataMensal(int $diaMes, string $dataAtual): string
    {
        $dataAtualObj = new DateTime($dataAtual);
        $ano = (int)$dataAtualObj->format('Y');
        $mes = (int)$dataAtualObj->format('m');

        if ((int)$dataAtualObj->format('d') < $diaMes) {
            $dataAtualObj->setDate($ano, $mes, $diaMes);
        } else {
            $mes++;
            if ($mes > 12) {
                $mes = 1;
                $ano++;
            }
            $dataAtualObj->setDate($ano, $mes, $diaMes);
        }
        $dataAtualObj->setTime(6, 0, 0);
        return $dataAtualObj->format('c');
    }

    private function calcularProximaDataIntervalo(int $intervaloDias, string $dataAtual): string
    {
        $dataAtualObj = new DateTime($dataAtual);
        $dataAtualObj->modify("+$intervaloDias days");
        $dataAtualObj->setTime(6, 0, 0);
        return $dataAtualObj->format('c');
    }
}
