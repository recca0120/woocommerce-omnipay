/**
 * WooCommerce Omnipay Admin Scripts
 *
 * 處理後台管理介面的互動功能（原生 JavaScript）
 */
(function() {
    'use strict';

    /**
     * 更新頻率式定期付款欄位限制 (Frequency Recurring)
     * - Year (Y): frequency=1, execTimes=1-9
     * - Month (M): frequency=1-12, execTimes=1-99
     * - Day (D): frequency=1-365, execTimes=1-999
     */
    function updateFrequencyRecurringConstraints(periodTypeInput) {
        const row = periodTypeInput.closest('tr');
        const periodType = periodTypeInput.value.toUpperCase();

        const frequencyInput = row.querySelector('input[name^="frequency"]');
        const execTimesInput = row.querySelector('input[name^="execTimes"]');

        if (!frequencyInput || !execTimesInput) {
            return;
        }

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

        if (parseInt(frequencyInput.value) > freqMax) {
            frequencyInput.value = freqMax;
        }
        if (parseInt(execTimesInput.value) > execMax) {
            execTimesInput.value = execMax;
        }
    }

    /**
     * 更新排程式定期付款欄位限制 (Scheduled Recurring)
     * - Year (Y): periodTimes=2-99
     * - Month (M): periodTimes=2-99
     * - Week (W): periodTimes=2-99
     * - Day (D): periodTimes=2-999
     */
    function updateScheduledRecurringConstraints(periodTypeInput) {
        const row = periodTypeInput.closest('tr');
        const periodType = periodTypeInput.value.toUpperCase();

        const periodTimesInput = row.querySelector('input[name^="periodTimes"]');

        if (!periodTimesInput) {
            return;
        }

        let timesMax = 99;
        const timesMin = 2;

        if (periodType === 'D') {
            timesMax = 999;
        }

        periodTimesInput.setAttribute('min', timesMin);
        periodTimesInput.setAttribute('max', timesMax);

        const currentValue = parseInt(periodTimesInput.value);
        if (currentValue > timesMax) {
            periodTimesInput.value = timesMax;
        } else if (currentValue < timesMin) {
            periodTimesInput.value = timesMin;
        }
    }

    /**
     * 根據週期類型更新欄位限制（統一入口）
     */
    function updatePeriodConstraints(periodTypeInput) {
        updateFrequencyRecurringConstraints(periodTypeInput);
        updateScheduledRecurringConstraints(periodTypeInput);
    }

    /**
     * 初始化 WooCommerce 風格的輸入表格
     *
     * @param {HTMLElement} container 容器元素 (td.forminp)
     */
    function initInputTable(container) {
        const containerId = container.id;
        if (!containerId) {
            return;
        }

        const tbody = container.querySelector('table tbody.accounts');
        if (!tbody) {
            return;
        }

        const rowTemplate = document.getElementById(containerId + '-row-template');
        if (!rowTemplate) {
            return;
        }

        // Add new row
        container.addEventListener('click', function(e) {
            if (e.target.classList.contains('add')) {
                e.preventDefault();
                const index = tbody.querySelectorAll('.account').length;
                const newRow = rowTemplate.innerHTML.replace(/\{\{INDEX\}\}/g, index);
                tbody.insertAdjacentHTML('beforeend', newRow);
            }

            // Remove selected rows (WooCommerce style - removes rows with .current class)
            if (e.target.classList.contains('remove_rows')) {
                e.preventDefault();
                const selectedRows = tbody.querySelectorAll('tr.current');
                if (selectedRows.length > 0) {
                    selectedRows.forEach(function(row) {
                        row.remove();
                    });
                } else {
                    // Fallback: remove all rows if none selected
                    tbody.innerHTML = '';
                }
            }
        });

        // Toggle row selection on click (WooCommerce style)
        tbody.addEventListener('click', function(e) {
            const row = e.target.closest('tr.account');
            if (row && !e.target.matches('input, select, button, a')) {
                row.classList.toggle('current');
            }
        });

        // 監聽 input 事件
        container.addEventListener('input', function(e) {
            // 監聽 periodType 變更，動態更新限制 (僅適用於 DCA 表格)
            if (e.target.name && e.target.name.startsWith('periodType')) {
                updatePeriodConstraints(e.target);
            }
        });

        // 初始化現有 rows 的限制 (僅適用於 DCA 表格)
        const periodTypeInputs = tbody.querySelectorAll('input[name^="periodType"]');
        periodTypeInputs.forEach(function(input) {
            updatePeriodConstraints(input);
        });
    }

    /**
     * 初始化所有 WooCommerce 風格輸入表格
     */
    function initAllInputTables() {
        // 尋找所有包含 wc_input_table 的 forminp 容器
        const containers = document.querySelectorAll('td.forminp');
        containers.forEach(function(container) {
            if (container.id && container.querySelector('table.wc_input_table')) {
                initInputTable(container);
            }
        });
    }

    // 在頁面載入時初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllInputTables);
    } else {
        initAllInputTables();
    }

})();
