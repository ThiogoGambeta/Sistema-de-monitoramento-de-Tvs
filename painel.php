<?php
/**
 * Painel Visual de Monitoramento
 * 
 * Mostra:
 * - Status de cada Raspberry (ligado/desligado)
 * - Status de cada TV (ligada/desligada)
 * - Distribuição atual de linhas por rasp
 * - URLs do monproc para cada rasp
 */

require_once 'funcoes.php';

$rasps = carregarRasps();
$distribuicao = obterDistribuicaoCompleta();
$linhasAtivas = buscarLinhasAtivas();
$erroBanco = getUltimoErroBanco();
$agora = time();

// Mapeia distribuição por ID do rasp (converte "Rasp01" -> "01")
$distribuicaoPorId = [];
foreach ($distribuicao as $raspKey => $dados) {
    $id = str_replace('Rasp', '', $raspKey);
    $distribuicaoPorId[$id] = $dados;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Monitoramento - Linhas de Produção</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        h1 {
            color: white;
            text-align: center;
            margin-bottom: 10px;
            font-size: 2rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .subtitle {
            color: rgba(255,255,255,0.9);
            text-align: center;
            margin-bottom: 30px;
            font-size: 0.95rem;
        }
        
        .stats-bar {
            background: white;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .rasps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
        }
        
        .rasp-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .rasp-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0,0,0,0.15);
        }
        
        .rasp-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        
        .rasp-info {
            flex: 1;
        }
        
        .rasp-id {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .rasp-name {
            font-size: 0.9rem;
            color: #666;
        }
        
        .rasp-local {
            font-size: 0.85rem;
            color: #999;
            margin-top: 2px;
        }
        
        .status-badges {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: flex-end;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            white-space: nowrap;
        }
        
        .status-on {
            background: #28a745;
            color: white;
        }
        
        .status-off {
            background: #dc3545;
            color: white;
        }
        
        .linhas-section {
            margin-top: 15px;
            margin-bottom: 15px;
        }
        
        .linhas-title {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .linhas-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .linha-item {
            background: #f8f9fa;
            padding: 10px 12px;
            border-radius: 6px;
            border-left: 4px solid #667eea;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .linha-numero {
            font-weight: bold;
            color: #333;
            font-size: 1rem;
        }
        
        .linha-espec {
            color: #666;
            font-size: 0.9rem;
            background: #e9ecef;
            padding: 3px 8px;
            border-radius: 4px;
        }
        
        .sem-linhas {
            text-align: center;
            color: #999;
            padding: 15px;
            font-style: italic;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .url-container {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        
        .url-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .url-link {
            display: block;
            background: #667eea;
            color: white;
            padding: 10px 12px;
            border-radius: 6px;
            text-decoration: none;
            word-break: break-all;
            font-size: 0.85rem;
            transition: background 0.2s;
            text-align: center;
        }
        
        .url-link:hover {
            background: #5568d3;
        }
        
        .url-link:active {
            background: #4456b3;
        }
        
        .empty-state {
            text-align: center;
            color: white;
            padding: 60px 20px;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        
        .empty-state h2 {
            margin-bottom: 10px;
        }
        
        .auto-refresh {
            text-align: center;
            color: rgba(255,255,255,0.9);
            margin-top: 30px;
            font-size: 0.9rem;
        }
        
        .auto-refresh strong {
            color: white;
        }
        
        .alerta-banco {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            color: #856404;
        }
        
        .alerta-banco.erro {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        .alerta-banco strong { display: block; margin-bottom: 8px; }
        .alerta-banco ul { margin: 8px 0 0 20px; }
        
        @media (max-width: 768px) {
            .rasps-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-bar {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Painel de Monitoramento</h1>
        <p class="subtitle">Linhas de Produção - Status em Tempo Real</p>
        
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-label">Linhas Rodando</div>
                <div class="stat-value"><?= count($linhasAtivas) ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Rasps Ativos</div>
                <div class="stat-value"><?= count($distribuicaoPorId) ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Rasps Online</div>
                <div class="stat-value">
                    <?php
                    $raspsOnline = 0;
                    foreach ($rasps as $r) {
                        if (raspEstaOnline($r['last_seen'] ?? 0)) $raspsOnline++;
                    }
                    echo $raspsOnline;
                    ?>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-label">TVs Ligadas</div>
                <div class="stat-value">
                    <?php
                    $tvsLigadas = 0;
                    foreach ($rasps as $r) {
                        if (raspEstaOnline($r['last_seen'] ?? 0) && ($r['status_tv'] ?? false)) {
                            $tvsLigadas++;
                        }
                    }
                    echo $tvsLigadas;
                    ?>
                </div>
            </div>
        </div>
        
        <?php if ($erroBanco): ?>
            <div class="alerta-banco erro">
                <strong>⚠️ Erro ao conectar/consultar o banco</strong>
                <p><?= htmlspecialchars($erroBanco) ?></p>
                <ul>
                    <li>Confira em <code>config.php</code>: host, dbname, user, password e porta.</li>
                    <li>O servidor onde o PHP roda precisa conseguir acessar o IP do banco (rede/firewall).</li>
                    <li>Verifique se a extensão <code>pdo_pgsql</code> está habilitada no PHP (<code>php -m</code>).</li>
                    <li>Teste a query direto no cliente SQL (pgAdmin, DBeaver, etc.).</li>
                </ul>
            </div>
        <?php elseif (count($linhasAtivas) === 0 && !empty($rasps)): ?>
            <div class="alerta-banco">
                <strong>ℹ️ Nenhuma linha retornada pela aplicação</strong>
                <p>Se no banco a query manual retorna linhas, confira:</p>
                <ul>
                    <li><strong>config.php no servidor</strong> — mesmo host, dbname, user e password do cliente onde a query manual funciona?</li>
                    <li><strong>Rede</strong> — o computador onde o Apache/PHP roda consegue acessar o IP do banco (ex.: 192.168.10.23)?</li>
                    <li><strong>Schema</strong> — a tabela está no schema <code>public</code>? Se não, defina <code>'schema' => 'nome_do_schema'</code> em config.php.</li>
                    <li>Abra <a href="teste_banco.php" target="_blank"><strong>teste_banco.php</strong></a> na mesma pasta do painel: ele mostra erro de conexão/query ou a lista de linhas.</li>
                </ul>
                <p style="margin-top:12px;">Se <strong>teste_banco.php</strong> der 404, coloque o arquivo na mesma pasta do <strong>painel.php</strong> no servidor (onde o Apache/ngrok servem os arquivos).</p>
            </div>
        <?php endif; ?>
        
        <?php if (empty($rasps)): ?>
            <div class="empty-state">
                <h2>Nenhum Raspberry cadastrado</h2>
                <p>Configure os rasps no arquivo rasps.json</p>
            </div>
        <?php else: ?>
            <div class="rasps-grid">
                <?php foreach ($rasps as $rasp): ?>
                    <?php
                    $raspId = $rasp['id'] ?? '';
                    $lastSeen = $rasp['last_seen'] ?? 0;
                    $raspOnline = raspEstaOnline($lastSeen);
                    $tvLigada = $raspOnline && ($rasp['status_tv'] ?? false);
                    $distribuicaoRasp = $distribuicaoPorId[$raspId] ?? null;
                    ?>
                    <div class="rasp-card">
                        <div class="rasp-header">
                            <div class="rasp-info">
                                <div class="rasp-id"><?= htmlspecialchars($rasp['name'] ?? "Raspberry {$raspId}") ?></div>
                                <div class="rasp-name">ID: <?= htmlspecialchars($raspId) ?></div>
                                <?php if (!empty($rasp['local'])): ?>
                                    <div class="rasp-local">📍 <?= htmlspecialchars($rasp['local']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="status-badges">
                                <span class="status-badge <?= $raspOnline ? 'status-on' : 'status-off' ?>">
                                    Rasp: <?= $raspOnline ? 'ON' : 'OFF' ?>
                                </span>
                                <span class="status-badge <?= $tvLigada ? 'status-on' : 'status-off' ?>">
                                    TV: <?= $tvLigada ? 'ON' : 'OFF' ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="linhas-section">
                            <div class="linhas-title">Linhas Atribuídas:</div>
                            <?php if ($distribuicaoRasp && !empty($distribuicaoRasp['linhas'])): ?>
                                <div class="linhas-list">
                                    <?php foreach ($distribuicaoRasp['linhas'] as $idx => $linha): ?>
                                        <div class="linha-item">
                                            <span class="linha-numero"><?= htmlspecialchars($linha) ?></span>
                                            <span class="linha-espec"><?= htmlspecialchars($distribuicaoRasp['drawing_names'][$idx] ?? '') ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="sem-linhas">Nenhuma linha rodando no momento</div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($distribuicaoRasp): ?>
                            <div class="url-container">
                                <div class="url-label">URL do Monitor:</div>
                                <a href="<?= htmlspecialchars($distribuicaoRasp['url']) ?>" target="_blank" class="url-link">
                                    Abrir Monitor
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="auto-refresh">
            ⏱️ Atualização automática a cada <strong>30 segundos</strong>
        </div>
    </div>
    
    <script>
        // Auto-refresh a cada 30 segundos
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
