<?php

require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../dao/AplicacaoAcessoDAO.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';

use dao\AplicacaoAcessoDAO;

class MediaHoraController {
    public function executar() {
        header('Content-Type: application/json');

        // Validação da chave de cliente e acesso
        $filtros = $_GET;
        $cliente = $filtros['cliente'] ?? null;
        $acesso = AplicacaoAcessoDAO::obterWebhookPermitido($cliente, 'deal');
        $webhook = $acesso['webhook_bitrix'] ?? null;

        if (!$webhook) {
            http_response_code(403);
            echo json_encode(['erro' => 'Acesso negado para consultar negociação.']);
            return;
        }

        // Validação de parâmetros obrigatórios
        $parametrosObrigatorios = ['spa', 'deal', 'inicio', 'fim', 'retorno'];
        foreach ($parametrosObrigatorios as $param) {
            if (empty($_GET[$param])) {
                http_response_code(400);
                echo json_encode(['erro' => "Parâmetro obrigatório ausente: $param"]);
                return;
            }
        }

        $spa = intval($_GET['spa']);
        $dealId = intval($_GET['deal']);
        $campoRetorno = $_GET['retorno'];
        $dataInicio = $_GET['inicio'];
        $dataFim = $_GET['fim'];

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
        $horasUteis = $this->calcularHorasUteis($inicio, $fim);

        // Monta os dados no mesmo padrão da DealController
        $dados = $_GET;
        $dados['webhook'] = $webhook;
        $dados['ID'] = $dealId;
        $dados[$campoRetorno] = $horasUteis;

        $resultado = BitrixDealHelper::editarNegociacao($dados);

        echo json_encode([
            'status' => 'sucesso',
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

    private function calcularHorasUteis($inicio, $fim) {
        $totalSegundos = 0;
        $current = clone $inicio;

        while ($current < $fim) {
            $diaSemana = (int) $current->format('N'); // 1 = segunda, 7 = domingo
            $horaAtual = (int) $current->format('H');

            if ($diaSemana >= 1 && $diaSemana <= 5 && $horaAtual >= 9 && $horaAtual < 18) {
                $proximo = clone $current;
                $proximo->modify('+1 minute');

                if ($proximo <= $fim) {
                    $totalSegundos += 60;
                }
            }

            $current->modify('+1 minute');
        }

        // Converter segundos para horas com duas casas decimais
        return round($totalSegundos / 3600, 2);
    }
}
