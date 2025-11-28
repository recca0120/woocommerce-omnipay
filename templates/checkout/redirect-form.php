<?php
/**
 * Redirect form template for POST redirect gateways
 *
 * This template renders a hidden form that auto-submits to the payment gateway
 *
 * @var string $url    The gateway URL to submit to
 * @var string $method The HTTP method (POST/GET)
 * @var array $data   The form data to submit
 */
defined('ABSPATH') || exit;
?>
<form id="omnipay-redirect-form" action="<?php echo esc_url($url); ?>" method="<?php echo esc_attr($method); ?>">
    <?php foreach ($data as $name => $value) { ?>
        <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" />
    <?php } ?>
</form>
<script>document.getElementById("omnipay-redirect-form").submit();</script>
