<?php

final class WebsiteHits
{
    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
        $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
        $hex = bin2hex($bytes);
        return substr($hex,0,8) . '-' . substr($hex,8,4) . '-' . substr($hex,12,4) . '-' . substr($hex,16,4) . '-' . substr($hex,20);
    }

    private static function text($value, int $limit): ?string
    {
        if (!is_string($value) || $value === '') return null;
        // Perbaiki byte UTF-8 tidak valid dari header tanpa menggagalkan INSERT.
        $value = json_decode(json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE), true);
        preg_match('/\A.{0,' . $limit . '}/us', $value, $match);
        return $match[0] ?? null;
    }

    public static function record(PDO $pdo, array $server, array $cookies, string $path, string $title, string $cookiePath): void
    {
        $ids = [];
        foreach (['school_visitor'=>31536000, 'school_visit_session'=>1800] as $name=>$lifetime) {
            $value = $cookies[$name] ?? null;
            $ids[$name] = is_string($value) && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $value)
                ? strtolower($value) : self::uuid();
        }
        $ip = $server['REMOTE_ADDR'] ?? '';
        $binaryIp = is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) ? inet_pton($ip) : null;
        $agent = self::text($server['HTTP_USER_AGENT'] ?? null, 16000);
        $bot = $agent !== null && preg_match('/bot|crawler|spider|slurp|bingpreview|headless|facebookexternalhit|curl|wget/i', $agent) ? 1 : 0;
        $stmt = $pdo->prepare('INSERT INTO website_hits
            (visitor_id, session_id, page_path, page_title, referrer_url, ip_address, user_agent, is_bot, visited_at)
            VALUES (:visitor, :session, :path, :title, :referrer, :ip, :agent, :bot, UTC_TIMESTAMP())');
        $stmt->execute([
            'visitor'=>$ids['school_visitor'], 'session'=>$ids['school_visit_session'],
            'path'=>self::text($path,2048), 'title'=>self::text($title,255),
            'referrer'=>self::text($server['HTTP_REFERER'] ?? null,2048),
            'ip'=>$binaryIp, 'agent'=>$agent, 'bot'=>$bot,
        ]);
        foreach (['school_visitor'=>31536000, 'school_visit_session'=>1800] as $name=>$lifetime) {
            setcookie($name, $ids[$name], [
                'expires'=>time()+$lifetime, 'path'=>$cookiePath,
                'secure'=>isset($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off' && $server['HTTPS'] !== '',
                'httponly'=>true, 'samesite'=>'Lax',
            ]);
        }
    }
}
