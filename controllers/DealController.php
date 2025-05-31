<?php

class DealController
{
    public function criar()
    {
        require_once __DIR__ . '/../helpers/BitrixHelper.php';

        $dados = $_GET;

        $resultado = BitrixHelper::criarNegocio($dados);

        header('Content-Type: application/json');
        echo json_encode($resultado);
    }

    public function consultar()
    {
        require_once __DIR__ . '/../helpers/BitrixHelper.php';

        $filtros = $_GET;
        $resultado = BitrixHelper::consultarNegociacao($filtros);

        header('Content-Type: application/json');
        echo json_encode($resultado);
    }

    public function editar()
    {
        echo json_encode(['mensagem' => 'Rota EDITAR acessada com sucesso']);
    }
}
