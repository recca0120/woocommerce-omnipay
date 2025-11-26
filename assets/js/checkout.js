/**
 * WooCommerce Omnipay Checkout Scripts
 *
 * 處理結帳頁面的信用卡欄位格式化（原生 JavaScript）
 */
(function() {
    'use strict';

    /**
     * 格式化信用卡號碼（每 4 位數加一個空格）
     *
     * @param {string} value 原始輸入值
     * @return {string} 格式化後的值
     */
    function formatCardNumber(value) {
        // 移除所有非數字字元
        var cleaned = value.replace(/\D/g, '');

        // 限制最多 16 位數
        cleaned = cleaned.substring(0, 16);

        // 每 4 位數加一個空格
        var formatted = cleaned.match(/.{1,4}/g);

        return formatted ? formatted.join(' ') : '';
    }

    /**
     * 格式化有效期月份（MM）
     *
     * @param {string} value 原始輸入值
     * @return {string} 格式化後的值
     */
    function formatExpiryMonth(value) {
        // 只保留數字
        var cleaned = value.replace(/\D/g, '');

        // 限制最多 2 位數
        cleaned = cleaned.substring(0, 2);

        // 如果第一位是 2-9，自動補 0
        if (cleaned.length === 1 && parseInt(cleaned) > 1) {
            cleaned = '0' + cleaned;
        }

        // 驗證範圍 01-12
        if (cleaned.length === 2) {
            var month = parseInt(cleaned);
            if (month < 1) {
                cleaned = '01';
            } else if (month > 12) {
                cleaned = '12';
            }
        }

        return cleaned;
    }

    /**
     * 格式化有效期年份（YYYY）
     *
     * @param {string} value 原始輸入值
     * @return {string} 格式化後的值
     */
    function formatExpiryYear(value) {
        // 只保留數字
        var cleaned = value.replace(/\D/g, '');

        // 限制最多 4 位數
        cleaned = cleaned.substring(0, 4);

        return cleaned;
    }

    /**
     * 格式化 CVV（3-4 位數）
     *
     * @param {string} value 原始輸入值
     * @return {string} 格式化後的值
     */
    function formatCVV(value) {
        // 只保留數字
        var cleaned = value.replace(/\D/g, '');

        // 限制最多 4 位數
        cleaned = cleaned.substring(0, 4);

        return cleaned;
    }

    /**
     * 初始化信用卡欄位格式化
     */
    function initCardFieldFormatting() {
        var cardNumber = document.querySelector('input[name="omnipay_number"]');
        var expiryMonth = document.querySelector('input[name="omnipay_expiryMonth"]');
        var expiryYear = document.querySelector('input[name="omnipay_expiryYear"]');
        var cvv = document.querySelector('input[name="omnipay_cvv"]');

        // 卡號格式化
        if (cardNumber) {
            cardNumber.addEventListener('input', function() {
                var cursorPosition = this.selectionStart;
                var oldValue = this.value;
                var newValue = formatCardNumber(oldValue);

                // 計算游標應該移動的位置
                // 如果新增了空格，游標需要往後移
                var spacesBeforeCursor = oldValue.substring(0, cursorPosition).split(' ').length - 1;
                var spacesAfterFormat = newValue.substring(0, cursorPosition).split(' ').length - 1;
                var cursorOffset = spacesAfterFormat - spacesBeforeCursor;

                this.value = newValue;

                // 恢復游標位置
                if (newValue !== oldValue) {
                    this.setSelectionRange(cursorPosition + cursorOffset, cursorPosition + cursorOffset);
                }
            });
        }

        // 有效期月份格式化
        if (expiryMonth) {
            expiryMonth.addEventListener('input', function() {
                this.value = formatExpiryMonth(this.value);
            });
        }

        // 有效期年份格式化
        if (expiryYear) {
            expiryYear.addEventListener('input', function() {
                this.value = formatExpiryYear(this.value);
            });
        }

        // CVV 格式化
        if (cvv) {
            cvv.addEventListener('input', function() {
                this.value = formatCVV(this.value);
            });
        }
    }

    // 在頁面載入時初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCardFieldFormatting);
    } else {
        initCardFieldFormatting();
    }

    // 當 WooCommerce 更新結帳區塊時重新初始化
    document.body.addEventListener('updated_checkout', initCardFieldFormatting);

})();
