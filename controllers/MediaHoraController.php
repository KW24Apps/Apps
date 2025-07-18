<?php
namespace Controllers;

require_once __DIR__ . '/../helpers/BitrixDealHelper.php';

use dao\AplicacaoAcessoDAO;
use Helpers\BitrixDealHelper;
use DateTime;

class MediaHoraController {
    public function executar() {
        header('Content-Type: application/json');

        $params = $_GET;
        $entityId = $params['spa'] ?? $params['entityId'] ?? null;
        $dealId = $params['deal'] ?? $params['id'] ?? null;
        $dataInicio = $params['inicio'];
        $dataFim = $params['fim'];
        $campoRetorno = $params['retorno'];
        $hruteis = $params['hruteis'] ?? '08-18(11:30-13:30)';         // Padrão de horas úteis, pode ser personalizado
        $fields =  $params['retorno'] ?? null;

        // Validação de parâmetros obrigatórios
        $parametrosObrigatorios = ['spa', 'deal', 'inicio', 'fim', 'retorno'];
        foreach ($parametrosObrigatorios as $param) {
            if (empty($params[$param])) {
                http_response_code(400);
                echo json_encode(['erro' => "Parâmetro obrigatório ausente: $param"]);
                return;
            }
        }

        // Conversão das datas para objeto DateTime
        $formatos = ['d/m/Y H:i:s', 'Y-m-d H:i:s'];
        $inicio = $this->parseData($dataInicio, $formatos);
        $fim = $this->parseData($dataFim, $formatos);

        if (!$inicio || !$fim) {
            http_response_code(400);
            echo json_encode(['erro' => 'Formato de data inválido. Use dd/mm/yyyy HH:MM:SS ou yyyy-mm-dd HH:MM:SS']);
            return;
        }

        // Cálculo de horas úteis
        
        $horasUteis = $this->calcularHorasUteis($inicio, $fim, $hruteis);

        // Preparar os dados para o BitrixDealHelper seguindo o mesmo padrão do DealController
        $params[$campoRetorno] = $horasUteis;

        $fields = [$campoRetorno => $horasUteis];
        $resultado = BitrixDealHelper::editarDeal($entityId, $dealId, $fields);

        echo json_encode([
            'success' => true,
            'id' => $dealId,
            'horas_uteis' => $horasUteis,
            'bitrix_response' => $resultado
        ]);

    }

    private function parseData($data, $formatos) {
        foreach ($formatos as $formato) {
            $dt = DateTime::createFromFormat($formato, $data);
            if ($dt !== false) {
                return $dt;
            }
        }
        return null;
    }

    private function parseHorarioUteis($hruteis) {
        $inicioUtil = '09:00';
        $fimUtil = '18:00';
        $pausaInicio = null;
        $pausaFim = null;

        if (strpos($hruteis, '(') !== false) {
            [$faixa, $pausa] = explode('(', $hruteis);
            $pausa = rtrim($pausa, ')');
        } else {
            $faixa = $hruteis;
            $pausa = null;
        }

        [$inicio, $fim] = explode('-', $faixa);

        $inicioUtil = strpos($inicio, ':') !== false ? $inicio : $inicio . ':00';
        $fimUtil = strpos($fim, ':') !== false ? $fim : $fim . ':00';

        if ($pausa) {
            [$pInicio, $pFim] = explode('-', $pausa);
            $pausaInicio = strpos($pInicio, ':') !== false ? $pInicio : $pInicio . ':00';
            $pausaFim = strpos($pFim, ':') !== false ? $pFim : $pFim . ':00';
        }

        return [
            'inicioUtil' => $inicioUtil,
            'fimUtil' => $fimUtil,
            'pausaInicio' => $pausaInicio,
            'pausaFim' => $pausaFim
        ];
    }

    private function calcularHorasUteis($inicio, $fim, $hruteis) {
        $config = $this->parseHorarioUteis($hruteis);

        $totalSegundos = 0;
        $currentDay = clone $inicio;
        $currentDay->setTime(0, 0, 0);

        while ($currentDay <= $fim) {
            $diaSemana = (int) $currentDay->format('N');
            if ($diaSemana >= 1 && $diaSemana <= 5) {
                $inicioUtil = DateTime::createFromFormat('Y-m-d H:i', $currentDay->format('Y-m-d') . ' ' . $config['inicioUtil']);
                $fimUtil = DateTime::createFromFormat('Y-m-d H:i', $currentDay->format('Y-m-d') . ' ' . $config['fimUtil']);

                $periodoInicio = max($inicio, $inicioUtil);
                $periodoFim = min($fim, $fimUtil);

                if ($periodoInicio < $periodoFim) {
                    $intervaloDia = ($periodoFim->getTimestamp() - $periodoInicio->getTimestamp());

                    // Descontar pausa se houver e se houver sobreposição real com o período
                    if ($config['pausaInicio'] && $config['pausaFim']) {
                        $pausaInicio = DateTime::createFromFormat('Y-m-d H:i', $currentDay->format('Y-m-d') . ' ' . $config['pausaInicio']);
                        $pausaFim = DateTime::createFromFormat('Y-m-d H:i', $currentDay->format('Y-m-d') . ' ' . $config['pausaFim']);

                        $inicioPausa = max($pausaInicio, $periodoInicio);
                        $fimPausa = min($pausaFim, $periodoFim);

                        if ($inicioPausa < $fimPausa) {
                            $intervaloDia -= ($fimPausa->getTimestamp() - $inicioPausa->getTimestamp());
                        }
                    }

                    $totalSegundos += $intervaloDia;
                }
            }

            $currentDay->modify('+1 day');
        }

        return $totalSegundos;
    }
}
