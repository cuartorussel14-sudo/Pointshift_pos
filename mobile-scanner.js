document.addEventListener('DOMContentLoaded', function() {
    const startBtn = document.getElementById('startScanner');
    const stopBtn = document.getElementById('stopScanner');
    const manualBtn = document.getElementById('manualSearchBtn');
    const manualInput = document.getElementById('manualBarcode');
    const scannerArea = document.getElementById('scanner-area');
    const placeholder = document.getElementById('scanner-placeholder');
    const productDetails = document.getElementById('posProductDetails');

    let lastScanned = null;
    let scanning = false;

    function updateProductDetails(html) {
        productDetails.innerHTML = html;
    }

    function showNotFound(code) {
        updateProductDetails(`
            <div class="alert alert-warning">Product not found for <strong>${code}</strong>.</div>
            <div class="d-grid gap-2">
                <a class="btn btn-sm btn-outline-primary" href="staff/manage_product.php?barcode=${encodeURIComponent(code)}">Add new product with this barcode</a>
            </div>
        `);
    }

    async function processBarcode(code) {
        if (!code) return;
        // prevent duplicate processing
        if (code === lastScanned) return;
        lastScanned = code;

        updateProductDetails('<div class="text-center">Searching for product... <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span></div>');

        try {
            const res = await fetch('staff/get_product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ barcode: code })
            });

            if (!res.ok) {
                // attempt to parse error body
                try {
                    const err = await res.json();
                    console.error('get_product error', err);
                } catch (e) {}
                showNotFound(code);
                return;
            }

            const json = await res.json();
            if (!json.success) {
                showNotFound(code);
                return;
            }

            const p = json.product;
            const detailsHtml = `
                <h5>${escapeHtml(p.name || 'Unnamed product')}</h5>
                <p class="mb-1"><strong>Barcode / SKU:</strong> ${escapeHtml(p.barcode || p.sku || '')}</p>
                <p class="mb-1"><strong>Price:</strong> ${formatPrice(p.price)}</p>
                <p class="mb-1"><strong>Stock:</strong> ${Number(p.stock_quantity || 0)}</p>
                <p class="mb-1"><strong>Category:</strong> ${escapeHtml(p.category_name || '')}</p>
                ${p.expiry ? `<p class=\"mb-1\"><strong>Expiry:</strong> ${escapeHtml(p.expiry)}</p>` : ''}
            `;
            updateProductDetails(detailsHtml);

        } catch (error) {
            console.error('Error fetching product', error);
            updateProductDetails('<div class="alert alert-danger">Error searching for product.</div>');
        } finally {
            setTimeout(() => { lastScanned = null; }, 1500);
        }
    }

    function formatPrice(v) {
        if (v === undefined || v === null || v === '') return '0.00';
        return Number(v).toFixed(2);
    }

    function escapeHtml(text) {
        if (!text && text !== 0) return '';
        return String(text).replace(/[&<>"'`]/g, function (s) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
                '`': '&#96;'
            })[s];
        });
    }

    function startQuagga() {
        if (scanning) return;
        if (typeof Quagga === 'undefined') {
            alert('Scanner library not loaded.');
            return;
        }

        placeholder && (placeholder.style.display = 'none');
        scannerArea.innerHTML = '<div id="interactive" class="viewport"><video autoplay muted playsinline></video><canvas class="drawingBuffer"></canvas></div>';

        Quagga.init({
            inputStream: {
                type : "LiveStream",
                target: document.querySelector('#interactive'),
                constraints: {
                    width: { min: 640, ideal: 1280 },
                    height: { min: 480, ideal: 720 },
                    facingMode: "environment"
                }
            },
            decoder: {
                readers: [
                    "ean_reader",
                    "ean_8_reader",
                    "code_128_reader",
                    "code_39_reader",
                    "upc_reader",
                    "upc_e_reader"
                ]
            },
            locate: true,
            numOfWorkers: navigator.hardwareConcurrency ? Math.max(1, navigator.hardwareConcurrency - 1) : 2
        }, function(err) {
            if (err) {
                console.error('Quagga init error', err);
                updateProductDetails('<div class="alert alert-danger">Failed to access camera or initialize scanner. Check camera permissions and HTTPS requirement on mobile browsers.</div>');
                placeholder && (placeholder.style.display = 'block');
                return;
            }
            Quagga.start();
            scanning = true;
            startBtn.disabled = true;
            stopBtn.disabled = false;
            updateProductDetails('<div class="alert alert-info">Scanner active. Point the camera at a barcode.</div>');

            Quagga.onDetected(function(result) {
                if (!result || !result.codeResult || !result.codeResult.code) return;
                const code = result.codeResult.code;
                processBarcode(code);
            });
        });
    }

    function stopQuagga() {
        if (!scanning) return;
        try {
            Quagga.stop();
        } catch (e) {
            console.warn('Quagga stop error', e);
        }
        scanning = false;
        startBtn.disabled = false;
        stopBtn.disabled = true;
        scannerArea.innerHTML = '<div id="scanner-placeholder" class="text-center">Click "Start Scanner" to open camera</div>';
        placeholder && (placeholder.style.display = 'block');
    }

    startBtn.addEventListener('click', function(e) {
        e.preventDefault();
        startQuagga();
    });

    stopBtn.addEventListener('click', function(e) {
        e.preventDefault();
        stopQuagga();
    });

    manualBtn.addEventListener('click', function(e) {
        e.preventDefault();
        const code = manualInput.value && manualInput.value.trim();
        if (!code) return;
        processBarcode(code);
    });

    // allow Enter key on manual input
    manualInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            manualBtn.click();
        }
    });

});
