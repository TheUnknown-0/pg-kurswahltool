<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Services\AdminLog;
use App\Services\Deadline;
use App\Services\LoginName;
use App\Services\LoginThrottle;

final class AuthController extends Controller
{
    public function form(): string
    {
        if ($this->ctx->auth->check()) {
            return $this->redirect($this->ctx->auth->isAdmin() ? '/admin' : '/');
        }

        return $this->ctx->view->render('login', ['title' => 'Anmelden', 'error' => null, 'login' => '']);
    }

    public function login(): string
    {
        $this->verifyCsrf();
        $login = LoginName::normalize((string) ($_POST['login'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $ip = $this->ctx->clientIp();
        $throttle = new LoginThrottle($this->ctx->db);

        if ($throttle->isBlocked($login, $ip)) {
            throw new HttpException(429, 'Zu viele Fehlversuche. Bitte warte fünf Minuten.');
        }
        $user = $this->ctx->db->fetchOne('SELECT id, role, password_hash FROM users WHERE login = ?', [$login]);
        // Auch ohne Konto einen Hash prüfen, damit die Antwortzeit nichts verrät
        $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringforsaltuDPhsLeTuR7JpO1VfwZGy2Ui0WT5wMu';
        if ($user === null || $user['password_hash'] === null || !password_verify($password, $hash)) {
            $throttle->recordFailure($login, $ip);

            return $this->ctx->view->render('login', ['title' => 'Anmelden', 'error' => 'Login oder Passwort ist falsch.', 'login' => $login]);
        }
        if ($user['role'] === 'student' && (new Deadline($this->ctx->db))->loginBlocked()) {
            return $this->ctx->view->render('login', ['title' => 'Anmelden', 'error' => 'Die Abgabefrist ist abgelaufen. Die Anmeldung ist geschlossen.', 'login' => $login]);
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $this->ctx->db->run('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        $throttle->clear($login);
        $this->ctx->auth->login((int) $user['id']);

        if ($user['role'] === 'admin') {
            AdminLog::add($this->ctx, 'login');
        }

        return $this->redirect($user['role'] === 'admin' ? '/admin' : '/');
    }

    public function logout(): string
    {
        $this->verifyCsrf();
        $this->ctx->auth->logout();

        return $this->redirect('/login');
    }
}
