<?php
class Encryption {
    private static $key = null;
    private static $cipher = "aes-256-gcm";
    private static $instance = null;
    
    private function __construct() {
        if (!extension_loaded('openssl')) {
            throw new Exception('OpenSSL extension is required for encryption.');
        }
        
        // Check for existing key or create a new one
        $keyFile = __DIR__ . '/../config/encryption.key';
        if (!file_exists($keyFile)) {
            // Generate a new encryption key
            $key = random_bytes(32); // 256 bits for AES-256
            if (!is_dir(__DIR__ . '/../config')) {
                mkdir(__DIR__ . '/../config', 0755, true);
            }
            file_put_contents($keyFile, base64_encode($key));
            chmod($keyFile, 0600); // Restrict access to the key file
        }
        
        self::$key = base64_decode(file_get_contents($keyFile));
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Encrypts data using AES-256-GCM
     * 
     * @param string $data The data to encrypt
     * @return array Contains the encrypted data, IV, and tag
     */
    public function encrypt($data) {
        if (empty($data)) {
            return [
                'data' => '',
                'iv' => '',
                'tag' => ''
            ];
        }
        
        $iv = random_bytes(openssl_cipher_iv_length(self::$cipher));
        $tag = "";
        
        $encrypted = openssl_encrypt(
            $data,
            self::$cipher,
            self::$key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        return [
            'data' => base64_encode($encrypted),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag)
        ];
    }
    
    /**
     * Decrypts data using AES-256-GCM
     * 
     * @param array $encryptedData Contains the encrypted data, IV, and tag
     * @return string|null The decrypted data or null if decryption fails
     */
    public function decrypt($encryptedData) {
        if (empty($encryptedData) || !is_array($encryptedData)) {
            return null;
        }
        
        try {
            $decrypted = openssl_decrypt(
                base64_decode($encryptedData['data']),
                self::$cipher,
                self::$key,
                OPENSSL_RAW_DATA,
                base64_decode($encryptedData['iv']),
                base64_decode($encryptedData['tag'])
            );
            
            return $decrypted !== false ? $decrypted : null;
        } catch (Exception $e) {
            error_log("Decryption error: " . $e->getMessage());
            return null;
        }
    }
}