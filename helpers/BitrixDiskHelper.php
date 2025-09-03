<?php
namespace Helpers;

require_once __DIR__ . '/../helpers/BitrixHelper.php';

use Helpers\BitrixHelper;

class BitrixDiskHelper
{
    /**
     * Renomeia uma pasta no Bitrix24 Disk.
     *
     * @param int $folderId O ID da pasta a ser renomeada.
     * @param string $newName O novo nome para a pasta.
     * @return array A resposta da API Bitrix24.
     */
    public static function renameFolder($folderId, $newName)
    {
        $response = BitrixHelper::chamarApi(
            'disk.folder.rename',
            [
                'id' => $folderId,
                'newName' => $newName
            ]
        );

        return $response;
    }

    /**
     * Encontra o ID de uma subpasta dentro de uma pasta pai, procurando por um trecho do nome.
     *
     * @param int $parentFolderId O ID da pasta pai onde a busca será feita.
     * @param string $nameSubstring O trecho do nome da subpasta a ser encontrado.
     * @return int|null O ID da primeira subpasta encontrada ou null se nenhuma for encontrada.
     */
    public static function findSubfolderIdByName($parentFolderId, $nameSubstring)
    {
        $start = 0;
        do {
            $params = [
                'id' => $parentFolderId,
                'start' => $start
            ];
            $children = BitrixHelper::chamarApi('disk.folder.getchildren', $params);

            if (isset($children['result']) && is_array($children['result'])) {
                foreach ($children['result'] as $item) {
                    // Verifica se é uma pasta e se o nome contém o trecho
                    if (isset($item['TYPE']) && $item['TYPE'] === 'folder' && strpos($item['NAME'], $nameSubstring) !== false) {
                        return (int)$item['ID']; // Retorna o ID da primeira pasta encontrada
                    }
                }
            }

            // Verifica se há mais páginas
            $hasMore = isset($children['next']) && $children['next'] > 0;
            if ($hasMore) {
                $start = $children['next']; // Atualiza o 'start' para a próxima página
            }

        } while ($hasMore); // Continua enquanto houver mais páginas

        return null; // Retorna null se não encontrar em nenhuma página
    }

    /**
     * Obtém o URL detalhado de uma pasta no Bitrix24 Disk.
     *
     * @param int $folderId O ID da pasta.
     * @return string|null O URL detalhado da pasta ou null se não for encontrado.
     */
    public static function getFolderDetailUrl(int $folderId): ?string
    {
        $response = BitrixHelper::chamarApi(
            'disk.folder.get',
            [
                'id' => $folderId
            ]
        );

        return $response['result']['DETAIL_URL'] ?? null;
    }
}
