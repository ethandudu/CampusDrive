<?php

namespace CampusDrive\Infrastructure\Database;

use InvalidArgumentException;

final class InvitationRepository extends DatabaseRepository
{
    public function getInvitationByToken(string $token): ?array
    {
        $stmt = $this->connection()->prepare("SELECT * FROM invitations WHERE token = ? AND is_used = 0");
        $stmt->execute([InputSanitizer::sanitize($token)]);
        return $stmt->fetch() ?: null;
    }

    public function markInvitationAsUsed(string $token): bool
    {
        $stmt = $this->connection()->prepare("UPDATE invitations SET is_used = 1 WHERE token = ?");
        return $stmt->execute([InputSanitizer::sanitize($token)]);
    }

    public function checkIfEmailIsAlreadyInvited(string $email, string $promotion_id): bool
    {
        $stmt = $this->connection()->prepare("SELECT COUNT(*) FROM invitations WHERE email = ? AND promotion_id = ?");
        $stmt->execute([InputSanitizer::sanitize($email), InputSanitizer::sanitize($promotion_id)]);
        return $stmt->fetchColumn() > 0;
    }

    public function createInvitation(string $email, string $promotion_id, string $token): bool
    {
        if (!InputSanitizer::isValidEmail($email)) {
            throw new InvalidArgumentException("Adresse email invalide.");
        }
        $stmt = $this->connection()->prepare("INSERT INTO invitations (email, promotion_id, token) VALUES (?, ?, ?)");
        return $stmt->execute([InputSanitizer::sanitize($email), $promotion_id, InputSanitizer::sanitize($token)]);
    }

    public function deleteInvitation(string $invitation_id, string $promotion_id): bool
    {
        $stmt = $this->connection()->prepare("DELETE FROM invitations WHERE id = ? AND promotion_id = ?");
        return $stmt->execute([InputSanitizer::sanitize($invitation_id), InputSanitizer::sanitize($promotion_id)]);
    }

    public function getPromotionInvitations(string $promotion_id): array
    {
        $stmt = $this->connection()->prepare("SELECT * FROM invitations WHERE promotion_id = ? ORDER BY created_at DESC");
        $stmt->execute([$promotion_id]);
        return $stmt->fetchAll();
    }
}
