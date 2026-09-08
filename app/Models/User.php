<?php

namespace App\Models;

use App\Helpers\Database;

class User
{
    public static function findByUsername(string $username): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM users WHERE username = :username LIMIT 1'
        );
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * All users, for the Settings > Users list.
     */
    public static function all(): array
    {
        $stmt = Database::connection()->query(
            'SELECT id, username, full_name, email, role, is_active, last_login_at
             FROM users
             ORDER BY username ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * Update a user's profile fields (not the password).
     */
    public static function updateProfile(int $id, string $username, string $fullName, string $role, int $isActive): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users
             SET username = :username, full_name = :full_name, role = :role, is_active = :is_active
             WHERE id = :id'
        );
        $stmt->execute([
            'username'  => $username,
            'full_name' => $fullName,
            'role'      => $role,
            'is_active' => $isActive,
            'id'        => $id,
        ]);
    }

    public static function create(string $username, string $fullName, string $password, string $role = 'viewer'): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO users (username, full_name, password_hash, role)
             VALUES (:username, :full_name, :password_hash, :role)'
        );
        $stmt->execute([
            'username'      => $username,
            'full_name'     => $fullName,
            'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
            'role'          => $role,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function updatePasswordHash(int $id, string $hash): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET password_hash = :hash WHERE id = :id'
        );
        $stmt->execute(['hash' => $hash, 'id' => $id]);
    }

    public static function touchLastLogin(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE users SET last_login_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }
}