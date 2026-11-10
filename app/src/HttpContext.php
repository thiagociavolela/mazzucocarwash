<?php
declare(strict_types=1);
namespace Mazzuco;

final class HttpContext
{
    public static function clientIp(array $server, string $trustedProxies): string
    {
        $remote = (string)($server['REMOTE_ADDR'] ?? '');
        $peers = array_filter(array_map('trim', explode(',', $trustedProxies)));
        $forwarded = (string)($server['HTTP_X_REAL_IP'] ?? '');
        return in_array($remote, $peers, true) && filter_var($forwarded, FILTER_VALIDATE_IP) ? $forwarded : $remote;
    }
    public static function isHttps(array $server, string $trustedProxies): bool
    {
        if (!empty($server['HTTPS']) && strtolower((string)$server['HTTPS']) !== 'off') return true;
        $peers = array_filter(array_map('trim', explode(',', $trustedProxies)));
        // Trust only exact peer IPs explicitly configured by the operator.
        if (!in_array($server['REMOTE_ADDR'] ?? '', $peers, true)) return false;
        return strtolower(trim((string)($server['HTTP_X_FORWARDED_PROTO'] ?? ''))) === 'https';
    }
}
