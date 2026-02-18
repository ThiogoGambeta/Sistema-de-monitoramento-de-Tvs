<?php
require_once 'json_store.php';

$arquivo = 'rasps.json';
$dados = read_json_file($arquivo);
if (!isset($dados['rasps']) || !is_array($dados['rasps'])) $dados['rasps'] = [];

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? '';
$status_rasp = $input['status_rasp'] ?? false;
$status_tv = $input['status_tv'] ?? false;
$last_seen = $input['last_seen'] ?? time();

$found = false;
foreach($dados['rasps'] as &$r){
    if(($r['id'] ?? '') === $id){
        $r['status_rasp'] = $status_rasp;
        $r['status_tv'] = $status_tv;
        $r['last_seen'] = $last_seen;
        $found = true;
        break;
    }
}

if (write_json_file($arquivo, $dados)) {
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false, 'msg'=>'Falha ao salvar JSON']);
}
?>