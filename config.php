<?php
/**
 * Configurações do Sistema de Monitoramento
 * 
 * IMPORTANTE: Configure as credenciais do banco de dados PostgreSQL abaixo
 */

// Configurações do banco de dados PostgreSQL
$db_config = [
    'host' => '',        // Altere para o host do seu banco
    'dbname' => '',     // Altere para o nome do banco
    'user' => '',      // Altere para o usuário
    'password' => '',    // Altere para a senha
    'port' => 5432,                // Porta padrão PostgreSQL
    'schema' => 'public'           // Schema da tabela uni_mcaliper002 (geralmente 'public')
];

// Timeout para considerar rasp/TV offline (em segundos)
define('STATUS_TIMEOUT', 30);

// URL base da aplicação (servidor local via ngrok)
define('APP_BASE_URL', '');

// URL base do sistema monproc
define('MONPROC_BASE_URL', '');

// Grandezas por slot (formato da URL monproc)
define('GRANDEZAS_1', 'm_min,gr_m,kg_h,temp,ur');
define('GRANDEZAS_2', 'm_min,kg_h,gr_m,temp,ur');
