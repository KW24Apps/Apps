<?php
namespace dao;

use PDO;
use PDOException;

class BitrixSincDAO
{
    private $conn;

    public function __construct()
    {
        $config = require __DIR__ . '/../config/config.php';

        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
        $this->conn = new PDO($dsn, $config['usuario'], $config['senha'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    }

    public function buscarEmpresaPorIdBitrix($idBitrix)
    {
        $sql = "SELECT * FROM clientes WHERE id_bitrix = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$idBitrix]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function inserirEmpresa($dados)
    {
        $sql = "INSERT INTO clientes (nome, cnpj, chave_acesso, telefone, email, endereco, link_bitrix, id_bitrix) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $dados['nome'], $dados['cnpj'], $dados['chave_acesso'], $dados['telefone'],
            $dados['email'], $dados['endereco'], $dados['link_bitrix'], $dados['id_bitrix']
        ]);
        return $this->conn->lastInsertId();
    }

    public function atualizarEmpresa($dados)
    {
        $sql = "UPDATE clientes SET nome = ?, cnpj = ?, chave_acesso = ?, telefone = ?, email = ?, endereco = ?, link_bitrix = ? WHERE id_bitrix = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            $dados['nome'], $dados['cnpj'], $dados['chave_acesso'], $dados['telefone'],
            $dados['email'], $dados['endereco'], $dados['link_bitrix'], $dados['id_bitrix']
        ]);
    }

    public function sincronizarContato($empresaId, $dados)
    {
        $sql = "SELECT id FROM contatos WHERE id_bitrix = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$dados['id_bitrix']]);
        $contato = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($contato) {
            $sql = "UPDATE contatos SET nome = ?, cargo = ?, telefone = ?, email = ? WHERE id_bitrix = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$dados['nome'], $dados['cargo'], $dados['telefone'], $dados['email'], $dados['id_bitrix']]);
        } else {
            $sql = "INSERT INTO contatos (nome, cargo, telefone, email, id_bitrix) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$dados['nome'], $dados['cargo'], $dados['telefone'], $dados['email'], $dados['id_bitrix']]);
            $contatoId = $this->conn->lastInsertId();

            $sql = "INSERT INTO cliente_contato (cliente_id, contato_id) VALUES (?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$empresaId, $contatoId]);
        }
    }

    public function sincronizarAplicacao($empresaId, $aplicacaoId, $ativo, $webhook)
    {
        $sql = "SELECT id FROM cliente_aplicacoes WHERE cliente_id = ? AND aplicacao_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$empresaId, $aplicacaoId]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        //$ativo = ($ativo === 'Y') ? 1 : 0;

        if ($registro) {
            $sql = "UPDATE cliente_aplicacoes SET ativo = ?, webhook_bitrix = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$ativo, $webhook, $registro['id']]);
        } else {
            $sql = "INSERT INTO cliente_aplicacoes (cliente_id, aplicacao_id, webhook_bitrix, ativo) VALUES (?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$empresaId, $aplicacaoId, $webhook, $ativo]);
        }
    }
}
