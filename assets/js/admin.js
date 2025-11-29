/**
 * WooCommerce Omnipay Admin Scripts
 *
 * 處理後台管理介面的互動功能（原生 JavaScript）
 */
(function() {
    'use strict';

    /**
     * 初始化 DCA Periods Table
     *
     * @param {string} fieldKey 欄位 key
     */
    function initDcaPeriodsTable(fieldKey) {
        const container = document.getElementById(fieldKey);
        if (!container) {
            return;
        }

        const tbody = container.querySelector('table tbody');
        const rowTemplate = document.getElementById(fieldKey + '-row-template').innerHTML;

        // Add new row
        container.addEventListener('click', function(e) {
            if (e.target.classList.contains('add')) {
                e.preventDefault();
                const index = tbody.querySelectorAll('.account').length;
                const newRow = rowTemplate.replace(/\{\{INDEX\}\}/g, index);
                tbody.insertAdjacentHTML('beforeend', newRow);
            }

            // Remove all rows
            if (e.target.classList.contains('remove_rows')) {
                e.preventDefault();
                tbody.innerHTML = '';
            }
        });
    }

    /**
     * 初始化所有 DCA Tables
     */
    function initAllDcaTables() {
        // 自動偵測所有 DCA periods table
        const tables = document.querySelectorAll('[id$="_dca_periods"]');
        tables.forEach(function(table) {
            initDcaPeriodsTable(table.id);
        });
    }

    // 在頁面載入時初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllDcaTables);
    } else {
        initAllDcaTables();
    }

})();
