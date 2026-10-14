<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../lib/Storage.php';
require __DIR__ . '/../lib/PagLoop.php';
require __DIR__ . '/../lib/Utmify.php';

$config  = require __DIR__ . '/config.php';
$storage = new Storage($config['db_path']);

function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Método não permitido', 405);
}

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);

if (!is_array($in)) fail('JSON inválido');

$amount = (float)str_replace(',', '.', $in['amount'] ?? '0');

if ($amount <= 0) {
    fail('Informe um valor válido.');
}

// Dados do cliente — vêm do formulário OU dos valores padrão
$name     = trim($in['name']     ?? 'Cliente Padrão');
$email    = trim($in['email']    ?? 'cliente@exemplo.com');
$document = preg_replace('/\D/', '', $in['document'] ?? '11144477735');
$phone    = preg_replace('/\D/', '', $in['phone']    ?? '11999999999');

// Se o segundo HTML mandar um "fixedCustomer", usa ele
if (!empty($in['fixedCustomer']) && is_array($in['fixedCustomer'])) {
    $fc = $in['fixedCustomer'];
    if (!empty($fc['name']))     $name     = trim($fc['name']);
    if (!empty($fc['email']))    $email    = trim($fc['email']);
    if (!empty($fc['document'])) $document = preg_replace('/\D/', '', $fc['document']);
    if (!empty($fc['phone']))    $phone    = preg_replace('/\D/', '', $fc['phone']);
}

// Validação mínima (CPF precisa ter 11 dígitos)
if (strlen($document) !== 11) {
    fail('CPF inválido.');
}

$utms = array_intersect_key($in['utms'] ?? [], array_flip([
    'src','sck','utm_source','utm_campaign','utm_medium','utm_content','utm_term'
]));

$orderId     = 'pedido-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
$createdAtUtc= gmdate('Y-m-d H:i:s');

try {
    $pagloop = new PagLoop($config['pagloop']);

    $deposit = $pagloop->createPixDeposit([
        'amount'            => $amount,
        'external_id'       => $orderId,
        'clientCallbackUrl' => $config['app']['webhook_url'],
        'payer'             => [
            'name'     => $name,
            'email'    => $email,
            'document' => $document,
            'phone'    => $phone ?: null,
        ],
    ]);

    $qr            = $deposit['qrCodeResponse'] ?? [];
    $transactionId = $qr['transactionId'] ?? null;
    $pixCode       = $qr['qrcode']        ?? null;

    if (!$transactionId || !$pixCode) {
        throw new RuntimeException('Resposta inválida da PagLoop: ' . json_encode($deposit));
    }

    // Persiste localmente
    $storage->createOrder([
        'order_id'          => $orderId,
        'transaction_id'    => $transactionId,
        'status'            => 'waiting_payment',
        'amount_cents'      => (int)round($amount * 100),
        'customer_name'     => $name,
        'customer_email'    => $email,
        'customer_phone'    => $phone,
        'customer_document' => $document,
        'customer_ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
        'product_id'        => 'prod-1',
        'product_name'      => 'Produto Digital',
        'utms'              => $utms,
        'created_at'        => $createdAtUtc,
        'raw_deposit'       => $deposit,
    ]);

    // Envia waiting_payment para a Utmify (não bloqueia se falhar)
    try {
        (new Utmify($config['utmify']))->sendOrder([
            'order_id'          => $orderId,
            'platform'          => $config['app']['platform_name'],
            'amount_cents'      => (int)round($amount * 100),
            'customer_name'     => $name,
            'customer_email'    => $email,
            'customer_phone'    => $phone,
            'customer_document' => $document,
            'customer_ip'       => $_SERVER['REMOTE_ADDR'] ?? null,
            'product_id'        => 'prod-1',
            'product_name'      => 'Produto Digital',
            'utms'              => $utms,
            'created_at'        => $createdAtUtc,
        ], 'waiting_payment');
    } catch (Throwable $e) {
        error_log('Utmify waiting_payment: ' . $e->getMessage());
    }

    echo json_encode([
        'success'  => true,
        'orderId'  => $orderId,
        'pixCode'  => $pixCode,
    ]);

} catch (Throwable $e) {
    error_log('checkout.php: ' . $e->getMessage());
    fail('Não foi possível gerar o PIX. Tente novamente.', 500);
}