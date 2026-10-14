<?php
class PagLoop
{
    private string $baseUrl, $clientId, $clientSecret, $cacheFile;

    public function __construct(array $c)
    {
        $this->baseUrl      = rtrim($c['base_url'], '/');
        $this->clientId     = $c['client_id'];
        $this->clientSecret = $c['client_secret'];
        $this->cacheFile    = sys_get_temp_dir() . '/pagloop_' . md5($this->clientId) . '.json';
    }

    public function getToken(): string
    {
        if (is_file($this->cacheFile)) {
            $c = json_decode(file_get_contents($this->cacheFile), true);
            if ($c && ($c['expires_at'] ?? 0) > time() + 60) return $c['token'];
        }

        $res = $this->http('POST', '/api/auth/login', [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
        ], null, false);

        if (empty($res['token'])) {
            throw new RuntimeException('Auth PagLoop falhou: ' . json_encode($res));
        }

        file_put_contents($this->cacheFile, json_encode([
            'token'      => $res['token'],
            'expires_at' => time() + 3500,
        ]));

        return $res['token'];
    }

    public function createPixDeposit(array $payload): array
    {
        return $this->http('POST', '/api/payments/deposit',
                           $payload, $this->getToken(), true);
    }

    public function getStatus(string $txId): array
    {
        return $this->http('GET',
            '/api/transactions/getStatusTransac/' . urlencode($txId),
            null, $this->getToken(), true);
    }

    private function http(string $m, string $path, ?array $body,
                          ?string $token, bool $auth): array
    {
        $ch = curl_init($this->baseUrl . $path);
        $headers = ['Content-Type: application/json'];
        if ($auth && $token) $headers[] = 'Authorization: Bearer ' . $token;

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $m,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

        $raw  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($raw === false) throw new RuntimeException("cURL PagLoop: $err");
        if ($code >= 400)   throw new RuntimeException("PagLoop HTTP $code: $raw");

        return json_decode($raw, true) ?? [];
    }
}