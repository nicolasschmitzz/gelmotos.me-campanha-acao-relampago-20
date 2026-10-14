<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../lib/Storage.php';
$config  = require __DIR__ . '/config.php';
$storage = new Storage($config['db_path']);

$orderId = $_GET['order_id'] ?? '';
if (!$orderId) {
    http_response_code(400);
    echo json_encode(['error' => 'order_id obrigatório']);
    exit;
}

$order = $storage->findByOrderId($orderId);
if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Pedido não encontrado']);
    exit;
}

$map = [
    'waiting_payment' => 'pending',
    'paid'            => 'paid',
    'refused'         => 'failed',
    'refunded'        => 'refunded',
    'held'            => 'pending',
];

echo json_encode([
    'status' => $map[$order['status']] ?? 'pending',
    'raw'    => $order['status'],
]);