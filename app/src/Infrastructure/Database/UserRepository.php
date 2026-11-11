<?php

namespace CampusDrive\Infrastructure\Database;

use InvalidArgumentException;

final class UserRepository extends DatabaseRepository
{
    public function createUser(string $email, string $password, ?string $promotion_id, string $role): array
    {
        if (!InputSanitizer::isValidEmail($email)) {
            throw new InvalidArgumentException("Adresse email invalide.");
        }

        if ($this->isEmailRegistered($email)) {
            throw new InvalidArgumentException("Cette adresse email est déjà utilisée.");
        }
        if (!in_array($role, ['student', 'ambassador'], true)) {
            throw new InvalidArgumentException('Invalid role.');
        }

        if ($role === 'ambassador') {
            $activationToken = bin2hex(random_bytes(16));
        } else {
            $activationToken = null;
        }

        $stmt = $this->connection()->prepare("INSERT INTO users (email, password, role, promotion_id, activation_token) VALUES (?, ?, ?, ?, ?)");
        return [$stmt->execute([
            InputSanitizer::sanitize($email),
            $password,
            InputSanitizer::sanitize($role),
            InputSanitizer::sanitize($promotion_id),
            $activationToken ?? null
        ]), $activationToken];
    }

    public function deleteUser(string $user_id): bool
    {
        $stmt = $this->connection()->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([InputSanitizer::sanitize($user_id)]);
    }

    public function deleteUserForAdmin(string $userId, string $adminId): bool
    {
        if ($userId === $adminId) {
            throw new InvalidArgumentException('admin_cannot_delete_self');
        }
        $stmt = $this->connection()->prepare('DELETE FROM users WHERE id = ? AND id <> ?');
        $stmt->execute([$userId, $adminId]);
        return $stmt->rowCount() === 1;
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

    public function updateUserLanguage(string $userId, string $language): bool
    {
        if (!in_array($language, ['fr', 'en'], true)) {
            throw new InvalidArgumentException('Unsupported language.');
        }

        $stmt = $this->connection()->prepare('UPDATE users SET language = ? WHERE id = ?');
        return $stmt->execute([$language, $userId]);
    }

    public function activateUser(string $activationToken): bool
    {
        $stmt = $this->connection()->prepare("UPDATE users SET activation_token = NULL WHERE activation_token = ?");
        return $stmt->execute([InputSanitizer::sanitize($activationToken)]);
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
            if ($user['activation_token'] !== null) {
                return [
                    'user' => null,
                    'error' => 'account_not_activated'
                ];
            }
            return [$user];
        }

        if ($this->matchesLegacyEncodedPassword($password, $user['password'])) {
            // Move the account to a hash of the real password so the legacy fallback is only needed once.
            $user['password'] = password_hash($password, PASSWORD_DEFAULT);
            $this->storePasswordHash((string) $user['id'], $user['password']);

            if ($user['activation_token'] !== null) {
                return [
                    'error' => 'account_not_activated'
                ];
            }
            return [$user];
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

    public function createPasswordResetToken(string $email, string $tokenHash, int $expiresAt): ?string
    {
        return $this->createResetToken('email', InputSanitizer::sanitize($email), $tokenHash, $expiresAt);
    }

    public function createPasswordResetTokenForUser(string $userId, string $tokenHash, int $expiresAt): ?string
    {
        return $this->createResetToken('id', $userId, $tokenHash, $expiresAt);
    }

    private function createResetToken(string $column, ?string $value, string $tokenHash, int $expiresAt): ?string
    {
        $this->connection()->beginTransaction();
        try {
            $lock = $this->connection()->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
            $stmt = $this->connection()->prepare("SELECT id, email FROM users WHERE {$column} = ?" . $lock);
            $stmt->execute([$value]);
            $user = $stmt->fetch();
            if (!$user) {
                $this->connection()->rollBack();
                return null;
            }
            $delete = $this->connection()->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
            $delete->execute([$user['id']]);

            $insert = $this->connection()->prepare(
                'INSERT INTO password_reset_tokens (token_hash, user_id, expires_at) VALUES (?, ?, ?)'
            );
            $insert->execute([$tokenHash, $user['id'], $expiresAt]);
            $this->connection()->commit();
        } catch (\Throwable $e) {
            $this->connection()->rollBack();
            throw $e;
        }

        return (string) $user['email'];
    }

    public function resetPasswordWithToken(string $tokenHash, string $newPassword): bool
    {
        $connection = $this->connection();
        $connection->beginTransaction();

        try {
            $stmt = $connection->prepare(
                'SELECT user_id FROM password_reset_tokens WHERE token_hash = ? AND expires_at > ?'
            );
            $stmt->execute([$tokenHash, time()]);
            $userId = $stmt->fetchColumn();

            if ($userId === false) {
                $connection->rollBack();
                return false;
            }

            $claim = $connection->prepare(
                'DELETE FROM password_reset_tokens WHERE token_hash = ? AND expires_at > ?'
            );
            $claim->execute([$tokenHash, time()]);

            if ($claim->rowCount() !== 1) {
                $connection->rollBack();
                return false;
            }

            $this->storePasswordHash((string) $userId, password_hash($newPassword, PASSWORD_DEFAULT));
            $deleteOthers = $connection->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
            $deleteOthers->execute([$userId]);
            $connection->commit();

            return true;
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
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

    public function getAllUsers(): array
    {
        return $this->connection()->query(
            'SELECT u.id, u.email, u.role, u.promotion_id, u.activation_token, p.name AS promo_name
             FROM users u LEFT JOIN promotions p ON p.id = u.promotion_id ORDER BY u.email'
        )->fetchAll();
    }

    public function updateEmail(string $userId, string $email): bool
    {
        $email = trim($email);
        if (strlen($email) > 255 || !InputSanitizer::isValidEmail($email)) {
            throw new InvalidArgumentException('invalid_admin_email');
        }

        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            $stmt = $connection->prepare('SELECT email FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$userId]);
            $oldEmail = $stmt->fetchColumn();
            if ($oldEmail === false) {
                throw new InvalidArgumentException('admin_record_missing');
            }
            $storedEmail = InputSanitizer::sanitize($email);
            if ($storedEmail !== $oldEmail) {
                $stmt = $connection->prepare('UPDATE users SET email = ? WHERE id = ?');
                $stmt->execute([$storedEmail, $userId]);
                $stmt = $connection->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
                $stmt->execute([$userId]);
            }
            $connection->commit();
            return true;
        } catch (\PDOException $e) {
            $connection->rollBack();
            if ($e->getCode() === '23000') {
                throw new InvalidArgumentException('admin_email_taken', 0, $e);
            }
            throw $e;
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    public function updatePromotionForAdmin(string $userId, ?string $promotionId, bool $confirmAmbassadorMove): void
    {
        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            if ($promotionId !== null) {
                $stmt = $connection->prepare('SELECT id FROM promotions WHERE id = ? FOR UPDATE');
                $stmt->execute([$promotionId]);
                if ($stmt->fetchColumn() === false) {
                    throw new InvalidArgumentException('admin_record_missing');
                }
            }
            $stmt = $connection->prepare('SELECT role, promotion_id FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if (!$user) {
                throw new InvalidArgumentException('admin_record_missing');
            }
            if ($user['role'] === 'ambassador' && $user['promotion_id'] !== $promotionId && !$confirmAmbassadorMove) {
                throw new InvalidArgumentException('admin_confirm_ambassador_move');
            }
            $stmt = $connection->prepare('UPDATE users SET promotion_id = ? WHERE id = ?');
            $stmt->execute([$promotionId, $userId]);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
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
