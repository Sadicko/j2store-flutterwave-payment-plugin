<?php
defined('_JEXEC') or die('Restricted access');
?>
<form id="flutterwave-form" action="<?php echo $vars->callback_url; ?>" method="post">
    <script src="https://checkout.flutterwave.com/v3.js"></script>
    <input type="hidden" name="order_id" value="<?php echo $vars->order_id; ?>" />
    <input type="hidden" name="amount" value="<?php echo $vars->amount; ?>" />
    <input type="hidden" name="email" value="<?php echo $vars->user_email; ?>" />
    <input type="hidden" name="currency" value="<?php echo $vars->currency_code; ?>" />
    <button type="button" onclick="payWithFlutterwave()">Pay Now</button>
</form>

<script type="text/javascript">
    function payWithFlutterwave() {
        FlutterwaveCheckout({
            public_key: '<?php echo $vars->public_key; ?>',
            tx_ref: '' + Math.floor((Math.random() * 1000000000) + 1),
            amount: '<?php echo $vars->amount; ?>',
            currency: '<?php echo $vars->currency_code; ?>',
            payment_options: "card, mobilemoneyghana, ussd",
            customer: {
                email: '<?php echo $vars->user_email; ?>',
            },
            callback: function (data) {
                if (data.status === "successful") {
                    document.getElementById("flutterwave-form").submit();
                }
            },
            onclose: function() {
                // close modal
            },
        });
    }
</script>
