<?php

namespace CampusDrive\Infrastructure\Database;

use InvalidArgumentException;

final class UserRepository extends DatabaseRepository
{
    public function createUser(string $email, string $password, ?string $promotion_id, string $role): bool
    {
        if (!InputSanitizer::isValidEmail($email)) {
            throw new InvalidArgumentException("Adresse email invalide.");
        }

        if ($this->isEmailRegistered($email)) {
            throw new InvalidArgumentException("Cette adresse email est déjà utilisée.");
        }
        if (!in_array($role, ['student', 'delegate'], true)) {
            throw new InvalidArgumentException('Invalid role.');
        }

        $stmt = $this->connection()->prepare("INSERT INTO users (email, password, role, promotion_id) VALUES (?, ?, ?, ?)");
        return $stmt->execute([
            InputSanitizer::sanitize($email),
            $password,
            InputSanitizer::sanitize($role),
            InputSanitizer::sanitize($promotion_id)
        ]);
    }

    public function deleteUser(string $user_id): bool
    {
        $stmt = $this->connection()->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([InputSanitizer::sanitize($user_id)]);
    }

    public function getUserDetails(string $user_id): ?array
    {
        $stmt = $this->connection()->prepare("SELECT u.*, p.name as promo_name, p.status as promo_status FROM users u LEFT JOIN promotions p ON u.promotion_id = p.id WHERE u.id = ?");
        $stmt->execute([InputSanitizer::sanitize($user_id)]);
        return $stmt->fetch() ?: null;
    }

    public function updateUserLanguage(int $userId, string $language): bool
    {
        if (!in_array($language, ['fr', 'en'], true)) {
            throw new InvalidArgumentException('Unsupported language.');
        }

        $stmt = $this->connection()->prepare('UPDATE users SET language = ? WHERE id = ?');
        return $stmt->execute([$language, $userId]);
    }

    public function loginUser(string $email, string $password): ?array
    {
        $stmt = $this->connection()->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([InputSanitizer::sanitize($email)]);
        $user = $stmt->fetch();

        if ($user && password_verify(InputSanitizer::sanitize($password), $user['password'])) {
            return $user;
        }

        return null;
    }

    public function updateUserPassword(string $userId, string $currentPassword, string $newPassword): bool
    {
        $currentPassword = InputSanitizer::sanitize($currentPassword);
        $newPassword = InputSanitizer::sanitize($newPassword);
        $stmt = $this->connection()->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            echo 'user: ' . print_r($user, true);
            echo 'current : ' . $currentPassword . ' | stored : ' . $user['password'];
            return false; // Current password is incorrect
        }

        $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = $this->connection()->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $updateStmt->execute([$hashedNewPassword, $userId]);
    }

    public function getTotalUsers(): int
    {
        $stmt = $this->connection()->query("SELECT COUNT(*) FROM users");
        return (int) $stmt->fetchColumn();
    }

    private function isEmailRegistered(string $email): bool
    {
        $stmt = $this->connection()->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([InputSanitizer::sanitize($email)]);
        return $stmt->fetchColumn() > 0;
    }
}
