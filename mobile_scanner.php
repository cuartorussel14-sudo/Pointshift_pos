<?php
$requireLogin = require_once '../config.php';
User::requireLogin();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cashier') {
    header('Location: ../login.php');
    exit();
}
$title = 'Mobile Barcode Scanner';
$db = Database::getInstance()->getConnection();

// Cashier version: same UI/logic as staff scanner but with POS send-to-cart support (kept minimal here)

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


                    </div>
                </div>
                <div class="card-body">
                    <!-- Mobile-first scanner: prompt to use mobile app instead of browser camera -->
                    <div class="alert alert-info">
                        <h6 class="mb-1"><i class="fas fa-mobile-alt me-2"></i>Use the PointShift Mobile App to scan</h6>
                        <p class="small mb-0">For reliable scanning (better camera, vibration, and offline support) use the mobile app scanner. Open the app on your phone and go to Scanner.</p>
                    </div>

                    <!-- Download Expo Go and Setup Instructions -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><i class="fas fa-download me-2"></i>Download Expo Go & Setup Instructions</h6>
                                <a href="mobile_app_download.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-upload me-1"></i> Download Mobile App zip
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted">Expo Go is required to run the PointShift Mobile App on your device. Follow these steps to get started:</p>

                            <h6 class="small mt-3"><i class="fas fa-desktop me-2"></i>Web Setup (Computer)</h6>
                            <ol class="small">
                                <li><strong>Download Node.js:</strong> If you plan to develop or modify the mobile app, download and install Node.js from <a href="https://nodejs.org/" target="_blank">nodejs.org</a>. This includes npm for package management.</li>
                                <li><strong>Set up a Terminal:</strong> Open your system's terminal or command prompt. On Windows, use Command Prompt or PowerShell; on macOS/Linux, use Terminal. Ensure Node.js is installed by running <code>node -v</code> and <code>npm -v</code> in the terminal.</li>
                                <li><strong>Download the Mobile App:</strong> Download the PointShift Mobile App source code as a zip file from <a href="<?php echo SITE_URL; ?>/cashier/mobile_app_download.php" target="_blank">mobile-app.zip</a>.</li>
                                <li><strong>Extract and Set Up the Mobile App:</strong> Extract the downloaded zip file to a folder (e.g., mobile-app). Open your terminal and navigate to the extracted folder: <code>cd path/to/mobile-app</code>. Install dependencies by running <code>npm install</code>.</li>
                                <li><strong>Configure API Endpoint:</strong> Open <code>config/api.js</code> in the mobile-app folder and update the <code>API_BASE_URL</code> with your computer's IP address (find it with <code>ipconfig</code> on Windows).</li>
                                <li><strong>Start the Development Server:</strong> Run <code>npm start</code> or <code>npx expo start</code> in the terminal to start the Expo development server.</li>
                            </ol>

                            <h6 class="small mt-3"><i class="fas fa-mobile-alt me-2"></i>Mobile Setup (Phone)</h6>
                            <ol class="small" start="7">
                                <li><strong>Download Expo Go:</strong> Install the Expo Go app on your smartphone from the official stores.</li>
                                <div class="d-flex gap-2 mb-2">
                                    <a href="https://apps.apple.com/app/expo-go/id982107779" target="_blank" class="btn btn-outline-primary btn-sm">
                                        <i class="fab fa-apple me-1"></i> Download for iOS (App Store)
                                    </a>
                                    <a href="https://play.google.com/store/apps/details?id=host.exp.exponent" target="_blank" class="btn btn-outline-success btn-sm">
                                        <i class="fab fa-google-play me-1"></i> Download for Android (Google Play)
                                    </a>
                                </div>
                                <li><strong>Open Expo Go:</strong> Launch the Expo Go app on your device after installation.</li>
                                <li><strong>Scan QR Code or Enter URL:</strong> In Expo Go, scan the QR code shown below or enter the project URL: <code><?php echo SITE_URL; ?>/mobile-app/</code></li>
                                <div class="mb-2">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode(SITE_URL . '/mobile-app/'); ?>" alt="Expo Project QR" style="max-width:150px;">
                                </div>
                                <li><strong>Load the App:</strong> The PointShift Mobile App will load automatically. Navigate to the Scanner section within the app.</li>
                                <li><strong>Start Scanning:</strong> Use the scanner in the app to scan barcodes. Results will sync back to this web interface.</li>
                            </ol>
                            <div class="alert alert-warning small">
                                <strong>Note:</strong> Ensure your device and the server are on the same network for optimal performance. If you encounter issues, check the mobile-app README for troubleshooting.
                            </div>
                        </div>
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

    <!-- Add to POS cart button appears with product -->
</div>

<style>
#status-indicator{position:fixed;top:20px;right:20px;padding:10px;background:rgba(0,0,0,0.7);color:#fff;border-radius:5px;z-index:1000;display:none}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusIndicator = document.getElementById('status-indicator');
    const scannedValueEl = document.getElementById('scannedValue');

    function updateStatus(msg, err=false){
        if(!statusIndicator) return; statusIndicator.textContent=msg; statusIndicator.style.display='block'; statusIndicator.style.backgroundColor = err ? 'rgba(220,53,69,0.9)' : 'rgba(0,0,0,0.7)'; setTimeout(()=>statusIndicator.style.display='none',3000);
    }
    function showMobileQR(){
        const qrImg = document.getElementById('mobileScannerQRImg');
        const linkEl = document.getElementById('mobileScannerLink');
        const link = 'pointshift://scan';
        qrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(link);
        linkEl.textContent = link; document.getElementById('mobileScannerQR').style.display = 'block';
    }
    async function handleScanned(code){
        if(!code) return; if(scannedValueEl) scannedValueEl.value = code;
        try{
            const res = await fetch('../../ajax/get_product.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ barcode: code })});
            const data = await res.json();
            if(data.success && data.product){ renderProduct(data.product); updateStatus('Product found'); }
            else { renderNotFound(code); updateStatus('Product not found'); }
        }catch(e){ console.error(e); updateStatus('Error processing code', true); }
    }
    (function initFromUrl(){ try { const p=new URLSearchParams(window.location.search); const s=p.get('scanned')||p.get('code'); if(s){ setTimeout(()=>handleScanned(s),200);} } catch(e){} })();

    function renderProduct(product){
        const info = document.getElementById('productInfo'); if(!info) return;
        const expiryDate = product.expiry ? new Date(product.expiry).toLocaleDateString() : 'Not set';
        const stockClass = product.stock_quantity <= (product.low_stock_threshold || 10) ? 'text-danger' : 'text-success';
        info.innerHTML = `
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
            <div class="mt-3 d-flex justify-content-end">
                <button class="btn btn-outline-primary" id="addToPosBtn"><i class="fas fa-cart-plus"></i> Add to POS</button>
            </div>
        `;
        document.getElementById('addToPosBtn').addEventListener('click', async function(){
            try{
                const resp = await fetch('../pos.php?action=add_scanned', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ barcode: product.barcode, quantity: 1 })});
                const data = await resp.json();
                if(data && data.success){ updateStatus('Added to POS'); }
                else { updateStatus(data.error || 'Failed to add to POS', true); }
            }catch(e){ updateStatus('Error adding to POS', true); }
        });
    }
    function renderNotFound(code){
        const info = document.getElementById('productInfo'); if(!info) return;
        info.innerHTML = `
            <div class="alert alert-warning">
                <h5>Product Not Found</h5>
                <p>Barcode: ${code}</p>
            </div>
        `;
    }

    document.getElementById('manualSearchForm').addEventListener('submit', async function(e){
        e.preventDefault();
        const code = document.getElementById('manualSearchInput').value.trim(); if(!code) return; if(scannedValueEl) scannedValueEl.value = code;
        try{
            const res = await fetch('../../ajax/get_product.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ barcode: code })});
            const data = await res.json();
            if(data.success && data.product){ renderProduct(data.product); updateStatus('Product found'); }
            else { renderNotFound(code); updateStatus('Product not found'); }
        }catch(err){ console.error(err); updateStatus('Error searching product', true); }
    });
});
</script>
<div id="status-indicator"></div>
<?php
$content = ob_get_clean();
include 'views/layout.php';
?>
