/**
 * WooCommerce Omnipay Admin Scripts
 *
 * 處理後台管理介面的互動功能（原生 JavaScript）
 */
(function() {
    'use strict';

    /**
     * 根據週期類型更新欄位限制
     * Year (Y): frequency=1, execTimes=1-9
     * Month (M): frequency=1-12, execTimes=1-99
     * Day (D): frequency=1-365, execTimes=1-999
     */
    function updatePeriodConstraints(periodTypeInput) {
        const row = periodTypeInput.closest('tr');
        const frequencyInput = row.querySelector('input[name^="dca_frequency"]');
        const execTimesInput = row.querySelector('input[name^="dca_execTimes"]');
        const periodType = periodTypeInput.value.toUpperCase();

        let freqMax = 365;
        let execMax = 999;

        if (periodType === 'Y') {
            freqMax = 1;
            execMax = 9;
        } else if (periodType === 'M') {
            freqMax = 12;
            execMax = 99;
        }

        frequencyInput.setAttribute('max', freqMax);
        execTimesInput.setAttribute('max', execMax);

        // 調整超出範圍的值
        if (parseInt(frequencyInput.value) > freqMax) {
            frequencyInput.value = freqMax;
        }
        if (parseInt(execTimesInput.value) > execMax) {
            execTimesInput.value = execMax;
        }
    }

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

        // 監聽 periodType 變更，動態更新限制
        container.addEventListener('input', function(e) {
            if (e.target.name && e.target.name.startsWith('dca_periodType')) {
                updatePeriodConstraints(e.target);
            }
        });

        // 初始化現有 rows 的限制
        const periodTypeInputs = tbody.querySelectorAll('input[name^="dca_periodType"]');
        periodTypeInputs.forEach(function(input) {
            updatePeriodConstraints(input);
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
