<?php
/**
 * Funções compartilhadas do sistema
 */

require_once 'config.php';
require_once 'json_store.php';

// Guarda o último erro de banco para exibição no painel
$_ultimo_erro_banco = null;

/**
 * Retorna o último erro de conexão/query (para diagnóstico no painel)
 */
function getUltimoErroBanco() {
    global $_ultimo_erro_banco;
    return $_ultimo_erro_banco;
}

/**
 * Conecta no banco de dados PostgreSQL
 */
function conectarBanco() {
    global $db_config, $_ultimo_erro_banco;
    
    if (!extension_loaded('pdo_pgsql')) {
        $_ultimo_erro_banco = 'Extensão PHP "pdo_pgsql" não está habilitada. Habilite no php.ini.';
        error_log($_ultimo_erro_banco);
        return null;
    }
    
    $dsn = sprintf(
        "pgsql:host=%s;port=%d;dbname=%s",
        $db_config['host'],
        $db_config['port'],
        $db_config['dbname']
    );
    
    try {
        $opcoes = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10  // timeout de conexão em segundos (host inacessível falha mais rápido)
        ];
        $pdo = new PDO($dsn, $db_config['user'], $db_config['password'], $opcoes);
        return $pdo;
    } catch (PDOException $e) {
        $_ultimo_erro_banco = 'Conexão com o banco falhou: ' . $e->getMessage();
        error_log($_ultimo_erro_banco);
        return null;
    }
}

/**
 * Busca todas as linhas ativas (rodando nas últimas 12 horas)
 * Retorna array ordenado por linha ASC
 */
function buscarLinhasAtivas($pdo = null) {
    global $_ultimo_erro_banco;
    
    if ($pdo === null) {
        $pdo = conectarBanco();
        if ($pdo === null) return [];
    }
    
    $schema = 'public';
    if (isset($GLOBALS['db_config']['schema']) && preg_match('/^[a-zA-Z0-9_]+$/', (string)$GLOBALS['db_config']['schema'])) {
        $schema = $GLOBALS['db_config']['schema'];
    }
    $tabela = $schema . 'bda';
    
    $sql = "
        WITH linhas_ativas AS (
            SELECT
                linha,
                drawing_name,
                MAX(measurement_created) AS ultima_medicao
            FROM " . $tabela . "
            WHERE linha IS NOT NULL
              AND drawing_name IS NOT NULL
              AND drawing_name NOT ILIKE '%injeção%'
              AND drawing_name NOT ILIKE '%estampo%'
            GROUP BY linha, drawing_name
            HAVING MAX(measurement_created) >= NOW() - INTERVAL '12 hours'
        )
        SELECT *
        FROM linhas_ativas
        ORDER BY linha ASC
    ";
    
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        $_ultimo_erro_banco = 'Query linhas ativas falhou: ' . $e->getMessage();
        error_log($_ultimo_erro_banco);
        return [];
    }
}

/**
 * Distribui linhas em pares para cada rasp
 * Retorna array: ['Rasp01' => [linha1, linha2], 'Rasp02' => [linha3, linha4], ...]
 */
function distribuirLinhasEmRasps($linhas) {
    $rasps = [];
    $index = 0;
    
    foreach ($linhas as $linha) {
        $raspNum = floor($index / 2) + 1;
        $raspId = sprintf('Rasp%02d', $raspNum);
        
        if (!isset($rasps[$raspId])) {
            $rasps[$raspId] = [];
        }
        
        $rasps[$raspId][] = $linha;
        $index++;
    }
    
    return $rasps;
}

/**
 * Monta URL do monproc: linha1, perfil1, grandezas1, linha2, perfil2, grandezas2
 * $slot1 e $slot2 = ['linha' => valor, 'drawing_name' => valor]
 */
function montarMonprocUrl($slot1, $slot2 = null) {
    $params = [
        'linha1' => rawurlencode((string)$slot1['linha']),
        'perfil1' => rawurlencode((string)$slot1['drawing_name']),
        'grandezas1' => GRANDEZAS_1
    ];
    
    if ($slot2 !== null && isset($slot2['linha'], $slot2['drawing_name'])) {
        $params['linha2'] = rawurlencode((string)$slot2['linha']);
        $params['perfil2'] = rawurlencode((string)$slot2['drawing_name']);
        $params['grandezas2'] = GRANDEZAS_2;
    }
    
    return MONPROC_BASE_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

/**
 * Obtém a URL do monproc para um rasp específico (por ID)
 * Retorna null se não houver linhas suficientes para aquele rasp
 */
function obterUrlParaRasp($raspId) {
    $pdo = conectarBanco();
    if ($pdo === null) return null;
    
    $linhas = buscarLinhasAtivas($pdo);
    $rasps = distribuirLinhasEmRasps($linhas);
    
    // Converte ID do formato "01" para "Rasp01"
    $raspKey = 'Rasp' . str_pad($raspId, 2, '0', STR_PAD_LEFT);
    
    if (!isset($rasps[$raspKey]) || empty($rasps[$raspKey])) {
        return null;
    }
    
    $linhasDoRasp = $rasps[$raspKey];
    $slot1 = $linhasDoRasp[0];
    $slot2 = $linhasDoRasp[1] ?? null;
    
    if (empty($slot1['drawing_name'])) {
        return null;
    }
    
    return montarMonprocUrl($slot1, $slot2);
}

/**
 * Obtém todas as distribuições de linhas por rasp
 * Retorna array com URLs e informações de cada rasp
 */
function obterDistribuicaoCompleta() {
    $pdo = conectarBanco();
    if ($pdo === null) return [];
    
    $linhas = buscarLinhasAtivas($pdo);
    $rasps = distribuirLinhasEmRasps($linhas);
    
    $resultado = [];
    foreach ($rasps as $raspId => $linhasDoRasp) {
        $slot1 = $linhasDoRasp[0];
        $slot2 = $linhasDoRasp[1] ?? null;
        
        if (!empty($slot1['drawing_name'])) {
            $resultado[$raspId] = [
                'url' => montarMonprocUrl($slot1, $slot2),
                'linhas' => array_column($linhasDoRasp, 'linha'),
                'drawing_names' => array_column($linhasDoRasp, 'drawing_name')
            ];
        }
    }
    
    return $resultado;
}

/**
 * Verifica se um rasp está online (baseado no last_seen)
 */
function raspEstaOnline($last_seen) {
    if (!$last_seen) return false;
    return (time() - $last_seen) < STATUS_TIMEOUT;
}

/**
 * Carrega dados dos rasps do JSON
 */
function carregarRasps() {
    $arquivo = 'rasps.json';
    $dados = read_json_file($arquivo);
    
    if (!isset($dados['rasps']) || !is_array($dados['rasps'])) {
        return [];
    }
    
    return $dados['rasps'];
}

/**
 * Encontra um rasp pelo ID
 */
function encontrarRaspPorId($id) {
    $rasps = carregarRasps();
    
    foreach ($rasps as $rasp) {
        if (($rasp['id'] ?? '') === $id) {
            return $rasp;
        }
    }
    
    return null;
}
