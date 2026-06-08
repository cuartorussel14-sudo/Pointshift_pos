<?php
require_once 'Encryption.php';

class User {
    private $db;
    private $id;
    private $username;
    private $email;
    private $role;
    private $firstName;
    private $lastName;
    private $status;
    private $encryption;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->encryption = Encryption::getInstance();
    }
    
    public function login($username, $password) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                // Decrypt email if it's encrypted
                if (!empty($user['email_encrypted'])) {
                    $encryptedEmail = [
                        'data' => $user['email_encrypted'],
                        'iv' => $user['email_iv'],
                        'tag' => $user['email_tag']
                    ];
                    $user['email'] = $this->encryption->decrypt($encryptedEmail);
                }
                
                $this->setUserData($user);
                $this->setSession();

                // Update last_activity for "Last seen" display
                $stmt = $this->db->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
                $stmt->execute([$this->id]);

                return true;
            }
            return false;
        } catch(PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }
    
    public function register($data) {
        try {
            // Begin transaction
            $this->db->beginTransaction();
            
            // Check if username or email exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$data['username'], $data['email']]);
            
            if ($stmt->fetch()) {
                $this->db->rollBack();
                return false;
            }
            
            // Encrypt sensitive data
            $encryptedEmail = $this->encryption->encrypt($data['email']);
            
            // Insert new user with encrypted data
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $this->db->prepare(
                "INSERT INTO users (
                    username, email, email_encrypted, email_iv, email_tag,
                    password, first_name, last_name, role
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, 'staff'
                )"
            );
            
            $result = $stmt->execute([
                $data['username'],
                $data['email'],
                $encryptedEmail['data'],
                $encryptedEmail['iv'],
                $encryptedEmail['tag'],
                $hashedPassword,
                $data['first_name'],
                $data['last_name']
            ]);
            
            if ($result) {
                $this->db->commit();
                return true;
            }
            
            $this->db->rollBack();
            return false;
            
        } catch(PDOException $e) {
            $this->db->rollBack();
            error_log("Registration error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateProfile($data) {
        try {
            $this->db->beginTransaction();
            
            // Encrypt sensitive data
            $encryptedEmail = $this->encryption->encrypt($data['email']);
            
            $sql = "UPDATE users SET 
                    email = ?,
                    email_encrypted = ?,
                    email_iv = ?,
                    email_tag = ?,
                    first_name = ?,
                    last_name = ?";
            
            $params = [
                $data['email'],
                $encryptedEmail['data'],
                $encryptedEmail['iv'],
                $encryptedEmail['tag'],
                $data['first_name'],
                $data['last_name']
            ];
            
            // Add password update if provided
            if (!empty($data['password'])) {
                $sql .= ", password = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $this->id;
            
            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute($params);
            
            if ($success) {
                $this->db->commit();
                $this->email = $data['email'];
                $this->firstName = $data['first_name'];
                $this->lastName = $data['last_name'];
                $this->updateSession();
                return true;
            }
            
            $this->db->rollBack();
            return false;
            
        } catch(PDOException $e) {
            $this->db->rollBack();
            error_log("Profile update error: " . $e->getMessage());
            return false;
        }
    }
    
    private function updateSession() {
        $_SESSION['email'] = $this->email;
        $_SESSION['first_name'] = $this->firstName;
        $_SESSION['last_name'] = $this->lastName;
    }
    
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public static function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: ' . SITE_URL . '/login.php');
            exit();
        }
    }
    
    public static function requireAdmin() {
        self::requireLogin();
        if (!self::isAdmin()) {
            header('Location: ' . SITE_URL . '/dashboard.php');
            exit();
        }
    }
    
    public static function logout() {
        // Update last_activity to the current time for accurate "Last seen" display
        if (isset($_SESSION['user_id'])) {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        }
        session_destroy();
        header('Location: https://pointshift.online/login.php');
        exit();
    }
    
    private function setUserData($user) {
        $this->id = $user['id'];
        $this->username = $user['username'];
        $this->email = $user['email'];
        $this->role = $user['role'];
        $this->firstName = $user['first_name'];
        $this->lastName = $user['last_name'];
        $this->status = $user['status'];
    }
    
    private function setSession() {
        $_SESSION['user_id'] = $this->id;
        $_SESSION['username'] = $this->username;
        $_SESSION['email'] = $this->email;
        $_SESSION['role'] = $this->role;
        $_SESSION['first_name'] = $this->firstName;
        $_SESSION['last_name'] = $this->lastName;
    }
    
    // Getters
    public function getId() { return $this->id; }
    public function getUsername() { return $this->username; }
    public function getEmail() { return $this->email; }
    public function getRole() { return $this->role; }
    public function getFirstName() { return $this->firstName; }
    public function getLastName() { return $this->lastName; }
    public function getFullName() { return $this->firstName . ' ' . $this->lastName; }
    public function getStatus() { return $this->status; }
}