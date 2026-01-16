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
    private $hashCliente = 'e6e973a473050bebc1fbd9f02ed62f6e';
    private $baseUrl = "https://www.publicacoesonline.com.br/index_pe.php";
    
    // Configurações de Campos Bitrix24
    private $campoProcessoBitrix = 'ufCrm_1704206234';
    private $campoControleBitrix = 'ufCrm_1768480439';
    private $campoRetornoApi     = 'UF_CRM_1753210523';
    private $valorRetornoApi     = 'Nova Atualização PO';
    
    // Configurações de Integração
    private $folderIdDisk = 3309742;
    private $userIdBitrix = 43;

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
        $data = $data ?? date('Y-m-d');
        $url = "{$this->baseUrl}?hashCliente={$this->hashCliente}&data={$data}&retorno=JSON";
        
        $tentativas = 0;
        $maxTentativas = 10;

        while ($tentativas < $maxTentativas) {
            $tentativas++;
            LogHelper::logPublicacoes("Tentativa $tentativas de consulta para a data: $data", __METHOD__);

            $body = @file_get_contents($url);
            
            if (!$body) {
                LogHelper::logPublicacoes("Falha na requisição (body vazio). Aguardando 5 minutos para retentar...", __METHOD__);
                sleep(300);
                continue;
            }

            $decoded = json_decode($body, true);

            if (isset($decoded['erros'])) {
                $msgErro = $decoded['erros']['mensagem'] ?? '';
                if (strpos($msgErro, 'Nenhuma Publicação disponivel') !== false) {
                    return ['status' => 'sucesso', 'publicacoes' => []];
                }
                LogHelper::logPublicacoes("API retornou erro: $msgErro. Aguardando 5 minutos para retentar...", __METHOD__);
                sleep(300);
                continue;
            }

            if (is_array($decoded)) {
                return ['status' => 'sucesso', 'publicacoes' => $decoded];
            }

            LogHelper::logPublicacoes("Resposta inválida da API. Retentando...", __METHOD__);
            sleep(300);
        }

        return ['status' => 'erro', 'mensagem' => 'Limite de tentativas de reprocessamento atingido.'];
    }

    /**
     * Busca um Negócio (Deal) no Bitrix24 pelo número do processo (com e sem pontos).
     */
    public function buscarDealPorProcesso(string $numeroProcesso): ?array
    {
        if (empty($numeroProcesso)) return null;

        $numeroLimpo = preg_replace('/\D/', '', $numeroProcesso);
        $variantes = [$numeroLimpo];

        if (strlen($numeroLimpo) === 20) {
            $variantes[] = $this->formatarProcessoCNJ($numeroLimpo);
        }

        $res = BitrixHelper::listarItensCrm(
            2,
            [$this->campoProcessoBitrix => $variantes],
            ['id', $this->campoControleBitrix],
            1
        );

        if (!empty($res['items'][0])) {
            return [
                'id' => $res['items'][0]['id'],
                'data_controle' => $res['items'][0][$this->campoControleBitrix] ?? null
            ];
        }

        return null;
    }

    private function formatarProcessoCNJ(string $n): string
    {
        return substr($n, 0, 7) . '-' . substr($n, 7, 2) . '.' . substr($n, 9, 4) . '.' .
               substr($n, 13, 1) . '.' . substr($n, 14, 2) . '.' . substr($n, 16, 4);
    }

    /**
     * Converte diversos formatos de data para timestamp de forma segura (comparação nominal).
     */
    private function parseDataParaTimestamp(?string $dataStr): int
    {
        if (empty($dataStr)) return 0;

        $dataLimpa = preg_replace('/[T]/', ' ', $dataStr);
        $dataLimpa = preg_replace('/[Z]|[\+\-]\d{2}:\d{2}/', '', $dataLimpa);
        $dataLimpa = trim(substr($dataLimpa, 0, 19));

        $d = \DateTime::createFromFormat('d/m/Y H:i:s', $dataLimpa);
        if ($d) return $d->getTimestamp();

        $d = \DateTime::createFromFormat('d/m/Y', $dataLimpa);
        if ($d) return $d->getTimestamp();

        $d = \DateTime::createFromFormat('Y-m-d H:i:s', $dataLimpa);
        if ($d) return $d->getTimestamp();

        $d = \DateTime::createFromFormat('Y-m-d', $dataLimpa);
        if ($d) return $d->getTimestamp();

        $ts = strtotime($dataLimpa);
        return $ts !== false ? $ts : 0;
    }

    /**
     * Verifica se a publicação precisa ser atualizada comparando timestamps nominais.
     */
    private function precisaAtualizar(?string $dataControle, array $pub): bool
    {
        if (!$dataControle) return true;

        $tsControle = $this->parseDataParaTimestamp($dataControle);

        $datas = [
            $pub['dataPublicacao'] ?? null,
            $pub['dataDisponibilizacao'] ?? null,
            $pub['dataDisponibilizacaoWebservice'] ?? null,
        ];

        $timestamps = [];
        foreach (array_filter($datas) as $dStr) {
            $timestamps[] = $this->parseDataParaTimestamp($dStr);
        }

        if (!$timestamps) return true;

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

        foreach ($publicacoes as $pub) {
            $numeroProcesso = $pub['numeroProcesso'] ?? $pub['numeroProcessoCNJ'] ?? null;
            if (!$numeroProcesso) continue;

            $deal = $this->buscarDealPorProcesso($numeroProcesso);

            if ($deal) {
                $totalEncontrados++;

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

            $correspondencias[] = [
                'processo' => $numeroProcesso,
                'id_bitrix' => $deal['id'] ?? 'Vazio',
                'status' => $status
            ];
        }

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
        if (!$ids) return ['status' => 'nada'];

        $resultado = BitrixDealHelper::editarDeal($entityTypeId, $ids, $fields);

        if ($resultado['status'] === 'sucesso' && !empty($publicacoesOriginais)) {
            foreach ($ids as $index => $id) {
                $pub = $publicacoesOriginais[$index] ?? null;
                if ($pub) {
                    $this->registrarTimelinePublicacao($id, $pub);
                }
            }
        }

        return $resultado;
    }

    /**
     * Registra a publicação na Timeline com formatação limpa e anexo funcional.
     */
    private function registrarTimelinePublicacao(string $idBitrix, array $pub): void
    {
        $titulo = "⚖️ NOVA PUBLICAÇÃO ENCONTRADA - " . ($pub['data'] ?? date('d/m/Y'));
        
        // Formatação em Texto Simples para garantir renderização correta no Bitrix
        $msg = $titulo . "\n\n";
        $msg .= "📅 Data Disponibilização DJEN: " . ($pub['dataDisponibilizacao'] ?? 'N/A') . "\n";
        $msg .= "📅 Data Publicação DJEN: " . ($pub['dataPublicacao'] ?? 'N/A') . "\n";
        $msg .= "🌐 Data Disponibilização PO: " . ($pub['dataDisponibilizacaoWebservice'] ?? 'N/A') . "\n";
        $msg .= "📰 Nome do Jornal: " . ($pub['nomeJornal'] ?? 'N/A') . "\n\n";
        
        $msg .= "📝 Conteúdo na íntegra:\n";
        $msg .= strip_tags($pub['conteudo'] ?? 'Conteúdo não disponível.');

        $fileIds = [];

        if (!empty($pub['conteudo64'])) {
            $fileName = "Publicacao_" . ($pub['numeroProcesso'] ?? 'SemNumero') . "_" . date('Ymd_His') . ".html";
            
            // Salva na pasta do Disk e anexa ao comentário
            UtilHelpers::uploadBase64ParaPastaDisk($fileName, $pub['conteudo64'], $this->folderIdDisk);
            
            // Para o anexo na Timeline ser funcional e ter nome/extensão, 
            // enviamos o conteúdo base64 diretamente no comentário com o prefixo 'n'
            $fileIds[] = [
                $fileName,
                $pub['conteudo64']
            ];
            
            $msg .= "\n\n📎 ARQUIVO ANEXADO: $fileName";
        }

        $params = [
            'fields' => [
                'ENTITY_ID' => (int)$idBitrix,
                'ENTITY_TYPE' => 'deal',
                'COMMENT' => $msg,
                'AUTHOR_ID' => $this->userIdBitrix,
                'FILES' => $fileIds
            ]
        ];

        BitrixHelper::chamarApi('crm.timeline.comment.add', $params);
    }

    /**
     * Monta o payload de campos para atualização no Bitrix24.
     */
    private function montarFieldsDeAtualizacao(array $publicacao): array
    {
        $fields = [];

        // Injeta o valor fixo para o campo de retorno da API (Gatilho Bitrix)
        $fields[$this->campoRetornoApi] = $this->valorRetornoApi;

        foreach ($this->mapaCamposAtualizacao as $chave => $uf) {
            if (array_key_exists($chave, $publicacao)) {
                $fields[$uf] = $publicacao[$chave] ?: '';
            }
        }

        // Define a data de controle baseada na data mais recente da publicação
        $datas = [
            $publicacao['dataPublicacao'] ?? null,
            $publicacao['dataDisponibilizacao'] ?? null,
            $publicacao['dataDisponibilizacaoWebservice'] ?? null,
        ];

        $datas = array_filter($datas);

        if ($datas) {
            $timestamps = [];
            foreach ($datas as $dStr) {
                $timestamps[$this->parseDataParaTimestamp($dStr)] = $dStr;
            }
            $maxTs = max(array_keys($timestamps));
            $fields[$this->campoControleBitrix] = $timestamps[$maxTs];
        }

        return $fields;
    }
}
