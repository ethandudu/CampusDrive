<?php

namespace CampusDrive\Infrastructure\Security;

final class ClientIpResolver
{
    /** Published at https://www.cloudflare.com/ips */
    public const CLOUDFLARE_RANGES = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    /**
     * @param array<string, mixed> $server
     * @param string[]             $trustedProxies IPs or CIDR ranges allowed to set forwarding headers
     * @param bool                 $behindCloudflare trust CF-Connecting-IP when the peer is Cloudflare (or a trusted proxy)
     */
    public static function resolve(array $server, array $trustedProxies = [], bool $behindCloudflare = false): string
    {
        $remote = (string) ($server['REMOTE_ADDR'] ?? '');
        if (filter_var($remote, FILTER_VALIDATE_IP) === false) {
            return '0.0.0.0';
        }

        if ($behindCloudflare) {
            $cfIp = trim((string) ($server['HTTP_CF_CONNECTING_IP'] ?? ''));
            $peerTrusted = self::isInRanges($remote, array_merge(self::CLOUDFLARE_RANGES, $trustedProxies));
            if ($peerTrusted && filter_var($cfIp, FILTER_VALIDATE_IP) !== false) {
                return $cfIp;
            }
        }

        $forwarded = (string) ($server['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($trustedProxies !== [] && $forwarded !== '' && self::isInRanges($remote, $trustedProxies)) {
            // Walk from the right: the first address that is not a trusted proxy is the client.
            foreach (array_reverse(array_map('trim', explode(',', $forwarded))) as $hop) {
                if (filter_var($hop, FILTER_VALIDATE_IP) === false) {
                    break;
                }
                $remote = $hop;
                if (!self::isInRanges($hop, $trustedProxies)) {
                    break;
                }
            }
        }

        return $remote;
    }

    /**
     * @param string[] $ranges
     */
    public static function isInRanges(string $ip, array $ranges): bool
    {
        $ipBin = @inet_pton($ip);
        if ($ipBin === false) {
            return false;
        }

        foreach ($ranges as $range) {
            [$subnet, $bits] = array_pad(explode('/', (string) $range, 2), 2, null);
            $subnetBin = @inet_pton((string) $subnet);
            if ($subnetBin === false || strlen($subnetBin) !== strlen($ipBin)) {
                continue;
            }

            $maxBits = strlen($ipBin) * 8;
            $bits = $bits === null ? $maxBits : (int) $bits;
            if ($bits < 0 || $bits > $maxBits) {
                continue;
            }

            $bytes = intdiv($bits, 8);
            $rest = $bits % 8;
            if (substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
                continue;
            }
            if ($rest === 0) {
                return true;
            }

            $mask = (0xFF << (8 - $rest)) & 0xFF;
            if ((ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask)) {
                return true;
            }
        }

        return false;
    }
}
