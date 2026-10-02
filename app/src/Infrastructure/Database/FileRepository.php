<?php

namespace CampusDrive\Infrastructure\Database;

use CampusDrive\Infrastructure\Storage\UploadStorage;
use PDO;
use RuntimeException;

final class FileRepository extends DatabaseRepository
{
    public function createFileRecord(string $user_id, string $promotion_id, string $original_name, string $file_path, string $file_type, ?string $folder_id = null): bool
    {
        $stmt = $this->connection()->prepare("INSERT INTO files (user_id, promotion_id, original_name, file_path, file_type, folder_id, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        return $stmt->execute([
            $user_id,
            $promotion_id,
            $original_name,
            $file_path,
            $file_type,
            $folder_id
        ]);
    }

    public function getPromotionFiles(string $promotion_id): array
    {
        $stmt = $this->connection()->prepare("SELECT f.*, u.email as uploader_email FROM files f JOIN users u ON f.user_id = u.id WHERE f.promotion_id = ? AND f.status = 'approved' ORDER BY f.created_at DESC");
        $stmt->execute([InputSanitizer::sanitize($promotion_id)]);
        return $stmt->fetchAll();
    }

    public function getPromotionFolders(string $promotion_id): array
    {
        $stmt = $this->connection()->prepare("SELECT * FROM folders WHERE promotion_id = ? ORDER BY created_at DESC");
        $stmt->execute([InputSanitizer::sanitize($promotion_id)]);
        return $stmt->fetchAll();
    }

    public function getPromotionFolderFiles(?string $folder_id, string $promotion_id): array
    {
        if ($folder_id === null) {
            $foldersStmt = $this->connection()->prepare("SELECT id, parent_id, promotion_id, name, created_at FROM folders WHERE promotion_id = ? ORDER BY name ");
            $foldersStmt->execute([$promotion_id]);
            $allFolders = $foldersStmt->fetchAll();

            $rootFolders = array_values(array_filter($allFolders, static function (array $candidate) use ($allFolders): bool {
                if (empty($candidate['parent_id'])) {
                    return true;
                }

                foreach ($allFolders as $folder) {
                    if ((string) $folder['id'] === (string) $candidate['parent_id']) {
                        return false;
                    }
                }

                return true;
            }));

            $filesStmt = $this->connection()->prepare("SELECT f.id, f.created_at, f.folder_id, f.original_name, u.email as uploader_email FROM files f JOIN users u ON f.user_id = u.id WHERE f.promotion_id = ? AND f.folder_id IS NULL AND f.status = 'approved' ORDER BY f.created_at DESC");
            $filesStmt->execute([$promotion_id]);
            $rootFiles = $filesStmt->fetchAll();

            return [
                'folder' => null,
                'parent_folder' => null,
                'folders' => $rootFolders,
                'files' => $rootFiles
            ];
        }

        $folderStmt = $this->connection()->prepare("SELECT id, parent_id, promotion_id, name, created_at FROM folders WHERE id = ?");
        $folderStmt->execute([$folder_id]);
        $folder = $folderStmt->fetch();

        if (!$folder || $folder['promotion_id'] !== $promotion_id) {
            return ['folder' => null, 'parent_folder' => null, 'folders' => [], 'files' => []];
        }

        $foldersStmt = $this->connection()->prepare("SELECT id, parent_id, promotion_id, name, created_at FROM folders WHERE promotion_id = ? ORDER BY name ");
        $foldersStmt->execute([$promotion_id]);
        $allFolders = $foldersStmt->fetchAll();

        $subfolders = array_values(array_filter($allFolders, static function (array $candidate) use ($folder_id): bool {
            return !empty($candidate['parent_id']) && (string) $candidate['parent_id'] === $folder_id;
        }));

        $filesStmt = $this->connection()->prepare("SELECT f.id, f.created_at, f.folder_id, f.original_name, u.email as uploader_email FROM files f JOIN users u ON f.user_id = u.id WHERE f.folder_id = ? AND f.promotion_id = ? AND f.status = 'approved' ORDER BY f.created_at DESC");
        $filesStmt->execute([$folder_id, $promotion_id]);
        $files = $filesStmt->fetchAll();

        $parentFolder = null;
        if (!empty($folder['parent_id'])) {
            $parentStmt = $this->connection()->prepare("SELECT id, parent_id, promotion_id, name, created_at FROM folders WHERE id = ?");
            $parentStmt->execute([$folder['parent_id']]);
            $parentFolder = $parentStmt->fetch() ?: null;
        }

        return [
            'folder' => $folder,
            'parent_folder' => $parentFolder,
            'folders' => $subfolders,
            'files' => $files
        ];
    }

    public function deletePromotionFolder(string $folder_id): bool
    {
        $folderStmt = $this->connection()->prepare("SELECT id FROM folders WHERE id = ?");
        $folderStmt->execute([$folder_id]);
        $folder = $folderStmt->fetch();

        if (!$folder) {
            return false;
        }

        foreach ($this->getChildFolderIds($folder_id) as $childFolderId) {
            $this->deletePromotionFolder($childFolderId);
        }

        $filesStmt = $this->connection()->prepare("SELECT id FROM files WHERE folder_id = ?");
        $filesStmt->execute([$folder_id]);
        $fileIds = $filesStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($fileIds as $fileId) {
            $this->rejectPromotionFile((string) $fileId);
        }

        $stmt = $this->connection()->prepare("DELETE FROM folders WHERE id = ?");
        return $stmt->execute([$folder_id]);
    }

    public function getUserPendingFiles(string $user_id): array
    {
        $stmt = $this->connection()->prepare("SELECT * FROM files WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC");
        $stmt->execute([InputSanitizer::sanitize($user_id)]);
        return $stmt->fetchAll();
    }

    public function getPromotionPendingFiles(string $promotion_id): array
    {
        $stmt = $this->connection()->prepare("SELECT f.*, u.email as uploader_email FROM files f JOIN users u ON f.user_id = u.id WHERE f.promotion_id = ? AND f.status = 'pending' ORDER BY f.created_at ");
        $stmt->execute([$promotion_id]);
        return $stmt->fetchAll();
    }

    public function approvePromotionFile(string $file_id): bool
    {
        $stmt = $this->connection()->prepare("UPDATE files SET status = 'approved' WHERE id = ?");
        return $stmt->execute([InputSanitizer::sanitize($file_id)]);
    }

    public function rejectPromotionFile(string $file_id): bool
    {
        $fileStmt = $this->connection()->prepare("SELECT file_path FROM files WHERE id = ?");
        $fileStmt->execute([$file_id]);
        $file = $fileStmt->fetch();

        if ($file && !empty($file['file_path'])) {
            (new UploadStorage())->delete($file['file_path']);
        }

        $stmt = $this->connection()->prepare("DELETE FROM files WHERE id = ?");
        return $stmt->execute([$file_id]);
    }

    public function getFile(string $file_id): ?array
    {
        $stmt = $this->connection()->prepare("SELECT * FROM files WHERE id = ?");
        $stmt->execute([InputSanitizer::sanitize($file_id)]);
        return $stmt->fetch() ?: null;
    }

    public function createFolder(string $promotion_id, int|string|null $parent_id, string $folder_name): string
    {
        if ($parent_id === '' || $parent_id === 'null') {
            $parent_id = null;
        }

        $folderId = $this->connection()->query('SELECT UUID()')->fetchColumn();
        if (!is_string($folderId)) {
            throw new RuntimeException('Could not generate a folder UUID.');
        }

        $stmt = $this->connection()->prepare("INSERT INTO folders (id, promotion_id, parent_id, name) VALUES (?, ?, ?, ?)");
        $stmt->execute([$folderId, $promotion_id, $parent_id, InputSanitizer::sanitize($folder_name)]);
        return $folderId;
    }

    public function getTotalFiles(): int
    {
        $stmt = $this->connection()->query("SELECT COUNT(*) FROM files");
        return (int) $stmt->fetchColumn();
    }

    private function getChildFolderIds(string $folder_id): array
    {
        $stmt = $this->connection()->prepare("SELECT id FROM folders WHERE parent_id = ?");
        $stmt->execute([$folder_id]);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
