<?php
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        // If the PDO extension isn't available, throw a clear exception so the caller
        // (for example an admin diagnostics page) can show a helpful message instead
        // of producing an opaque 500 internal server error when the file is included.
        if (!class_exists('PDO')) {
            throw new Exception("PDO extension is not enabled. Please enable the 'pdo' and 'pdo_mysql' PHP extensions.");
        }

        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            // Throw an exception instead of terminating the script so callers can handle the error
            throw new Exception("Connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Return the active PDO connection. The return type is omitted to avoid a
     * fatal error on hosts where the PDO class is not available at include time.
     *
     * @return mixed|null PDO instance or null if not connected
     */
    public function getConnection() {
        return $this->connection;
    }
}
?>
