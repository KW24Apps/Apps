<?php

namespace Helpers;

require_once __DIR__ . '/../helpers/BitrixHelper.php';

use Helpers\BitrixHelper;

class BitrixMessageHelper
{
    /**
     * Envia uma mensagem para um chat ou usuário no Bitrix24.
     *
     * @param string $dialogId ID do diálogo (ex: 'chat123' para chats ou '123' para usuários).
     * @param string $message Texto da mensagem.
     * @param array $options Opções adicionais (ATTACH, KEYBOARD, etc).
     * @return array Resposta da API.
     */
    public static function enviarMensagem(string $dialogId, string $message, array $options = [])
    {
        $params = [
            'DIALOG_ID' => $dialogId,
            'MESSAGE'   => $message
        ];

        // Mescla opções adicionais se fornecidas
        if (!empty($options)) {
            $params = array_merge($params, $options);
        }

        return BitrixHelper::chamarApi('im.message.add', $params);
    }
}
