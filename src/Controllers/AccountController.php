<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminLog;

/**
 * Eigenes Passwort ändern (Schüler und Admin).
 */
final class AccountController extends Controller
{
    private const MIN_LENGTH = 8;

    public function passwordForm(): string
    {
        $user = $this->ctx->auth->require();

        return $this->page($user, null);
    }

    public function savePassword(): string
    {
        $user = $this->ctx->auth->require();
        $this->verifyCsrf();
        $current = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['new'] ?? '');
        $hash = (string) $this->ctx->db->fetchValue('SELECT password_hash FROM users WHERE id = ?', [$user['id']]);
        $error = match (true) {
            !password_verify($current, $hash) => 'Das bisherige Passwort stimmt nicht.',
            mb_strlen($new) < self::MIN_LENGTH => 'Das neue Passwort muss mindestens ' . self::MIN_LENGTH . ' Zeichen haben.',
            $new !== (string) ($_POST['confirm'] ?? '') => 'Die beiden neuen Passwörter stimmen nicht überein.',
            $new === $current => 'Das neue Passwort muss sich vom bisherigen unterscheiden.',
            default => null,
        };
        if ($error !== null) {
            return $this->page($user, $error);
        }
        $this->ctx->db->run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        if ($user['role'] === 'admin') {
            AdminLog::add($this->ctx, 'eigenes_passwort');
        }
        $this->ctx->session->regenerate();
        $this->ctx->session->flash('ok', 'Dein Passwort wurde geändert.');

        return $this->redirect($user['role'] === 'admin' ? '/admin' : '/');
    }

    private function page(array $user, ?string $error): string
    {
        return $this->ctx->view->render('passwort', [
            'title' => 'Passwort ändern', 'error' => $error, 'minLength' => self::MIN_LENGTH,
            'back' => $user['role'] === 'admin' ? '/admin' : '/',
            'nav' => $user['role'] === 'admin' ? 'passwort' : null,
        ]);
    }
}
