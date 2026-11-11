<?php

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

final class AdminPagesTest extends TestCase
{
    private PDO $db;
    private string $baseUrl;
    private array $userIds = [];
    private ?string $promotionId = null;
    private ?string $otherPromotionId = null;
    private string $cookie = '';

    protected function setUp(): void
    {
        $this->baseUrl = getenv('ADMIN_TEST_BASE_URL') ?: '';
        if ($this->baseUrl === '') {
            $this->markTestSkipped('ADMIN_TEST_BASE_URL is not configured for HTTP integration tests.');
        }
        $this->db = new PDO(
            sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('ADMIN_TEST_DB_HOST'), getenv('ADMIN_TEST_DB_NAME')),
            getenv('ADMIN_TEST_DB_USER'),
            getenv('ADMIN_TEST_DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }

    protected function tearDown(): void
    {
        if (!isset($this->db)) {
            return;
        }
        if ($this->promotionId !== null) {
            $stmt = $this->db->prepare('DELETE FROM promotions WHERE id = ?');
            $stmt->execute([$this->promotionId]);
        }
        if ($this->otherPromotionId !== null) {
            $stmt = $this->db->prepare('DELETE FROM promotions WHERE id = ?');
            $stmt->execute([$this->otherPromotionId]);
        }
        foreach ($this->userIds as $id) {
            $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$id]);
        }
    }

    private function request(string $path, ?array $post = null): array
    {
        $headers = ['Cookie: ' . $this->cookie];
        if ($post !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        $context = stream_context_create(['http' => [
            'method' => $post === null ? 'GET' : 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $post === null ? '' : http_build_query($post),
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => 10,
        ]]);
        $body = file_get_contents($this->baseUrl . '/' . $path, false, $context);
        $this->assertNotFalse($body);
        $responseHeaders = http_get_last_response_headers();
        $this->assertNotNull($responseHeaders);
        foreach ($responseHeaders as $header) {
            if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $header, $match)) {
                $this->cookie = $match[1];
            }
        }
        return [$responseHeaders, $body];
    }

    private function createUser(string $role): array
    {
        $id = $this->db->query('SELECT UUID()')->fetchColumn();
        $email = 'http_' . bin2hex(random_bytes(6)) . '@example.com';
        $stmt = $this->db->prepare('INSERT INTO users (id, email, password, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$id, $email, password_hash('Password123!', PASSWORD_DEFAULT), $role]);
        $this->userIds[] = $id;
        return [$id, $email];
    }

    private function login(string $email): void
    {
        [, $login] = $this->request('login.php');
        $this->assertMatchesRegularExpression('/name="csrf_token" value="([^"]+)"/', $login);
        preg_match('/name="csrf_token" value="([^"]+)"/', $login, $match);
        [$headers] = $this->request('login.php', [
            'csrf_token' => $match[1], 'email' => $email, 'password' => 'Password123!', 'action' => 'login',
        ]);
        $this->assertStringContainsString('302', $headers[0]);
    }

    public function testAuthorizationCsrfAndManagementActions(): void
    {
        [$adminId, $adminEmail] = $this->createUser('admin');
        [$studentId, $studentEmail] = $this->createUser('student');
        [$otherId, $otherEmail] = $this->createUser('student');
        $this->promotionId = $this->db->query('SELECT UUID()')->fetchColumn();
        $stmt = $this->db->prepare("INSERT INTO promotions (id, name, status) VALUES (?, 'HTTP test', 'active')");
        $stmt->execute([$this->promotionId]);
        $stmt = $this->db->prepare('UPDATE users SET promotion_id = ? WHERE id = ?');
        $stmt->execute([$this->promotionId, $studentId]);
        $this->otherPromotionId = $this->db->query('SELECT UUID()')->fetchColumn();
        $stmt = $this->db->prepare("INSERT INTO promotions (id, name, status) VALUES (?, 'HTTP test extended', 'active')");
        $stmt->execute([$this->otherPromotionId]);
        $stmt = $this->db->prepare('UPDATE users SET promotion_id = ? WHERE id = ?');
        $stmt->execute([$this->otherPromotionId, $otherId]);

        foreach (['admin_users.php', 'admin_promotions.php'] as $page) {
            [$headers] = $this->request($page);
            $this->assertContains('Location: login.php', $headers);
        }
        $this->login($studentEmail);
        foreach (['admin_users.php', 'admin_promotions.php'] as $page) {
            [$headers] = $this->request($page, ['action' => 'delete', 'user_id' => $adminId, 'promotion_id' => $this->promotionId]);
            $this->assertContains('Location: login.php', $headers);
        }
        $this->cookie = '';
        $this->login($adminEmail);
        foreach (['admin_users.php', 'admin_promotions.php'] as $page) {
            [$headers] = $this->request($page, ['action' => 'delete', 'user_id' => $studentId, 'promotion_id' => $this->promotionId]);
            $this->assertStringContainsString('400', $headers[0]);
        }
        [, $page] = $this->request('admin_users.php');
        $this->assertStringContainsString($studentEmail, $page);
        $this->assertStringContainsString('<table', $page);
        $this->assertStringContainsString('name="q"', $page);
        $this->assertStringContainsString('bootstrap.bundle.min.js', $page);
        foreach (['email', 'promotion', 'mail', 'reset', 'delete'] as $action) {
            $this->assertStringContainsString('data-bs-target="#' . $action . '-modal-' . $studentId . '"', $page);
            $this->assertStringContainsString('id="' . $action . '-modal-' . $studentId . '"', $page);
        }
        $this->assertStringNotContainsString('id="delete-modal-' . $adminId . '"', $page);
        $this->assertStringContainsString('href="mailto:' . rawurlencode($studentEmail) . '"', $page);
        [, $filtered] = $this->request('admin_users.php?q=' . urlencode(strtoupper($studentEmail)));
        $this->assertStringContainsString($studentEmail, $filtered);
        $this->assertStringNotContainsString($adminEmail, $filtered);
        $this->assertStringContainsString('action="admin_users.php?q=' . urlencode(strtoupper($studentEmail)) . '"', $filtered);
        [, $filtered] = $this->request('admin_users.php?q=' . urlencode('http TEST'));
        $this->assertStringContainsString($studentEmail, $filtered);
        $this->assertStringNotContainsString($adminEmail, $filtered);
        [, $filtered] = $this->request('admin_users.php?q=' . urlencode('ÉTUDIANT'));
        $this->assertStringContainsString($studentEmail, $filtered);
        $this->assertStringNotContainsString($adminEmail, $filtered);
        [, $filtered] = $this->request('admin_users.php?q=' . urlencode('<no such user>'));
        $this->assertStringContainsString('Aucun résultat', $filtered);
        $this->assertStringContainsString('&lt;no such user&gt;', $filtered);
        $this->assertStringNotContainsString($studentEmail, $filtered);
        [, $promotionPage] = $this->request('admin_promotions.php?q=HTTP+test');
        $this->assertStringContainsString('<table', $promotionPage);
        $this->assertStringContainsString('name="q"', $promotionPage);
        $this->assertStringContainsString('href="admin_users.php?promotion=' . $this->promotionId . '"', $promotionPage);
        foreach (['rename', 'ambassador', 'delete'] as $action) {
            $this->assertStringContainsString('data-bs-target="#' . $action . '-modal-' . $this->promotionId . '"', $promotionPage);
            $this->assertStringContainsString('id="' . $action . '-modal-' . $this->promotionId . '"', $promotionPage);
        }
        [, $members] = $this->request('admin_users.php?promotion=' . $this->promotionId);
        $this->assertStringContainsString($studentEmail, $members);
        $this->assertStringNotContainsString($otherEmail, $members);
        $this->assertStringContainsString('name="promotion" value="' . $this->promotionId . '"', $members);
        [, $members] = $this->request('admin_users.php?promotion=' . $this->promotionId . '&q=' . urlencode(strtoupper($studentEmail)));
        $this->assertStringContainsString($studentEmail, $members);
        $this->assertStringNotContainsString($otherEmail, $members);
        [, $members] = $this->request('admin_users.php?promotion=not-found');
        $this->assertStringContainsString('Utilisateur ou promotion introuvable.', $members);
        $this->assertStringNotContainsString($studentEmail, $members);
        preg_match('/name="csrf_token" value="([^"]+)"/', $page, $match);
        $this->assertNotEmpty($match[1] ?? null);
        $csrf = $match[1];
        [$headers] = $this->request('admin_users.php?q=HTTP+test', ['csrf_token' => $csrf, 'action' => 'email', 'user_id' => $studentId, 'email' => 'changed_' . $studentEmail]);
        $this->assertContains('Location: admin_users.php?success=admin_updated&q=HTTP+test', $headers);
        [$headers] = $this->request('admin_users.php?promotion=' . $this->promotionId . '&q=HTTP+test', ['csrf_token' => $csrf, 'action' => 'promotion', 'user_id' => $studentId, 'promotion_id' => $this->promotionId]);
        $this->assertContains('Location: admin_users.php?success=admin_updated&q=HTTP+test&promotion=' . $this->promotionId, $headers);
        [, $body] = $this->request('admin_users.php', ['csrf_token' => $csrf, 'action' => 'delete', 'user_id' => $adminId]);
        $this->assertStringContainsString('Vous ne pouvez pas supprimer votre propre compte ici.', $body);
        [, $body] = $this->request('admin_users.php', ['csrf_token' => $csrf, 'action' => 'reset', 'user_id' => $studentId]);
        $this->assertStringContainsString('Impossible d’envoyer le message.', $body);
        [$headers] = $this->request('admin_promotions.php?q=HTTP+test', ['csrf_token' => $csrf, 'action' => 'rename', 'promotion_id' => $this->promotionId, 'name' => 'HTTP renamed']);
        $this->assertContains('Location: admin_promotions.php?success=admin_updated&q=HTTP+test', $headers);
        $stmt = $this->db->prepare('SELECT name FROM promotions WHERE id = ?');
        $stmt->execute([$this->promotionId]);
        $this->assertSame('HTTP renamed', $stmt->fetchColumn());
        [$headers] = $this->request('admin_promotions.php', ['csrf_token' => $csrf, 'action' => 'delete', 'promotion_id' => $this->promotionId]);
        $this->assertContains('Location: admin_promotions.php?success=admin_promotion_deleted', $headers);
        $stmt->execute([$this->promotionId]);
        $this->assertFalse($stmt->fetchColumn());
    }

    public function testGlobalAnnouncementSettingsAndStudentDisplay(): void
    {
        [, $adminEmail] = $this->createUser('admin');
        [$studentId, $studentEmail] = $this->createUser('student');
        $this->promotionId = $this->db->query('SELECT UUID()')->fetchColumn();
        $stmt = $this->db->prepare("INSERT INTO promotions (id, name, status) VALUES (?, 'Announcement test', 'active')");
        $stmt->execute([$this->promotionId]);
        $stmt = $this->db->prepare('UPDATE users SET promotion_id = ? WHERE id = ?');
        $stmt->execute([$this->promotionId, $studentId]);
        $savedSettings = $this->db->query("SELECT setting_key, value FROM settings WHERE setting_key IN ('announcement_text', 'announcement_enabled')")->fetchAll(PDO::FETCH_KEY_PAIR);

        try {
            [$headers] = $this->request('admin_settings.php');
            $this->assertContains('Location: login.php', $headers);
            $this->login($studentEmail);
            [$headers] = $this->request('admin_settings.php', ['announcement_text' => 'unauthorized', 'announcement_enabled' => '1']);
            $this->assertContains('Location: login.php', $headers);
            $this->cookie = '';
            $this->login($adminEmail);
            [$headers] = $this->request('admin_settings.php', ['announcement_text' => 'invalid', 'announcement_enabled' => '1']);
            $this->assertStringContainsString('400', $headers[0]);
            [, $settingsPage] = $this->request('admin_settings.php');
            preg_match('/name="csrf_token" value="([^"]+)"/', $settingsPage, $match);
            $this->assertNotEmpty($match[1] ?? null);
            $csrf = $match[1];
            $announcement = '<script>alert("test")</script> & hello';
            [$headers] = $this->request('admin_settings.php', ['csrf_token' => $csrf, 'announcement_text' => $announcement, 'announcement_enabled' => '1']);
            $this->assertContains('Location: admin_settings.php?success=1', $headers);
            [, $settingsPage] = $this->request('admin_settings.php');
            $this->assertStringContainsString('&lt;script&gt;alert(&quot;test&quot;)&lt;/script&gt; &amp; hello', $settingsPage);
            $this->assertMatchesRegularExpression('/id="announcement-enabled"[^>]*checked/', $settingsPage);
            $this->cookie = '';
            $this->login($studentEmail);
            [, $studentPage] = $this->request('student.php');
            $this->assertStringContainsString('&lt;script&gt;alert(&quot;test&quot;)&lt;/script&gt; &amp; hello', $studentPage);
            $this->assertStringNotContainsString('<script>alert("test")</script>', $studentPage);
            $this->cookie = '';
            $this->login($adminEmail);
            [, $settingsPage] = $this->request('admin_settings.php');
            preg_match('/name="csrf_token" value="([^"]+)"/', $settingsPage, $match);
            [$headers] = $this->request('admin_settings.php', ['csrf_token' => $match[1], 'announcement_text' => $announcement, 'announcement_enabled' => '0']);
            $this->assertContains('Location: admin_settings.php?success=1', $headers);
            $this->cookie = '';
            $this->login($studentEmail);
            [, $studentPage] = $this->request('student.php');
            $this->assertStringNotContainsString('id="announcement"', $studentPage);
        } finally {
            $this->db->exec("DELETE FROM settings WHERE setting_key IN ('announcement_text', 'announcement_enabled')");
            $stmt = $this->db->prepare('INSERT INTO settings (setting_key, value) VALUES (?, ?)');
            foreach ($savedSettings as $key => $value) {
                $stmt->execute([$key, $value]);
            }
        }
    }
}
