<?php

require_once __DIR__ . '/config.php';
class Database
{
    private static ?PDO $instance = null;

    private function __construct()
    {
    }

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            try {
                self::$instance = new PDO(
                    sprintf(
                        'mysql:host=%s;dbname=%s;charset=utf8mb4',
                        DB_HOST,
                        DB_NAME
                    ),
                    DB_USER,
                    DB_PASSWORD,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                die('Erreur de connexion à la base de données : ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    public static function getInvitationByToken(string $token): ?array
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM invitations WHERE token = ? AND is_used = 0");
        $stmt->execute([self::sanitizeInput($token)]);
        return $stmt->fetch() ?: null;
    }

    public static function createUser(string $email, string $password, ?int $promotion_id): bool
    {
        if (!self::validateEmail($email)) {
            throw new InvalidArgumentException("Adresse email invalide.");
        }

        if (self::isEmailRegistered($email)) {
            throw new InvalidArgumentException("Cette adresse email est déjà utilisée.");
        }

        $pdo = self::getConnection();
        $stmt = $pdo->prepare("INSERT INTO users (email, password, role, promotion_id) VALUES (?, ?, 'student', ?)");
        return $stmt->execute([self::sanitizeInput($email), $password, self::sanitizeInput($promotion_id)]);
    }

    public static function getUserDetails(string $user_id): ?array
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("SELECT u.*, p.name as promo_name, p.status as promo_status FROM users u LEFT JOIN promotions p ON u.promotion_id = p.id WHERE u.id = ?");
        $stmt->execute([self::sanitizeInput($user_id)]);
        return $stmt->fetch() ?: null;
    }

    public static function updateUserLanguage(int $userId, string $language): bool
    {
        if (!in_array($language, ['fr', 'en'], true)) {
            throw new InvalidArgumentException('Unsupported language.');
        }

        $pdo = self::getConnection();
        $stmt = $pdo->prepare('UPDATE users SET language = ? WHERE id = ?');

        return $stmt->execute([$language, $userId]);
    }

    public static function createPromotion(string $name): int
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("INSERT INTO promotions (name, status, created_by) VALUES (?, 'pending', ?)");
        $stmt->execute([self::sanitizeInput($name), $_SESSION['user_id']]);
        return (int)$pdo->lastInsertId();
    }

    public static function updatePromotionStatus(int $promotion_id, string $status): bool
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("UPDATE promotions SET status = ? WHERE id = ?");
        return $stmt->execute([self::sanitizeInput($status), $promotion_id]);
    }

    public static function getPromotionStatus(int $promotion_id): ?string
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("SELECT status FROM promotions WHERE id = ?");
        $stmt->execute([self::sanitizeInput($promotion_id)]);
        $result = $stmt->fetch();
        return $result ? $result['status'] : null;
    }

    public static function getPromotionDetails(int $promotion_id): ?array
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM promotions WHERE id = ?");
        $stmt->execute([$promotion_id]);
        return $stmt->fetch() ?: null;
    }

    public static function getPendingPromotions(): array
    {
        $pdo = self::getConnection();
        $stmt = $pdo->query("SELECT * FROM promotions WHERE status = 'pending' ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public static function attachUserToPromotion(int $user_id, int $promotion_id): bool
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("UPDATE users SET promotion_id = ? WHERE id = ?");
        return $stmt->execute([self::sanitizeInput($promotion_id), $user_id]);
    }

    public static function createFileRecord(int $user_id, int $promotion_id, string $original_name, string $file_path, string $file_type, ?int $folder_id = null): bool
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("INSERT INTO files (user_id, promotion_id, original_name, file_path, file_type, folder_id, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        return $stmt->execute([
            $user_id,
            $promotion_id,
            $original_name,
            $file_path,
            $file_type,
            $folder_id
        ]);
    }

    public static function markInvitationAsUsed(string $token): bool
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("UPDATE invitations SET is_used = 1 WHERE token = ?");
        return $stmt->execute([self::sanitizeInput($token)]);
    }

    public static function loginUser(string $email, string $password): ?array
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([self::sanitizeInput($email)]);
        $user = $stmt->fetch();

        if ($user && password_verify(self::sanitizeInput($password), $user['password'])) {
            return $user;
        }

        return null;
    }

    public static function getPromotionFiles(int $promotion_id): array
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("SELECT f.*, u.email as uploader_email FROM files f JOIN users u ON f.user_id = u.id WHERE f.promotion_id = ? AND f.status = 'approved' ORDER BY f.created_at DESC");
        $stmt->execute([self::sanitizeInput($promotion_id)]);
        return $stmt->fetchAll();
    }

    public static function getPromotionFolders(int $promotion_id): array
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM folders WHERE promotion_id = ? ORDER BY created_at DESC");
        $stmt->execute([self::sanitizeInput($promotion_id)]);
        return $stmt->fetchAll();
    }

    public static function getPromotionFolderFiles(?int $folder_id, int $promotion_id): array
    {
        $pdo = self::getConnection();

        if ($folder_id === null) {
            $foldersStmt = $pdo->prepare("SELECT id, parent_id, promotion_id, name, created_at FROM folders WHERE promotion_id = ? ORDER BY name ASC");
            $foldersStmt->execute([$promotion_id]);
            $allFolders = $foldersStmt->fetchAll();

            $rootFolders = array_values(array_filter($allFolders, static function (array $candidate) use ($allFolders): bool {
                if (empty($candidate['parent_id'])) {
                    return true;
                }

                foreach ($allFolders as $folder) {
                    if ((int) $folder['id'] === (int) $candidate['parent_id']) {
                        return false;
                    }
                }

                return true;
            }));

            $filesStmt = $pdo->prepare("SELECT f.id, f.created_at, f.folder_id, f.original_name, u.email as uploader_email FROM files f JOIN users u ON f.user_id = u.id WHERE f.promotion_id = ? AND f.folder_id IS NULL AND f.status = 'approved' ORDER BY f.created_at DESC");
            $filesStmt->execute([$promotion_id]);
            $rootFiles = $filesStmt->fetchAll();

            return [
                'folder' => null,
                'parent_folder' => null,
                'folders' => $rootFolders,
                'files' => $rootFiles
            ];
        }

        $folderStmt = $pdo->prepare("SELECT id, parent_id, promotion_id, name, created_at FROM folders WHERE id = ?");
        $folderStmt->execute([$folder_id]);
        $folder = $folderStmt->fetch();

        if (!$folder || (int) $folder['promotion_id'] !== $promotion_id) {
            return ['folder' => null, 'parent_folder' => null, 'folders' => [], 'files' => []];
        }

        $foldersStmt = $pdo->prepare("SELECT id, parent_id, promotion_id, name, created_at FROM folders WHERE promotion_id = ? ORDER BY name ASC");
        $foldersStmt->execute([$promotion_id]);
        $allFolders = $foldersStmt->fetchAll();

        $subfolders = array_values(array_filter($allFolders, static function (array $candidate) use ($folder_id): bool {
            return !empty($candidate['parent_id']) && (int) $candidate['parent_id'] === $folder_id;
        }));

        $filesStmt = $pdo->prepare("SELECT f.id, f.created_at, f.folder_id, f.original_name, u.email as uploader_email FROM files f JOIN users u ON f.user_id = u.id WHERE f.folder_id = ? AND f.status = 'approved' ORDER BY f.created_at DESC");
        $filesStmt->execute([$folder_id]);
        $files = $filesStmt->fetchAll();

        $parentFolder = null;
        if (!empty($folder['parent_id'])) {
            $parentStmt = $pdo->prepare("SELECT id, parent_id, promotion_id, name, created_at FROM folders WHERE id = ?");
            $parentStmt->execute([(int) $folder['parent_id']]);
            $parentFolder = $parentStmt->fetch() ?: null;
        }

        return [
            'folder' => $folder,
            'parent_folder' => $parentFolder,
            'folders' => $subfolders,
            'files' => $files
        ];
    }

    public static function deletePromotionFolder(int $folder_id): bool
    {
        $pdo = self::getConnection();
        $folderStmt = $pdo->prepare("SELECT id FROM folders WHERE id = ?");
        $folderStmt->execute([$folder_id]);
        $folder = $folderStmt->fetch();

        if (!$folder) {
            return false;
        }

        foreach (self::getChildFolderIds($folder_id) as $childFolderId) {
            self::deletePromotionFolder($childFolderId);
        }

        $filesStmt = $pdo->prepare("SELECT id FROM files WHERE folder_id = ?");
        $filesStmt->execute([$folder_id]);
        $fileIds = $filesStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($fileIds as $fileId) {
            self::rejectPromotionFile((int) $fileId);
        }

        $stmt = $pdo->prepare("DELETE FROM folders WHERE id = ?");
        return $stmt->execute([$folder_id]);
    }

    public static function getUserPendingFiles(int $user_id): array
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM files WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC");
        $stmt->execute([self::sanitizeInput($user_id)]);
        return $stmt->fetchAll();
    }

    public static function getPromotionPendingFiles(int $promotion_id): array
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("SELECT f.*, u.email as uploader_email FROM files f JOIN users u ON f.user_id = u.id WHERE f.promotion_id = ? AND f.status = 'pending' ORDER BY f.created_at ASC");
        $stmt->execute([$promotion_id]);
        return $stmt->fetchAll();
    }

    public static function approvePromotionFile(int $file_id): bool
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("UPDATE files SET status = 'approved' WHERE id = ?");
        return $stmt->execute([self::sanitizeInput($file_id)]);
    }

    public static function rejectPromotionFile(int $file_id): bool
    {
        $pdo = self::getConnection();
        $fileStmt = $pdo->prepare("SELECT file_path FROM files WHERE id = ?");
        $fileStmt->execute([$file_id]);
        $file = $fileStmt->fetch();

        if ($file && !empty($file['file_path'])) {
            $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $file['file_path'];
            if (is_file($fullPath)) {
                unlink($fullPath);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
        return $stmt->execute([$file_id]);
    }

    public static function getFile(int $file_id): ?array
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
        $stmt->execute([self::sanitizeInput($file_id)]);
        return $stmt->fetch() ?: null;
    }

    public static function checkIfEmailIsAlreadyInvited(string $email, int $promotion_id): bool
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM invitations WHERE email = ? AND promotion_id = ?");
        $stmt->execute([self::sanitizeInput($email), self::sanitizeInput($promotion_id)]);
        return $stmt->fetchColumn() > 0;
    }

    public static function createInvitation(string $email, int $promotion_id, string $token): bool
    {
        if (!self::validateEmail($email)) {
            throw new InvalidArgumentException("Adresse email invalide.");
        }
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("INSERT INTO invitations (email, promotion_id, token) VALUES (?, ?, ?)");
        return $stmt->execute([self::sanitizeInput($email), $promotion_id, self::sanitizeInput($token)]);
    }

    public static function getPromotionInvitations(int $promotion_id): array
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM invitations WHERE promotion_id = ? ORDER BY created_at DESC");
        $stmt->execute([$promotion_id]);
        return $stmt->fetchAll();
    }

    public static function createFolder(int $promotion_id, int|string|null $parent_id, string $folder_name): bool
    {
        if ($parent_id === '' || $parent_id === 'null') {
            $parent_id = null;
        } elseif (!is_null($parent_id)) {
            $parent_id = (int) $parent_id;
        }
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("INSERT INTO folders (promotion_id, parent_id, name) VALUES (?, ?, ?)");
        return $stmt->execute([$promotion_id, $parent_id, self::sanitizeInput($folder_name)]);
    }

    private static function getChildFolderIds(int $folder_id): array
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("SELECT id FROM folders WHERE parent_id = ?");
        $stmt->execute([$folder_id]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function isEmailRegistered(string $email): bool
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([self::sanitizeInput($email)]);
        return $stmt->fetchColumn() > 0;
    }

    private static function insertLog(string $action, ?int $userId = null): void
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare("INSERT INTO logs (action, user_id, created_at, ip_address) VALUES (?, ?, NOW(), ?)");
        $stmt->execute([self::sanitizeInput($action), $userId, self::sanitizeInput($_SERVER['REMOTE_ADDR'])]);
    }

    private static function sanitizeInput(?string $input): ?string
    {
        if (empty($input)) {
            return null;
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    private static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}