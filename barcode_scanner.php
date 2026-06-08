<?php
require_once '../config.php';
User::requireLogin();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header('Location: ../login.php');
    exit();
}

$title = 'Barcode Scanner';
$db = Database::getInstance()->getConnection();

// Handle manual search form
$product = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    $code = trim($_POST['code']);
    if ($code !== '') {
        $stmt = $db->prepare("SELECT p.*, c.name as category_name 
                             FROM products p 
                             LEFT JOIN categories c ON p.category_id = c.id 
                             WHERE p.barcode = ? OR p.sku = ? LIMIT 1");
        $stmt->execute([$code, $code]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

ob_start();
?>
<div class="container py-4">
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">📷 Barcode Scanner</h5>
                            <small class="text-muted">Scan product barcodes using your device camera or manually search to view inventory details.</small>
                        </div>
                        <div class="btn-group">
                            <!-- Encourage use of the mobile app scanner -->
                            <button class="btn btn-primary btn-sm" id="openMobileScannerBtn">
                                <i class="fas fa-mobile-alt"></i> Open Mobile App Scanner
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" id="showMobileQRBtn">
                                <i class="fas fa-qrcode"></i> Show QR to Open App
                            </button>
                            <button class="btn btn-outline-info btn-sm" id="openStaffScannerBtn">
                                <i class="fas fa-boxes"></i> Staff Inventory Scanner
                            </button>
                            <button class="btn btn-outline-info btn-sm" id="openCashierScannerBtn">
                                <i class="fas fa-cart-plus"></i> Cashier POS Scanner
                            </button>
                            <button class="btn btn-outline-info btn-sm" id="toggleScanExpiry" title="Toggle scan expiry mode">
                                <i class="fas fa-calendar-day"></i> Scan Expiry
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Mobile-first scanner: prompt to use mobile app instead of browser camera -->
                    <div class="alert alert-info">
                        <h6 class="mb-1"><i class="fas fa-mobile-alt me-2"></i>Use the PointShift Mobile App to scan</h6>
                        <p class="small mb-0">For reliable scanning (better camera, vibration, and offline support) use the mobile app scanner. Open the app on your phone and go to Scanner.</p>
                    </div>
                    <div class="d-flex gap-3 align-items-center mb-3">
                        <button class="btn btn-primary" id="openMobileScannerBtnInline"><i class="fas fa-mobile-alt me-1"></i>Open Mobile App</button>
                        <button class="btn btn-outline-secondary" id="showMobileQRBtnInline"><i class="fas fa-qrcode me-1"></i>Show QR</button>
                    </div>
                    <div id="mobileScannerQR" style="display:none;">
                        <p class="small text-muted">Scan this QR with your mobile app (or the Expo Go app) to open the scanner:</p>
                        <img id="mobileScannerQRImg" src="" alt="Mobile Scanner QR" style="max-width:200px;">
                        <p class="small text-muted mt-2">If you already have the app installed, try opening the link: <code id="mobileScannerLink">pointshift://scan</code></p>
                    </div>

                    <!-- Scanned barcode display removed for browser view; mobile app handles scanning -->
                    <div class="mb-3">
                        <!-- Hidden field to keep legacy JS flow working (holds last scanned code from mobile app callbacks) -->
                        <input type="hidden" id="scannedValue" value="">
                        <div class="alert alert-secondary small mb-0">Scanning is handled via the native PointShift Mobile App. Use the buttons above to open the app or show the QR to launch the scanner.</div>
                    </div>

                    <!-- Manual search (AJAX) -->
                    <form id="manualSearchForm" class="mt-2">
                        <div class="input-group">
                            <input type="text" id="manualSearchInput" name="code" class="form-control" placeholder="Enter or scan barcode...">
                            <button type="submit" class="btn btn-primary" id="manualSearchBtn">Search</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Product Information Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Product Details</h5>
                </div>
                <div class="card-body" id="productInfo">
                    <div class="alert alert-info">Scan a product barcode to view details</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add New Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addProductModalLabel">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addProductForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="newProductBarcode" class="form-label">Barcode</label>
                                    <input type="text" class="form-control" id="newProductBarcode" readonly required>
                                </div>
                                <div class="mb-3">
                                    <label for="newProductName" class="form-label">Product Name</label>
                                    <input type="text" class="form-control" id="newProductName" required>
                                </div>
                                <div class="mb-3">
                                    <label for="newProductSKU" class="form-label">SKU</label>
                                    <input type="text" class="form-control" id="newProductSKU">
                                </div>
                                <div class="mb-3">
                                    <label for="newProductPrice" class="form-label">Price</label>
                                    <input type="number" step="0.01" class="form-control" id="newProductPrice" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="newProductCategory" class="form-label">Category</label>
                                    <input type="text" class="form-control" id="newProductCategory" list="categoryList">
                                    <datalist id="categoryList">
                                        <?php
                                        $categories = $db->query("SELECT name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
                                        foreach ($categories as $category) {
                                            echo "<option value=\"" . htmlspecialchars($category) . "\">";
                                        }
                                        ?>
                                    </datalist>
                                </div>
                                <div class="mb-3">
                                    <label for="newProductStock" class="form-label">Initial Stock</label>
                                    <input type="number" class="form-control" id="newProductStock" value="1" min="0" required>
                                </div>
                                <div class="mb-3">
                                    <label for="newProductExpiry" class="form-label">Expiry Date</label>
                                    <input type="date" class="form-control" id="newProductExpiry">
                                </div>
                                <div class="mb-3">
                                    <label for="newProductDescription" class="form-label">Description</label>
                                    <textarea class="form-control" id="newProductDescription" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addProductForm" class="btn btn-primary">Add Product</button>
                </div>
            </div>
        </div>
    </div>

                <!-- Add Stock Modal -->
                <div class="modal fade" id="addStockModal" tabindex="-1" aria-labelledby="addStockModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addStockModalLabel">Add Stock</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="addStockForm">
                                    <div class="mb-3">
                                        <label class="form-label">Barcode</label>
                                        <input type="text" id="addStockBarcode" class="form-control" readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Product</label>
                                        <input type="text" id="addStockName" class="form-control" readonly>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Quantity to add</label>
                                        <input type="number" id="addStockQuantity" class="form-control" value="1" min="1" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Expiry (optional)</label>
                                        <input type="date" id="addStockExpiry" class="form-control">
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Add Stock</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                            <!-- Toast container -->
                            <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
                                <div id="globalToastContainer"></div>
                            </div>
</div>

<style>
    .viewport {
        position: relative;
        width: 100%;
        height: 300px;
    }
    #interactive.viewport > canvas, #interactive.viewport > video {
        max-width: 100%;
        width: 100%;
        height: 300px;
        object-fit: cover;
    }
    canvas.drawing, canvas.drawingBuffer {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
    }
    .scanner-laser {
        position: absolute;
        top: 40%;
        bottom: 40%;
        left: 10%;
        right: 10%;
        border: 2px solid transparent;
        opacity: 0;
        transition: opacity 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
        z-index: 2;
        pointer-events: none;
    }
    .scanner-laser.active {
        border-color: #28a745;
        opacity: 1;
        box-shadow: 0 0 10px rgba(40,167,69,0.6);
    }
    #status-indicator {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 10px;
        background: rgba(0,0,0,0.7);
        color: white;
        border-radius: 5px;
        z-index: 1000;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // We removed browser-camera scanning in favor of the mobile app scanner.
    let lastScannedCode = '';
    let lastProductId = null; // track currently displayed product id
    let isScanning = false;
    let scanExpiryMode = false; // when true, scanned value will be treated as expiry date
    const statusIndicator = document.getElementById('status-indicator');
    const scannedValueEl = document.getElementById('scannedValue'); // input[type=hidden]
    
    function updateStatus(message, isError = false) {
        if (!statusIndicator) return;
        statusIndicator.textContent = message;
        statusIndicator.style.display = 'block';
        statusIndicator.style.backgroundColor = isError ? 'rgba(220,53,69,0.9)' : 'rgba(0,0,0,0.7)';
        setTimeout(() => {
            statusIndicator.style.display = 'none';
        }, 3000);
    }

    // Show a Bootstrap toast notification in the global toast container
    function showToast(message, type = 'info', title = '') {
        try {
            const container = document.getElementById('globalToastContainer');
            if (!container) return;
            const toastId = 'toast_' + Date.now();
            const bgClass = type === 'success' ? 'bg-success text-white' : (type === 'error' || type === 'danger' ? 'bg-danger text-white' : 'bg-primary text-white');
            const toastHtml = `
                <div id="${toastId}" class="toast ${bgClass}" role="status" aria-live="polite" aria-atomic="true">
                    <div class="toast-header ${bgClass}">
                        <strong class="me-auto text-white">${title || (type === 'success' ? 'Success' : (type === 'danger' ? 'Error' : 'Info'))}</strong>
                        <small class="text-white-50 ms-2">now</small>
                        <button type="button" class="btn-close btn-close-white ms-2 mb-1" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body text-white">${message}</div>
                </div>
            `;
            const wrapper = document.createElement('div');
            wrapper.innerHTML = toastHtml;
            const toastEl = wrapper.firstElementChild;
            container.appendChild(toastEl);
            const bsToast = new bootstrap.Toast(toastEl, { delay: 4000 });
            bsToast.show();
            // remove toast DOM after hidden
            toastEl.addEventListener('hidden.bs.toast', () => { toastEl.remove(); });
        } catch (e) {
            console.error('showToast error', e);
        }
    }

    // Instead of using the browser camera, provide a mobile app scanner flow.
    function openMobileScanner() {
        // Try to open a custom URL scheme. If the app isn't installed, this will do nothing.
        const scheme = 'pointshift://scan';
        // Provide a fallback web URL (mobile app README) for users who need instructions.
        const fallback = '<?php echo SITE_URL; ?>/mobile-app/';
        // Attempt to open the app via window.location — mobile browsers may prompt to open
        window.location.href = scheme;
        // Also open the fallback README in a new tab after short delay (so app can open first)
        setTimeout(() => { window.open(fallback, '_blank'); }, 800);
    }

    function showMobileQR() {
        const qrImg = document.getElementById('mobileScannerQRImg');
        const linkEl = document.getElementById('mobileScannerLink');
        const link = 'pointshift://scan';
        // Use a public QR generator (serverless) to render the QR image
        qrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(link);
        linkEl.textContent = link;
        document.getElementById('mobileScannerQR').style.display = 'block';
    }

    // Handle scanned code arriving via URL param (e.g., pointshift-app opens web URL like https://.../staff/barcode_scanner.php?scanned=12345)
    async function handleIncomingScannedCode(code) {
        if (!code) return;
        // populate hidden field
        if (scannedValueEl) scannedValueEl.value = code;
        updateStatus('Scanned code received');
        try {
            const res = await fetch('../ajax/get_product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ barcode: code })
            });
            const data = await res.json();
            if (data.success && data.product) {
                displayProductInfo(data.product);
                updateStatus('Product found');
            } else {
                showAddNewProductButton(code);
                updateStatus('Product not found');
            }
        } catch (err) {
            console.error('Error handling scanned code', err);
            updateStatus('Error processing scanned code', true);
        }
    }

    // Check for incoming scanned code via URL (used by the mobile app to open this page with a code)
    (function checkUrlForScanned() {
        try {
            const params = new URLSearchParams(window.location.search);
            const scanned = params.get('scanned') || params.get('code');
            if (scanned) {
                // Slight delay to allow UI to initialize
                setTimeout(() => handleIncomingScannedCode(scanned), 200);
            }
        } catch (e) { /* ignore */ }
    })();

    function displayProductInfo(product) {
        const infoDiv = document.getElementById('productInfo');
        if (!infoDiv || !product) return;

        const expiryDate = product.expiry ? new Date(product.expiry).toLocaleDateString() : 'Not set';
        const stockClass = product.stock_quantity <= (product.low_stock_threshold || 10) ? 'text-danger' : 'text-success';
        infoDiv.innerHTML = `
            <h4>${product.name}</h4>
            <div class="row g-3">
                <div class="col-6">
                    <p class="mb-1"><strong>SKU:</strong> ${product.sku || 'N/A'}</p>
                    <p class="mb-1"><strong>Barcode:</strong> ${product.barcode}</p>
                    <p class="mb-1"><strong>Category:</strong> ${product.category_name || 'Uncategorized'}</p>
                </div>
                <div class="col-6">
                    <p class="mb-1"><strong>Price:</strong> ₱${parseFloat(product.price).toFixed(2)}</p>
                    <p class="mb-1"><strong>Stock:</strong> <span class="${stockClass}">${product.stock_quantity}</span></p>
                    <p class="mb-1"><strong>Expiry:</strong> <span id="productExpiryDisplay">${expiryDate}</span></p>
                </div>
            </div>
            ${product.description ? `<p class="mt-2"><strong>Description:</strong> ${product.description}</p>` : ''}

            <div class="mt-3">
                <div class="d-flex gap-2">
                    <div class="flex-grow-1">
                        <div class="input-group">
                            <input type="date" id="productExpiryInput" class="form-control" value="${product.expiry ? product.expiry.split(' ')[0] : ''}">
                            <button class="btn btn-outline-primary" id="updateExpiryBtn">Update Expiry</button>
                        </div>
                    </div>
                    <div>
                        <button class="btn btn-outline-success" id="showAddStockModalBtn"><i class="fas fa-plus"></i> Add Stock</button>
                    </div>
                </div>
            </div>
        `;

        // attach listener for update expiry
        document.getElementById('updateExpiryBtn').addEventListener('click', async function(e){
            e.preventDefault();
            const newExpiry = document.getElementById('productExpiryInput').value;
            if (!newExpiry) { updateStatus('Please select a date', true); return; }
            if (!product.id) { updateStatus('Cannot update expiry: missing product id', true); return; }
            await updateProductExpiry(product.id, newExpiry);
        });
        // no send-to-pos button in staff scanner (removed)
    }

    function showAddNewProductButton(barcode) {
        const infoDiv = document.getElementById('productInfo');
        if (!infoDiv) return;

        infoDiv.innerHTML = `
            <div class="alert alert-warning">
                <h5>Product Not Found</h5>
                <p>Barcode: ${barcode}</p>
                <button type="button" class="btn btn-primary" id="showAddProductModalBtn">
                    Add New Product
                </button>
            </div>
        `;
        document.getElementById('newProductBarcode').value = barcode;
    }

    // Event Listeners for mobile app flow
    document.getElementById('openMobileScannerBtn')?.addEventListener('click', openMobileScanner);
    document.getElementById('showMobileQRBtn')?.addEventListener('click', showMobileQR);
    document.getElementById('openMobileScannerBtnInline')?.addEventListener('click', openMobileScanner);
    document.getElementById('showMobileQRBtnInline')?.addEventListener('click', showMobileQR);
    document.getElementById('openStaffScannerBtn')?.addEventListener('click', function() {
        const scheme = 'pointshift://scan?role=staff';
        window.location.href = scheme;
        setTimeout(() => { window.open('<?php echo SITE_URL; ?>/mobile-app/', '_blank'); }, 800);
    });
    document.getElementById('openCashierScannerBtn')?.addEventListener('click', function() {
        const scheme = 'pointshift://scan?role=cashier';
        window.location.href = scheme;
        setTimeout(() => { window.open('<?php echo SITE_URL; ?>/mobile-app/', '_blank'); }, 800);
    });
    // Manual search via AJAX
    document.getElementById('manualSearchForm').addEventListener('submit', async function(e){
        e.preventDefault();
        const code = document.getElementById('manualSearchInput').value.trim();
        if (!code) return;
    // store scanned value in hidden field (used by add-stock / add-product flows)
    if (scannedValueEl) scannedValueEl.value = code;
        try {
            const res = await fetch('../ajax/get_product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ barcode: code })
            });
            const data = await res.json();
            if (data.success && data.product) {
                displayProductInfo(data.product);
                updateStatus('Product found');
            } else {
                showAddNewProductButton(code);
                updateStatus('Product not found');
            }
        } catch (err) {
            console.error(err);
            updateStatus('Error searching product', true);
        }
    });

    // Toggle scan expiry mode
    document.getElementById('toggleScanExpiry').addEventListener('click', function(){
        scanExpiryMode = !scanExpiryMode;
        this.classList.toggle('active', scanExpiryMode);
        updateStatus(scanExpiryMode ? 'Expiry scan enabled: next scan will be treated as expiry date' : 'Expiry scan disabled');
    });

    // Helper: parse possible date strings from scanned codes
    function parseDateFromCode(code) {
        if (!code) return null;
        code = code.trim();
        // Accept YYYY-MM-DD
        if (/^\d{4}-\d{2}-\d{2}$/.test(code)) return code;
        // Accept YYYYMMDD
        if (/^\d{8}$/.test(code)) {
            return code.slice(0,4) + '-' + code.slice(4,6) + '-' + code.slice(6,8);
        }
        // Accept YYMMDD -> assume 20YY
        if (/^\d{6}$/.test(code)) {
            const yy = code.slice(0,2);
            const yyyy = (parseInt(yy,10) > 30 ? '19' : '20') + yy; // heuristic
            return yyyy + '-' + code.slice(2,4) + '-' + code.slice(4,6);
        }
        // Accept MMDDYYYY
        if (/^\d{8}$/.test(code)) {
            // ambiguous with YYYYMMDD; already tried YYYYMMDD first
            return null;
        }
        // try Date parse fallback
        const d = new Date(code);
        if (!isNaN(d.getTime())) {
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth()+1).padStart(2,'0');
            const dd = String(d.getDate()).padStart(2,'0');
            return `${yyyy}-${mm}-${dd}`;
        }
        return null;
    }

    // Helper: call server endpoint to update product expiry
    async function updateProductExpiry(productId, expiry) {
        try {
            const resp = await fetch('update_product_expiry.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: productId, expiry: expiry })
            });
            const data = await resp.json();
            if (data.success && data.product) {
                // update displayed expiry
                const disp = document.getElementById('productExpiryDisplay');
                if (disp) disp.textContent = data.product.expiry ? new Date(data.product.expiry).toLocaleDateString() : 'Not set';
                // Also prefill addProduct modal expiry if present
                if (document.getElementById('newProductExpiry')) document.getElementById('newProductExpiry').value = data.product.expiry ? data.product.expiry.split(' ')[0] : '';
                updateStatus('Expiry updated');
            } else {
                updateStatus(data.error || 'Failed to update expiry', true);
            }
        } catch (err) {
            console.error('Error updating expiry', err);
            updateStatus('Error updating expiry', true);
        }
    }
    
    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'showAddProductModalBtn') {
            const modal = new bootstrap.Modal(document.getElementById('addProductModal'));
            modal.show();
        }
        if (e.target && e.target.id === 'showAddStockModalBtn') {
            // prefill barcode for stock addition if available
            const modalEl = document.getElementById('addStockModal');
            if (modalEl) {
                const modal = new bootstrap.Modal(modalEl);
                const barcodeField = document.getElementById('addStockBarcode');
                const nameField = document.getElementById('addStockName');
                if (barcodeField) barcodeField.value = (document.getElementById('scannedValue') && (document.getElementById('scannedValue').value || '') !== '') ? document.getElementById('scannedValue').value.trim() : '';
                if (nameField && typeof lastProductId !== 'undefined') {
                    // try to set name if displayed
                    const prodName = document.querySelector('#productInfo h4');
                    if (prodName) nameField.value = prodName.textContent.trim();
                }
                modal.show();
            }
        }
    });

    // Handle add product form submit
    document.getElementById('addProductForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = {
            barcode: document.getElementById('newProductBarcode').value.trim(),
            name: document.getElementById('newProductName').value.trim(),
            sku: document.getElementById('newProductSKU').value.trim(),
            category: document.getElementById('newProductCategory').value.trim(),
            expiry: document.getElementById('newProductExpiry').value,
            price: parseFloat(document.getElementById('newProductPrice').value) || 0,
            stock: parseInt(document.getElementById('newProductStock').value) || 1,
            description: document.getElementById('newProductDescription').value.trim()
        };

        try {
            const response = await fetch('add_scanned_to_inventory.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });

            const data = await response.json();
            if (data.success && data.product) {
                bootstrap.Modal.getInstance(document.getElementById('addProductModal')).hide();
                displayProductInfo(data.product);
                updateStatus('Product added successfully!');
            } else {
                updateStatus(data.error || 'Failed to add product', true);
            }
        } catch (error) {
            console.error('Error adding product:', error);
            updateStatus('Error adding product', true);
        }
    });

    // send-to-POS functionality removed from staff scanner (handled only by cashier/mobile scanner)

    // Handle add stock form submit
    document.addEventListener('submit', async function(e){
        if (e.target && e.target.id === 'addStockForm') {
            e.preventDefault();
            const barcode = (document.getElementById('addStockBarcode') || {}).value || (document.getElementById('scannedValue') || {}).value || '';
            const qty = parseInt((document.getElementById('addStockQuantity') || {}).value, 10) || 1;
            const expiry = (document.getElementById('addStockExpiry') || {}).value || '';
            if (!barcode) { updateStatus('Barcode is required', true); return; }

            try {
                const response = await fetch('add_scanned_to_inventory.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ barcode: barcode.trim(), quantity: qty })
                });
                const data = await response.json();
                if (data.success && data.product) {
                    // if expiry provided, update expiry as well
                    if (expiry) {
                        await updateProductExpiry(data.product.id, expiry);
                    }
                    // hide modal if open
                    try { bootstrap.Modal.getInstance(document.getElementById('addStockModal')).hide(); } catch (er) {}
                    displayProductInfo(data.product);
                    // show popup notification for added stock
                    showToast('New stock has been added\nQuantity: ' + qty, 'success', 'Stock Added');
                } else {
                    updateStatus(data.error || 'Failed to add stock', true);
                }
            } catch (err) {
                console.error('Error adding stock', err);
                updateStatus('Error adding stock', true);
            }
        }
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', stopBarcodeScanner);
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/views/layout.php';
?>