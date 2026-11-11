<?php

namespace Tests\Integration;

use CampusDrive\Infrastructure\Storage\UploadStorage;
use PDO;
use PHPUnit\Framework\TestCase;

final class UploadHttpTest extends TestCase
{
    private PDO $db;
    private string $userId;
    private string $promotionId;
    private string $email;
    private string $cookie = '';
    private string $csrf;

    protected function setUp(): void
    {
        if (!getenv('UPLOAD_TEST_BASE_URL') || !getenv('UPLOAD_TEST_DB_HOST')) {
            $this->markTestSkipped('Upload HTTP test server and isolated database are not configured.');
        }
        $this->db = new PDO(
            'mysql:host=' . getenv('UPLOAD_TEST_DB_HOST') . ';dbname=' . getenv('UPLOAD_TEST_DB_NAME') . ';charset=utf8mb4',
            getenv('UPLOAD_TEST_DB_USER'),
            getenv('UPLOAD_TEST_DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $this->promotionId = $this->db->query('SELECT UUID()')->fetchColumn();
        $this->userId = $this->db->query('SELECT UUID()')->fetchColumn();
        $this->email = 'upload_' . bin2hex(random_bytes(6)) . '@example.com';
        $this->db->prepare("INSERT INTO promotions (id, name, status) VALUES (?, 'Upload test', 'active')")->execute([$this->promotionId]);
        $this->db->prepare("INSERT INTO users (id, email, password, role, language, promotion_id) VALUES (?, ?, ?, 'student', 'en', ?)")
            ->execute([$this->userId, $this->email, password_hash('Password123!', PASSWORD_DEFAULT), $this->promotionId]);
    }

    protected function tearDown(): void
    {
        if (!isset($this->db, $this->promotionId, $this->userId)) {
            return;
        }
        $stmt = $this->db->prepare('SELECT file_path FROM files WHERE user_id = ?');
        $stmt->execute([$this->userId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
            (new UploadStorage())->delete($name);
        }
        $this->db->prepare('DELETE FROM promotions WHERE id = ?')->execute([$this->promotionId]);
        $this->db->prepare('DELETE FROM users WHERE id = ?')->execute([$this->userId]);
    }

    public function testCleanInfectedAndInvalidUploads(): void
    {
        $url = getenv('UPLOAD_TEST_BASE_URL');
        $this->login($url);

        $response = $this->upload($url, "%PDF-1.4\n%%EOF\n");
        self::assertStringContainsString('File uploaded successfully!', $response);
        self::assertSame(1, $this->fileCount());
        $stmt = $this->db->prepare('SELECT file_path, status FROM files WHERE user_id = ?');
        $stmt->execute([$this->userId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        self::assertSame('pending', $record['status']);
        self::assertNotNull((new UploadStorage())->find($record['file_path']));
        self::assertCount(1, glob((new UploadStorage())->directory() . DIRECTORY_SEPARATOR . '*'));

        $eicar = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$' . 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';
        $compressed = gzcompress($eicar);
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($compressed) . " /Filter /FlateDecode >>\nstream\n"
            . $compressed . "\nendstream\nendobj\n%%EOF\n";
        $response = $this->upload($url, $pdf);
        self::assertStringContainsString('This file was rejected by the security scan.', $response);
        self::assertSame(1, $this->fileCount());
        self::assertCount(1, glob((new UploadStorage())->directory() . DIRECTORY_SEPARATOR . '*'));

        $response = $this->upload($url, 'not an allowed file type');
        self::assertStringContainsString('Unsupported format.', $response);
        self::assertSame(1, $this->fileCount());
        self::assertCount(1, glob((new UploadStorage())->directory() . DIRECTORY_SEPARATOR . '*'));

        $this->csrf = 'invalid';
        $response = $this->upload($url, "%PDF-1.4\n%%EOF\n");
        self::assertStringContainsString('Security error', $response);
        self::assertSame(1, $this->fileCount());
    }

    public function testScannerOutageRejectsUpload(): void
    {
        $url = getenv('UPLOAD_TEST_OUTAGE_URL');
        if (!$url) {
            $this->markTestSkipped('UPLOAD_TEST_OUTAGE_URL is not configured.');
        }
        $this->db->prepare("UPDATE users SET language = 'fr' WHERE id = ?")->execute([$this->userId]);
        $this->login($url);
        $response = $this->upload($url, "%PDF-1.4\n%%EOF\n");
        self::assertStringContainsString('L’analyse de sécurité est indisponible.', $response);
        self::assertSame(0, $this->fileCount());
        self::assertSame([], glob((new UploadStorage())->directory() . DIRECTORY_SEPARATOR . '*'));
    }

    private function login(string $url): void
    {
        $page = $this->request($url . '/login.php');
        self::assertMatchesRegularExpression('/name="csrf_token" value="([^"]+)"/', $page);
        preg_match('/name="csrf_token" value="([^"]+)"/', $page, $matches);
        $this->request($url . '/login.php', http_build_query([
            'csrf_token' => $matches[1], 'email' => $this->email, 'password' => 'Password123!', 'action' => 'login',
        ]), 'application/x-www-form-urlencoded');
        $page = $this->request($url . '/student.php');
        self::assertMatchesRegularExpression('/name="csrf_token" value="([^"]+)"/', $page);
        preg_match('/name="csrf_token" value="([^"]+)"/', $page, $matches);
        $this->csrf = $matches[1];
    }

    private function upload(string $url, string $contents): string
    {
        $boundary = 'test' . bin2hex(random_bytes(12));
        $body = "--$boundary\r\nContent-Disposition: form-data; name=\"csrf_token\"\r\n\r\n{$this->csrf}\r\n"
            . "--$boundary\r\nContent-Disposition: form-data; name=\"file_upload\"; filename=\"test.pdf\"\r\n"
            . "Content-Type: application/pdf\r\n\r\n$contents\r\n--$boundary--\r\n";
        return $this->request($url . '/student.php', $body, 'multipart/form-data; boundary=' . $boundary);
    }

    private function request(string $url, ?string $body = null, ?string $contentType = null): string
    {
        $headers = ['Cookie: ' . $this->cookie];
        if ($contentType !== null) {
            $headers[] = 'Content-Type: ' . $contentType;
        }
        $context = stream_context_create(['http' => [
            'method' => $body === null ? 'GET' : 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $body ?? '',
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 60,
        ]]);
        $response = file_get_contents($url, false, $context);
        self::assertNotFalse($response);
        foreach (http_get_last_response_headers() ?: [] as $header) {
            if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $header, $match)) {
                $this->cookie = $match[1];
            }
        }
        return $response;
    }

    private function fileCount(): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM files WHERE user_id = ?');
        $stmt->execute([$this->userId]);
        return (int) $stmt->fetchColumn();
    }
}
