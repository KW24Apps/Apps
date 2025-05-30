<?php

class DealController
{
    public function criar()
    {
        require_once __DIR__ . '/../helpers/BitrixHelper.php';

       $dados = $_POST;

        $resultado = criarNegocio($dados);

        header('Content-Type: application/json');
        echo json_encode($resultado);
    }

    public function consultar()
    {
        echo json_encode(['mensagem' => 'Rota CONSULTAR acessada com sucesso']);
    }

    public function editar()
    {
        echo json_encode(['mensagem' => 'Rota EDITAR acessada com sucesso']);
    }
}
