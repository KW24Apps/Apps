<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Inclui o DAO para salvar os jobs diretamente, contornando o helper
require_once __DIR__ . '/../../../Repositories/BatchJobDAO.php';
use Repositories\BatchJobDAO;

// Inclui o BitrixHelper para consultar metadados dos campos
require_once __DIR__ . '/../../../helpers/BitrixHelper.php';
use Helpers\BitrixHelper;

$cliente = $_GET['cliente'] ?? $_POST['cliente'] ?? null;
if (!$cliente) {
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Parâmetro cliente é obrigatório']);
    exit;
}

try {
    // Conecta ao banco para buscar o webhook
    $config = [
        'host' => 'localhost',
        'dbname' => 'kw24co49_api_kwconfig',
        'usuario' => 'kw24co49_kw24',
        'senha' => 'BlFOyf%X}#jXwrR-vi'
    ];
    $pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8", $config['usuario'], $config['senha']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT ca.webhook_bitrix FROM clientes c JOIN cliente_aplicacoes ca ON ca.cliente_id = c.id JOIN aplicacoes a ON ca.aplicacao_id = a.id WHERE c.chave_acesso = :chave AND a.slug = 'import' AND ca.ativo = 1 AND ca.webhook_bitrix IS NOT NULL AND ca.webhook_bitrix != '' LIMIT 1");
    $stmt->bindParam(':chave', $cliente);
    $stmt->execute();
    $webhook = $stmt->fetchColumn();

    if (!$webhook) {
        throw new Exception('Webhook não encontrado para o cliente: ' . $cliente);
    }

    // Recupera dados da sessão
    $mapeamento = $_SESSION['mapeamento'] ?? [];
    $formData = $_SESSION['importacao_form'] ?? [];
    $funilSelecionado = $formData['funil'] ?? null;

    if (empty($mapeamento) || !$funilSelecionado) {
        throw new Exception('Dados de mapeamento ou funil não encontrados na sessão');
    }

    // Busca o arquivo CSV
    $uploadDir = __DIR__ . '/../uploads/';
    $nomeArquivoSessao = $formData['arquivo_salvo'] ?? null;
    if (!$nomeArquivoSessao || !file_exists($uploadDir . $nomeArquivoSessao)) {
        throw new Exception('Arquivo CSV não encontrado no servidor');
    }
    $csvFile = $uploadDir . $nomeArquivoSessao;

    // Processa o CSV para criar os deals, com logging detalhado
    $deals = [];
    $linhasLidas = 0;
    $linhasVazias = 0;
    $linhasInvalidas = 0;

    if (($handle = fopen($csvFile, 'r')) !== FALSE) {
        $header = fgetcsv($handle);
        $numeroLinha = 1;

        while (($row = fgetcsv($handle)) !== FALSE) {
            $numeroLinha++;
            $linhasLidas++;

            if (empty(array_filter($row, function($value) { return $value !== null && $value !== ''; }))) {
                $linhasVazias++;
                error_log("DEBUG: Linha $numeroLinha pulada (vazia).");
                continue;
            }

            $deal = [];
            foreach ($header as $i => $nomeColuna) {
                $nomeColuna = trim($nomeColuna);
                if (isset($mapeamento[$nomeColuna])) {
                    $codigoBitrix = $mapeamento[$nomeColuna];
                    $deal[$codigoBitrix] = $row[$i] ?? '';
                }
            }

            if (!empty($deal) && !empty(array_filter($deal))) {
                // Adiciona os dados dos campos fixos do formulário, se mapeados
                $camposFixosFormulario = [
                    'Responsavel pelo Lead Gerado' => $formData['responsavel_id'] ?? null,
                    'Solicitante do Import' => $formData['solicitante_id'] ?? null,
                    'Identificador da Importacao' => $formData['identificador'] ?? null
                ];

                foreach ($camposFixosFormulario as $nomeCampoAmigavel => $valorDoFormulario) {
                    // Verifica se este campo amigável foi mapeado para um código Bitrix
                    if (isset($mapeamento[$nomeCampoAmigavel]) && !is_null($valorDoFormulario)) {
                        $codigoBitrix = $mapeamento[$nomeCampoAmigavel];
                        
                        // Lógica de formatação específica para campos de usuário (Responsável e Solicitante)
                        if (($nomeCampoAmigavel === 'Responsavel pelo Lead Gerado' || $nomeCampoAmigavel === 'Solicitante do Import') && is_numeric($valorDoFormulario) && $valorDoFormulario > 0) {
                            $deal[$codigoBitrix] = ['user', (int)$valorDoFormulario];
                        } 
                        // Lógica original para outros campos CRM_ENTITY ou campos não-usuário
                        else if (isset($camposBitrixMetadata[$codigoBitrix]) && $camposBitrixMetadata[$codigoBitrix]['type'] === 'crm_entity') {
                            if (is_numeric($valorDoFormulario) && $valorDoFormulario > 0) {
                                $deal[$codigoBitrix] = ['user', (int)$valorDoFormulario];
                            } else {
                                $deal[$codigoBitrix] = $valorDoFormulario; // Mantém o valor original se não for um ID válido
                            }
                        } else {
                            $deal[$codigoBitrix] = $valorDoFormulario;
                        }
                    }
                }
                
                $deals[] = $deal;
            } else {
                $linhasInvalidas++;
                error_log("DEBUG: Linha $numeroLinha pulada (inválida ou sem campos mapeados). Dados: " . implode(', ', $row));
            }
        }
        fclose($handle);
    }

    if (empty($deals)) {
        throw new Exception('Nenhum deal válido encontrado no arquivo CSV');
    }

    // Divide os deals em chunks de até 2000 itens cada
    $maxDealsPerJob = 2000;
    $chunks = array_chunk($deals, $maxDealsPerJob);
    
    $jobIds = [];
    $totalDealsProcessados = 0;
    $dao = new BatchJobDAO();

    // Extrai os IDs corretos do funil selecionado
    $partesFunil = explode('_', $funilSelecionado);
    $entityTypeId = $partesFunil[0] ?? null;
    $categoryId = $partesFunil[1] ?? null;

    if (!$entityTypeId || !$categoryId) {
        throw new Exception("ID do funil inválido na sessão: '$funilSelecionado'");
    }

    // Define globalmente o webhook para o BitrixHelper
    $GLOBALS['ACESSO_AUTENTICADO']['webhook_bitrix'] = $webhook;

    // Consulta os metadados dos campos do Bitrix para a entidade atual
    $camposBitrixMetadata = BitrixHelper::consultarCamposCrm($entityTypeId);
    
    // Processa cada chunk, criando um job para cada um
    foreach ($chunks as $chunk) {
        // Formata os deals dentro do chunk para garantir que campos CRM_ENTITY de usuário estejam corretos
        $formattedChunk = [];
        foreach ($chunk as $deal) {
            $formattedDeal = [];
            foreach ($deal as $codigoBitrix => $valor) {
                // Verifica se o campo é um campo de usuário (CRM_ENTITY) e se o valor é um ID
                if (isset($camposBitrixMetadata[$codigoBitrix]) && $camposBitrixMetadata[$codigoBitrix]['type'] === 'crm_entity') {
                    // A API do Bitrix para campos CRM_ENTITY de usuário espera um array ['user', ID]
                    // ou apenas o ID se o campo for configurado para aceitar apenas usuários.
                    // Vamos usar o formato de array para maior compatibilidade.
                    if (is_numeric($valor) && $valor > 0) {
                        $formattedDeal[$codigoBitrix] = ['user', (int)$valor];
                    } else {
                        $formattedDeal[$codigoBitrix] = $valor; // Mantém o valor original se não for um ID válido
                    }
                } else {
                    $formattedDeal[$codigoBitrix] = $valor;
                }
            }
            $formattedChunk[] = $formattedDeal;
        }

        $jobId = uniqid('job_', true);
        $tipoJob = 'criar_deals';
        
        $dadosJob = [
            'spa' => $entityTypeId,
            'category_id' => $categoryId,
            'deals' => $formattedChunk, // Usa o chunk formatado
            'webhook' => $webhook
        ];
        
        $totalItensChunk = count($formattedChunk);
        $ok = $dao->criarJob($jobId, $tipoJob, $dadosJob, $totalItensChunk);
        
        if ($ok) {
            $jobIds[] = $jobId;
            $totalDealsProcessados += $totalItensChunk;
        } else {
            throw new Exception("Falha ao inserir o job $jobId no banco de dados.");
        }
    }

    // Salva os dados de log na sessão para exibir na página de sucesso
    $_SESSION['importacao_log'] = [
        'linhas_lidas' => $linhasLidas,
        'linhas_vazias' => $linhasVazias,
        'linhas_invalidas' => $linhasInvalidas,
        'total_importado' => $totalDealsProcessados
    ];

    // Redireciona para página de sucesso
    $redirectUrl = "/Apps/public/form/sucesso.php?cliente=" . urlencode($cliente) . 
                  "&jobs=" . urlencode(implode(',', $jobIds)) . 
                  "&total=" . $totalDealsProcessados;
    
    header("Location: $redirectUrl");
    exit;

} catch (Exception $e) {
    $redirectUrl = "/Apps/public/form/erro.php?cliente=" . urlencode($cliente) . 
                  "&mensagem=" . urlencode($e->getMessage());
    header("Location: $redirectUrl");
    exit;
}
?>
