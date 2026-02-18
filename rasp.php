<?php
/**
 * Endpoint que cada Raspberry acessa
 * 
 * Uso: rasp.php?id=01
 * 
 * Este arquivo consulta o banco, distribui as linhas rodando,
 * e redireciona o rasp para a URL correta do monproc
 */

require_once 'funcoes.php';

// Pega o ID do rasp (pode vir por GET ou tentar identificar por IP)
$raspId = $_GET['id'] ?? '';

// Se não veio por GET, tenta identificar por IP
if (empty($raspId)) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $rasps = carregarRasps();
    
    foreach ($rasps as $rasp) {
        if (($rasp['ip'] ?? '') === $ip) {
            $raspId = $rasp['id'];
            break;
        }
    }
}

// Se ainda não encontrou, retorna erro
if (empty($raspId)) {
    http_response_code(404);
    die('Raspberry não identificado. Use ?id=01 ou configure o IP no rasps.json');
}

// Obtém a URL do monproc para este rasp
$urlMonproc = obterUrlParaRasp($raspId);

if ($urlMonproc === null) {
    $erroBanco = getUltimoErroBanco();
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?= $erroBanco ? 'Erro no banco' : 'Aguardando linhas...' ?></title>
        <meta http-equiv="refresh" content="30">
        <style>
            body {
                font-family: Arial, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
                background: #f0f0f0;
            }
            .message {
                text-align: center;
                padding: 40px;
                background: white;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                max-width: 500px;
            }
            .erro { color: #c00; margin: 15px 0; font-size: 0.9rem; }
        </style>
    </head>
    <body>
        <div class="message">
            <h2>Raspberry <?= htmlspecialchars($raspId) ?></h2>
            <?php if ($erroBanco): ?>
                <p class="erro"><strong>Erro ao conectar/consultar o banco:</strong><br><?= htmlspecialchars($erroBanco) ?></p>
                <p><small>Verifique config.php e a rede. Página atualiza em 30s.</small></p>
            <?php else: ?>
                <p>Aguardando linhas em operação...</p>
                <p><small>Nenhuma linha com medição nas últimas 12h. Atualizando em 30s.</small></p>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Redireciona para a URL do monproc
header('Location: ' . $urlMonproc, true, 302);
exit;
