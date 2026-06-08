<?php
require_once '../config.php';
requireLogin();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    header('Location: ../login.php');
    exit();
}

$page_title = 'Transaction History';
$db = Database::getInstance()->getConnection();

// Get filter parameters
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$limit = $_GET['limit'] ?? 50;

// Build query with filters
$sql = "SELECT o.*, u.first_name, u.last_name, u.username, o.created_at as date 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

if ($date_from) {
    $sql .= " AND DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $sql .= " AND DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY o.created_at DESC LIMIT " . (int)$limit;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get transaction details if requested
$details = [];
$selectedTransaction = null;
if (isset($_GET['details'])) {
    $tid = intval($_GET['details']);
    
    // Get transaction info
    $stmt = $db->prepare("SELECT o.*, u.first_name, u.last_name FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->execute([$tid]);
    $selectedTransaction = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get transaction items
    $stmt = $db->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $stmt->execute([$tid]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

ob_start();
?>

<style>
@media print {
    .no-print { display: none !important; }
    .print-only { display: block !important; }
    body { font-size: 12px; }
    .content-card { box-shadow: none !important; border: 1px solid #000 !important; }
    .table { font-size: 11px; }
    @page { margin: 1cm; }
}
.print-only { display: none; }
</style>

<!-- Filter Section -->
<div class="row mb-4 no-print">
    <div class="col-12">
        <div class="content-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-filter me-2"></i>Filter Transactions
                </h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Order number, cashier name...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Limit</label>
                        <select class="form-select" name="limit">
                            <option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                            <option value="200" <?php echo $limit == 200 ? 'selected' : ''; ?>>200</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-custom me-2">
                            <i class="fas fa-search me-2"></i>Filter
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-custom" onclick="printTransactions()">
                            <i class="fas fa-print me-2"></i>Print List
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Transaction List -->
<div class="row">
    <div class="col-12">
        <div class="content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-receipt me-2"></i>Transaction History
                </h5>
                <span class="badge bg-primary"><?php echo count($transactions); ?> transactions</span>
            </div>
            <div class="card-body">
                <div class="print-only">
                    <div class="text-center mb-4">
                        <h3>POINTSHIFT POS</h3>
                        <h4>Transaction History Report</h4>
                        <p>Generated on: <?php echo date('F j, Y g:i A'); ?></p>
                        <p>Cashier: <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?></p>
                        <?php if ($date_from || $date_to): ?>
                            <p>Period: <?php echo $date_from ?: 'Beginning'; ?> to <?php echo $date_to ?: 'Now'; ?></p>
                        <?php endif; ?>
                        <hr>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Order #</th>
                                <th>Date & Time</th>
                                <th>Cashier</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th class="no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-info-circle me-2"></i>No transactions found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $t): ?>
                                    <tr>
                                        <td>
                                            <strong class="text-primary"><?php echo htmlspecialchars($t['order_number'] ?? 'ORD-' . $t['id']); ?></strong>
                                        </td>
                                        <td>
                                            <div><?php echo date('M j, Y', strtotime($t['date'])); ?></div>
                                            <small class="text-muted"><?php echo date('g:i A', strtotime($t['date'])); ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                            $cashierName = trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? ''));
                                            echo htmlspecialchars($cashierName ?: $t['username'] ?: 'Unknown');
                                            ?>
                                        </td>
                                        <td>
                                            <strong class="text-success"><?php echo formatCurrency($t['total_amount']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $t['status'] === 'completed' ? 'success' : ($t['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                <?php echo ucfirst($t['status']); ?>
                                            </span>
                                        </td>
                                        <td class="no-print">
                                            <button type="button" class="btn btn-info btn-sm btn-view-transaction" data-id="<?php echo $t['id']; ?>">
                                                <i class="fas fa-eye me-1"></i>View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Details Modal/Section -->
<?php if ($details && $selectedTransaction): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-file-invoice me-2"></i>Transaction Details - <?php echo htmlspecialchars($selectedTransaction['order_number'] ?? 'ORD-' . $selectedTransaction['id']); ?>
                </h5>
                <div class="no-print">
                    <button class="btn btn-outline-primary btn-sm me-2" onclick="printTransactionDetails()">
                        <i class="fas fa-print me-1"></i>Print Receipt
                    </button>
                    <a href="transactions.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-times me-1"></i>Close
                    </a>
                </div>
            </div>
            <div class="card-body" id="transactionDetails">
                <div class="print-only">
                    <div class="text-center mb-4">
                        <h3>POINTSHIFT POS</h3>
                        <p>Transaction Receipt</p>
                        <hr style="border-top: 2px solid #000;">
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6>Transaction Information</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td><strong>Order Number:</strong></td>
                                <td><?php echo htmlspecialchars($selectedTransaction['order_number'] ?? 'ORD-' . $selectedTransaction['id']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Date:</strong></td>
                                <td><?php echo date('F j, Y g:i A', strtotime($selectedTransaction['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Cashier:</strong></td>
                                <td><?php echo htmlspecialchars(trim(($selectedTransaction['first_name'] ?? '') . ' ' . ($selectedTransaction['last_name'] ?? '')) ?: 'Unknown'); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                    <span class="badge bg-<?php echo $selectedTransaction['status'] === 'completed' ? 'success' : ($selectedTransaction['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst($selectedTransaction['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <h6>Items Purchased</h6>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $subtotal = 0;
                            foreach ($details as $d): 
                                $itemTotal = $d['total_price'];
                                $subtotal += $itemTotal;
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($d['name']); ?></td>
                                    <td class="text-center"><?php echo $d['quantity']; ?></td>
                                    <td class="text-end"><?php echo formatCurrency($d['unit_price']); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($itemTotal); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Subtotal:</th>
                                <th class="text-end"><?php echo formatCurrency($subtotal); ?></th>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-end">Tax (12%):</th>
                                <th class="text-end"><?php echo formatCurrency($selectedTransaction['tax_amount'] ?? ($subtotal * 0.12)); ?></th>
                            </tr>
                            <tr class="table-success">
                                <th colspan="3" class="text-end">TOTAL:</th>
                                <th class="text-end"><?php echo formatCurrency($selectedTransaction['total_amount']); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="print-only text-center mt-4">
                    <hr style="border-top: 2px solid #000;">
                    <p>Thank you for your business!</p>
                    <p><small>This receipt was generated on <?php echo date('F j, Y g:i A'); ?></small></p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function printTransactions() {
    window.print();
}

function printTransactionDetails() {
    // Create a new window for printing just the transaction details
    const printContent = document.getElementById('transactionDetails').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Transaction Receipt</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
                th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
                th { background-color: #f5f5f5; font-weight: bold; }
                .text-center { text-align: center; }
                .text-end { text-align: right; }
                .table-borderless td { border: none; }
                .badge { padding: 3px 8px; border-radius: 3px; color: white; }
                .bg-success { background-color: #28a745; }
                .bg-warning { background-color: #ffc107; color: #000; }
                .bg-danger { background-color: #dc3545; }
                hr { border: 1px solid #000; margin: 15px 0; }
            </style>
        </head>
        <body>
            ${printContent}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>

<?php
$content = ob_get_clean();
include 'views/layout.php';
?>

<!-- Transaction Modal for Cashier (AJAX) -->
<div class="modal fade" id="transactionModal" tabindex="-1" aria-labelledby="transactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="transactionModalLabel">Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="transactionModalBody">
                <div class="text-center py-3">Loading...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="printModalBtn">Print Receipt</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
        const modalEl = document.getElementById('transactionModal');
        const bsModal = modalEl ? new bootstrap.Modal(modalEl) : null;

        document.querySelectorAll('.btn-view-transaction').forEach(btn => {
                btn.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        if (!id) return;
                        const endpoint = '../get_transaction.php?id=' + encodeURIComponent(id);
                        const body = document.getElementById('transactionModalBody');
                        body.innerHTML = '<div class="text-center py-3">Loading...</div>';
                        if (bsModal) bsModal.show();

                        fetch(endpoint, { credentials: 'same-origin' })
                                .then(resp => resp.json())
                                .then(data => {
                                        if (!data.success) {
                                                body.innerHTML = '<div class="alert alert-danger">' + (data.message || 'Failed to load transaction') + '</div>';
                                                return;
                                        }
                                        const t = data.transaction;
                                        const items = data.items || [];
                                        let html = '';
                                        html += `<div class="row mb-3"><div class="col-md-6"><h6>Transaction Information</h6><table class="table table-borderless table-sm">`;
                                        html += `<tr><td><strong>Order Number:</strong></td><td>${escapeHtml(t.order_number || 'ORD-' + t.id)}</td></tr>`;
                                        html += `<tr><td><strong>Date & Time:</strong></td><td>${escapeHtml(t.created_at)}</td></tr>`;
                                        html += `<tr><td><strong>Cashier:</strong></td><td>${escapeHtml((t.first_name||'') + ' ' + (t.last_name||'') || t.username || 'Unknown')}</td></tr>`;
                                        html += `<tr><td><strong>Status:</strong></td><td><span class="badge bg-${t.status === 'completed' ? 'success' : (t.status === 'pending' ? 'warning' : 'danger')}">${escapeHtml(capitalize(t.status))}</span></td></tr>`;
                                        html += `</table></div><div class="col-md-6"><h6>Payment Information</h6><table class="table table-borderless table-sm">`;
                                        html += `<tr><td><strong>Payment Method:</strong></td><td>${escapeHtml(t.payment_method || 'Cash')}</td></tr>`;
                                        html += `<tr><td><strong>Amount Received:</strong></td><td>${escapeHtml(formatCurrency(t.amount_received || t.total_amount))}</td></tr>`;
                                        html += `<tr><td><strong>Change:</strong></td><td>${escapeHtml(formatCurrency((t.amount_received || t.total_amount) - t.total_amount))}</td></tr>`;
                                        html += `</table></div></div>`;

                                        html += `<h6>Items Purchased</h6><div class="table-responsive"><table class="table table-bordered"><thead class="table-light"><tr><th>Product</th><th class="text-center">Qty</th><th class="text-end">Unit Price</th><th class="text-end">Total</th></tr></thead><tbody>`;
                                        let subtotal = 0;
                                        items.forEach(it => {
                                                const itemTotal = parseFloat(it.total_price || 0);
                                                subtotal += itemTotal;
                                                html += `<tr><td>${escapeHtml(it.name)}</td><td class="text-center">${escapeHtml(it.quantity)}</td><td class="text-end">${escapeHtml(formatCurrency(it.unit_price))}</td><td class="text-end">${escapeHtml(formatCurrency(itemTotal))}</td></tr>`;
                                        });
                                        html += `</tbody><tfoot>`;
                                        html += `<tr><th colspan="3" class="text-end">Subtotal:</th><th class="text-end">${escapeHtml(formatCurrency(subtotal))}</th></tr>`;
                                        const tax = parseFloat(t.tax_amount ?? (subtotal * 0.12));
                                        html += `<tr><th colspan="3" class="text-end">Tax (12%):</th><th class="text-end">${escapeHtml(formatCurrency(tax))}</th></tr>`;
                                        html += `<tr class="table-success"><th colspan="3" class="text-end">TOTAL:</th><th class="text-end">${escapeHtml(formatCurrency(t.total_amount))}</th></tr>`;
                                        html += `</tfoot></table></div>`;

                                        body.innerHTML = html;
                                        document.getElementById('printModalBtn').onclick = function() { printModalContent(); };
                                })
                                .catch(err => {
                                        body.innerHTML = '<div class="alert alert-danger">Error fetching transaction</div>';
                                        console.error(err);
                                });
                });
        });

        function printModalContent() {
                const content = document.getElementById('transactionModalBody').innerHTML;
                const w = window.open('', '_blank');
                w.document.write(`<!doctype html><html><head><title>Receipt</title><style>body{font-family:Arial,sans-serif;font-size:12px}table{width:100%;border-collapse:collapse}th,td{padding:8px;border:1px solid #ddd}</style></head><body>${content}</body></html>`);
                w.document.close();
                w.print();
        }

        function escapeHtml(unsafe) { if (unsafe === null || unsafe === undefined) return ''; return String(unsafe).replace(/[&<>"'`]/g, function (s) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','`':'&#96;'})[s]; }); }
        function capitalize(s){ return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
        function formatCurrency(v){ try { return new Intl.NumberFormat(undefined,{style:'currency',currency:'PHP'}).format(parseFloat(v||0)); } catch(e){ return v; } }
});
</script>
