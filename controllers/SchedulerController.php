<?php
namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../helpers/LogHelper.php';

use Helpers\LogHelper;
use Helpers\BitrixDealHelper;
use Helpers\BitrixHelper;
use DateTime;

class SchedulerController
{
    public function executar()
    {
        LogHelper::logSchedulerController('Início do método executar');

        // 1. Pega parâmetros básicos
        $spa = $_GET['spa'] ?? null;
        $dealId = $_GET['deal'] ?? $_GET['id'] ?? null;

        if (!$spa || !$dealId) {
            LogHelper::logSchedulerController('Erro: Parâmetros spa ou dealId não informados');
            header('Content-Type: application/json');
            echo json_encode(['erro' => 'Parâmetros spa e deal/id são obrigatórios']);
            return;
        }

        // 2. Busca campos da config_extra (via acesso autenticado)
        $configExtra = $GLOBALS['ACESSO_AUTENTICADO']['config_extra'] ?? null;
        if (!$configExtra) {
            LogHelper::logSchedulerController('Erro: Configuração extra não encontrada');
            header('Content-Type: application/json');
            echo json_encode(['erro' => 'Configuração extra não encontrada']);
            return;
        }

        $configJson = json_decode($configExtra, true);
        $spaKey = 'SPA_' . $spa;

        if (!isset($configJson[$spaKey]['campos'])) {
            LogHelper::logSchedulerController("Erro: SPA '$spaKey' não encontrada no config_extra");
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
        $item = $resultado['result'] ?? [];

        // Inicializa o retorno com os campos nome amigável
        $retorno = [];
        foreach ($campos as $campo) {
            $campoFormatado = array_key_first(BitrixHelper::formatarCampos([$campo['uf'] => null]));
            // Retorna só o texto amigável, mas pode retornar o array completo se quiser
            $retorno[$campo['nome']] = $item[$campoFormatado]['texto'] ?? null;
        }
        $retorno['id'] = $item['id'] ?? null;

        // Extrai UFs dos campos de Retorno API e RETORNO DATA
        $ufRetornoApi = null;
        $ufRetornoData = null;
        foreach ($campos as $campo) {
            if ($campo['nome'] === 'Retorno API') {
                $ufRetornoApi = $campo['uf'];
            }
            if ($campo['nome'] === 'RETORNO DATA') {
                $ufRetornoData = $campo['uf'];
            }
        }

        // 7. Calcula próxima data
        $periodo = $retorno['Período'] ?? null;
        $dataAtual = $retorno['RETORNO DATA'] ?? null;
        if (!$dataAtual) {
            // Primeira execução: usar Data de início
            $dataAtual = $retorno['Data de início'] ?? (new DateTime())->format('c');
            $dt = new DateTime($dataAtual);
            $dt->setTime(1, 0, 0);
            $dataAtual = $dt->format('c');
        } else {
            // Ajusta horário para 6h mesmo na data de retorno existente
            $dt = new DateTime($dataAtual);
            $dt->setTime(1, 0, 0);
            $dataAtual = $dt->format('c');
        }
        $proximaData = null;

        // ==== PERÍODO ====
        if (!$retorno['RETORNO DATA']) {
            // Primeira execução: usa data início com horário fixo, sem cálculo
            $proximaData = $dataAtual;
        } else {
            if ($periodo === 'Semanal') {
                $diasSemana = $retorno['Dias da semana'] ?? [];
                $proximaData = $this->calcularProximaDataSemanal($diasSemana, $dataAtual);
            } elseif ($periodo === 'Mensal') {
                $diaMes = (int)($retorno['Dias de criação de tarefas (Mês)'] ?? 1);
                $proximaData = $this->calcularProximaDataMensal($diaMes, $dataAtual);
            } elseif ($periodo === 'Intervalo de tempo' || $periodo === 'Intervalo de dias') {
                $intervaloDias = (int)($retorno['Intervalo de tempo'] ?? 0);
                $proximaData = $this->calcularProximaDataIntervalo($intervaloDias, $dataAtual);
            }
        }

        // ==== TÉRMINO ====
        // Campo lista: $retorno['Término'] pode ser 'Data fixa', 'Depois de X repetições' ou 'Nunca'
        $termino = $retorno['Término'] ?? 'Nunca';

        if ($termino === 'Data fixa') {
            $dataTermino = $retorno['Data de término ou repetições'] ?? null;
            if (!$this->validarTerminoPorData($proximaData, $dataTermino)) {
                $proximaData = null;
            }
        } elseif ($termino === 'Depois de X repetições') {
            $quantidadeMax = (int)($retorno['Quantidade de repetições'] ?? 0);
            if ($quantidadeMax > 0) {
                $continua = $this->validarTerminoPorRepeticoes(
                    $retorno['Data de início'] ?? $dataAtual,
                    $dataAtual,
                    $quantidadeMax,
                    $periodo,
                    $diasSemana ?? []
                );
                if (!$continua) {
                    $proximaData = null;
                }
            }
        }



        // Monta array para atualizar campos no Bitrix
        $fieldsParaAtualizar = [];
        if ($ufRetornoApi) {
            $fieldsParaAtualizar[$ufRetornoApi] = $proximaData ? 'Próxima tarefa agendada' : 'Ciclo finalizado';
        }
        if ($ufRetornoData) {
            $fieldsParaAtualizar[$ufRetornoData] = $proximaData;
        }

        // Atualiza no Bitrix
        $update = BitrixDealHelper::editarDeal($spa, $dealId, $fieldsParaAtualizar);
        if (!$update['success']) {
            LogHelper::logSchedulerController('Erro ao atualizar deal: ' . json_encode($update));
        }

        $retorno['Proxima Data'] = $proximaData;

        header('Content-Type: application/json');
        echo json_encode(['result' => ['item' => $retorno]]);
    }

    private function calcularProximaDataSemanal(array $diasSemana, string $dataAtual): string
    {
        LogHelper::logSchedulerController("Início calcularProximaDataSemanal - diasSemana: " . json_encode($diasSemana) . " | dataAtual: $dataAtual");
        $mapaDias = [
            'Segunda' => 1,
            'Terça' => 2,
            'Quarta' => 3,
            'Quinta' => 4,
            'Sexta' => 5,
            'Sábado' => 6,
            'Domingo' => 7,
        ];
        $diasSemana = array_map(fn($d) => $mapaDias[$d] ?? 0, $diasSemana);
        $diasSemana = array_filter($diasSemana); // remove zeros
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
                $dataAtualObj->setTime(1, 0, 0);
                LogHelper::logSchedulerController("Próxima data encontrada no ciclo atual: " . $dataAtualObj->format('c'));
                return $dataAtualObj->format('c');
            }
        }

        // Se não encontrou dia depois de hoje, pega o primeiro do próximo ciclo e garante que está no dia certo
        $primeiroDia = $diasSemana[0];
        $dataAtualObj->modify('next ' . $diasDaSemanaMap[$primeiroDia]);
        $dataAtualObj->setTime(1, 0, 0);

        // Avança enquanto não estiver em um dia permitido (caso o modify('next') não caia exatamente no dia)
        while (!in_array((int)$dataAtualObj->format('N'), $diasSemana)) {
            $dataAtualObj->modify('+1 day');
            $dataAtualObj->setTime(1, 0, 0);
        }
        LogHelper::logSchedulerController("Próxima data do próximo ciclo: " . $dataAtualObj->format('c'));
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
        $dataAtualObj->setTime(1, 0, 0);
        return $dataAtualObj->format('c');
    }

    private function calcularProximaDataIntervalo(int $intervaloDias, string $dataAtual): string
    {
        $dataAtualObj = new DateTime($dataAtual);
        $dataAtualObj->modify("+$intervaloDias days");
        $dataAtualObj->setTime(1, 0, 0);
        return $dataAtualObj->format('c');
    }

    private function validarTerminoPorData(string $proximaData, ?string $dataTermino): bool
    {
        if (!$dataTermino) {
            return true; // Sem data de término, continua
        }

        $dtProxima = new DateTime($proximaData);
        $dtTermino = new DateTime($dataTermino);

        return $dtProxima <= $dtTermino;
    }

    private function validarTerminoPorRepeticoes(string $dataInicio, string $dataAtual, int $repeticoesMax, string $periodo, array $diasSemana = []): bool
    {
        $dtInicio = new DateTime($dataInicio);
        $dtAtual = new DateTime($dataAtual);

        if ($repeticoesMax <= 0) {
            return true; // Sem limite, continua
        }

        $totalRepeticoes = 0;

        switch ($periodo) {
            case 'Semanal':
                $totalDias = $dtInicio->diff($dtAtual)->days;
                $semanas = floor($totalDias / 7);
                $eventosPorSemana = count($diasSemana);
                $totalRepeticoes = $semanas * $eventosPorSemana;

                // Ajuste para dias da semana adicionais no período atual
                $diaSemanaInicio = (int)$dtInicio->format('N');
                $diaSemanaAtual = (int)$dtAtual->format('N');
                foreach ($diasSemana as $dia) {
                    if ($dia >= $diaSemanaInicio && $dia <= $diaSemanaAtual) {
                        $totalRepeticoes++;
                    }
                }
                break;

            case 'Mensal':
                $meses = (($dtAtual->format('Y') - $dtInicio->format('Y')) * 12) + ($dtAtual->format('m') - $dtInicio->format('m'));
                $totalRepeticoes = $meses + 1; // Considera o mês atual

                break;

            case 'Intervalo de tempo':
                $intervaloDias = (int)$repeticoesMax; // Nesse caso, $repeticoesMax representa o intervalo
                $diasPassados = $dtInicio->diff($dtAtual)->days;
                $totalRepeticoes = floor($diasPassados / $intervaloDias) + 1;
                break;

            default:
                $totalRepeticoes = 0; // Indefinido
        }

        return $totalRepeticoes <= $repeticoesMax;
    }

}
