<?php
class User {
    private $conn;
    private $table = 'users';
    private $hasStatusColumn = null;
    private $hasSalaryColumn = null;

    public function __construct($db) {
        $this->conn = $db;
        $this->ensureUserCredentialsSchema();
        $this->ensureUserRoleSchema();
        $this->ensureStaffTableSchema();
        $this->conn->exec("CREATE TABLE IF NOT EXISTS auth_login_attempts (
            identifier VARCHAR(190) PRIMARY KEY,
            failed_count INT NOT NULL DEFAULT 0,
            locked_until DATETIME NULL,
            last_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function loginAllowed($identifier) {
        $stmt = $this->conn->prepare("SELECT locked_until FROM auth_login_attempts WHERE identifier = ? LIMIT 1");
        $stmt->execute([strtolower(trim($identifier))]);
        $lockedUntil = $stmt->fetchColumn();
        return !$lockedUntil || strtotime($lockedUntil) <= time();
    }

    public function recordLoginFailure($identifier) {
        $identifier = strtolower(trim($identifier));
        $stmt = $this->conn->prepare("INSERT INTO auth_login_attempts (identifier, failed_count, locked_until) VALUES (?, 1, NULL) ON DUPLICATE KEY UPDATE failed_count = failed_count + 1, locked_until = IF(failed_count + 1 >= 5, DATE_ADD(NOW(), INTERVAL 15 MINUTE), locked_until), last_attempt_at = NOW()");
        $stmt->execute([$identifier]);
    }

    public function clearLoginFailures($identifier) {
        $stmt = $this->conn->prepare("DELETE FROM auth_login_attempts WHERE identifier = ?");
        $stmt->execute([strtolower(trim($identifier))]);
    }

    private function columnExists($column) {
        if ($column === 'status' && $this->hasStatusColumn !== null) {
            return $this->hasStatusColumn;
        }
        if ($column === 'salary' && $this->hasSalaryColumn !== null) {
            return $this->hasSalaryColumn;
        }

        $stmt = $this->conn->prepare("SHOW COLUMNS FROM {$this->table} LIKE ?");
        $stmt->execute([$column]);
        $exists = (bool)$stmt->fetch(PDO::FETCH_ASSOC);

        if ($column === 'status') {
            $this->hasStatusColumn = $exists;
        }
        if ($column === 'salary') {
            $this->hasSalaryColumn = $exists;
        }

        return $exists;
    }

    private function ensureUserCredentialsSchema() {
        $check = $this->conn->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'username'");
        $check->execute();

        if ((int)$check->fetchColumn() === 0) {
            try {
                $this->conn->exec("ALTER TABLE users ADD COLUMN username VARCHAR(80) NULL AFTER email");
                $this->conn->exec("CREATE UNIQUE INDEX idx_users_username ON users(username)");
            } catch (PDOException $e) {
                // Ignore drift errors for older local schemas.
            }
        }
    }

    private function ensureUserRoleSchema() {
        $check = $this->conn->prepare("SELECT column_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'role'");
        $check->execute();
        $columnType = strtolower((string)$check->fetchColumn());

        if ($columnType === '') {
            $this->conn->exec("ALTER TABLE users ADD COLUMN role ENUM('customer','admin','staff') NOT NULL DEFAULT 'customer' AFTER password_hash");
            return;
        }

        if (strpos($columnType, "'staff'") === false) {
            $this->conn->exec("ALTER TABLE users MODIFY COLUMN role ENUM('customer','admin','staff') NOT NULL DEFAULT 'customer'");
        }
    }

    private function generateUniqueUsername($name, $email) {
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '.', trim($name)) ?? 'staff');
        $base = trim($base, '.');

        if ($base === '') {
            $base = strtolower(strstr($email, '@', true) ?: 'staff');
        }

        $safeBase = preg_replace('/[^a-z0-9._-]+/', '', $base) ?: 'staff';
        $candidate = $safeBase;
        $suffix = 1;

        while ($this->usernameExists($candidate)) {
            $candidate = $safeBase . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function usernameExists($username) {
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function ensureStaffTableSchema() {
        $requiredColumns = [
            'staff_role' => "ALTER TABLE staff_management ADD COLUMN staff_role VARCHAR(60) NOT NULL DEFAULT 'Service Staff' AFTER staff_email",
            'department' => "ALTER TABLE staff_management ADD COLUMN department VARCHAR(80) NOT NULL DEFAULT 'Operations' AFTER staff_role",
            'staff_phone' => "ALTER TABLE staff_management ADD COLUMN staff_phone VARCHAR(20) NULL AFTER staff_role",
            'staff_shift' => "ALTER TABLE staff_management ADD COLUMN staff_shift ENUM('Morning','Evening','Night') NOT NULL DEFAULT 'Morning' AFTER staff_phone",
            'is_active' => "ALTER TABLE staff_management ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER staff_status",
            'deleted_at' => "ALTER TABLE staff_management ADD COLUMN deleted_at DATETIME NULL AFTER is_active",
        ];

        foreach ($requiredColumns as $column => $sql) {
            $check = $this->conn->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'staff_management' AND column_name = ?");
            $check->execute([$column]);

            if ((int)$check->fetchColumn() === 0) {
                try {
                    $this->conn->exec($sql);
                } catch (PDOException $e) {
                    // Ignore schema drift failure here since the page will still load for valid databases.
                }
            }
        }
    }

    private function ensureStaffColumns() {
        if (!$this->columnExists('salary')) {
            try {
                $this->conn->exec("ALTER TABLE {$this->table} ADD COLUMN salary DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER role");
                $this->hasSalaryColumn = true;
            } catch (PDOException $e) {
                $this->hasSalaryColumn = false;
            }
        }

        if (!$this->columnExists('status')) {
            try {
                $this->conn->exec("ALTER TABLE {$this->table} ADD COLUMN status ENUM('on_duty','on_leave','removed') NOT NULL DEFAULT 'on_duty' AFTER salary");
                $this->hasStatusColumn = true;
            } catch (PDOException $e) {
                $this->hasStatusColumn = false;
            }
        }
    }

    public function getTotalMonthlyPayroll() {
        $stmt = $this->conn->prepare("SELECT COALESCE(SUM(staff_salary), 0) FROM staff_management WHERE staff_status != 'removed'");
        $stmt->execute();
        return (float)$stmt->fetchColumn();
    }

    public function countOrdersHandledByStaff($staffId) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM staff_ratings WHERE staff_id = ?");
        $stmt->execute([(int)$staffId]);
        return (int)$stmt->fetchColumn();
    }

    public function findByEmail($email) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByLogin($login) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE email = ? OR username = ? LIMIT 1");
        $stmt->execute([$login, $login]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getStaffById($id) {
        $stmt = $this->conn->prepare("SELECT id, staff_name, staff_email, staff_role, department, staff_phone, staff_shift, staff_salary, staff_status, performance_rating, rating_count FROM staff_management WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getStaffForUser($userId) {
        $stmt = $this->conn->prepare("SELECT sm.* FROM staff_management sm INNER JOIN users u ON u.email = sm.staff_email WHERE u.id = ? AND u.role = 'staff' AND sm.is_active = 1 AND sm.deleted_at IS NULL LIMIT 1");
        $stmt->execute([(int)$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($name, $email, $password, $role = 'customer', $phone = null, $salary = 0, $status = 'on_duty', $designation = 'Service Staff', $shift = 'Morning', $department = 'Operations') {
        $name = Validator::name($name);
        $email = Validator::email($email);
        $password = Validator::password($password);
        $phone = Validator::phone($phone);
        $salary = Validator::price($salary);
        $roles = ['customer', 'admin', 'staff'];
        $designations = ['Manager', 'Chef', 'Cook', 'Waiter', 'Cashier', 'Inventory Manager', 'Finance', 'Cleaner', 'Service Staff'];
        if ($name === false || $email === false || $password === false || $phone === false || $salary === false || !in_array($role, $roles, true)) {
            return false;
        }
        if ($this->findByEmail($email)) {
            return false;
        }
        if (!in_array($status, ['on_duty', 'on_leave'], true)) {
            $status = 'on_duty';
        }
        if (!in_array($designation, $designations, true) || !in_array($shift, ['Morning', 'Evening', 'Night'], true) || !in_array($department, ['Operations', 'Inventory', 'Finance'], true)) {
            return false;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);

        if ($role === 'staff') {
            $status = in_array($status, ['on_duty', 'on_leave'], true) ? $status : 'on_duty';
            $startedTransaction = !$this->conn->inTransaction();

            if ($startedTransaction) {
                $this->conn->beginTransaction();
            }

            try {
                $staffStmt = $this->conn->prepare("INSERT INTO staff_management (staff_name, staff_email, staff_role, department, staff_phone, staff_shift, staff_salary, staff_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $staffStmt->execute([$name, $email, $designation, $department, $phone, $shift, (float)$salary, $status]);

                $staffId = (int)$this->conn->lastInsertId();
                $username = $this->generateUniqueUsername($name, $email);

                $userStmt = $this->conn->prepare("INSERT INTO users (name, email, username, password_hash, role, phone, status, salary) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $userStmt->execute([$name, $email, $username, $hash, 'staff', $phone, $status, (float)$salary]);

                if ($startedTransaction) {
                    $this->conn->commit();
                }

                return $staffId;
            } catch (Throwable $exception) {
                if ($startedTransaction && $this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }
                throw $exception;
            }
        }

        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (name, email, username, password_hash, role, phone) VALUES (?, ?, ?, ?, ?, ?)");
        $username = $this->generateUniqueUsername($name, $email);
        $stmt->execute([$name, $email, $username, $hash, $role, $phone]);
        return (int)$this->conn->lastInsertId();
    }

    public function promoteToStaff($account, $password, $salary, $status, $designation, $shift, $phone) {
        if (!$account || ($account['role'] ?? '') !== 'customer') {
            return false;
        }

        $password = Validator::password($password);
        $salary = Validator::price($salary);
        $phone = Validator::phone($phone);
        if ($password === false || $salary === false || $phone === false || !in_array($status, ['on_duty', 'on_leave'], true) || !in_array($designation, ['Manager', 'Chef', 'Cook', 'Waiter', 'Cashier', 'Inventory Manager', 'Finance', 'Cleaner', 'Service Staff'], true) || !in_array($shift, ['Morning', 'Evening', 'Night'], true)) {
            return false;
        }
        $startedTransaction = !$this->conn->inTransaction();
        if ($startedTransaction) {
            $this->conn->beginTransaction();
        }

        try {
            $staffStmt = $this->conn->prepare("INSERT INTO staff_management (staff_name, staff_email, staff_role, department, staff_phone, staff_shift, staff_salary, staff_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $staffStmt->execute([$account['name'], $account['email'], $designation, 'Operations', $phone, $shift, (float)$salary, $status]);

            $userStmt = $this->conn->prepare("UPDATE users SET password_hash = ?, role = 'staff', phone = ?, salary = ?, status = ?, is_active = 1 WHERE id = ? AND role = 'customer'");
            $userStmt->execute([password_hash($password, PASSWORD_DEFAULT), $phone, (float)$salary, $status, (int)$account['id']]);

            if ($startedTransaction) {
                $this->conn->commit();
            }

            return $userStmt->rowCount() === 1;
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $exception;
        }
    }

    public function updateStaff($id, $name, $email, $salary, $role, $phone, $shift, $status, $department = 'Operations') {
        $id = Validator::positiveInteger($id);
        $name = Validator::name($name);
        $email = Validator::email($email);
        $salary = Validator::price($salary);
        $phone = Validator::phone($phone);
        $allowedRoles = ['Manager', 'Chef', 'Cook', 'Waiter', 'Cashier', 'Inventory Manager', 'Finance', 'Cleaner', 'Service Staff'];
        $allowedStatus = ['on_duty', 'on_leave', 'removed'];
        if ($id === false || $name === false || $email === false || $salary === false || $phone === false || !in_array($role, $allowedRoles, true) || !in_array($shift, ['Morning', 'Evening', 'Night'], true) || !in_array($department, ['Operations', 'Inventory', 'Finance'], true)) {
            return false;
        }
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'on_duty';
        }

        $existing = $this->getStaffById($id);
        if (!$existing) {
            return false;
        }
        $emailStmt = $this->conn->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $emailStmt->execute([$email, $this->staffUserId($existing['staff_email'])]);
        if ($emailStmt->fetchColumn()) {
            return false;
        }

        $startedTransaction = !$this->conn->inTransaction();
        if ($startedTransaction) {
            $this->conn->beginTransaction();
        }

        try {
            $stmt = $this->conn->prepare("UPDATE staff_management SET staff_name = ?, staff_email = ?, staff_salary = ?, staff_role = ?, department = ?, staff_phone = ?, staff_shift = ?, staff_status = ? WHERE id = ?");
            $updated = $stmt->execute([trim($name), trim($email), (float)$salary, trim($role), trim($department), trim($phone), trim($shift), $status, (int)$id]);

            $userStmt = $this->conn->prepare("UPDATE users SET name = ?, email = ?, salary = ?, phone = ?, status = ? WHERE email = ? AND role = 'staff'");
            $userStmt->execute([trim($name), trim($email), (float)$salary, trim($phone), $status, $existing['staff_email']]);

            if ($startedTransaction) {
                $this->conn->commit();
            }

            return $updated;
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $exception;
        }
    }

    private function staffUserId($email) {
        $stmt = $this->conn->prepare('SELECT id FROM users WHERE email = ? AND role = \'staff\' LIMIT 1');
        $stmt->execute([$email]);
        return (int)$stmt->fetchColumn();
    }

    public function updateLastLogin($id) {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET last_login_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function countActiveCustomers() {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'customer'");
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function listByRole($role) {
        if ($role === 'staff') {
            $stmt = $this->conn->prepare("SELECT sm.id, sm.staff_name AS name, sm.staff_email AS email, sm.staff_role AS role, sm.department, sm.staff_phone AS phone, sm.staff_shift AS shift, sm.staff_salary AS salary, sm.staff_status AS status, sm.performance_rating, sm.rating_count, u.username, u.id AS user_id FROM staff_management sm LEFT JOIN users u ON u.email = sm.staff_email AND u.role = 'staff' WHERE sm.is_active = 1 AND sm.deleted_at IS NULL ORDER BY sm.staff_name ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        if ($this->columnExists('status')) {
            $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE role = ? AND status != 'removed' ORDER BY name ASC");
            $stmt->execute([$role]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE role = ? ORDER BY name ASC");
        $stmt->execute([$role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listActiveAccounts($role = 'all', $searchTerm = '') {
        $sql = "SELECT u.id, u.name, u.username, u.email, u.phone, u.role, u.status, u.is_active, u.last_login_at, u.created_at,
                    sm.staff_role, sm.staff_shift
                FROM users u
                LEFT JOIN staff_management sm ON sm.staff_email = u.email AND sm.is_active = 1 AND sm.deleted_at IS NULL
                WHERE u.is_active = 1 AND u.status != 'removed'";
        $params = [];

        if (in_array($role, ['customer', 'staff', 'admin'], true)) {
            $sql .= " AND u.role = ?";
            $params[] = $role;
        }

        $searchTerm = trim($searchTerm);
        if ($searchTerm !== '') {
            $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
            $searchPattern = '%' . $searchTerm . '%';
            $params[] = $searchPattern;
            $params[] = $searchPattern;
            $params[] = $searchPattern;
        }

        $sql .= " ORDER BY u.role ASC, u.name ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countByRole($role) {
        if ($role === 'staff') {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM staff_management WHERE is_active = 1 AND deleted_at IS NULL");
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        }

        if ($this->columnExists('status')) {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table} WHERE role = ? AND status != 'removed'");
            $stmt->execute([$role]);
            return (int)$stmt->fetchColumn();
        }

        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table} WHERE role = ?");
        $stmt->execute([$role]);
        return (int)$stmt->fetchColumn();
    }

    public function countStaffByStatus($status) {
        $allowedStatus = ['on_duty', 'on_leave'];
        if (!in_array($status, $allowedStatus, true)) {
            return 0;
        }

        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM staff_management WHERE staff_status = ? AND is_active = 1 AND deleted_at IS NULL");
        $stmt->execute([$status]);
        return (int)$stmt->fetchColumn();
    }

    public function setStaffStatus($id, $status) {
        $allowed = ['on_duty', 'on_leave', 'removed'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $staff = $this->getStaffById($id);
        if (!$staff) {
            return false;
        }

        $stmt = $this->conn->prepare("UPDATE staff_management SET staff_status = ? WHERE id = ? AND is_active = 1 AND deleted_at IS NULL");
        $updated = $stmt->execute([$status, $id]);
        $userStmt = $this->conn->prepare("UPDATE users SET status = ? WHERE email = ? AND role = 'staff'");
        $userStmt->execute([$status, $staff['staff_email']]);
        return $updated;
    }

    public function deleteStaff($id) {
        $staff = $this->getStaffById($id);
        $stmt = $this->conn->prepare("UPDATE staff_management SET is_active = 0, deleted_at = NOW() WHERE id = ? AND is_active = 1");
        $deleted = $stmt->execute([$id]);
        if ($staff) {
            $userStmt = $this->conn->prepare("UPDATE users SET status = 'removed', is_active = 0 WHERE email = ? AND role = 'staff'");
            $userStmt->execute([$staff['staff_email']]);
        }
        return $deleted;
    }

    public function deactivateAccount($id) {
        $account = $this->findById((int)$id);
        if (!$account || (int)$account['id'] === (int)($_SESSION['user_id'] ?? 0)) {
            return false;
        }

        $startedTransaction = !$this->conn->inTransaction();
        if ($startedTransaction) {
            $this->conn->beginTransaction();
        }

        try {
            $stmt = $this->conn->prepare("UPDATE users SET is_active = 0, status = 'removed' WHERE id = ?");
            $updated = $stmt->execute([(int)$id]);

            if ($account['role'] === 'staff') {
                $staffStmt = $this->conn->prepare("UPDATE staff_management SET is_active = 0, deleted_at = NOW(), staff_status = 'removed' WHERE staff_email = ? AND is_active = 1");
                $staffStmt->execute([$account['email']]);
            }

            if ($startedTransaction) {
                $this->conn->commit();
            }

            return $updated;
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $exception;
        }
    }
}
