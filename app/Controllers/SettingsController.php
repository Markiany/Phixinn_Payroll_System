<?php

namespace App\Controllers;

use App\Helpers\Auth;
use App\Models\User;

class SettingsController
{
    /**
     * Settings home — currently just the Users list.
     */
    public function index(): void
    {
        Auth::requireRole('admin');

        $user = Auth::user();
        $users = User::all();

        $error = $_SESSION['settings_error'] ?? null;
        $success = $_SESSION['settings_success'] ?? null;

        unset($_SESSION['settings_error'], $_SESSION['settings_success']);

        require __DIR__ . '/../../resources/views/settings/index.php';
    }

    /**
     * Show the edit form for a single user account.
     */
    public function editUser(string $id): void
    {
        Auth::requireRole('admin');

        $user = Auth::user();
        $editUser = User::findById((int) $id);

        if (!$editUser) {
            http_response_code(404);
            echo '404 - User not found';
            return;
        }

        $error = $_SESSION['settings_error'] ?? null;
        unset($_SESSION['settings_error']);

        require __DIR__ . '/../../resources/views/settings/edit-user.php';
    }

    /**
     * Save changes to a user account: username, full name, role,
     * active status, and (optionally) a new password.
     */
    public function updateUser(string $id): void
    {
        Auth::requireRole('admin');

        $targetId = (int) $id;
        $target = User::findById($targetId);

        if (!$target) {
            http_response_code(404);
            echo '404 - User not found';
            return;
        }

        $username = trim($_POST['username'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        // Every account is an admin account — this system is only used
        // by HR staff, so there's no role to pick.
        $role = 'admin';
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $newPassword = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        $errors = [];

        if ($username === '' || $fullName === '') {
            $errors[] = 'Username and full name are required.';
        }

        if (
            $username !== '' &&
            !preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)
        ) {
            $errors[] = 'Username must be 3-50 characters (letters, numbers, underscore, dot, dash only).';
        }

        // Duplicate username check (excluding this same user).
        if (empty($errors)) {
            $existing = User::findByUsername($username);
            if ($existing && (int) $existing['id'] !== $targetId) {
                $errors[] = 'That username is already taken.';
            }
        }

        // Password change is optional here — only validate if they typed one.
        if ($newPassword !== '' || $confirm !== '') {
            if (strlen($newPassword) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            }
            if ($newPassword !== $confirm) {
                $errors[] = 'New password and confirmation do not match.';
            }
        }

        // Prevent an admin from locking themselves out by deactivating
        // their own account through this form.
        $currentUser = Auth::user();
        if ($currentUser && (int) $currentUser['id'] === $targetId && $isActive === 0) {
            $errors[] = 'You cannot deactivate your own account.';
        }

        if (!empty($errors)) {
            $_SESSION['settings_error'] = implode(' ', $errors);
            header('Location: /settings/users/' . $targetId . '/edit');
            exit;
        }

        User::updateProfile($targetId, $username, $fullName, $role, $isActive);

        if ($newPassword !== '') {
            User::updatePasswordHash($targetId, password_hash($newPassword, PASSWORD_ARGON2ID));
        }

        \App\Helpers\AuditLogger::log('user.updated', 'user', (string) $targetId);

        $_SESSION['settings_success'] = "Account '{$username}' updated successfully.";
        header('Location: /settings');
        exit;
    }
    /**
     * Holiday management page.
     */
    public function holidays(): void
    {
        Auth::requireRole('admin');

        $db = \App\Helpers\Database::connection();

        $stmt = $db->query("
            SELECT
                id,
                holiday_name,
                holiday_date,
                holiday_type,
                description,
                is_active,
                created_at,
                updated_at
            FROM holidays
            ORDER BY holiday_date DESC, holiday_name ASC
        ");

        $holidays = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $error = $_SESSION['holiday_error'] ?? null;
        $success = $_SESSION['holiday_success'] ?? null;

        unset($_SESSION['holiday_error'], $_SESSION['holiday_success']);

        $editHoliday = null;
        if (isset($_GET['edit']) && ctype_digit((string) $_GET['edit'])) {
            $editId = (int) $_GET['edit'];

            $stmt = $db->prepare("
                SELECT
                    id,
                    holiday_name,
                    holiday_date,
                    holiday_type,
                    description,
                    is_active
                FROM holidays
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $editId]);
            $editHoliday = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        }

        $user = Auth::user();

        require __DIR__ . '/../../resources/views/settings/holidays.php';
    }

    /**
     * Create a holiday.
     */
    public function storeHoliday(): void
    {
        Auth::requireRole('admin');

        $name = trim((string) ($_POST['holiday_name'] ?? ''));
        $date = trim((string) ($_POST['holiday_date'] ?? ''));
        $type = trim((string) ($_POST['holiday_type'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $allowedTypes = [
            'Regular Holiday',
            'Special Non-Working Holiday',
            'Special Working Holiday',
        ];

        $errors = [];

        if ($name === '') {
            $errors[] = 'Holiday name is required.';
        }

        $dateValid = false;
        if ($date !== '') {
            $parsed = \DateTime::createFromFormat('Y-m-d', $date);
            $dateValid = $parsed && $parsed->format('Y-m-d') === $date;
        }

        if (!$dateValid) {
            $errors[] = 'A valid holiday date is required.';
        }

        if (!in_array($type, $allowedTypes, true)) {
            $errors[] = 'Please select a valid holiday type.';
        }

        if (empty($errors)) {
            $db = \App\Helpers\Database::connection();

            $stmt = $db->prepare("
                SELECT id
                FROM holidays
                WHERE holiday_date = :holiday_date
                  AND holiday_name = :holiday_name
                LIMIT 1
            ");
            $stmt->execute([
                ':holiday_date' => $date,
                ':holiday_name' => $name,
            ]);

            if ($stmt->fetch()) {
                $errors[] = 'A holiday with the same name and date already exists.';
            }
        }

        if (!empty($errors)) {
            $_SESSION['holiday_error'] = implode(' ', $errors);
            header('Location: /settings/holidays');
            exit;
        }

        $db = \App\Helpers\Database::connection();

        $stmt = $db->prepare("
            INSERT INTO holidays
                (holiday_name, holiday_date, holiday_type, description, is_active, created_at, updated_at)
            VALUES
                (:holiday_name, :holiday_date, :holiday_type, :description, :is_active, NOW(), NOW())
        ");

        $stmt->execute([
            ':holiday_name' => $name,
            ':holiday_date' => $date,
            ':holiday_type' => $type,
            ':description' => $description !== '' ? $description : null,
            ':is_active' => $isActive,
        ]);

        \App\Helpers\AuditLogger::log('holiday.created', 'holiday', (string) $db->lastInsertId());

        $_SESSION['holiday_success'] = "Holiday '{$name}' added successfully.";
        header('Location: /settings/holidays');
        exit;
    }

    /**
     * Update a holiday.
     */
    public function updateHoliday(string $id): void
    {
        Auth::requireRole('admin');

        $holidayId = (int) $id;
        $name = trim((string) ($_POST['holiday_name'] ?? ''));
        $date = trim((string) ($_POST['holiday_date'] ?? ''));
        $type = trim((string) ($_POST['holiday_type'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $allowedTypes = [
            'Regular Holiday',
            'Special Non-Working Holiday',
            'Special Working Holiday',
        ];

        $errors = [];

        if ($holidayId <= 0) {
            $errors[] = 'Invalid holiday.';
        }

        if ($name === '') {
            $errors[] = 'Holiday name is required.';
        }

        $parsed = $date !== ''
            ? \DateTime::createFromFormat('Y-m-d', $date)
            : false;

        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            $errors[] = 'A valid holiday date is required.';
        }

        if (!in_array($type, $allowedTypes, true)) {
            $errors[] = 'Please select a valid holiday type.';
        }

        $db = \App\Helpers\Database::connection();

        if (empty($errors)) {
            $stmt = $db->prepare("
                SELECT id
                FROM holidays
                WHERE holiday_date = :holiday_date
                  AND holiday_name = :holiday_name
                  AND id <> :id
                LIMIT 1
            ");
            $stmt->execute([
                ':holiday_date' => $date,
                ':holiday_name' => $name,
                ':id' => $holidayId,
            ]);

            if ($stmt->fetch()) {
                $errors[] = 'A holiday with the same name and date already exists.';
            }
        }

        if (!empty($errors)) {
            $_SESSION['holiday_error'] = implode(' ', $errors);
            header('Location: /settings/holidays?edit=' . $holidayId);
            exit;
        }

        $stmt = $db->prepare("
            UPDATE holidays
            SET
                holiday_name = :holiday_name,
                holiday_date = :holiday_date,
                holiday_type = :holiday_type,
                description = :description,
                is_active = :is_active,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':holiday_name' => $name,
            ':holiday_date' => $date,
            ':holiday_type' => $type,
            ':description' => $description !== '' ? $description : null,
            ':is_active' => $isActive,
            ':id' => $holidayId,
        ]);

        \App\Helpers\AuditLogger::log('holiday.updated', 'holiday', (string) $holidayId);

        $_SESSION['holiday_success'] = "Holiday '{$name}' updated successfully.";
        header('Location: /settings/holidays');
        exit;
    }

    /**
     * Delete a holiday.
     */
    public function deleteHoliday(string $id): void
    {
        Auth::requireRole('admin');

        $holidayId = (int) $id;

        if ($holidayId <= 0) {
            $_SESSION['holiday_error'] = 'Invalid holiday.';
            header('Location: /settings/holidays');
            exit;
        }

        $db = \App\Helpers\Database::connection();

        $stmt = $db->prepare("SELECT holiday_name FROM holidays WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $holidayId]);
        $holiday = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$holiday) {
            $_SESSION['holiday_error'] = 'Holiday not found.';
            header('Location: /settings/holidays');
            exit;
        }

        $stmt = $db->prepare("DELETE FROM holidays WHERE id = :id");
        $stmt->execute([':id' => $holidayId]);

        \App\Helpers\AuditLogger::log('holiday.deleted', 'holiday', (string) $holidayId);

        $_SESSION['holiday_success'] = "Holiday '{$holiday['holiday_name']}' deleted successfully.";
        header('Location: /settings/holidays');
        exit;
    }

}
