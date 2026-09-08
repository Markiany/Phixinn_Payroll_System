<?php

namespace App\Controllers;

use App\Helpers\Auth;
use App\Models\User;

class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            header('Location: /dashboard');
            exit;
        }

        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);

        require __DIR__ . '/../../resources/views/auth/login.php';
    }

    public function login(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $_SESSION['login_error'] =
                'Please enter both username and password.';

            header('Location: /login');
            exit;
        }

        if (Auth::attempt($username, $password)) {
            header('Location: /dashboard');
            exit;
        }

        $_SESSION['login_error'] =
            'Invalid username or password.';

        header('Location: /login');
        exit;
    }

    public function logout(): void
    {
        Auth::logout();

        header('Location: /login');
        exit;
    }

    /**
     * Show registration page.
     *
     * Registration is open: anyone can create a new system account,
     * whether or not other accounts already exist. The first account
     * created is automatically given the admin role.
     */
    public function showRegister(): void
    {
        $db = \App\Helpers\Database::connection();

        $stmt = $db->query('SELECT COUNT(*) FROM users');
        $userCount = (int) $stmt->fetchColumn();

        $isFirstAccount = ($userCount === 0);

        $error = $_SESSION['register_error'] ?? null;
        $success = $_SESSION['register_success'] ?? null;
        $old = $_SESSION['register_old'] ?? [];

        unset(
            $_SESSION['register_error'],
            $_SESSION['register_success'],
            $_SESSION['register_old']
        );

        require __DIR__ . '/../../resources/views/auth/register.php';
    }

    /**
     * Process registration.
     */
    public function register(): void
    {
        $db = \App\Helpers\Database::connection();

        // Check current number of users.
        $stmt = $db->query('SELECT COUNT(*) FROM users');
        $userCount = (int) $stmt->fetchColumn();

        $isFirstAccount = ($userCount === 0);

        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        // Every account created here is an admin account — this system
        // is only used by HR staff, so there's no role to pick.
        $role = 'admin';

        $errors = [];

        // Required fields
        if (
            $username === '' ||
            $fullName === '' ||
            $password === ''
        ) {
            $errors[] =
                'Please fill in all required fields.';
        }

        // Username validation
        if (
            $username !== '' &&
            !preg_match(
                '/^[a-zA-Z0-9_.-]{3,50}$/',
                $username
            )
        ) {
            $errors[] =
                'Username must be 3-50 characters (letters, numbers, underscore, dot, dash only).';
        }

        // Password validation
        if (strlen($password) < 8) {
            $errors[] =
                'Password must be at least 8 characters.';
        }

        // Password confirmation
        if ($password !== $confirm) {
            $errors[] =
                'Passwords do not match.';
        }

        // Duplicate username
        if (
            empty($errors) &&
            User::findByUsername($username)
        ) {
            $errors[] =
                'That username is already taken.';
        }

        // Validation failed
        if (!empty($errors)) {

            $_SESSION['register_error'] =
                implode(' ', $errors);

            $_SESSION['register_old'] = [
                'username' => $username,
                'full_name' => $fullName,
                'role' => $role
            ];

            header('Location: /register');
            exit;
        }

        // Create user
        $newUserId = User::create(
            $username,
            $fullName,
            $password,
            $role
        );

        // Audit log
        \App\Helpers\AuditLogger::log(
            'user.created',
            'user',
            (string) $newUserId
        );

        // First account
        if ($isFirstAccount) {

            $_SESSION['register_success'] =
                "Administrator account '{$username}' created successfully. You can now sign in.";

            header('Location: /login');
            exit;
        }

        // Normal admin-created account
        $_SESSION['register_success'] =
            "Account '{$username}' created successfully.";

        header('Location: /register');
        exit;
    }
}