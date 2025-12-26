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
            if (e.target.name && e.target.name.startsWith('periodType')) {
                updatePeriodConstraints(e.target);
            }
        });

        // 初始化現有 rows 的限制
        const periodTypeInputs = tbody.querySelectorAll('input[name^="periodType"]');
        periodTypeInputs.forEach(function(input) {
            updatePeriodConstraints(input);
        });
    }

    /**
     * 初始化所有 DCA Tables
     */
    function initAllDcaTables() {
        // 自動偵測所有 DCA periods table
        const tables = document.querySelectorAll('[id$="_periods"]');
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
