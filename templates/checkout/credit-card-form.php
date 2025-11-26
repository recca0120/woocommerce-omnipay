<?php
/**
 * Credit Card Form Template
 *
 * @var string $gateway_id Gateway ID
 * @var array $billing_data Billing data with firstName and lastName
 */
defined('ABSPATH') || exit;
?>
<fieldset id="<?php echo esc_attr($gateway_id); ?>-cc-form" class="wc-credit-card-form wc-payment-form">
    <p class="form-row form-row-wide">
        <label><?php esc_html_e('Card Number', 'woocommerce-omnipay'); ?> <span class="required">*</span></label>
        <input type="text"
               name="omnipay_number"
               class="input-text wc-credit-card-form-card-number"
               inputmode="numeric"
               autocomplete="cc-number"
               autocorrect="no"
               autocapitalize="no"
               spellcheck="no"
               placeholder="•••• •••• •••• ••••" />
    </p>

    <p class="form-row form-row-first">
        <label><?php esc_html_e('Expiry Month', 'woocommerce-omnipay'); ?> <span class="required">*</span></label>
        <input type="text"
               name="omnipay_expiryMonth"
               class="input-text"
               inputmode="numeric"
               autocomplete="cc-exp-month"
               placeholder="MM"
               maxlength="2" />
    </p>

    <p class="form-row form-row-last">
        <label><?php esc_html_e('Expiry Year', 'woocommerce-omnipay'); ?> <span class="required">*</span></label>
        <input type="text"
               name="omnipay_expiryYear"
               class="input-text"
               inputmode="numeric"
               autocomplete="cc-exp-year"
               placeholder="YYYY"
               maxlength="4" />
    </p>

    <div class="clear"></div>

    <p class="form-row form-row-first">
        <label><?php esc_html_e('CVV', 'woocommerce-omnipay'); ?> <span class="required">*</span></label>
        <input type="text"
               name="omnipay_cvv"
               class="input-text wc-credit-card-form-card-cvc"
               inputmode="numeric"
               autocomplete="cc-csc"
               placeholder="•••"
               maxlength="4" />
    </p>

    <div class="clear"></div>

    <p class="form-row form-row-first">
        <label><?php esc_html_e('First Name', 'woocommerce-omnipay'); ?> <span class="required">*</span></label>
        <input type="text"
               name="omnipay_firstName"
               class="input-text"
               autocomplete="given-name"
               value="<?php echo esc_attr($billing_data['firstName']); ?>" />
    </p>

    <p class="form-row form-row-last">
        <label><?php esc_html_e('Last Name', 'woocommerce-omnipay'); ?> <span class="required">*</span></label>
        <input type="text"
               name="omnipay_lastName"
               class="input-text"
               autocomplete="family-name"
               value="<?php echo esc_attr($billing_data['lastName']); ?>" />
    </p>

    <div class="clear"></div>
</fieldset>
