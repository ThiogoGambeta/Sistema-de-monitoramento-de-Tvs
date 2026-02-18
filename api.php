<?php
/**
 * API para consultar status dos Raspberries
 * 
 * Retorna JSON com:
 * - Lista de rasps com status (rasp online/offline, TV ligada/desligada)
 * - Distribuição atual de linhas por rasp
 */

require_once 'funcoes.php';

header('Content-Type: application/json');

$rasps = carregarRasps();
$distribuicao = obterDistribuicaoCompleta();
$agora = time();

// Mapeia distribuição por ID
$distribuicaoPorId = [];
foreach ($distribuicao as $raspKey => $dados) {
    $id = str_replace('Rasp', '', $raspKey);
    $distribuicaoPorId[$id] = $dados;
}

// Monta resposta com status de cada rasp
$response = [
    'rasps' => [],
    'timestamp' => $agora
];

foreach ($rasps as $rasp) {
    $raspId = $rasp['id'] ?? '';
    $lastSeen = $rasp['last_seen'] ?? 0;
    $raspOnline = raspEstaOnline($lastSeen);
    
    $raspData = [
        'id' => $raspId,
        'name' => $rasp['name'] ?? '',
        'ip' => $rasp['ip'] ?? '',
        'local' => $rasp['local'] ?? '',
        'status_rasp' => $raspOnline,
        'status_tv' => $raspOnline && ($rasp['status_tv'] ?? false),
        'last_seen' => $lastSeen,
        'distribuicao' => $distribuicaoPorId[$raspId] ?? null
    ];
    
    $response['rasps'][] = $raspData;
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
