<?php
// debug_users_api.php - API de debug que sempre retorna dados válidos
header('Content-Type: application/json; charset=utf-8');

$q = $_GET['q'] ?? '';

// Simula dados válidos sempre
$usuarios = [
    ['id' => '1', 'name' => 'João Silva Debug'],
    ['id' => '2', 'name' => 'Maria Santos Debug'],
    ['id' => '3', 'name' => 'Pedro Oliveira Debug']
];

// Filtra baseado na query se fornecida
if (!empty($q)) {
    $usuarios = array_filter($usuarios, function($user) use ($q) {
        return stripos($user['name'], $q) !== false;
    });
    $usuarios = array_values($usuarios); // Re-indexa o array
}

// Log para debug
error_log("DEBUG API: Query='$q', Retornando " . count($usuarios) . " usuários");

echo json_encode($usuarios);
exit;
?>
