<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/plain; charset=utf-8');

echo "=== Teste de dependências ===\n\n";

// 1. Extensões
echo "curl:        " . (extension_loaded('curl')        ? 'OK' : 'FALTANDO') . "\n";
echo "pdo_sqlite:  " . (extension_loaded('pdo_sqlite')  ? 'OK' : 'FALTANDO') . "\n";
echo "sqlite3:     " . (extension_loaded('sqlite3')     ? 'OK' : 'FALTANDO') . "\n\n";

// 2. Arquivos esperados
$files = [
    __DIR__ . '/config.php',
    __DIR__ . '/checkout.php',
    __DIR__ . '/../lib/Storage.php',
    __DIR__ . '/../lib/PagLoop.php',
    __DIR__ . '/../lib/Utmify.php',
];
foreach ($files as $f) {
    echo (is_file($f) ? 'OK   ' : 'FALTA ') . $f . "\n";
}
echo "\n";

// 3. Config
try {
    $config = require __DIR__ . '/config.php';
    echo "config.php carregado.\n";
    echo "  pagloop.client_id:     " . substr($config['pagloop']['client_id'], 0, 6) . "****\n";
    echo "  pagloop.client_secret: " . (empty($config['pagloop']['client_secret']) ? 'VAZIO!' : substr($config['pagloop']['client_secret'], 0, 4) . '****') . "\n";
    echo "  utmify.api_token:      " . (empty($config['utmify']['api_token'])       ? 'VAZIO!' : substr($config['utmify']['api_token'], 0, 6) . '****') . "\n";
    echo "  app.webhook_url:       " . $config['app']['webhook_url'] . "\n";
    echo "  db_path:               " . $config['db_path'] . "\n\n";

    // 4. Testa criar o SQLite
    $dir = dirname($config['db_path']);
    if (!is_dir($dir)) {
        echo "Pasta data/ não existe, tentando criar...\n";
        mkdir($dir, 0775, true);
    }
    echo "Pasta data/ gravável: " . (is_writable($dir) ? 'SIM' : 'NÃO') . "\n";

    // 5. Testa Storage
    require __DIR__ . '/../lib/Storage.php';
    $storage = new Storage($config['db_path']);
    echo "Storage: OK (SQLite criado em {$config['db_path']})\n\n";

    // 6. Testa auth PagLoop
    require __DIR__ . '/../lib/PagLoop.php';
    $pl = new PagLoop($config['pagloop']);
    echo "Autenticando na PagLoop...\n";
    $token = $pl->getToken();
    echo "Token PagLoop: " . substr($token, 0, 20) . "***\n\n";

    echo "TUDO OK ✅\n";
} catch (Throwable $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . " linha " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}