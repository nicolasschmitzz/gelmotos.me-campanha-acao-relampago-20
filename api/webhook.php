<?php
header('Content-Type: text/plain');

require __DIR__ . '/../lib/Storage.php';
require __DIR__ . '/../lib/Utmify.php';

$config  = require __DIR__ . '/config.php';
$storage = new Storage($config['db_path']);

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

// Log de auditoria (útil para MED)
@file_put_contents(
    __DIR__ . '/../data/webhook.log',
    sprintf("[%s] IP=%s PAYLOAD=%s\n", gmdate('c'),
            $_SERVER['REMOTE_ADDR'] ?? '-', $raw),
    FILE_APPEND
);

if (!is_array($data)) {
    http_response_code(400); echo 'invalid payload'; exit;
}

$txId   = $data['transaction_id'] ?? null;
$status = strtoupper($data['status'] ?? '');

if (!$txId) { http_response_code(400); echo 'missing transaction_id'; exit; }

$order = $storage->findByTransactionId($txId);
if (!$order) {
    http_response_code(200); echo 'order not found (ignored)'; exit;
}

$utmify = new Utmify($config['utmify']);
$utms   = json_decode($order['utms'] ?: '[]', true) ?: [];

$base = [
    'order_id'          => $order['order_id'],
    'platform'          => $config['app']['platform_name'],
    'amount_cents'      => (int)$order['amount_cents'],
    'customer_name'     => $order['customer_name'],
    'customer_email'    => $order['customer_email'],
    'customer_phone'    => $order['customer_phone'],
    'customer_document' => $order['customer_document'],
    'customer_ip'       => $order['customer_ip'],
    'product_id'        => $order['product_id'],
    'product_name'      => $order['product_name'],
    'utms'              => $utms,
    'created_at'        => $order['created_at'],
];

try {
    switch ($status) {
        case 'COMPLETED':
            $approvedAtUtc = gmdate('Y-m-d H:i:s');
            $storage->updateStatus($order['order_id'], 'paid', $approvedAtUtc, $data);

            $feeCents = isset($data['fee']) && is_numeric($data['fee'])
                ? (int)round(abs((float)$data['fee']) * 100) : 0;

            $utmify->sendOrder(
                $base + ['gateway_fee_cents' => $feeCents],
                'paid',
                $approvedAtUtc
            );
            break;

        case 'FAILED':
            $storage->updateStatus($order['order_id'], 'refused', null, $data);
            $utmify->sendOrder($base, 'refused');
            break;

        case 'HELD':
            // MED — apenas registra
            $storage->updateStatus($order['order_id'], 'held', null, $data);
            break;

        case 'PENDING':
        default:
            // já enviamos waiting_payment no checkout
            break;
    }
} catch (Throwable $e) {
    error_log('webhook.php: ' . $e->getMessage());
    http_response_code(500); echo 'processing error'; exit;
}

http_response_code(200);
echo 'ok';