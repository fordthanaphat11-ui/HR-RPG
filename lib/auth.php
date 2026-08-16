<?php
declare(strict_types=1);

function authIsLoggedIn(): bool
{
    return isset($_SESSION['username']) && trim((string) $_SESSION['username']) !== '';
}

function authRole(): string
{
    if (!authIsLoggedIn()) return 'guest';
    return ($_SESSION['auth_role'] ?? 'admin') === 'employee' ? 'employee' : 'admin';
}

function authIsAdmin(): bool
{
    return authRole() === 'admin';
}

function authIsEmployee(): bool
{
    return authRole() === 'employee';
}

function authEmployeeId(): ?int
{
    return authIsEmployee() && isset($_SESSION['employee_id']) ? (int) $_SESSION['employee_id'] : null;
}

function authMustChangePassword(): bool
{
    return authIsEmployee() && !empty($_SESSION['must_change_password']);
}

function authDisplayName(): string
{
    return trim((string) ($_SESSION['display_name'] ?? $_SESSION['username'] ?? 'ผู้ใช้งาน'));
}

function authRedirect(string $location): never
{
    if (isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true') {
        header('HX-Redirect: ' . $location);
    } else {
        header('Location: ' . $location);
    }
    exit;
}

function authCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return (string) $_SESSION['csrf_token'];
}

function authValidateCsrf(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals((string) $_SESSION['csrf_token'], $token);
}

function authStartAdminSession(string $username): void
{
    session_regenerate_id(true);
    $_SESSION['username'] = $username;
    $_SESSION['auth_role'] = 'admin';
    $_SESSION['display_name'] = $username;
    unset($_SESSION['employee_id'], $_SESSION['must_change_password']);
}

function authStartEmployeeSession(array $account): void
{
    session_regenerate_id(true);
    $_SESSION['username'] = (string) $account['username'];
    $_SESSION['auth_role'] = 'employee';
    $_SESSION['employee_id'] = (int) $account['employee_id'];
    $_SESSION['display_name'] = (string) $account['Name'];
    $_SESSION['must_change_password'] = (int) $account['must_change_password'] === 1;
}
