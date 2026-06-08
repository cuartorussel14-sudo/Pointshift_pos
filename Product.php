<?php

class Product {
    private $db;
    private $conn;
    private $encryption;
    
    public function __construct() {
        require_once 'Database.php';
        require_once 'Encryption.php';
        
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
        $this->encryption = Encryption::getInstance();
    }
    
    /**
     * Creates a new product with encrypted sensitive fields
     */
    public function createProduct($data) {
        // Encrypt sensitive fields
        $nameEncrypted = $this->encryption->encrypt($data['name']);
        $skuEncrypted = $this->encryption->encrypt($data['sku']);
        $barcodeEncrypted = $this->encryption->encrypt($data['barcode']);
        $descriptionEncrypted = $this->encryption->encrypt($data['description']);
        
        $sql = "INSERT INTO products (
            name, name_encrypted, name_iv, name_tag,
            sku, sku_encrypted, sku_iv, sku_tag,
            barcode, barcode_encrypted, barcode_iv, barcode_tag,
            description, description_encrypted, description_iv, description_tag,
            price, quantity, category_id, status
        ) VALUES (
            :name, :name_encrypted, :name_iv, :name_tag,
            :sku, :sku_encrypted, :sku_iv, :sku_tag,
            :barcode, :barcode_encrypted, :barcode_iv, :barcode_tag,
            :description, :description_encrypted, :description_iv, :description_tag,
            :price, :quantity, :category_id, :status
        )";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                'name' => $data['name'],
                'name_encrypted' => $nameEncrypted['data'],
                'name_iv' => $nameEncrypted['iv'],
                'name_tag' => $nameEncrypted['tag'],
                
                'sku' => $data['sku'],
                'sku_encrypted' => $skuEncrypted['data'],
                'sku_iv' => $skuEncrypted['iv'],
                'sku_tag' => $skuEncrypted['tag'],
                
                'barcode' => $data['barcode'],
                'barcode_encrypted' => $barcodeEncrypted['data'],
                'barcode_iv' => $barcodeEncrypted['iv'],
                'barcode_tag' => $barcodeEncrypted['tag'],
                
                'description' => $data['description'],
                'description_encrypted' => $descriptionEncrypted['data'],
                'description_iv' => $descriptionEncrypted['iv'],
                'description_tag' => $descriptionEncrypted['tag'],
                
                'price' => $data['price'],
                'quantity' => $data['quantity'],
                'category_id' => $data['category_id'],
                'status' => $data['status']
            ]);
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating product: " . $e->getMessage());
            throw new Exception("Failed to create product");
        }
    }
    
    /**
     * Updates a product with encrypted sensitive fields
     */
    public function updateProduct($id, $data) {
        // Encrypt sensitive fields
        $nameEncrypted = $this->encryption->encrypt($data['name']);
        $skuEncrypted = $this->encryption->encrypt($data['sku']);
        $barcodeEncrypted = $this->encryption->encrypt($data['barcode']);
        $descriptionEncrypted = $this->encryption->encrypt($data['description']);
        
        $sql = "UPDATE products SET 
            name = :name, 
            name_encrypted = :name_encrypted,
            name_iv = :name_iv,
            name_tag = :name_tag,
            
            sku = :sku,
            sku_encrypted = :sku_encrypted,
            sku_iv = :sku_iv,
            sku_tag = :sku_tag,
            
            barcode = :barcode,
            barcode_encrypted = :barcode_encrypted,
            barcode_iv = :barcode_iv,
            barcode_tag = :barcode_tag,
            
            description = :description,
            description_encrypted = :description_encrypted,
            description_iv = :description_iv,
            description_tag = :description_tag,
            
            price = :price,
            quantity = :quantity,
            category_id = :category_id,
            status = :status
            WHERE id = :id";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'name' => $data['name'],
                'name_encrypted' => $nameEncrypted['data'],
                'name_iv' => $nameEncrypted['iv'],
                'name_tag' => $nameEncrypted['tag'],
                
                'sku' => $data['sku'],
                'sku_encrypted' => $skuEncrypted['data'],
                'sku_iv' => $skuEncrypted['iv'],
                'sku_tag' => $skuEncrypted['tag'],
                
                'barcode' => $data['barcode'],
                'barcode_encrypted' => $barcodeEncrypted['data'],
                'barcode_iv' => $barcodeEncrypted['iv'],
                'barcode_tag' => $barcodeEncrypted['tag'],
                
                'description' => $data['description'],
                'description_encrypted' => $descriptionEncrypted['data'],
                'description_iv' => $descriptionEncrypted['iv'],
                'description_tag' => $descriptionEncrypted['tag'],
                
                'price' => $data['price'],
                'quantity' => $data['quantity'],
                'category_id' => $data['category_id'],
                'status' => $data['status']
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("Error updating product: " . $e->getMessage());
            throw new Exception("Failed to update product");
        }
    }
    
    /**
     * Gets a product with decrypted sensitive fields
     */
    public function getProduct($id) {
        $sql = "SELECT * FROM products WHERE id = :id";
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(['id' => $id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                // Decrypt sensitive fields if they exist
                if (!empty($product['name_encrypted'])) {
                    $product['name'] = $this->encryption->decrypt([
                        'data' => $product['name_encrypted'],
                        'iv' => $product['name_iv'],
                        'tag' => $product['name_tag']
                    ]);
                }
                
                if (!empty($product['sku_encrypted'])) {
                    $product['sku'] = $this->encryption->decrypt([
                        'data' => $product['sku_encrypted'],
                        'iv' => $product['sku_iv'],
                        'tag' => $product['sku_tag']
                    ]);
                }
                
                if (!empty($product['barcode_encrypted'])) {
                    $product['barcode'] = $this->encryption->decrypt([
                        'data' => $product['barcode_encrypted'],
                        'iv' => $product['barcode_iv'],
                        'tag' => $product['barcode_tag']
                    ]);
                }
                
                if (!empty($product['description_encrypted'])) {
                    $product['description'] = $this->encryption->decrypt([
                        'data' => $product['description_encrypted'],
                        'iv' => $product['description_iv'],
                        'tag' => $product['description_tag']
                    ]);
                }
            }
            
            return $product;
        } catch (PDOException $e) {
            error_log("Error getting product: " . $e->getMessage());
            throw new Exception("Failed to get product");
        }
    }
    
    /**
     * Gets all products with decrypted sensitive fields
     */
    public function getAllProducts() {
        $sql = "SELECT * FROM products";
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($products as &$product) {
                // Decrypt sensitive fields if they exist
                if (!empty($product['name_encrypted'])) {
                    $product['name'] = $this->encryption->decrypt([
                        'data' => $product['name_encrypted'],
                        'iv' => $product['name_iv'],
                        'tag' => $product['name_tag']
                    ]);
                }
                
                if (!empty($product['sku_encrypted'])) {
                    $product['sku'] = $this->encryption->decrypt([
                        'data' => $product['sku_encrypted'],
                        'iv' => $product['sku_iv'],
                        'tag' => $product['sku_tag']
                    ]);
                }
                
                if (!empty($product['barcode_encrypted'])) {
                    $product['barcode'] = $this->encryption->decrypt([
                        'data' => $product['barcode_encrypted'],
                        'iv' => $product['barcode_iv'],
                        'tag' => $product['barcode_tag']
                    ]);
                }
                
                if (!empty($product['description_encrypted'])) {
                    $product['description'] = $this->encryption->decrypt([
                        'data' => $product['description_encrypted'],
                        'iv' => $product['description_iv'],
                        'tag' => $product['description_tag']
                    ]);
                }
            }
            
            return $products;
        } catch (PDOException $e) {
            error_log("Error getting all products: " . $e->getMessage());
            throw new Exception("Failed to get products");
        }
    }
    
    /**
     * Searches for products with decrypted sensitive fields
     */
    public function searchProducts($term) {
        $sql = "SELECT * FROM products WHERE 
            name LIKE :term OR 
            sku LIKE :term OR 
            barcode LIKE :term OR 
            description LIKE :term";
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute(['term' => "%$term%"]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($products as &$product) {
                // Decrypt sensitive fields if they exist
                if (!empty($product['name_encrypted'])) {
                    $product['name'] = $this->encryption->decrypt([
                        'data' => $product['name_encrypted'],
                        'iv' => $product['name_iv'],
                        'tag' => $product['name_tag']
                    ]);
                }
                
                if (!empty($product['sku_encrypted'])) {
                    $product['sku'] = $this->encryption->decrypt([
                        'data' => $product['sku_encrypted'],
                        'iv' => $product['sku_iv'],
                        'tag' => $product['sku_tag']
                    ]);
                }
                
                if (!empty($product['barcode_encrypted'])) {
                    $product['barcode'] = $this->encryption->decrypt([
                        'data' => $product['barcode_encrypted'],
                        'iv' => $product['barcode_iv'],
                        'tag' => $product['barcode_tag']
                    ]);
                }
                
                if (!empty($product['description_encrypted'])) {
                    $product['description'] = $this->encryption->decrypt([
                        'data' => $product['description_encrypted'],
                        'iv' => $product['description_iv'],
                        'tag' => $product['description_tag']
                    ]);
                }
            }
            
            return $products;
        } catch (PDOException $e) {
            error_log("Error searching products: " . $e->getMessage());
            throw new Exception("Failed to search products");
        }
    }
}
