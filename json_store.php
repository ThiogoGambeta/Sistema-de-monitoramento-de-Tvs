<?php
// json_store.php
// Funções seguras para ler e gravar arquivos JSON (backup, lock, escrita atômica)

function read_json_file(string $path) {
    if (!file_exists($path)) {
        return null; // ou ['rasps' => []] se preferir inicializar
    }

    $contents = @file_get_contents($path);
    if ($contents === false) return null;

    $data = json_decode($contents, true);
    if (!is_array($data)) return null;
    return $data;
}
function write_json_file(string $path, array $data): bool {
    // Gera string JSON segura
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        error_log("Erro ao codificar JSON: " . json_last_error_msg());
        return false;
    }

    // Faz backup (silencioso)
    $backupPath = $path . '.bak.' . date('Ymd-His');
    @copy($path, $backupPath);

    // 🔹 Limpa backups antigos (mantém só os 3 mais recentes)
    $backups = glob($path . '.bak.*');
    if (is_array($backups) && count($backups) > 3) {
        // Ordena por data desc (mais recente primeiro)
        rsort($backups);
        // Remove os mais antigos
        foreach (array_slice($backups, 3) as $oldBackup) {
            @unlink($oldBackup);
        }
    }

    // Escrita atômica: escreve em arquivo temporário e renomeia
    $dir = dirname($path);
    $tmp = tempnam($dir, 'tmp_json_');
    if ($tmp === false) {
        error_log("Não foi possível criar arquivo temporário para $path");
        return false;
    }

    $fp = fopen($tmp, 'c');
    if ($fp === false) {
        @unlink($tmp);
        error_log("Falha ao abrir tmp file para escrita: $tmp");
        return false;
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        @unlink($tmp);
        error_log("Falha ao adquirir lock no tmp file: $tmp");
        return false;
    }

    $bytes = fwrite($fp, $json);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    if ($bytes === false) {
        @unlink($tmp);
        error_log("Falha ao escrever no tmp file: $tmp");
        return false;
    }

    if (!rename($tmp, $path)) {
        if (file_put_contents($path, $json, LOCK_EX) === false) {
            @unlink($tmp);
            error_log("Falha ao renomear tmp para $path e falha no fallback");
            return false;
        }
        @unlink($tmp);
    }

    return true;
}
?>