<?php

namespace App\Helpers;

use App\Models\User;

class Auth
{
    public static function attempt(string $username, string $password): bool
    {
        $user = User::findByUsername($username);

        if (!$user || !$user['is_active']) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }
    
        // Rehash transparently if the algorithm/cost has changed.
        if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID)) {
            User::updatePasswordHash($user['id'], password_hash($password, PASSWORD_ARGON2ID));
        }

        session_regenerate_id(true);
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];

        User::touchLastLogin($user['id']);

        AuditLogger::log('user.login', 'user', (string) $user['id']);

        return true;
    }

    public static function logout(): void
    {
        if (self::check()) {
            AuditLogger::log('user.logout', 'user', (string) $_SESSION['user_id']);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return [
            'id'        => $_SESSION['user_id'],
            'username'  => $_SESSION['username'],
            'role'      => $_SESSION['role'],
            'full_name' => $_SESSION['full_name'],
        ];
    }

    public static function hasRole(string ...$roles): bool
    {
        return self::check() && in_array($_SESSION['role'], $roles, true);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: /login');
            exit;
        }
    }

    public static function requireRole(string ...$roles): void
    {
        self::requireLogin();
        if (!self::hasRole(...$roles)) {
            http_response_code(403);
            echo '403 - You do not have permission to access this page.';
            exit;
        }
    }
}