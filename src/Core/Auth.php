<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Angemeldeter Benutzer (Schüler oder Admin) über die Session.
 */
final class Auth
{
    /** @var array<string, mixed>|null|false false = noch nicht geladen */
    private array|null|false $user = false;

    public function __construct(
        private readonly Session $session,
        private readonly Database $db,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if ($this->user === false) {
            $id = $this->session->get('user_id');
            $this->user = is_int($id)
                ? $this->db->fetchOne('SELECT id, login, role, display_name FROM users WHERE id = ?', [$id])
                : null;
        }

        return $this->user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function isAdmin(): bool
    {
        return ($this->user()['role'] ?? null) === 'admin';
    }

    public function login(int $userId): void
    {
        $this->session->regenerate();
        $this->session->set('user_id', $userId);
        $this->user = false;
        $this->db->run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$userId]);
    }

    public function logout(): void
    {
        $this->session->destroy();
        $this->user = null;
    }

    public function require(): array
    {
        $user = $this->user();
        if ($user === null) {
            throw new HttpException(401);
        }

        return $user;
    }

    public function requireAdmin(): array
    {
        $user = $this->require();
        if ($user['role'] !== 'admin') {
            throw new HttpException(403);
        }

        return $user;
    }
}
