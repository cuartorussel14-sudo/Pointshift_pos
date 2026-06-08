// hardware.js: barcode scanner (keyboard-wedge) + QZ Tray scaffold
// Initialize when DOM is ready. If script is loaded after DOMContentLoaded, run immediately.
(function initHardware() {
    function main() {
        console.debug('hardware.js main() init');
        // Visual indicator for troubleshooting: briefly show that the script loaded
        try {
            if (!document.getElementById('hardware-js-loaded')) {
                const badge = document.createElement('div');
                badge.id = 'hardware-js-loaded';
                badge.textContent = 'Hardware JS loaded';
                badge.style.position = 'fixed';
                badge.style.left = '8px';
                badge.style.top = '8px';
                badge.style.zIndex = 2200;
                badge.style.background = 'rgba(13,110,253,0.9)';
                badge.style.color = '#fff';
                badge.style.padding = '4px 8px';
                badge.style.borderRadius = '4px';
                badge.style.fontSize = '12px';
                document.body.appendChild(badge);
                setTimeout(() => { try { badge.remove(); } catch (e) {} }, 2500);
            }
        } catch (e) { console.debug('hardware loaded badge failed', e); }
        // Barcode scanner (keyboard-wedge) - hidden input
        (function () {
        let buffer = '';
        let lastTime = Date.now();
        const scannerInput = document.createElement('input');
        scannerInput.type = 'text';
        scannerInput.id = 'scanner-input';
        scannerInput.autocomplete = 'off';
        scannerInput.style.position = 'absolute';
        scannerInput.style.left = '-10000px';
        document.body.appendChild(scannerInput);

        function resetBuffer() { buffer = ''; }

        // Focus hidden input when page focused
        window.addEventListener('focus', () => scannerInput.focus());
        // Start focused
        scannerInput.focus();

        scannerInput.addEventListener('input', (e) => {
            const now = Date.now();
            if (now - lastTime > 300) buffer = '';
            lastTime = now;
            buffer += e.target.value;
            scannerInput.value = '';
        });

        scannerInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const code = buffer.trim();
                resetBuffer();
                if (code) {
                    // POST to pos.php to find product by barcode
                    fetch('pos.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=get_product_by_barcode&barcode=' + encodeURIComponent(code)
                    }).then(r => r.json()).then(product => {
                        if (product && product.id) {
                            if (typeof addToCart === 'function') {
                                addToCart(product);
                            } else {
                                console.warn('addToCart not available');
                            }
                        } else {
                            // Optionally try search by name or show message
                            alert('Product not found for barcode: ' + code);
                        }
                    }).catch(err => {
                        console.error('Barcode lookup error', err);
                    });
                }
            }
        });

        // Try to keep focus on the hidden input
        setInterval(() => {
            const active = document.activeElement;
            if (!active || active.id !== 'scanner-input') {
                scannerInput.focus();
            }
        }, 1000);
        })();

        // QZ Tray scaffold
    const qzLoaded = typeof qz !== 'undefined';
    const printerSelect = document.getElementById('printer-select');
    const configureBtn = document.getElementById('configure-printer-btn');
    console.debug('hardware elements:', { printerSelect: !!printerSelect, configureBtn: !!configureBtn });
        const printBtn = document.getElementById('print-receipt-btn');
        const testPrintBtn = document.getElementById('test-print-btn');
        const savePrinterBtn = document.getElementById('save-printer-btn');

        function showPrinterModal() {
            const modalEl = document.getElementById('printerConfigModal');
            if (!modalEl) return;
            try {
                // Prefer Bootstrap API if available
                if (typeof bootstrap !== 'undefined' && bootstrap && typeof bootstrap.Modal === 'function') {
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                } else {
                    throw new Error('Bootstrap Modal API not available');
                }
            } catch (err) {
                console.warn('Bootstrap modal not available or failed, using manual fallback', err);
                try {
                    // Minimal manual fallback to make modal visible
                    modalEl.classList.add('show');
                    modalEl.style.display = 'block';
                    modalEl.setAttribute('aria-modal', 'true');
                    modalEl.removeAttribute('aria-hidden');

                    // add backdrop
                    const backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    backdrop.id = 'manual-modal-backdrop';
                    document.body.appendChild(backdrop);

                    // ensure close buttons and backdrop remove the manual backdrop and restore attributes
                    function cleanupManualModal() {
                        try {
                            const b = document.getElementById('manual-modal-backdrop');
                            if (b && b.parentNode) b.parentNode.removeChild(b);
                            modalEl.classList.remove('show');
                            modalEl.style.display = 'none';
                            modalEl.setAttribute('aria-hidden', 'true');
                            modalEl.removeAttribute('aria-modal');
                        } catch (e) { console.debug('cleanupManualModal failed', e); }
                    }

                    // close buttons inside modal
                    modalEl.querySelectorAll('[data-bs-dismiss], .btn-close').forEach(btn => {
                        try { btn.addEventListener('click', cleanupManualModal); } catch (e) {}
                    });

                    // clicking backdrop should also close
                    try {
                        document.getElementById('manual-modal-backdrop')?.addEventListener('click', cleanupManualModal);
                    } catch (e) {}
                } catch (e) { console.error('Manual modal fallback failed', e); }
                // report the error to server for diagnostics
                try { reportJSError({ message: 'Bootstrap modal init failed or unavailable', error: String(err), stack: err && err.stack }); } catch (e) {}
            }

            // populate printers
            if (typeof qz !== 'undefined' && qz.websocket && qz.websocket.isActive()) {
                qz.printers.find().then(list => {
                    populatePrinterList(list);
                }).catch(err => {
                    console.warn('QZ find printers failed', err);
                    populatePrinterList([]);
                });
            } else {
                populatePrinterList([]);
            }
        }

        // Expose a global helper so other scripts or console can open the modal
        try {
            window.openPrinterConfig = showPrinterModal;
        } catch (e) { console.debug('Unable to expose openPrinterConfig', e); }

        // Ensure configure button has a fallback onclick in case event listeners aren't attached
        try {
            if (configureBtn && !configureBtn.getAttribute('data-open-printer-bound')) {
                configureBtn.addEventListener('click', function (ev) {
                    console.debug('configureBtn fallback clicked');
                    try { window.openPrinterConfig(); } catch (e) { console.error('openPrinterConfig missing', e); }
                });
                configureBtn.setAttribute('data-open-printer-bound', '1');
            }
        } catch (e) { console.debug('Failed to bind configureBtn fallback', e); }

        // Delegated click handler: catches clicks even if the direct handler wasn't attached
        document.addEventListener('click', function (ev) {
            try {
                const target = ev.target;
                if (target && target.closest && target.closest('#configure-printer-btn')) {
                    console.debug('Delegated handler caught configure-printer-btn click');
                    // Give visual feedback for users without DevTools
                    try { showLocalToast('Printer', 'Opening printer configuration...', 'info'); } catch (e) {}
                    // attempt QZ connect first
                    if (typeof qz !== 'undefined') {
                        qz.websocket.connect().then(() => console.log('QZ connected')).catch(err => console.warn('QZ connect', err));
                    }
                    showPrinterModal();
                }
            } catch (e) { console.error('Delegated configure click handler failed', e); }
        }, true);

        // Simple transient toast notification for quick user feedback
        function showLocalToast(title, message, type) {
            try {
                const types = { info: 'info', success: 'success', warning: 'warning', danger: 'danger', error: 'danger' };
                const cls = 'alert alert-' + (types[type] || 'info') + ' position-fixed top-0 end-0 m-3';
                const el = document.createElement('div');
                el.className = cls;
                el.style.zIndex = 2000;
                el.style.minWidth = '220px';
                el.innerHTML = `<div style="font-weight:600;">${title}</div><div style="font-size:0.85rem;">${message}</div>`;
                document.body.appendChild(el);
                setTimeout(() => {
                    try { el.classList.add('fade'); el.remove(); } catch (e) {}
                }, 3800);
                return el;
            } catch (e) { console.debug('showLocalToast failed', e); }
        }

        // Expose globally so other scripts can reuse
        try { window.showLocalToast = showLocalToast; } catch (e) {}

        // Report JS errors to the server for diagnosis
        function reportJSError(payload) {
            try {
                // Non-blocking fire-and-forget
                fetch('../ajax/log_js_error.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                }).catch(() => {});
            } catch (e) { /* ignore */ }
        }

        // Global error handlers
        try {
            window.addEventListener('error', function (ev) {
                try {
                    const err = ev.error || {};
                    reportJSError({ message: ev.message, filename: ev.filename || ev.target && ev.target.src, lineno: ev.lineno, colno: ev.colno, stack: err.stack || null, userAgent: navigator.userAgent, context: 'window.onerror' });
                } catch (e) {}
            });
            window.addEventListener('unhandledrejection', function (ev) {
                try {
                    const reason = ev.reason || {};
                    reportJSError({ message: 'UnhandledRejection', reason: typeof reason === 'string' ? reason : (reason.message || String(reason)), stack: reason.stack || null, userAgent: navigator.userAgent, context: 'unhandledrejection' });
                } catch (e) {}
            });
        } catch (e) { console.debug('Global error handlers not installed', e); }

        function populatePrinterList(list) {
            if (!printerSelect) return;
            printerSelect.innerHTML = '';
            if (!list || list.length === 0) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.text = 'No printers found';
                printerSelect.appendChild(opt);
                return;
            }
            const saved = localStorage.getItem('pos_printer_name') || '';
            list.forEach(name => {
                const opt = document.createElement('option');
                opt.value = name;
                opt.text = name;
                if (name === saved) opt.selected = true;
                printerSelect.appendChild(opt);
            });
        }

        if (configureBtn) configureBtn.addEventListener('click', () => {
            // attempt QZ connect first
            if (typeof qz !== 'undefined') {
                qz.websocket.connect().then(() => console.log('QZ connected')).catch(err => console.warn('QZ connect', err));
            }
            showPrinterModal();
        });

        if (savePrinterBtn) savePrinterBtn.addEventListener('click', () => {
            const sel = printerSelect ? printerSelect.value : '';
            if (sel) {
                localStorage.setItem('pos_printer_name', sel);
                // simple toast
                alert('Printer saved: ' + sel);
            } else {
                alert('No printer selected');
            }
        });

        if (testPrintBtn) testPrintBtn.addEventListener('click', () => {
            const sel = printerSelect ? printerSelect.value : '';
            if (!sel) return alert('Select a printer first');
            // Print a short test
            const data = ['*** TEST PRINT ***\n', 'PointShift POS - Test\n', '\n', '----------------\n', '\n'];
            printText(sel, data);
        });

        if (printBtn) printBtn.addEventListener('click', () => {
            // Gather receipt text from DOM or request a server-rendered receipt
            const receiptText = buildReceiptText();
            const printer = localStorage.getItem('pos_printer_name');
            if (!printer) return alert('No printer configured. Click Configure Printer first.');
            printText(printer, [receiptText]);
        });

        function buildReceiptText() {
            // Minimal receipt generator based on DOM values (customize as needed)
            const storeName = document.querySelector('h1') ? document.querySelector('h1').innerText : document.title;
            const total = document.getElementById('total') ? document.getElementById('total').innerText : '';
            let lines = '';
            lines += storeName + '\n';
            lines += '-------------------------------\n';
            // iterate cart rows if available
            const cartRows = document.querySelectorAll('#cart-tbody tr');
            cartRows.forEach(r => {
                const cols = r.querySelectorAll('td');
                if (cols.length >= 3) {
                    const name = cols[0].innerText.trim();
                    const qty = cols[1].innerText.trim();
                    const price = cols[2].innerText.trim();
                    lines += `${name} ${qty} ${price}\n`;
                }
            });
            lines += '-------------------------------\n';
            lines += 'TOTAL: ' + total + '\n';
            lines += '\n\n';
            return lines;
        }

        function printText(printer, dataArray) {
            if (typeof qz === 'undefined' || !qz.websocket || !qz.websocket.isActive()) {
                alert('Printing requires QZ Tray to be running and connected. Please install and start QZ Tray.');
                return;
            }
            qz.printers.find(printer).then(() => {
                const config = qz.configs.create(printer);
                return qz.print(config, dataArray);
            }).then(() => {
                alert('Print job sent');
            }).catch(err => {
                console.error('Print error', err);
                alert('Print failed: ' + err);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', main);
    } else {
        main();
    }
})();
