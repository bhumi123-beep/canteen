<?php
require_once __DIR__ . '/../models/User.php';

class AuthController {
    private $userModel;

    public function __construct($db) {
        $this->userModel = new User($db);
    }

    public function login($email, $password, $expectedRole) {
        $email = trim((string)$email);
        $expectedRole = Validator::role($expectedRole);
        if ($expectedRole === false || $email === '' || strlen($email) > 254 || strlen((string)$password) > 128) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }
        if (!$this->userModel->loginAllowed($email)) {
            return ['success' => false, 'message' => 'Too many failed attempts. Please try again in 15 minutes.'];
        }
        $user = $this->userModel->findByLogin($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->userModel->recordLoginFailure($email);
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }
        if (($user['is_active'] ?? 1) != 1 || ($user['status'] ?? '') === 'removed') {
            $this->userModel->recordLoginFailure($email);
            return ['success' => false, 'message' => 'This account is inactive. Please contact an administrator.'];
        }
        if ($user['role'] !== $expectedRole) {
            $this->userModel->recordLoginFailure($email);
            return ['success' => false, 'message' => "This account is not registered as $expectedRole."];
        }
        $staff = null;
        if ($user['role'] === 'staff') {
            $staff = $this->userModel->getStaffForUser($user['id']);
            if (!$staff) {
                return ['success' => false, 'message' => 'This staff account has no active staff profile.'];
            }
        }
        $this->userModel->clearLoginFailures($email);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        unset($_SESSION['staff_id'], $_SESSION['staff_role']);
        if ($staff) {
            $_SESSION['staff_id'] = (int)$staff['id'];
            $_SESSION['staff_role'] = $staff['staff_role'];
        }
        $this->userModel->updateLastLogin($user['id']);
        return ['success' => true, 'role' => $user['role'], 'staff_role' => $staff['staff_role'] ?? null];
    }

    public function register($name, $email, $password, $phone) {
        $name = Validator::name($name);
        $email = Validator::email($email);
        $password = Validator::password($password);
        $phone = Validator::phone($phone);
        if ($name === false || $email === false || $password === false || $phone === false) {
            return ['success' => false, 'message' => 'Please provide valid registration details.'];
        }
        if ($this->userModel->findByEmail($email)) {
            return ['success' => false, 'message' => 'Email already registered.'];
        }
        $id = $this->userModel->create($name, $email, $password, 'customer', $phone);
        return ['success' => true, 'id' => $id];
    }

    public function logout() {
        session_unset();
        session_destroy();
    }
}
