(function() {
    'use strict';

    /**
     * Initialize barcode rendering when DOM is ready
     */
    function initBarcodes() {
        var barcodeElements = document.querySelectorAll('.omnipay-barcode');

        if (barcodeElements.length === 0) {
            return;
        }

        // Check if JsBarcode is available
        if (typeof JsBarcode === 'undefined') {
            console.warn('JsBarcode library not loaded');
            return;
        }

        barcodeElements.forEach(function(element) {
            var value = element.getAttribute('data-barcode');
            var format = element.getAttribute('data-format') || 'CODE39';

            if (!value) {
                return;
            }

            try {
                JsBarcode(element, value, {
                    format: format,
                    width: 2,
                    height: 50,
                    displayValue: true,
                    fontSize: 14,
                    margin: 10
                });
            } catch (e) {
                console.error('Failed to render barcode:', e);
                // Show the value as text if barcode rendering fails
                element.textContent = value;
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBarcodes);
    } else {
        initBarcodes();
    }
})();
