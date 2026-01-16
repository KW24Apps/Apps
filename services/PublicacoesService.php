<?php

namespace Services;

require_once __DIR__ . '/../helpers/LogHelper.php';
require_once __DIR__ . '/../helpers/BitrixHelper.php';
require_once __DIR__ . '/../helpers/BitrixDealHelper.php';
require_once __DIR__ . '/../helpers/UtilHelpers.php';

use Helpers\LogHelper;
use Helpers\BitrixHelper;
use Helpers\BitrixDealHelper;
use Helpers\UtilHelpers;

class PublicacoesService
{
    // Hash de autenticação do cliente na API Publicações Online
    private $hashCliente = 'e6e973a473050bebc1fbd9f02ed62f6e';
    // URL base para consulta de publicações
    private $baseUrl = "https://www.publicacoesonline.com.br/index_pe.php";
    
    // Campo do Bitrix que armazena o número do processo
    private $campoProcessoBitrix = 'ufCrm_1704206234';
    // Campo de controle para evitar duplicidade de atualizações
    private $campoControleBitrix = 'ufCrm_1768480439';
    // Campo de retorno para sinalizar atualização ao Bitrix
    private $campoRetornoApi     = 'UF_CRM_1753210523';
    // Mensagem padrão enviada no retorno da API
    private $valorRetornoApi     = 'Nova Atualização PO';
    
    // ID da pasta no Bitrix Disk para armazenamento de arquivos
    private $folderIdDisk = 3309742;
    // ID do usuário Bitrix que assina os comentários na timeline
    private $userIdBitrix = 43;

    // Mapeamento entre campos da API e campos customizados do Bitrix
    private $mapaCamposAtualizacao = [
        'nomeAdvogado'                   => 'UF_CRM_1768421701',
        'oab'                            => 'UF_CRM_1768421967',
        'nomeAdvogadoRedirecionado'      => 'UF_CRM_1768422093',
        'numeroProcesso'                 => 'UF_CRM_1704206234',
        'numeroProcessoCNJ'              => 'UF_CRM_1768422196',
        'parte_autora'                   => 'UF_CRM_1768422317',
        'parte_reu'                      => 'UF_CRM_1768422386',
        'orgao'                          => 'UF_CRM_1711995192',
        'cidade'                         => 'UF_CRM_1656606829',
        'vara'                           => 'UF_CRM_1768422455',
        'uf'                             => 'UF_CRM_66994DD23580A',
        'esfera'                         => 'UF_CRM_1768422522',
        'dataDisponibilizacao'           => 'UF_CRM_1768422630',
        'dataPublicacao'                 => 'UF_CRM_1768422711',
        'dataDisponibilizacaoWebservice' => 'UF_CRM_1768510437',
    ];

    /**
     * Consulta as publicações do dia com mecanismo de Retry.
     */
    public function fetchDailyPublications(?string $data = null): array
    {
        // Define a data da consulta (padrão hoje)
        $data = $data ?? date('Y-m-d');
        // Monta a URL de consulta com hash e formato JSON
        $url = "{$this->baseUrl}?hashCliente={$this->hashCliente}&data={$data}&retorno=JSON";
        
        $tentativas = 0;
        $maxTentativas = 10;

        // Loop de tentativas em caso de falha na API
        while ($tentativas < $maxTentativas) {
            $tentativas++;
            // LogHelper::logPublicacoes("Tentativa $tentativas de consulta para a data: $data", __METHOD__); // Log de sucesso removido

            // Realiza a chamada para a API externa
            $body = @file_get_contents($url);
            
            // Verifica se a resposta da requisição está vazia
            if (!$body) {
                LogHelper::logPublicacoes("Falha na requisição (body vazio). Aguardando 5 minutos para retentar...", __METHOD__);
                sleep(300);
                continue;
            }

            // Decodifica o JSON retornado pela API
            $decoded = json_decode($body, true);

            // Trata mensagens de erro retornadas pela API
            if (isset($decoded['erros'])) {
                $msgErro = $decoded['erros']['mensagem'] ?? '';
                // Retorna vazio se não houver publicações disponíveis
                if (strpos($msgErro, 'Nenhuma Publicação disponivel') !== false) {
                    return ['status' => 'sucesso', 'publicacoes' => []];
                }
                LogHelper::logPublicacoes("API retornou erro: $msgErro. Aguardando 5 minutos para retentar...", __METHOD__);
                sleep(300);
                continue;
            }

            // Retorna as publicações em caso de sucesso
            if (is_array($decoded)) {
                return ['status' => 'sucesso', 'publicacoes' => $decoded];
            }

            LogHelper::logPublicacoes("Resposta inválida da API. Retentando...", __METHOD__);
            sleep(300);
        }

        // Retorna erro caso atinja o limite de tentativas
        return ['status' => 'erro', 'mensagem' => 'Limite de tentativas de reprocessamento atingido.'];
    }

    /**
     * Busca um Negócio (Deal) no Bitrix24 pelo número do processo (com e sem pontos).
     */
    public function buscarDealPorProcesso(string $numeroProcesso): ?array
    {
        // Aborta se o número do processo estiver vazio
        if (empty($numeroProcesso)) return null;

        // Remove caracteres não numéricos para busca simplificada
        $numeroLimpo = preg_replace('/\D/', '', $numeroProcesso);
        $variantes = [$numeroLimpo];

        // Adiciona variante formatada se for um padrão CNJ válido
        if (strlen($numeroLimpo) === 20) {
            $variantes[] = $this->formatarProcessoCNJ($numeroLimpo);
        }

        // Consulta o CRM do Bitrix buscando pelo campo de processo
        $res = BitrixHelper::listarItensCrm(
            2,
            [$this->campoProcessoBitrix => $variantes],
            ['id', $this->campoControleBitrix],
            1
        );

        // Retorna o ID e a data de controle do card encontrado
        if (!empty($res['items'][0])) {
            return [
                'id' => $res['items'][0]['id'],
                'data_controle' => $res['items'][0][$this->campoControleBitrix] ?? null
            ];
        }

        // Retorna nulo se nenhum card for localizado
        return null;
    }

    private function formatarProcessoCNJ(string $n): string
    {
        // Aplica a máscara padrão do CNJ (0000000-00.0000.0.00.0000)
        return substr($n, 0, 7) . '-' . substr($n, 7, 2) . '.' . substr($n, 9, 4) . '.' .
               substr($n, 13, 1) . '.' . substr($n, 14, 2) . '.' . substr($n, 16, 4);
    }

    /**
     * Converte diversos formatos de data para timestamp de forma segura (comparação nominal).
     */
    private function parseDataParaTimestamp(?string $dataStr): int
    {
        // Retorna zero se a string de data estiver vazia
        if (empty($dataStr)) return 0;

        // Normaliza a string removendo caracteres de fuso horário e separadores T/Z
        $dataLimpa = preg_replace('/[T]/', ' ', $dataStr);
        $dataLimpa = preg_replace('/[Z]|[\+\-]\d{2}:\d{2}/', '', $dataLimpa);
        $dataLimpa = trim(substr($dataLimpa, 0, 19));

        // Tenta converter a data usando múltiplos formatos comuns
        $d = \DateTime::createFromFormat('d/m/Y H:i:s', $dataLimpa);
        if ($d) return $d->getTimestamp();

        $d = \DateTime::createFromFormat('d/m/Y', $dataLimpa);
        if ($d) return $d->getTimestamp();

        $d = \DateTime::createFromFormat('Y-m-d H:i:s', $dataLimpa);
        if ($d) return $d->getTimestamp();

        $d = \DateTime::createFromFormat('Y-m-d', $dataLimpa);
        if ($d) return $d->getTimestamp();

        // Fallback para a função nativa strtotime
        $ts = strtotime($dataLimpa);
        return $ts !== false ? $ts : 0;
    }

    /**
     * Verifica se a publicação precisa ser atualizada comparando timestamps nominais.
     */
    private function precisaAtualizar(?string $dataControle, array $pub): bool
    {
        // Se não houver data de controle, a atualização é obrigatória
        if (!$dataControle) return true;

        // Converte a data de controle do Bitrix para timestamp
        $tsControle = $this->parseDataParaTimestamp($dataControle);

        // Coleta todas as datas disponíveis na publicação
        $datas = [
            $pub['dataPublicacao'] ?? null,
            $pub['dataDisponibilizacao'] ?? null,
            $pub['dataDisponibilizacaoWebservice'] ?? null,
        ];

        // Converte as datas da publicação para timestamps
        $timestamps = [];
        foreach (array_filter($datas) as $dStr) {
            $timestamps[] = $this->parseDataParaTimestamp($dStr);
        }

        // Se não houver datas na publicação, força a atualização
        if (!$timestamps) return true;

        // Compara a data mais recente da publicação com a do Bitrix
        $tsMaisRecente = max($timestamps);
        return $tsMaisRecente > $tsControle;
    }

    /**
     * Orquestra a montagem das atualizações para um lote de publicações.
     */
    public function montarAtualizacoesParaPublicacoes(array $publicacoes, string $dataConsulta): array
    {
        $ids = [];
        $fields = [];
        $correspondencias = [];
        $totalEncontrados = 0;

        // Itera sobre cada publicação retornada pela API
        foreach ($publicacoes as $pub) {
            // Identifica o número do processo na publicação
            $numeroProcesso = $pub['numeroProcesso'] ?? $pub['numeroProcessoCNJ'] ?? null;
            if (!$numeroProcesso) continue;

            // Tenta localizar o card correspondente no Bitrix
            $deal = $this->buscarDealPorProcesso($numeroProcesso);

            if ($deal) {
                $totalEncontrados++;

                // Verifica se o card precisa de novos dados
                if ($this->precisaAtualizar($deal['data_controle'], $pub)) {
                    $payload = $this->montarFieldsDeAtualizacao($pub);
                    $ids[] = $deal['id'];
                    $fields[] = $payload;
                    $status = 'Atualizar';
                } else {
                    $status = 'Já Atualizado';
                }
            } else {
                $status = 'Vazio';
            }

            // Registra o mapeamento para o relatório final
            $correspondencias[] = [
                'processo' => $numeroProcesso,
                'id_bitrix' => $deal['id'] ?? 'Vazio',
                'status' => $status
            ];
        }

        // Retorna o lote de IDs e campos para atualização em massa
        return [
            'correspondencias' => $correspondencias,
            'total_encontrados' => $totalEncontrados,
            'ids' => $ids,
            'fields' => $fields,
            'total_para_atualizar' => count($ids)
        ];
    }

    /**
     * Executa a edição em lote e dispara os comentários na Timeline.
     */
    public function executarBatchEdicao(int $entityTypeId, array $ids, array $fields, array $publicacoesOriginais = []): array
    {
        // Aborta se não houver IDs para atualizar
        if (!$ids) return ['status' => 'nada'];

        // Envia o lote de atualizações para o Bitrix via Helper
        $resultado = BitrixDealHelper::editarDeal($entityTypeId, $ids, $fields);

        // Se a atualização for bem-sucedida, registra cada publicação na timeline
        if ($resultado['status'] === 'sucesso' && !empty($publicacoesOriginais)) {
            foreach ($ids as $index => $id) {
                $pub = $publicacoesOriginais[$index] ?? null;
                if ($pub) {
                    $this->registrarTimelinePublicacao($id, $pub);
                }
            }
        }

        // Retorna o status final da operação em lote
        return $resultado;
    }

    /**
     * Registra a publicação na Timeline com formatação limpa e anexo funcional.
     */
    private function registrarTimelinePublicacao(string $idBitrix, array $pub): void
    {
        // Define o título do comentário na timeline
        $titulo = "⚖️ NOVA PUBLICAÇÃO ENCONTRADA - " . ($pub['data'] ?? date('d/m/Y'));
        
        // Monta o corpo da mensagem com as datas e o nome do jornal
        $msg = $titulo . "\n\n";
        $msg .= "📅 Data Disponibilização DJEN: " . ($pub['dataDisponibilizacao'] ?? 'N/A') . "\n";
        $msg .= "📅 Data Publicação DJEN: " . ($pub['dataPublicacao'] ?? 'N/A') . "\n";
        $msg .= "🌐 Data Disponibilização PO: " . ($pub['dataDisponibilizacaoWebservice'] ?? 'N/A') . "\n";
        $msg .= "📰 Nome do Jornal: " . ($pub['nomeJornal'] ?? 'N/A') . "\n\n";
        
        // Adiciona o conteúdo da publicação removendo tags HTML
        $msg .= "📝 Conteúdo na íntegra:\n";
        $msg .= strip_tags($pub['conteudo'] ?? 'Conteúdo não disponível.');

        $fileIds = [];

        // Processa o anexo se houver conteúdo em base64
        if (!empty($pub['conteudo64'])) {
            // Gera um nome único para o arquivo HTML da publicação
            $fileName = "Publicacao_" . ($pub['numeroProcesso'] ?? 'SemNumero') . "_" . date('Ymd_His') . ".html";
            
            // Faz o upload do arquivo para o Bitrix Disk
            UtilHelpers::uploadBase64ParaPastaDisk($fileName, $pub['conteudo64'], $this->folderIdDisk);
            
            // Prepara o anexo para ser enviado junto com o comentário
            $fileIds[] = [
                $fileName,
                $pub['conteudo64']
            ];
            
            $msg .= "\n\n📎 ARQUIVO ANEXADO: $fileName";
        }

        // Configura os parâmetros para a chamada da API de timeline
        $params = [
            'fields' => [
                'ENTITY_ID' => (int)$idBitrix,
                'ENTITY_TYPE' => 'deal',
                'COMMENT' => $msg,
                'AUTHOR_ID' => $this->userIdBitrix,
                'FILES' => $fileIds
            ]
        ];

        // Envia o comentário para o Bitrix24
        BitrixHelper::chamarApi('crm.timeline.comment.add', $params);
    }

    /**
     * Monta o payload de campos para atualização no Bitrix24.
     */
    private function montarFieldsDeAtualizacao(array $publicacao): array
    {
        $fields = [];

        // Define a mensagem de atualização no campo de retorno do Bitrix
        $fields[$this->campoRetornoApi] = $this->valorRetornoApi;

        // Mapeia os dados da publicação para os campos UF do Bitrix
        foreach ($this->mapaCamposAtualizacao as $chave => $uf) {
            if (array_key_exists($chave, $publicacao)) {
                $fields[$uf] = $publicacao[$chave] ?: '';
            }
        }

        // Coleta datas para definir o novo ponto de controle do card
        $datas = [
            $publicacao['dataPublicacao'] ?? null,
            $publicacao['dataDisponibilizacao'] ?? null,
            $publicacao['dataDisponibilizacaoWebservice'] ?? null,
        ];

        $datas = array_filter($datas);

        // Define a data de controle como a data mais recente encontrada
        if ($datas) {
            $timestamps = [];
            foreach ($datas as $dStr) {
                $timestamps[$this->parseDataParaTimestamp($dStr)] = $dStr;
            }
            $maxTs = max(array_keys($timestamps));
            $fields[$this->campoControleBitrix] = $timestamps[$maxTs];
        }

        // Retorna o array de campos pronto para o Bitrix
        return $fields;
    }
}
