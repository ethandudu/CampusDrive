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

    /**
     * Minimal, always-fresh view of a user used to revalidate an existing session.
     *
     * @return array{id: string, role: string, promotion_id: ?string, password: string}|null
     */
    public function getSessionState(string $user_id): ?array
    {
        $stmt = $this->connection()->prepare("SELECT id, role, promotion_id, password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
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

        if (!$user) {
            return null;
        }

        if (password_verify($password, $user['password'])) {
            return $user;
        }

        if ($this->matchesLegacyEncodedPassword($password, $user['password'])) {
            // Move the account to a hash of the real password so the legacy fallback is only needed once.
            $user['password'] = password_hash($password, PASSWORD_DEFAULT);
            $this->storePasswordHash((string) $user['id'], $user['password']);

            return $user;
        }

        return null;
    }

    public function verifyPassword(string $userId, string $password): bool
    {
        $stmt = $this->connection()->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        return $user
            && (password_verify($password, $user['password'])
                || $this->matchesLegacyEncodedPassword($password, $user['password']));
    }

    public function updateUserPassword(string $userId, string $currentPassword, string $newPassword): bool
    {
        if (!$this->verifyPassword($userId, $currentPassword)) {
            return false; // Current password is incorrect
        }

        return $this->storePasswordHash($userId, password_hash($newPassword, PASSWORD_DEFAULT));
    }

    /**
     * Re-hashes the current password with a fresh salt. The stored hash changes, which ends every
     * session opened before (see SessionPolicy::fingerprint) without the user changing their password.
     */
    public function rotatePasswordHash(string $userId, string $password): bool
    {
        return $this->updateUserPassword($userId, $password, $password);
    }

    public function getTotalUsers(): int
    {
        $stmt = $this->connection()->query("SELECT COUNT(*) FROM users");
        return (int) $stmt->fetchColumn();
    }

    private function storePasswordHash(string $userId, string $hash): bool
    {
        $stmt = $this->connection()->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$hash, $userId]);
    }

    /**
     * Login and password change used to hash the HTML-encoded (and trimmed) password, while
     * registration hashed the raw one. Hashes created by the former still have to be accepted.
     */
    private function matchesLegacyEncodedPassword(string $password, string $hash): bool
    {
        $encoded = InputSanitizer::sanitize($password);

        return $encoded !== null && $encoded !== $password && password_verify($encoded, $hash);
    }

    private function isEmailRegistered(string $email): bool
    {
        $stmt = $this->connection()->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([InputSanitizer::sanitize($email)]);
        return $stmt->fetchColumn() > 0;
    }
}
