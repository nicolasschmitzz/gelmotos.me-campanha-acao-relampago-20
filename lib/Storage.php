<?php
class Storage
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->migrate();
    }

    private function migrate(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id TEXT UNIQUE NOT NULL,
                transaction_id TEXT,
                status TEXT NOT NULL,
                amount_cents INTEGER NOT NULL,
                customer_name TEXT,
                customer_email TEXT,
                customer_phone TEXT,
                customer_document TEXT,
                customer_ip TEXT,
                product_id TEXT,
                product_name TEXT,
                utms TEXT,
                created_at TEXT NOT NULL,
                approved_at TEXT,
                raw_deposit TEXT,
                raw_webhook TEXT
            );
        ");
    }

    public function createOrder(array $d): void
    {
        $sql = "INSERT INTO orders
            (order_id, transaction_id, status, amount_cents, customer_name, customer_email,
             customer_phone, customer_document, customer_ip, product_id, product_name,
             utms, created_at, raw_deposit)
            VALUES (:order_id, :transaction_id, :status, :amount_cents, :customer_name,
             :customer_email, :customer_phone, :customer_document, :customer_ip,
             :product_id, :product_name, :utms, :created_at, :raw_deposit)";

        $this->pdo->prepare($sql)->execute([
            ':order_id'          => $d['order_id'],
            ':transaction_id'    => $d['transaction_id']    ?? null,
            ':status'            => $d['status']            ?? 'waiting_payment',
            ':amount_cents'      => $d['amount_cents'],
            ':customer_name'     => $d['customer_name']     ?? null,
            ':customer_email'    => $d['customer_email']    ?? null,
            ':customer_phone'    => $d['customer_phone']    ?? null,
            ':customer_document' => $d['customer_document'] ?? null,
            ':customer_ip'       => $d['customer_ip']       ?? null,
            ':product_id'        => $d['product_id']        ?? null,
            ':product_name'      => $d['product_name']      ?? null,
            ':utms'              => json_encode($d['utms']  ?? []),
            ':created_at'        => $d['created_at'],
            ':raw_deposit'       => json_encode($d['raw_deposit'] ?? []),
        ]);
    }

    public function findByOrderId(string $orderId): ?array
    {
        $s = $this->pdo->prepare("SELECT * FROM orders WHERE order_id = ?");
        $s->execute([$orderId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByTransactionId(string $txId): ?array
    {
        $s = $this->pdo->prepare("SELECT * FROM orders WHERE transaction_id = ?");
        $s->execute([$txId]);
        return $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function updateStatus(string $orderId, string $status,
                                 ?string $approvedAt, array $raw = []): void
    {
        $s = $this->pdo->prepare("
            UPDATE orders
               SET status = :status,
                   approved_at = COALESCE(:approved_at, approved_at),
                   raw_webhook = :raw
             WHERE order_id = :order_id
        ");
        $s->execute([
            ':status'      => $status,
            ':approved_at' => $approvedAt,
            ':raw'         => json_encode($raw),
            ':order_id'    => $orderId,
        ]);
    }
}