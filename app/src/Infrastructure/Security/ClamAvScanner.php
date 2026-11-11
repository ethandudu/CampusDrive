<?php

namespace CampusDrive\Infrastructure\Security;

use Closure;
use RuntimeException;

final class ClamAvScanner implements UploadScanner
{
    private const MAX_BYTES = 10485760;
    private const CHUNK_BYTES = 65536;
    private const MAX_REPLY_BYTES = 4096;

    /** @param null|Closure(string, float): resource $connect */
    public function __construct(
        private readonly string $host = 'clamav',
        private readonly int $port = 3310,
        private readonly float $timeout = 45,
        private readonly ?Closure $connect = null,
    ) {
    }

    public static function fromEnvironment(): self
    {
        $host = getenv('CLAMAV_HOST');
        $port = getenv('CLAMAV_PORT');
        $timeout = getenv('CLAMAV_TIMEOUT');
        if (($port !== false && (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535))
            || ($timeout !== false && (!ctype_digit($timeout) || (int) $timeout < 1 || (int) $timeout > 300))) {
            throw new RuntimeException('Invalid ClamAV connection settings.');
        }

        return new self($host === false ? 'clamav' : $host, $port === false ? 3310 : (int) $port, $timeout === false ? 45 : (float) $timeout);
    }

    public function isClean(string $path): bool
    {
        if (!preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9.-]*\z/D', $this->host)
            || $this->port < 1 || $this->port > 65535 || $this->timeout <= 0 || $this->timeout > 300) {
            throw new RuntimeException('Invalid ClamAV connection settings.');
        }

        $file = @fopen($path, 'rb');
        if ($file === false) {
            throw new RuntimeException('Unable to open temporary upload for scanning.');
        }

        $socket = false;
        try {
            $stat = fstat($file);
            if ($stat === false || $stat['size'] > self::MAX_BYTES) {
                throw new RuntimeException('Upload exceeds scanner input limit or cannot be inspected.');
            }
            $deadline = microtime(true) + $this->timeout;
            $address = 'tcp://' . $this->host . ':' . $this->port;
            if ($this->connect !== null) {
                $socket = ($this->connect)($address, $this->timeout);
            } else {
                $socket = @stream_socket_client($address, $errorCode, $errorMessage, $this->timeout);
            }
            if (!is_resource($socket)) {
                throw new RuntimeException('Unable to connect to ClamAV.');
            }

            $this->writeAll($socket, "zINSTREAM\0", $deadline);
            $total = 0;
            while (!feof($file)) {
                $chunk = fread($file, self::CHUNK_BYTES);
                if ($chunk === false || ($chunk === '' && !feof($file))) {
                    throw new RuntimeException('Unable to read temporary upload.');
                }
                if ($chunk === '') {
                    break;
                }
                $length = strlen($chunk);
                $total += $length;
                if ($total > self::MAX_BYTES) {
                    throw new RuntimeException('Upload exceeds scanner input limit.');
                }
                $this->writeAll($socket, pack('N', $length) . $chunk, $deadline);
            }
            $this->writeAll($socket, pack('N', 0), $deadline);

            $reply = '';
            while (strlen($reply) < self::MAX_REPLY_BYTES) {
                $this->setTimeout($socket, $deadline);
                $byte = @fread($socket, 1);
                if ($byte === false || $byte === '') {
                    throw new RuntimeException('ClamAV response was incomplete or timed out.');
                }
                if ($byte === "\0") {
                    if ($reply === 'stream: OK') {
                        return true;
                    }
                    if (preg_match('/\Astream: [^\x00-\x1f\x7f]+ FOUND\z/D', $reply)) {
                        return false;
                    }
                    throw new RuntimeException('ClamAV returned an error or invalid response.');
                }
                $reply .= $byte;
            }
            throw new RuntimeException('ClamAV response exceeded the size limit.');
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
            fclose($file);
        }
    }

    /** @param resource $socket */
    private function writeAll($socket, string $data, float $deadline): void
    {
        $offset = 0;
        while ($offset < strlen($data)) {
            $this->setTimeout($socket, $deadline);
            $written = @fwrite($socket, substr($data, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Unable to send upload to ClamAV.');
            }
            $offset += $written;
        }
    }

    /** @param resource $socket */
    private function setTimeout($socket, float $deadline): void
    {
        $remaining = $deadline - microtime(true);
        if ($remaining <= 0) {
            throw new RuntimeException('ClamAV scan timed out.');
        }
        $seconds = (int) $remaining;
        stream_set_timeout($socket, $seconds, (int) (($remaining - $seconds) * 1000000));
    }
}
