<?php
// ARQUIVO DE TESTE - VERIFICAR SE O SERVIDOR ESTÁ SINCRONIZADO
// Se você conseguir ver esta mensagem, o servidor está atualizado

echo json_encode([
    'status' => 'SERVIDOR_ATUALIZADO',
    'timestamp' => date('Y-m-d H:i:s'),
    'git_hash' => '140c2e3',
    'mensagem' => 'Servidor sincronizado com sucesso!'
]);
?>
