<?php
/**
 * app/models/User.php
 * -----------------------------------------------------------------------
 * User Model
 * -----------------------------------------------------------------------
 * All data access for the users table + login-related security logic
 * (failed attempts, account locking, remember-me tokens, login logs).
 * Every query uses PDO prepared statements - no string concatenation
 * of user input into SQL, ever.
 */

if (!defined('POS_APP')) {
    die('Direct access not permitted.');
}

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Finds a single active user by username, joined with their role name.
     */
    public function findByUsername(string $username): ?array
    {
        $sql = "SELECT u.user_id, u.username, u.password_hash, u.full_name, u.email,
                       u.is_active, u.failed_attempts, u.locked_until, u.branch_id,
                       r.role_id, r.role_name,
                       b.branch_code, b.branch_name, b.is_active AS branch_is_active
                FROM Users u
                INNER JOIN Roles r ON r.role_id = u.role_id
                LEFT JOIN Branches b ON b.branch_id = u.branch_id
                WHERE u.username = :username";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findById(int $userId): ?array
    {
        $sql = "SELECT u.user_id, u.username, u.full_name, u.email, u.is_active, u.branch_id,
                       r.role_id, r.role_name,
                       b.branch_code, b.branch_name
                FROM Users u
                INNER JOIN Roles r ON r.role_id = u.role_id
                LEFT JOIN Branches b ON b.branch_id = u.branch_id
                WHERE u.user_id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $user = $stmt->fetch();
        return $user ?: null;
    }

    /** Finds only active accounts so inactive accounts cannot receive reset links. */
    public function findActiveByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT user_id, full_name, email FROM Users WHERE email = :email AND is_active = 1');
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /** Replaces prior active email-reset links and returns a raw, one-time token. */
    public function createEmailPasswordResetToken(int $userId, string $ip): string
    {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + (30 * 60));
        $this->db->beginTransaction();
        try {
            $revoke = $this->db->prepare("UPDATE EmailPasswordResetTokens SET used_at = GETDATE() WHERE user_id = :id AND used_at IS NULL");
            $revoke->bindValue(':id', $userId, PDO::PARAM_INT);
            $revoke->execute();
            $insert = $this->db->prepare('INSERT INTO EmailPasswordResetTokens (user_id, token_hash, expires_at, request_ip) VALUES (:id, :hash, :expires, :ip)');
            $insert->bindValue(':id', $userId, PDO::PARAM_INT);
            $insert->bindValue(':hash', hash('sha256', $token), PDO::PARAM_STR);
            $insert->bindValue(':expires', $expires, PDO::PARAM_STR);
            $insert->bindValue(':ip', $ip, PDO::PARAM_STR);
            $insert->execute();
            $this->db->commit();
            return $token;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** Atomically consumes a reset token and changes the user's password. */
    public function consumeEmailPasswordResetToken(string $token, string $password): bool
    {
        $this->db->beginTransaction();
        try {
            $find = $this->db->prepare("SELECT token_id, user_id FROM EmailPasswordResetTokens WHERE token_hash = :hash AND used_at IS NULL AND expires_at > GETDATE()");
            $find->bindValue(':hash', hash('sha256', $token), PDO::PARAM_STR);
            $find->execute();
            $row = $find->fetch();
            if (!$row) { $this->db->rollBack(); return false; }
            $updateUser = $this->db->prepare('UPDATE Users SET password_hash = :hash, failed_attempts = 0, locked_until = NULL WHERE user_id = :id');
            $updateUser->bindValue(':hash', password_hash($password, PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS), PDO::PARAM_STR);
            $updateUser->bindValue(':id', (int) $row['user_id'], PDO::PARAM_INT);
            $updateUser->execute();
            $consume = $this->db->prepare('UPDATE EmailPasswordResetTokens SET used_at = GETDATE() WHERE token_id = :id AND used_at IS NULL');
            $consume->bindValue(':id', (int) $row['token_id'], PDO::PARAM_INT);
            $consume->execute();
            $this->db->commit();
            $this->logActivity((int) $row['user_id'], 'PASSWORD_RESET_EMAIL', 'Password reset using email link.');
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function verifyPassword(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }

    /**
     * Checks whether a password hash uses an outdated algorithm/cost
     * and rehashes it transparently on successful login if so.
     */
    public function rehashPasswordIfNeeded(int $userId, string $plainPassword, string $currentHash): void
    {
        if (password_needs_rehash($currentHash, PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS)) {
            $newHash = password_hash($plainPassword, PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS);
            $stmt = $this->db->prepare("UPDATE Users SET password_hash = :hash WHERE user_id = :id");
            $stmt->bindValue(':hash', $newHash, PDO::PARAM_STR);
            $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    // -------------------------------------------------------------
    // Account lockout handling
    // -------------------------------------------------------------

    public function isLocked(array $user): bool
    {
        if (empty($user['locked_until'])) {
            return false;
        }
        return strtotime($user['locked_until']) > time();
    }

    public function registerFailedAttempt(int $userId, int $currentAttempts): void
    {
        $attempts = $currentAttempts + 1;

        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $lockUntil = date('Y-m-d H:i:s', time() + (ACCOUNT_LOCK_MINUTES * 60));
            $sql = "UPDATE Users SET failed_attempts = :attempts, locked_until = :locked
                    WHERE user_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':attempts', $attempts, PDO::PARAM_INT);
            $stmt->bindValue(':locked', $lockUntil, PDO::PARAM_STR);
            $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $sql = "UPDATE Users SET failed_attempts = :attempts WHERE user_id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':attempts', $attempts, PDO::PARAM_INT);
            $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    public function resetFailedAttempts(int $userId): void
    {
        $sql = "UPDATE Users SET failed_attempts = 0, locked_until = NULL WHERE user_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function updateLastLogin(int $userId, string $ip): void
    {
        $sql = "UPDATE Users SET last_login = GETDATE(), last_login_ip = :ip WHERE user_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -------------------------------------------------------------
    // Login logs / audit trail
    // -------------------------------------------------------------

    public function logLoginAttempt(?int $userId, string $username, bool $success, string $ip, string $userAgent): void
    {
        $sql = "INSERT INTO LoginLogs (user_id, username, is_success, ip_address, user_agent, attempted_at)
                VALUES (:user_id, :username, :success, :ip, :ua, GETDATE())";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->bindValue(':success', $success ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
        $stmt->bindValue(':ua', $userAgent, PDO::PARAM_STR);
        $stmt->execute();
    }

    /** Records an employee's request without revealing whether the account exists. */
    public function requestPasswordReset(string $identifier, string $ip): void
    {
        $find = $this->db->prepare('SELECT user_id FROM Users WHERE username = :identifier OR email = :identifier');
        $find->bindValue(':identifier', $identifier, PDO::PARAM_STR);
        $find->execute();
        $user = $find->fetch();
        if (!$user) return;

        $clear = $this->db->prepare("UPDATE PasswordResetRequests SET status = 'superseded' WHERE user_id = :user_id AND status = 'pending'");
        $clear->bindValue(':user_id', (int) $user['user_id'], PDO::PARAM_INT);
        $clear->execute();
        $insert = $this->db->prepare("INSERT INTO PasswordResetRequests (user_id, status, request_ip) VALUES (:user_id, 'pending', :ip)");
        $insert->bindValue(':user_id', (int) $user['user_id'], PDO::PARAM_INT);
        $insert->bindValue(':ip', $ip, PDO::PARAM_STR);
        $insert->execute();
    }

    public function pendingPasswordResetRequests(): array
    {
        $stmt = $this->db->query("SELECT r.request_id, r.requested_at, r.request_ip, u.user_id, u.username, u.full_name FROM PasswordResetRequests r INNER JOIN Users u ON u.user_id = r.user_id WHERE r.status = 'pending' ORDER BY r.requested_at DESC");
        return $stmt->fetchAll();
    }

    public function resolvePasswordResetRequest(int $requestId, string $password, int $adminId): array
    {
        if (strlen($password) < 8) return [false, 'Password must be at least 8 characters.'];
        $this->db->beginTransaction();
        try {
            $find = $this->db->prepare("SELECT user_id FROM PasswordResetRequests WHERE request_id = :id AND status = 'pending'");
            $find->bindValue(':id', $requestId, PDO::PARAM_INT);
            $find->execute();
            $request = $find->fetch();
            if (!$request) { $this->db->rollBack(); return [false, 'This reset request is no longer pending.']; }
            $updateUser = $this->db->prepare('UPDATE Users SET password_hash = :hash, failed_attempts = 0, locked_until = NULL WHERE user_id = :id');
            $updateUser->bindValue(':hash', password_hash($password, PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS), PDO::PARAM_STR);
            $updateUser->bindValue(':id', (int) $request['user_id'], PDO::PARAM_INT);
            $updateUser->execute();
            $resolve = $this->db->prepare("UPDATE PasswordResetRequests SET status = 'resolved', resolved_at = GETDATE(), resolved_by = :admin_id WHERE request_id = :id");
            $resolve->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
            $resolve->bindValue(':id', $requestId, PDO::PARAM_INT);
            $resolve->execute();
            $this->db->commit();
            return [true, null];
        } catch (Exception $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    // -------------------------------------------------------------
    // Remember Me tokens
    // -------------------------------------------------------------

    /**
     * Stores a hashed, selector-based remember-me token.
     * The raw token is only ever sent to the browser cookie - the DB
     * only ever stores its hash (so a DB leak alone can't forge logins).
     */
    public function createRememberToken(int $userId): string
    {
        $selector = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(33));
        $validatorHash = hash('sha256', $validator);
        $expires = date('Y-m-d H:i:s', time() + REMEMBER_ME_LIFETIME);

        $sql = "INSERT INTO UserTokens (user_id, selector, validator_hash, expires_at)
                VALUES (:user_id, :selector, :hash, :expires)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':selector', $selector, PDO::PARAM_STR);
        $stmt->bindValue(':hash', $validatorHash, PDO::PARAM_STR);
        $stmt->bindValue(':expires', $expires, PDO::PARAM_STR);
        $stmt->execute();

        // Cookie value format: selector:validator
        return $selector . ':' . $validator;
    }

    public function findByRememberSelector(string $selector): ?array
    {
        $sql = "SELECT t.token_id, t.user_id, t.validator_hash, t.expires_at
                FROM UserTokens t
                WHERE t.selector = :selector AND t.expires_at > GETDATE()";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':selector', $selector, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function deleteRememberToken(string $selector): void
    {
        $stmt = $this->db->prepare("DELETE FROM UserTokens WHERE selector = :selector");
        $stmt->bindValue(':selector', $selector, PDO::PARAM_STR);
        $stmt->execute();
    }

    public function deleteAllRememberTokensForUser(int $userId): void
    {
        $stmt = $this->db->prepare("DELETE FROM UserTokens WHERE user_id = :id");
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    // -------------------------------------------------------------
    // Activity log (general audit trail, used app-wide later)
    // -------------------------------------------------------------

    public function logActivity(int $userId, string $action, string $description = ''): void
    {
        $sql = "INSERT INTO ActivityLogs (user_id, action, description, ip_address, created_at)
                VALUES (:user_id, :action, :description, :ip, GETDATE())";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':action', $action, PDO::PARAM_STR);
        $stmt->bindValue(':description', $description, PDO::PARAM_STR);
        // Use the same proxy-aware resolver as successful login updates, so
        // Activity Logs and Users.last_login_ip always record the same client IP.
        $stmt->bindValue(':ip', Helper::getClientIp(), PDO::PARAM_STR);
        $stmt->execute();
    }

    // -------------------------------------------------------------
    // User management (Roles & Permissions -> Users tab) + self-service profile
    // -------------------------------------------------------------

    private const SORTABLE = ['username', 'full_name', 'role_name', 'is_active', 'created_at'];

    public function paginate(array $filters, string $sortBy, string $sortDir, int $page, int $perPage): array
    {
        $sortBy  = in_array($sortBy, self::SORTABLE, true) ? $sortBy : 'full_name';
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if (!empty($filters['search'])) {
            $conditions[] = '(u.username LIKE :search_username OR u.full_name LIKE :search_name OR u.email LIKE :search_email)';
            $like = '%' . $filters['search'] . '%';
            $params[':search_username'] = $like;
            $params[':search_name'] = $like;
            $params[':search_email'] = $like;
        }
        if (!empty($filters['role_id'])) {
            $conditions[] = 'u.role_id = :role_id';
            $params[':role_id'] = $filters['role_id'];
        }
        if (array_key_exists('branch_id', $filters) && $filters['branch_id'] !== null && $filters['branch_id'] !== '') {
            if ((int) $filters['branch_id'] === -1) {
                $conditions[] = 'u.branch_id IS NULL';
            } else {
                $conditions[] = 'u.branch_id = :branch_id';
                $params[':branch_id'] = (int) $filters['branch_id'];
            }
        }
        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $base = "FROM Users u INNER JOIN Roles r ON r.role_id = u.role_id LEFT JOIN Branches b ON b.branch_id = u.branch_id {$where}";

        $countStmt = $this->db->prepare("SELECT COUNT(*) AS total {$base}");
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetch()['total'];

        $sortColumn = $sortBy === 'role_name' ? 'r.role_name' : "u.{$sortBy}";
        $sql = "SELECT u.user_id, u.username, u.full_name, u.email, u.is_active, u.last_login,
                       u.branch_id, r.role_id, r.role_name,
                       b.branch_code, b.branch_name
                {$base}
                ORDER BY {$sortColumn} {$sortDir}
                OFFSET :offset ROWS FETCH NEXT :perPage ROWS ONLY";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows' => $stmt->fetchAll(), 'total' => $total, 'page' => $page,
            'per_page' => $perPage, 'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) AS c FROM Users WHERE username = :username" . ($excludeId ? " AND user_id != :id" : "");
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        if ($excludeId) {
            $stmt->bindValue(':id', $excludeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return (int) $stmt->fetch()['c'] > 0;
    }

    public function create(string $username, string $fullName, string $email, string $password, int $roleId, bool $isActive, ?int $branchId = null): array
    {
        if ($this->usernameExists($username)) {
            return [null, 'That username is already taken.'];
        }
        if (strlen($password) < 8) {
            return [null, 'Password must be at least 8 characters.'];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO Users (role_id, branch_id, username, password_hash, full_name, email, is_active)
             OUTPUT INSERTED.user_id
             VALUES (:role_id, :branch_id, :username, :password_hash, :full_name, :email, :is_active)"
        );
        $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->bindValue(':branch_id', $branchId, $branchId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', password_hash($password, PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS), PDO::PARAM_STR);
        $stmt->bindValue(':full_name', $fullName, PDO::PARAM_STR);
        $stmt->bindValue(':email', $email !== '' ? $email : null, $email !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':is_active', $isActive ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();

        return [(int) $stmt->fetch()['user_id'], null];
    }

    /** Username is intentionally not editable here - it's the login identifier and changing it under an active session gets messy fast. */
    public function updateProfile(int $userId, string $fullName, string $email, ?int $roleId, ?bool $isActive, ?int $branchId = null, bool $updateBranch = false): array
    {
        $sets = ['full_name = :full_name', 'email = :email'];
        $params = [
            ':full_name' => $fullName,
            ':email'     => $email !== '' ? $email : null,
            ':id'        => $userId,
        ];

        if ($roleId !== null) {
            $sets[] = 'role_id = :role_id';
            $params[':role_id'] = $roleId;
        }
        if ($isActive !== null) {
            $sets[] = 'is_active = :is_active';
            $params[':is_active'] = $isActive ? 1 : 0;
        }
        if ($updateBranch) {
            $sets[] = 'branch_id = :branch_id';
            $params[':branch_id'] = $branchId;
        }

        $stmt = $this->db->prepare("UPDATE Users SET " . implode(', ', $sets) . " WHERE user_id = :id");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, $key === ':id' || $key === ':role_id' ? PDO::PARAM_INT : ($key === ':is_active' ? PDO::PARAM_INT : PDO::PARAM_STR));
        }
        $stmt->execute();

        return [true, null];
    }

    public function getPasswordHash(int $userId): ?string
    {
        $stmt = $this->db->prepare("SELECT password_hash FROM Users WHERE user_id = :id");
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        return $row ? $row['password_hash'] : null;
    }

    public function setPassword(int $userId, string $newPassword): array
    {
        if (strlen($newPassword) < 8) {
            return [false, 'Password must be at least 8 characters.'];
        }

        $stmt = $this->db->prepare("UPDATE Users SET password_hash = :hash WHERE user_id = :id");
        $stmt->bindValue(':hash', password_hash($newPassword, PASSWORD_HASH_ALGO, PASSWORD_HASH_OPTIONS), PDO::PARAM_STR);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return [true, null];
    }
}
