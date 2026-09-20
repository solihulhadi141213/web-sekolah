<?php

class RateLimiter
{
    private PDO $Conn;

    public function __construct(PDO $Conn)
    {
        $this->Conn = $Conn;
        $this->Conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /** Fixed window per IP. Gunakan window tetap untuk setiap endpoint. */
    public function check(string $endpoint, int $maxHit = 5, int $window = 60): void
    {
        if ($endpoint === '' || strlen($endpoint) > 255 || $maxHit < 1 || $maxHit > 65534 || $window < 1) {
            throw new InvalidArgumentException('Endpoint, maxHit (1-65534), atau window tidak valid.');
        }
        if ($this->Conn->inTransaction()) {
            throw new LogicException('Jalankan RateLimiter sebelum transaksi aplikasi.');
        }

        $now = time();
        $requestTime = intdiv($now, $window) * $window;
        $params = [':ip' => $this->getClientIP(), ':endpoint' => $endpoint, ':request_time' => $requestTime];

        // Kunci baris sampai pembacaan selesai untuk menghitung request paralel dengan benar.
        $this->Conn->beginTransaction();
        try {
            $stmt = $this->Conn->prepare('
                INSERT INTO rate_limit (ip_address, endpoint, request_time, hit_count)
                VALUES (:ip, :endpoint, :request_time, 1)
                ON DUPLICATE KEY UPDATE hit_count = LEAST(hit_count + 1, 65535)
            ');
            $stmt->execute($params);
            $stmt = $this->Conn->prepare('
                SELECT hit_count FROM rate_limit
                WHERE ip_address = :ip AND endpoint = :endpoint AND request_time = :request_time
                FOR UPDATE
            ');
            $stmt->execute($params);
            $hits = (int) $stmt->fetchColumn();
            $this->Conn->commit();
        } catch (Throwable $error) {
            if ($this->Conn->inTransaction()) {
                $this->Conn->rollBack();
            }
            throw $error;
        }

        // Jangan hapus window aktif milik endpoint lain. Batasi beban pembersihan.
        if (random_int(1, 100) === 1) {
            try {
                $stmt = $this->Conn->prepare('
                    DELETE FROM rate_limit
                    WHERE endpoint = :endpoint AND request_time < :expired LIMIT 1000
                ');
                $stmt->execute([':endpoint' => $endpoint, ':expired' => $now - $window]);
            } catch (PDOException $error) {
                error_log('RateLimiter cleanup failed: ' . $error->getMessage());
            }
        }

        if ($hits > $maxHit) {
            $retryAfter = max(1, $requestTime + $window - time());
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            header('Retry-After: ' . $retryAfter);
            echo json_encode([
                'response' => [
                    'message' => 'Terlalu banyak permintaan. Silakan coba beberapa saat lagi.',
                    'code' => 429,
                ],
                'metadata' => ['retry_after' => $retryAfter],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    private function getClientIP(): string
    {
        // Proxy tepercaya harus dikonfigurasi di server; jangan percaya header dari klien.
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return '0.0.0.0';
        }
        return inet_ntop(inet_pton($ip));
    }
}
