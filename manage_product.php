<?php
require_once '../config.php';
User::requireLogin();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}
// Get DB connection
$db = Database::getInstance()->getConnection();
// Fetch categories for dropdown
$categories = $db->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
// View filter for stats/preset product listing (all, low_stock, out_of_stock, in_stock)
$view = isset($_GET['view']) ? $_GET['view'] : 'all';

// Pagination setup
$perPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$whereConditions = [];
$whereConditions[] = "p.status = 'active'"; // Only show active products to staff
$params = [];

if (!empty($search)) {
  $whereConditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter > 0) {
    $whereConditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

// Apply view filter to product listing (affects which products are shown/listed)
if ($view === 'low_stock') {
    $whereConditions[] = "p.stock_quantity <= p.low_stock_threshold AND p.stock_quantity > 0";
} elseif ($view === 'out_of_stock') {
    $whereConditions[] = "p.stock_quantity = 0";
} elseif ($view === 'in_stock') {
    $whereConditions[] = "p.stock_quantity > 0";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Count total products with filters
$countStmt = $db->prepare("SELECT COUNT(*) FROM products p $whereClause");
$countStmt->execute($params);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $perPage);

// Add product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $expiry = !empty($_POST['expiry']) ? $_POST['expiry'] : null;
    $stmt = $db->prepare("INSERT INTO products (name, sku, category_id, price, stock_quantity, low_stock_threshold, barcode, expiry, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['name'], $_POST['sku'], $_POST['category_id'], $_POST['price'], $_POST['stock_quantity'], $_POST['low_stock_threshold'],
        $_POST['barcode'], $expiry, $_POST['status']
    ]);
  // Create stock notification for newly added product if initial stock is low or out of stock
  try {
    require_once __DIR__ . '/../classes/Notification.php';
    $newId = $db->lastInsertId();
    $initialStock = intval($_POST['stock_quantity']);
    $storedThreshold = intval($_POST['low_stock_threshold'] ?? 10);
    if ($initialStock <= 0) {
      Notification::create($db, "Out of Stock: {$_POST['name']} is now out of stock.", 'out_of_stock', $newId);
      @file_put_contents(__DIR__ . '/../logs/notifications_actions.log', date('[Y-m-d H:i:s] ') . "create out_of_stock product_id={$newId} by_user=" . ($_SESSION['user_id'] ?? 'null') . "\n", FILE_APPEND | LOCK_EX);
    } elseif ($initialStock <= $storedThreshold) {
      Notification::create($db, "Low Stock Alert: {$_POST['name']} has only {$initialStock} items left.", 'low_stock', $newId);
      @file_put_contents(__DIR__ . '/../logs/notifications_actions.log', date('[Y-m-d H:i:s] ') . "create low_stock product_id={$newId} by_user=" . ($_SESSION['user_id'] ?? 'null') . "\n", FILE_APPEND | LOCK_EX);
    }
  } catch (Exception $e) {
    // ignore notification errors
  }
  // Create expiry notifications for newly added product (immediate or upcoming within 3 days)
  try {
    if ($expiry) {
      $expiryDate = date('Y-m-d', strtotime($expiry));
      $today = date('Y-m-d');
      $diff = (int)ceil((strtotime($expiryDate) - strtotime($today)) / 86400);
      if ($expiryDate <= $today) {
        // immediate expired
        Notification::create($db, "Product '{$_POST['name']}' has expired on {$expiryDate}.", 'expiry', $newId);
        @file_put_contents(__DIR__ . '/../logs/expiry_notifications.log', date('[Y-m-d H:i:s] ') . "manage_product:add created expiry notif for product_id={$newId}, expiry={$expiryDate}\n", FILE_APPEND);
      } elseif ($diff > 0 && $diff <= 3) {
        // upcoming expiry within threshold
        Notification::create($db, "Expiry Alert: Product '{$_POST['name']}' will expire on {$expiryDate} (in {$diff} days).", 'expiry', $newId);
        @file_put_contents(__DIR__ . '/../logs/expiry_notifications.log', date('[Y-m-d H:i:s] ') . "manage_product:add created upcoming expiry notif for product_id={$newId}, expiry={$expiryDate}, days={$diff}\n", FILE_APPEND);
      }
    }
  } catch (Exception $e) {
    @file_put_contents(__DIR__ . '/../logs/expiry_notifications.log', date('[Y-m-d H:i:s] ') . "manage_product:add error creating expiry notif: " . $e->getMessage() . "\n", FILE_APPEND);
  }
  // Create an add-product notification visible to admin/staff
  try {
    require_once __DIR__ . '/../classes/Notification.php';
    $actorId = $_SESSION['user_id'] ?? null;
    $actorName = 'Staff';
    if ($actorId) {
      $uStmt = $db->prepare("SELECT first_name, last_name, username FROM users WHERE id = ?");
      $uStmt->execute([$actorId]);
      $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
      if ($uRow) {
        $actorName = trim(($uRow['first_name'] ?? '') . ' ' . ($uRow['last_name'] ?? '')) ?: ($uRow['username'] ?? $actorName);
      }
    }
    Notification::create($db, "Product '{$_POST['name']}' (ID: {$newId}) was added by {$actorName}.", 'product_added', $newId);
  @file_put_contents(__DIR__ . '/../logs/notifications_actions.log', date('[Y-m-d H:i:s] ') . "create added product_id={$newId} by_user=" . ($_SESSION['user_id'] ?? 'null') . " actor_name={$actorName}\n", FILE_APPEND | LOCK_EX);
  } catch (Exception $e) {
    // ignore notification errors
  }
    echo '<script>document.addEventListener("DOMContentLoaded",function(){
        document.getElementById("productForm").reset();
        document.getElementById("product_id").value = "";
        document.getElementById("modalAddBtn").classList.remove("d-none");
        document.getElementById("modalUpdateBtn").classList.add("d-none");
    });</script>';
    header('Location: manage_product.php');
    exit();
}

// Update product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $product_id = intval($_POST['id']);
    $new_stock = intval($_POST['stock_quantity']);
    $user_id = $_SESSION['user_id'];
    
  // Get old stock quantity and expiry to track changes
  $oldStockStmt = $db->prepare("SELECT stock_quantity, name, expiry FROM products WHERE id = ?");
    $oldStockStmt->execute([$product_id]);
    $oldProduct = $oldStockStmt->fetch(PDO::FETCH_ASSOC);
    $old_stock = intval($oldProduct['stock_quantity']);
    $product_name = $oldProduct['name'];
  $old_expiry = $oldProduct['expiry'] ?? null;
    
    // Calculate stock change
    $stock_change = $new_stock - $old_stock;
    
    // Update product
    $expiry = !empty($_POST['expiry']) ? $_POST['expiry'] : null;
    $stmt = $db->prepare("UPDATE products SET name=?, sku=?, category_id=?, price=?, stock_quantity=?, low_stock_threshold=?, barcode=?, expiry=?, status=?, last_updated_by=? WHERE id=?");
    $stmt->execute([
        $_POST['name'], $_POST['sku'], $_POST['category_id'], $_POST['price'], $new_stock, $_POST['low_stock_threshold'],
        $_POST['barcode'], $expiry, $_POST['status'], $user_id, $product_id
    ]);
    
    // Record inventory change in inventory_reports if stock quantity changed
    if ($stock_change != 0) {
        $change_type = ($stock_change > 0) ? 'Added' : 'Removed';
        $quantity_changed = abs($stock_change);
        $remarks = ($stock_change > 0) 
            ? "Stock added by staff. Previous: $old_stock, New: $new_stock" 
            : "Stock removed by staff. Previous: $old_stock, New: $new_stock";
        
        $reportStmt = $db->prepare("INSERT INTO inventory_reports (product_id, change_type, quantity, quantity_changed, previous_quantity, new_quantity, date, remarks, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, NOW())");
        $reportStmt->execute([
            $product_id,
            $change_type,
            $quantity_changed,  // Use same value for 'quantity' (legacy column)
            $quantity_changed,
            $old_stock,
            $new_stock,
            $remarks,
            $user_id
        ]);
        // Create notifications for low stock / out of stock
    require_once __DIR__ . '/..//classes/Notification.php';
    try {
      $dbNotification = Database::getInstance()->getConnection();
      // Ensure we use the saved product low_stock_threshold from DB (keeps admin/staff consistent)
      $thStmt = $db->prepare("SELECT low_stock_threshold FROM products WHERE id = ?");
      $thStmt->execute([$product_id]);
      $saved_threshold = intval($thStmt->fetchColumn() ?? 0);

      if ($new_stock <= 0) {
        Notification::create($dbNotification, "Out of Stock: {$product_name} is now out of stock.", 'out_of_stock', $product_id);
      } elseif ($new_stock <= $saved_threshold) {
        Notification::create($dbNotification, "Low Stock Alert: {$product_name} has only {$new_stock} items left.", 'low_stock', $product_id);
      }
    } catch (Exception $e) {
      // swallow notification errors to avoid blocking product updates
    }
  // If expiry changed, create expiry notifications (immediate or upcoming within 3 days)
  try {
    if ($expiry) {
      $newExpiryDate = date('Y-m-d', strtotime($expiry));
      $today = date('Y-m-d');
      $oldExpiryNormalized = $old_expiry ? date('Y-m-d', strtotime($old_expiry)) : null;
      $diff = (int)ceil((strtotime($newExpiryDate) - strtotime($today)) / 86400);
      // Only create notifications when expiry actually changed
      if ($newExpiryDate !== $oldExpiryNormalized) {
        if ($newExpiryDate <= $today) {
          require_once __DIR__ . '/../classes/Notification.php';
          Notification::create($db, "Product '{$product_name}' has expired on {$newExpiryDate}.", 'expiry', $product_id);
          @file_put_contents(__DIR__ . '/../logs/expiry_notifications.log', date('[Y-m-d H:i:s] ') . "manage_product:update created expiry notif for product_id={$product_id}, expiry={$newExpiryDate}\n", FILE_APPEND);
        } elseif ($diff > 0 && $diff <= 3) {
          require_once __DIR__ . '/../classes/Notification.php';
          Notification::create($db, "Expiry Alert: Product '{$product_name}' will expire on {$newExpiryDate} (in {$diff} days).", 'expiry', $product_id);
          @file_put_contents(__DIR__ . '/../logs/expiry_notifications.log', date('[Y-m-d H:i:s] ') . "manage_product:update created upcoming expiry notif for product_id={$product_id}, expiry={$newExpiryDate}, days={$diff}\n", FILE_APPEND);
        }
      }
    }
  } catch (Exception $e) {
    @file_put_contents(__DIR__ . '/../logs/expiry_notifications.log', date('[Y-m-d H:i:s] ') . "manage_product:update error creating expiry notif: " . $e->getMessage() . "\n", FILE_APPEND);
  }
    }

    // Create an update-product notification visible to admin/staff
    try {
        require_once __DIR__ . '/../classes/Notification.php';
        $actorId = $_SESSION['user_id'] ?? null;
        $actorName = 'Staff';
        if ($actorId) {
            $uStmt = $db->prepare("SELECT first_name, last_name, username FROM users WHERE id = ?");
            $uStmt->execute([$actorId]);
            $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
            if ($uRow) {
                $actorName = trim(($uRow['first_name'] ?? '') . ' ' . ($uRow['last_name'] ?? '')) ?: ($uRow['username'] ?? $actorName);
            }
        }
        Notification::create($db, "Product '{$product_name}' (ID: {$product_id}) was updated by {$actorName}.", 'product_updated', $product_id);
        @file_put_contents(__DIR__ . '/../logs/notifications_actions.log', date('[Y-m-d H:i:s] ') . "create updated product_id={$product_id} by_user=" . ($_SESSION['user_id'] ?? 'null') . " actor_name={$actorName}\n", FILE_APPEND | LOCK_EX);
    } catch (Exception $e) {
        // ignore notification errors
    }

    header('Location: manage_product.php');
    exit();
}

// Delete product
if (isset($_GET['delete'])) {
  $delId = intval($_GET['delete']);
  // Fetch product name for notification
  $pstmt = $db->prepare("SELECT name FROM products WHERE id = ?");
  $pstmt->execute([$delId]);
  $prow = $pstmt->fetch(PDO::FETCH_ASSOC);
  $pname = $prow['name'] ?? "Product #{$delId}";

  // Soft-delete: mark product inactive to preserve FK integrity with inventory_reports and other tables
  $userId = $_SESSION['user_id'] ?? null;
  $stmt = $db->prepare("UPDATE products SET status = 'inactive', last_updated_by = ?, updated_at = NOW() WHERE id = ?");
  $stmt->execute([$userId, $delId]);

  // Create notification about deletion
  try {
    require_once __DIR__ . '/../classes/Notification.php';
    $userId = $_SESSION['user_id'] ?? 'unknown';
    Notification::create($db, "Product '{$pname}' (ID: {$delId}) was deleted by staff (user_id: {$userId}).", 'warning', $delId);
    @file_put_contents(__DIR__ . '/../logs/notifications_actions.log', date('[Y-m-d H:i:s] ') . "create deleted product_id={$delId} by_user=" . ($userId ?? 'null') . "\n", FILE_APPEND | LOCK_EX);
  } catch (Exception $e) {
    // ignore notification errors
  }
}


// Statistics queries (only consider active products)
$stats = [
  'total' => $db->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn(),
  'low_stock' => $db->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND stock_quantity <= low_stock_threshold AND stock_quantity > 0")->fetchColumn(),
  'out_of_stock' => $db->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND stock_quantity = 0")->fetchColumn(),
  // 'in_stock' replaces previous 'total_value' card: count of products with stock > 0
  'in_stock' => $db->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND stock_quantity > 0")->fetchColumn(),
];

// Fetch products with filters
$productsStmt = $db->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id $whereClause ORDER BY p.id DESC LIMIT $perPage OFFSET $offset");
$productsStmt->execute($params);
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

// Set page title for layout
$title = 'Inventory';
ob_start();
?>
<?php
// Compute expiring and expired products from current page
$expiringProducts = [];
$expiredProducts = [];
foreach ($products as $prod) {
  if (!empty($prod['expiry'])) {
    $expiryDate = new DateTime($prod['expiry']);
    $today = new DateTime();
    $interval = $today->diff($expiryDate);
    $daysUntilExpiry = (int)$interval->format('%R%a');
    if ($daysUntilExpiry < 0) {
      $expiredProducts[] = $prod;
    } elseif ($daysUntilExpiry <= 7) {
      $expiringProducts[] = $prod;
    }
  }
}
?>
<!-- Expiry Alerts -->
<?php if (!empty($expiredProducts)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
  <h5 class="alert-heading"><i class="fas fa-times-circle me-2"></i>Expired Products</h5>
  <p class="mb-2">The following products on this page have already expired:</p>
  <ul class="mb-0">
    <?php foreach ($expiredProducts as $ep): ?>
      <li><strong><?php echo htmlspecialchars($ep['name']); ?></strong> — expired on <?php echo date('M d, Y', strtotime($ep['expiry'])); ?></li>
    <?php endforeach; ?>
  </ul>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($expiringProducts)): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
  <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Products Nearing Expiration</h5>
  <p class="mb-2">These products will expire within 7 days:</p>
  <ul class="mb-0">
    <?php foreach ($expiringProducts as $ep):
      $daysLeft = (int)(new DateTime())->diff(new DateTime($ep['expiry']))->format('%a'); ?>
      <li><strong><?php echo htmlspecialchars($ep['name']); ?></strong> — <?php echo date('M d, Y', strtotime($ep['expiry'])); ?> (<?php echo $daysLeft; ?> day<?php echo $daysLeft != 1 ? 's' : ''; ?>)</li>
    <?php endforeach; ?>
  </ul>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<div class="container py-4">
    <h2>Inventory</h2>
    <p style="font-size: 12px; color: gray;">Page loaded at: <?php echo date('H:i:s'); ?> (ID: <?php echo uniqid(); ?>)</p>

    <!-- Statistics Cards (clickable) -->
    <div class="row mb-4">
        <?php
        // Build base query params to preserve search and category filters
        $baseParams = [];
        if (!empty($search)) $baseParams['search'] = $search;
        if ($category_filter > 0) $baseParams['category_id'] = $category_filter;

        // Helper to build link and active class
        function statLink($label, $viewKey, $count, $baseParams, $currentView) {
            $params = $baseParams;
            if ($viewKey !== 'all') {
                $params['view'] = $viewKey;
            }
            $query = http_build_query($params);
            $url = '?' . $query;
            $active = ($currentView === $viewKey) ? ' border border-primary' : '';
            return "<div class=\"col-md-3\"><a href=\"$url\" class=\"text-decoration-none text-dark\"><div class=\"card text-center$active\"><div class=\"card-body\"><h3 class=\"card-title text-danger\">$count</h3><p class=\"card-text\">$label</p></div></div></a></div>";
        }

        echo statLink('Total Products', 'all', $stats['total'], $baseParams, $view);
        echo statLink('Low Stock', 'low_stock', $stats['low_stock'], $baseParams, $view);
        echo statLink('Out of Stock', 'out_of_stock', $stats['out_of_stock'], $baseParams, $view);
        // In Stock replaces Total Value
        echo statLink('In Stock', 'in_stock', $stats['in_stock'], $baseParams, $view);
        ?>
    </div>

    <!-- Floating Add Button -->
    <button type="button" class="btn btn-success" style="position:fixed; bottom:30px; right:30px; z-index:1050; width:56px; height:56px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:2rem;" data-bs-toggle="modal" data-bs-target="#productModal" title="Add Product">
        <i class="fas fa-plus"></i>
    </button>

    <!-- Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <form method="POST" id="productForm">
            <div class="modal-header">
              <h5 class="modal-title" id="productModalLabel">Add / Update Product</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="id" id="product_id">
              <div class="row g-3">
                <div class="col-md-4">
                  <label for="name" class="form-label">Product Name</label>
                  <input type="text" name="name" id="name" required class="form-control" placeholder="Product Name">
                </div>
                <div class="col-md-4">
                  <label for="sku" class="form-label">SKU</label>
                  <input type="text" name="sku" id="sku" required class="form-control" placeholder="SKU">
                </div>
                <div class="col-md-4">
                  <label for="categorySelect" class="form-label">Category</label>
                  <select name="category_id" required class="form-select" id="categorySelect">
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                      <option value="<?=$cat['id']?>"><?=htmlspecialchars($cat['name'])?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label for="price" class="form-label">Price</label>
                  <input type="number" name="price" id="price" required class="form-control" step="0.01" placeholder="Price">
                </div>
                <div class="col-md-4">
                  <label for="stock_quantity" class="form-label">Quantity</label>
                  <input type="number" name="stock_quantity" id="stock_quantity" required class="form-control" placeholder="Quantity" min="0">
                </div>
                <div class="col-md-4">
                  <label for="low_stock_threshold" class="form-label">Low Stock Threshold</label>
                  <input type="number" name="low_stock_threshold" id="low_stock_threshold" required class="form-control" placeholder="Low Stock Threshold" min="0">
                </div>
                <div class="col-md-4">
                  <label for="barcode" class="form-label">Barcode</label>
                  <input type="text" name="barcode" id="barcode" required class="form-control" placeholder="Barcode">
                </div>
                <div class="col-md-4">
                  <label for="expiry" class="form-label">Expiry</label>
                  <input type="date" name="expiry" id="expiry" class="form-control" placeholder="Expiry">
                </div>
                <div class="col-md-4">
                  <label for="status" class="form-label">Status</label>
                  <input type="text" name="status" id="status" class="form-control" value="active" placeholder="Status">
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" name="add" class="btn btn-success" id="modalAddBtn">Add Product</button>
              <button type="submit" name="update" class="btn btn-primary d-none" id="modalUpdateBtn">Update Product</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Search and Filter Form -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Search by Name, SKU or Barcode" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                    <select name="category_id" class="form-select">
                        <option value="0">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-1"></i> Filter
                    </button>
                    <a href="manage_product.php" class="btn btn-secondary">
                        <i class="fas fa-redo me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>Name</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock Qty</th><th>Low Stock Threshold</th><th>Status</th><th>Barcode</th><th>Expiry</th><th>Actions</th>
      </tr>
    </thead>
        <tbody>
          <?php
            $firstProduct = $products[0] ?? null;
            if ($firstProduct) {
              $debugStock = intval($firstProduct['stock_quantity'] ?? 0);
              $debugThreshold = intval($firstProduct['low_stock_threshold'] ?? 0);
              echo "<!-- DEBUG First Product: Stock=$debugStock, Threshold=$debugThreshold, ID={$firstProduct['id']} -->";
            }
            ?>
            <?php foreach ($products as $p): 
                // expiry calculations for row styling and badge
                $expiryClass = '';
                $expiryBadge = '';
                $expiryText = '&mdash;';
                if (!empty($p['expiry'])) {
                    $expiryDate = new DateTime($p['expiry']);
                    $today = new DateTime();
                    $interval = $today->diff($expiryDate);
                    $daysUntilExpiry = (int)$interval->format('%R%a');
                    $expiryText = date('M d, Y', strtotime($p['expiry']));
                    if ($daysUntilExpiry < 0) {
                        $expiryClass = 'table-danger';
                        $expiryBadge = '<span class="badge bg-danger ms-2">EXPIRED</span>';
                    } elseif ($daysUntilExpiry <= 7) {
                        $expiryClass = 'table-warning';
                        $expiryBadge = '<span class="badge bg-warning text-dark ms-2">' . (int)$today->diff($expiryDate)->format('%a') . ' days left</span>';
                    }
                }
            ?>
            <tr class="<?php echo $expiryClass; ?>">
                <td><?=htmlspecialchars($p['name'] ?? '')?></td>
                <td><?=htmlspecialchars($p['sku'] ?? '')?></td>
                <td><?=htmlspecialchars($p['category_name'] ?? '')?></td>
                <td>₱<?=number_format($p['price'],2)?></td>
                <td><?=$p['stock_quantity']?></td>
                <td><?=$p['low_stock_threshold']?></td>
        <td>
        <?php
        $stock = intval($p['stock_quantity'] ?? 0);
        $threshold = intval($p['low_stock_threshold'] ?? 0);
        if ($stock <= 0) {
          echo '<span class="badge bg-danger stock-badge">Out of Stock</span>';
        } elseif ($stock <= $threshold) {
          echo '<span class="badge bg-warning text-dark stock-badge">Low Stock</span>';
        } else {
          echo '<span class="badge bg-success stock-badge">In Stock</span>';
        }
        ?>
      </td>
        <td>
            <div class="d-flex align-items-center gap-2">
                <span><?=htmlspecialchars($p['barcode'] ?? '')?></span>
                <?php if (!empty($p['barcode'])): ?>
                    <button class="btn btn-sm btn-outline-secondary" onclick="showBarcode('<?php echo htmlspecialchars($p['barcode'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>')" title="Show Barcode">
                        <i class="fas fa-barcode"></i>
                    </button>
                <?php endif; ?>
            </div>
        </td>
    <td>
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="expiryDropdownBtn-<?php echo $p['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false" data-product-id="<?php echo $p['id']; ?>">
          <?php echo $expiryText; ?>
        </button>
        <ul class="dropdown-menu p-2" aria-labelledby="expiryDropdownBtn-<?php echo $p['id']; ?>" id="expiryDropdownMenu-<?php echo $p['id']; ?>">
          <li class="small text-muted">Loading...</li>
        </ul>
      </div>
      <?php echo $expiryBadge; ?>
    </td>
                <!-- <td><?=htmlspecialchars($p['status'] ?? '')?></td> -->
        <td>
          <div class="btn-group" role="group">
            <button class="btn btn-primary btn-sm" onclick="editProduct(<?=htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8')?>)">Edit</button>
            <a href="?delete=<?=$p['id']?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete?')">Delete</a>
          </div>
        </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <!-- Pagination -->
    <nav aria-label="Product pagination">
      <ul class="pagination justify-content-center mt-3">
        <?php 
        $queryParams = [];
        if (!empty($search)) $queryParams['search'] = $search;
        if ($category_filter > 0) $queryParams['category_id'] = $category_filter;
        
        for ($i = 1; $i <= $totalPages; $i++): 
            $queryParams['page'] = $i;
            $queryString = http_build_query($queryParams);
        ?>
          <li class="page-item<?=($i == $page ? ' active' : '')?>">
            <a class="page-link" href="?<?=$queryString?>"><?=$i?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>

    <script>
    // Make category dropdown searchable
    document.addEventListener('DOMContentLoaded', function() {
        var select = document.getElementById('categorySelect');
        if (select) {
            select.setAttribute('data-live-search', 'true');
            // For advanced search, use a JS library like select2 or bootstrap-select
            // Example: $('#categorySelect').select2();
        }
    });

    // Edit product handler
    function editProduct(product) {
        var modal = new bootstrap.Modal(document.getElementById('productModal'));
        setTimeout(function() {
            document.getElementById('product_id').value = product.id ?? '';
            document.getElementById('name').value = product.name ?? '';
            document.getElementById('sku').value = product.sku ?? '';
            document.getElementById('categorySelect').value = product.category_id ?? '';
            document.getElementById('price').value = product.price ?? '';
            document.getElementById('stock_quantity').value = product.stock_quantity ?? '';
            document.getElementById('low_stock_threshold').value = product.low_stock_threshold ?? '';
            document.getElementById('barcode').value = product.barcode ?? '';
            document.getElementById('expiry').value = product.expiry ?? '';
            document.getElementById('status').value = product.status ?? '';
            document.getElementById('modalAddBtn').classList.add('d-none');
            document.getElementById('modalUpdateBtn').classList.remove('d-none');
        }, 200);
        modal.show();
    }

    // Reset modal for add
    if (document.getElementById('productModal')) {
        document.getElementById('productModal').addEventListener('show.bs.modal', function (event) {
            if (!event.relatedTarget || event.relatedTarget.classList.contains('btn-primary')) {
                document.getElementById('productForm').reset();
                document.getElementById('product_id').value = '';
                document.getElementById('modalAddBtn').classList.remove('d-none');
                document.getElementById('modalUpdateBtn').classList.add('d-none');
            }
        });
    
    }
    // Debug: Log badge count every 5 seconds
    setInterval(() => {
      const badges = document.querySelectorAll('.stock-badge');
      console.log(`Badges present: ${badges.length}`);
      badges.forEach((badge, index) => {
        console.log(`Badge ${index}: ${badge.textContent} (visible: ${badge.offsetWidth > 0})`);
      });
    }, 5000);  // Check every 5 seconds
    </script>
    
  <script>
  // Show barcode modal (for staff)
  function showBarcode(barcode, productName) {
    const valEl = document.getElementById('barcodeValue');
    const nameEl = document.getElementById('productNameDisplay');
    const imgEl = document.getElementById('barcodeImage');
    if (!valEl || !nameEl || !imgEl) return;
    valEl.textContent = barcode;
    nameEl.textContent = productName;
    imgEl.src = 'https://barcode.tec-it.com/barcode.ashx?data=' + encodeURIComponent(barcode) + '&code=Code128&translate-esc=on&unit=Fit&dpi=96&imagetype=Gif&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0';
    const modal = new bootstrap.Modal(document.getElementById('barcodeModal'));
    modal.show();
  }

  function printBarcode() {
    const printWindow = window.open('', '', 'height=400,width=600');
    const barcodeImg = document.getElementById('barcodeImage')?.src || '';
    const productName = document.getElementById('productNameDisplay')?.textContent || '';
    const barcodeValue = document.getElementById('barcodeValue')?.textContent || '';
    printWindow.document.write('<html><head><title>Print Barcode</title>');
    printWindow.document.write('<style>body{text-align:center;padding:20px;font-family:Arial;}img{max-width:100%;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<h3>' + productName + '</h3>');
    printWindow.document.write('<img src="' + barcodeImg + '" />');
    printWindow.document.write('<p style="margin-top:10px;font-size:14px;">' + barcodeValue + '</p>');
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
  }
  </script>

  <!-- Barcode Modal -->
  <div class="modal fade" id="barcodeModal" tabindex="-1" aria-labelledby="barcodeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="barcodeModalLabel"><i class="fas fa-barcode me-2"></i>Product Barcode</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center">
          <h6 class="mb-3" id="productNameDisplay"></h6>
          <div class="p-3 bg-light rounded mb-3">
            <img id="barcodeImage" src="" alt="Barcode" class="img-fluid" style="max-height:150px;" />
          </div>
          <p class="text-muted mb-0">Barcode: <strong id="barcodeValue"></strong></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-danger" onclick="printBarcode()"><i class="fas fa-print me-1"></i>Print</button>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/views/layout.php';
?>

<script>
// Populate expiry dropdowns for staff manage products
async function loadProductExpiriesStaff(productId) {
  const menu = document.getElementById('expiryDropdownMenu-' + productId);
  if (!menu) return;
  if (menu.getAttribute('data-loaded') === '1') return;
  try {
    const resp = await fetch('../get_product_expiries.php?id=' + encodeURIComponent(productId));
    if (!resp.ok) throw new Error('Network error');
    const data = await resp.json();
    menu.innerHTML = '';
    if (data.success && Array.isArray(data.expiries)) {
      menu.innerHTML = '';
      if (data.expiries.length > 0) {
        data.expiries.forEach(function(item, index){
          const li = document.createElement('li');
          li.className = 'small d-flex justify-content-between align-items-center';
          const qty = item.quantity !== undefined && item.quantity !== null ? ' — Qty: ' + item.quantity : '';

          // Calculate expiry status
          const today = new Date();
          const expiryDate = new Date(item.expiry);
          const daysDiff = Math.ceil((expiryDate - today) / (1000 * 60 * 60 * 24));
          let statusClass = '';
          if (daysDiff < 0) {
            statusClass = 'text-danger fw-bold'; // Expired
          } else if (daysDiff <= 7) {
            statusClass = 'text-warning fw-bold'; // Nearing expiration
          }

          const textSpan = document.createElement('span');
          textSpan.className = statusClass;
          textSpan.textContent = item.expiry + (qty);
          li.appendChild(textSpan);

          // Add remove button
          const removeBtn = document.createElement('button');
          removeBtn.className = 'btn btn-sm btn-outline-danger ms-2';
          removeBtn.innerHTML = '<i class="fas fa-trash"></i>';
          removeBtn.title = 'Remove this expiry';
          removeBtn.addEventListener('click', function(e){
            e.stopPropagation();
            if (confirm('Remove this expiry date?')) {
              removeExpiryStaff(item.id, productId);
            }
          });
          li.appendChild(removeBtn);

          menu.appendChild(li);
        });
      } else {
        const li = document.createElement('li');
        li.className = 'small text-muted';
        li.textContent = data.message || 'No expiry dates available';
        menu.appendChild(li);
      }

      const divider = document.createElement('li');
      divider.className = 'dropdown-divider my-2';
      menu.appendChild(divider);

      const formLi = document.createElement('li');
      formLi.className = 'px-2';
      formLi.innerHTML = `
        <div class="d-flex gap-2 align-items-center">
          <input type="date" class="form-control form-control-sm" id="expiryInput-${productId}" style="max-width:160px;">
          <input type="number" min="0" class="form-control form-control-sm" id="expiryQty-${productId}" placeholder="Qty" style="max-width:80px;">
          <button class="btn btn-sm btn-danger" id="addExpiryBtn-${productId}">Add</button>
        </div>
        <div class="small text-success mt-1" id="addExpiryMsg-${productId}" style="display:none;"></div>
      `;
      menu.appendChild(formLi);

      const addBtn = formLi.querySelector('#addExpiryBtn-' + productId);
      if (addBtn) {
        addBtn.addEventListener('click', async function(e){
          e.preventDefault();
          const dateEl = document.getElementById('expiryInput-' + productId);
          const qtyEl = document.getElementById('expiryQty-' + productId);
          const msgEl = document.getElementById('addExpiryMsg-' + productId);
          const expiryVal = dateEl ? dateEl.value : '';
          const qtyVal = qtyEl ? qtyEl.value : '';
          if (!expiryVal) {
            alert('Please select an expiry date');
            return;
          }
          try {
            addBtn.disabled = true;
            const resp = await fetch('../add_product_expiry.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ product_id: productId, expiry: expiryVal, quantity: qtyVal })
            });
            const res = await resp.json();
            if (res.success) {
              msgEl.style.display = 'block';
              msgEl.textContent = res.message || 'Added';
              menu.setAttribute('data-loaded', '0');
              loadProductExpiriesStaff(productId);
            } else {
              alert(res.message || 'Failed to add expiry');
            }
          } catch (err) {
            console.error(err);
            alert('Error adding expiry');
          } finally { addBtn.disabled = false; }
        });
      }
    } else {
      menu.innerHTML = '<li class="small text-danger">Error loading expiries</li>';
    }
    menu.setAttribute('data-loaded', '1');
  } catch (e) {
    menu.innerHTML = '<li class="small text-danger">Error loading expiries</li>';
    console.error(e);
  }
}

async function removeExpiryStaff(expiryId, productId) {
  try {
    const resp = await fetch('../remove_product_expiry.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: expiryId })
    });
    const data = await resp.json();
    if (data.success) {
      // Reload the expiry list
      const menu = document.getElementById('expiryDropdownMenu-' + productId);
      if (menu) {
        menu.setAttribute('data-loaded', '0');
        loadProductExpiriesStaff(productId);
      }
    } else {
      alert(data.message || 'Failed to remove expiry');
    }
  } catch (e) {
    console.error(e);
    alert('Error removing expiry');
  }
}

document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('button[data-bs-toggle="dropdown"][data-product-id]').forEach(function(btn){
    btn.addEventListener('show.bs.dropdown', function(e){
      const id = btn.getAttribute('data-product-id');
      if (id) loadProductExpiriesStaff(id);
    });
  });

  // Start notification polling for mobile stock updates
  pollNotificationsStaff();
  setInterval(pollNotificationsStaff, 10000); // Poll every 10 seconds
});

// Notification polling for stock updates and product changes (staff version)
let lastNotificationIdStaff = 0;
async function pollNotificationsStaff() {
  try {
    const resp = await fetch('../ajax/notifications.php');
    if (!resp.ok) return;
    const data = await resp.json();
    if (data.success && data.notifications) {
      data.notifications.forEach(function(notif) {
        if (notif.id > lastNotificationIdStaff && (notif.type === 'stock_update_mobile' || notif.type === 'product_added' || notif.type === 'product_updated' || notif.type === 'product_deleted')) {
          if (notif.type === 'stock_update_mobile') {
            showStockUpdatePopupStaff(notif);
          } else {
            showProductChangePopupStaff(notif);
          }
        }
      });
      if (data.notifications.length > 0) {
        lastNotificationIdStaff = Math.max(lastNotificationIdStaff, ...data.notifications.map(n => n.id));
      }
    }
  } catch (e) {
    console.error('Error polling notifications (staff):', e);
  }
}

function showStockUpdatePopupStaff(notification) {
  // Create modal HTML
  const modalHtml = `
    <div class="modal fade" id="stockUpdateModalStaff" tabindex="-1" aria-labelledby="stockUpdateModalStaffLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title" id="stockUpdateModalStaffLabel">
              <i class="fas fa-mobile-alt me-2"></i>Stock Updated via Mobile Scanner
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-success" role="alert">
              <h6 class="alert-heading"><i class="fas fa-check-circle me-2"></i>Stock Update Notification</h6>
              <p class="mb-2">${notification.message}</p>
              <hr>
              <p class="mb-0 small text-muted">Updated at: ${new Date(notification.created_at).toLocaleString()}</p>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
          </div>
        </div>
      </div>
    </div>
  `;

  // Remove existing modal if present
  const existingModal = document.getElementById('stockUpdateModalStaff');
  if (existingModal) {
    existingModal.remove();
  }

  // Add modal to body
  document.body.insertAdjacentHTML('beforeend', modalHtml);

  // Show modal
  const modal = new bootstrap.Modal(document.getElementById('stockUpdateModalStaff'));
  modal.show();

  // Auto-remove modal after it's hidden
  document.getElementById('stockUpdateModalStaff').addEventListener('hidden.bs.modal', function() {
    this.remove();
  });
}

function showProductChangePopupStaff(notification) {
  // Create modal HTML for product changes
  let modalClass = 'bg-info';
  let iconClass = 'fas fa-info-circle';
  if (notification.type === 'product_added') {
    modalClass = 'bg-success';
    iconClass = 'fas fa-plus-circle';
  } else if (notification.type === 'product_updated') {
    modalClass = 'bg-warning';
    iconClass = 'fas fa-edit';
  } else if (notification.type === 'product_deleted') {
    modalClass = 'bg-danger';
    iconClass = 'fas fa-trash';
  }

  const modalHtml = `
    <div class="modal fade" id="productChangeModalStaff" tabindex="-1" aria-labelledby="productChangeModalStaffLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header ${modalClass} text-white">
            <h5 class="modal-title" id="productChangeModalStaffLabel">
              <i class="${iconClass} me-2"></i>Product Change Notification
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-info" role="alert">
              <h6 class="alert-heading"><i class="${iconClass} me-2"></i>Product Update</h6>
              <p class="mb-2">${notification.message}</p>
              <hr>
              <p class="mb-0 small text-muted">Updated at: ${new Date(notification.created_at).toLocaleString()}</p>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-info" data-bs-dismiss="modal">OK</button>
          </div>
        </div>
      </div>
    </div>
  `;

  // Remove existing modal if present
  const existingModal = document.getElementById('productChangeModalStaff');
  if (existingModal) {
    existingModal.remove();
  }

  // Add modal to body
  document.body.insertAdjacentHTML('beforeend', modalHtml);

  // Show modal
  const modal = new bootstrap.Modal(document.getElementById('productChangeModalStaff'));
  modal.show();

  // Auto-remove modal after it's hidden
  document.getElementById('productChangeModalStaff').addEventListener('hidden.bs.modal', function() {
    this.remove();
  });
}
</script>