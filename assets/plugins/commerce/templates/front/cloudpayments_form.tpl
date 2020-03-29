<form action="" method="get" id="payment_request" style="display: none;"></form>
<script src="https://widget.cloudpayments.ru/bundles/cloudpayments"></script>
<script type="text/javascript">
    (function() {
        var payment_request = document.getElementById('payment_request');

        payment_request.addEventListener('submit', function (e) {
            e.preventDefault();
            var widget = new cp.CloudPayments(<?= json_encode($params) ?>);
            widget.charge(<?= json_encode($charge) ?>,
                function (options) {
                    window.location = options.onSuccess;
                },
                function (reason, options) {
                    window.location = options.onFail;
                }
            );
        });

        <?php if (ci()->commerce->getSetting('instant_redirect_to_payment') == 1 || !ci()->commerce->loadProcessor()->isOrderStarted()): ?> 
            payment_request.dispatchEvent(new Event('submit'));
        <?php endif; ?> 
    })();
</script>
