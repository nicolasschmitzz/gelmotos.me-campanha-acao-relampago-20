<?php
class Utmify
{
    private string $baseUrl, $token;

    public function __construct(array $c)
    {
        $this->baseUrl = rtrim($c['base_url'], '/');
        $this->token   = $c['api_token'];
    }

    public function sendOrder(array $o, string $status,
                              ?string $approvedDate = null,
                              ?string $refundedDate = null): array
    {
        $u = $o['utms'] ?? [];

        $payload = [
            'orderId'       => (string)$o['order_id'],
            'platform'      => $o['platform'] ?? 'PagLoop',
            'paymentMethod' => 'pix',
            'status'        => $status,
            'createdAt'     => $o['created_at'],
            'approvedDate'  => $approvedDate,
            'refundedAt'    => $refundedDate,
            'customer'      => [
                'name'     => $o['customer_name']     ?? 'Cliente',
                'email'    => $o['customer_email']    ?? '',
                'phone'    => $o['customer_phone']    ?? null,
                'document' => $o['customer_document'] ?? null,
                'country'  => 'BR',
                'ip'       => $o['customer_ip']       ?? null,
            ],
            'products'      => [[
                'id'           => $o['product_id']   ?? 'prod-1',
                'name'         => $o['product_name'] ?? 'Produto',
                'planId'       => null,
                'planName'     => null,
                'quantity'     => 1,
                'priceInCents' => (int)$o['amount_cents'],
            ]],
            'trackingParameters' => [
                'src'          => $u['src']          ?? null,
                'sck'          => $u['sck']          ?? null,
                'utm_source'   => $u['utm_source']   ?? null,
                'utm_campaign' => $u['utm_campaign'] ?? null,
                'utm_medium'   => $u['utm_medium']   ?? null,
                'utm_content'  => $u['utm_content']  ?? null,
                'utm_term'     => $u['utm_term']     ?? null,
            ],
            'commission'    => [
                'totalPriceInCents'     => (int)$o['amount_cents'],
                'gatewayFeeInCents'     => (int)($o['gateway_fee_cents'] ?? 0),
                'userCommissionInCents' => (int)$o['amount_cents']
                                         - (int)($o['gateway_fee_cents'] ?? 0),
            ],
            'isTest'        => false,
        ];

        return $this->http('/api-credentials/orders', $payload);
    }

    private function http(string $path, array $payload): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-token: ' . $this->token,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 20,
        ]);

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) throw new RuntimeException("cURL Utmify: $err");
        if ($code >= 400)   throw new RuntimeException("Utmify HTTP $code: $raw");

        return json_decode($raw, true) ?? ['raw' => $raw];
    }
}