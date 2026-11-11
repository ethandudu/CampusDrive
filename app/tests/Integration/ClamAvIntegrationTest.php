<?php

namespace Tests\Integration;

use CampusDrive\Infrastructure\Security\ClamAvScanner;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ClamAvIntegrationTest extends TestCase
{
    public function testCleanAndEicarFilesAndUnavailableDaemon(): void
    {
        if (getenv('CLAMAV_INTEGRATION') !== '1') {
            $this->markTestSkipped('Set CLAMAV_INTEGRATION=1 with a ready ClamAV service.');
        }

        $file = tempnam(sys_get_temp_dir(), 'clamtest_');
        try {
            $scanner = ClamAvScanner::fromEnvironment();
            file_put_contents($file, "%PDF-1.4\n%%EOF\n");
            self::assertTrue($scanner->isClean($file));
            $eicar = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$' . 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';
            file_put_contents($file, $eicar);
            self::assertFalse($scanner->isClean($file));
            $compressed = gzcompress($eicar);
            $pdf = "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($compressed) . " /Filter /FlateDecode >>\nstream\n"
                . $compressed . "\nendstream\nendobj\n%%EOF\n";
            file_put_contents($file, $pdf);
            self::assertSame('application/pdf', (new \finfo(FILEINFO_MIME_TYPE))->file($file));
            self::assertFalse($scanner->isClean($file));
            $handle = fopen($file, 'wb');
            ftruncate($handle, 10485760);
            fclose($handle);
            self::assertTrue($scanner->isClean($file));
            $this->expectException(RuntimeException::class);
            (new ClamAvScanner('127.0.0.1', 1, 1))->isClean($file);
        } finally {
            unlink($file);
        }
    }
}
