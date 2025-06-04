<?php
require_once __DIR__ . '/../helpers/BitrixHelper.php';
class BitrixTaskHelper


{
    //Criar tarefa automatica 
    public static function criarTarefaAutomatica(array $dados)
    {
    $titulo = $dados['titulo'] ?? null;
    $descricao = $dados['descricao'] ?? null;
    $responsavel = $dados['responsavel'] ?? null;
    $prazo = (int) ($dados['prazo'] ?? 0);
    $webhook = $dados['webhook'] ?? null;

    if (!$titulo || !$descricao || !$responsavel || !$prazo || !$webhook) {
        return ['erro' => 'Parâmetros obrigatórios ausentes.'];
    }

    $dataConclusao = self::calcularDataUtil($prazo);
    if (in_array($dataConclusao->format('N'), [6, 7])) {
        $dataConclusao->modify('next monday');
    }

    $params = [
        'fields' => [
            'TITLE' => $titulo,
            'DESCRIPTION' => $descricao,
            'RESPONSIBLE_ID' => $responsavel,
            'DEADLINE' => $dataConclusao->format('Y-m-d'),
        ]
    ];

    return BitrixHelper::chamarApi('tasks.task.add', $params, [
        'webhook' => $webhook,
        'log' => true
    ]);
}

private static function calcularDataUtil(int $dias): DateTime
{
    $data = new DateTime();
    $adicionados = 0;

    while ($adicionados < $dias) {
        $data->modify('+1 day');
        $diaSemana = $data->format('N');
        if ($diaSemana < 6) {
            $adicionados++;
        }
    }
    return $data;
}

}