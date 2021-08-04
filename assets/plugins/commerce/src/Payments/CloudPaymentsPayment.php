<?php

namespace Commerce\Payments;

use Helpers\Gpc;
use Exception;

require_once MODX_BASE_PATH . 'assets/snippets/FormLister/lib/Gpc.php';

class CloudPaymentsPayment extends Payment implements \Commerce\Interfaces\Payment
{
    public function __construct($modx, array $params = [])
    {
        parent::__construct($modx, $params);
        $this->lang = $modx->commerce->getUserLanguage('cloudpayments');
    }

    public function getMarkup()
    {
        if (empty($this->getSetting('public_id'))) {
            return $this->lang['cloudpayments.error.empty_public_id'];
        }

        return '';
    }

    public function getPaymentMarkup()
    {
        $debug = !empty($this->getSetting('debug'));

        $processor = $this->modx->commerce->loadProcessor();
        $order     = $processor->getOrder();
        $currency  = ci()->currency;

        $currencyCode = $this->getSetting('currency');
        if (empty($currencyCode)) {
            $currencyCode = $order['currency'];
        }

        $payment = $this->createPayment($order['id'], $currency->convert($order['amount'], $order['currency'], $currencyCode));

        $charge = [
            'publicId'    => $this->getSetting('public_id'),
            'amount'      => $payment['amount'],
            'currency'    => $currencyCode,
            'invoiceId'   => $order['id'],
            'skin'        => $this->getSetting('skin'),
            'onSuccess'   => $this->modx->getConfig('site_url') . 'commerce/cloudpayments/payment-success?paymentHash=' . $payment['hash'],
            'onFail'      => $this->modx->getConfig('site_url') . 'commerce/cloudpayments/payment-failed',
            'description' => ci()->tpl->parseChunk($this->lang['payments.payment_description'], [
                'order_id'  => $order['id'],
                'site_name' => $this->modx->getConfig('site_name'),
            ]),
            'data' => [
                'paymentHash' => $payment['hash'],
                'paymentId'   => $payment['id'],
            ],
        ];

        if (!empty($order['email']) && filter_var($order['email'], FILTER_VALIDATE_EMAIL)) {
            $charge['email'] = $order['email'];
        }

        if (!empty($order['phone'])) {
            $charge['data']['phone'] = $order['phone'];
        }

        if (!empty($order['name'])) {
            $charge['data']['name'] = $order['name'];
        }

        $items = $this->prepareItems($processor->getCart());
        $vat   = $this->getSetting('vat');

        $originalAmount   = $currency->convert($payment['amount'], $currencyCode, $order['currency']);
        $isPartialPayment = $payment['amount'] < $order['amount'];

        if ($isPartialPayment) {
            $items = $this->decreaseItemsAmount($items, $order['amount'], $originalAmount);
        }

        $products = [];

        foreach ($items as $i => $item) {
            $products[] = [
                'label'           => $item['name'],
                'price'           => $currency->convert($item['price'], $order['currency'], $currencyCode),
                'quantity'        => $item['count'],
                'amount'          => $currency->convert($item['total'], $order['currency'], $currencyCode),
                'measurementUnit' => isset($item['meta']['measurements']) ? $item['meta']['measurements'] : $this->lang['measures.units'],
                'method'          => 0,
                'object'          => 0,
                'vat'             => $vat,
            ];
        }

        $charge['data']['cloudPayments']['СustomerReceipt'] = [
            'items' => $products,
        ];

        $params = [
            'language' => $this->getSetting('language'),
        ];

        $view = new \Commerce\Module\Renderer($this->modx, null, [
            'path' => 'assets/plugins/commerce/templates/front/',
        ]);

        $response = $view->render('cloudpayments_form.tpl', [
            'params' => $params,
            'charge' => $charge,
        ]);

        if ($debug) {
            $this->modx->logEvent(0, 1, "Charge data: <pre>" . htmlentities(print_r($charge, true)) . "</pre>\n\nParams: <pre>" . htmlentities(print_r($params, true)) . "</pre>\n\nRequest script: <pre>" . htmlentities($response) . "</pre>", 'Commerce CloudPayments Debug: payment start');
        }

        return $response;
    }

    public function handleCallback()
    {
        if (!isset($_SERVER['HTTP_CONTENT_HMAC'])) {
            return false;
        }

        foreach (['InvoiceId', 'Amount', 'TransactionId', 'DateTime', 'Data'] as $field) {
            if (!isset($_POST[$field]) || !is_scalar($_POST[$field])) {
                $this->modx->logEvent(0, 3, 'Not enough data', 'Commerce CloudPayments');
                return false;
            }
        }

        $data = $_POST;
        (new Gpc(['Data']))->removeGpc($data);

        $debug = !empty($this->getSetting('debug'));
        $hash  = base64_encode(hash_hmac('SHA256', file_get_contents('php://input'), $this->getSetting('api_password'), true));

        if ($debug) {
            $this->modx->logEvent(0, 1, "Request data: <pre>" . htmlentities(print_r($data, true)) . "</pre>\nRequest hash: <pre>" . htmlentities($_SERVER['HTTP_CONTENT_HMAC']) . "</pre>\nCalculated hash: <pre>" . htmlentities($hash) . "</pre>", 'Commerce CloudPayments Debug: callback start');
        }

        if ($hash !== $_SERVER['HTTP_CONTENT_HMAC']) {
            $this->modx->logEvent(0, 3, 'Signature check failed: ' . $hash . ' != ' . $_SERVER['HTTP_CONTENT_HMAC'], 'Commerce CloudPayments');
            return false;
        };

        $json = json_decode($data['Data']);

        if (!empty($json->paymentId)) {
            try {
                $processor = $this->modx->commerce->loadProcessor();
                $order = $processor->loadOrder($data['InvoiceId']);

                $currencyCode = $this->getSetting('currency');
                if (empty($currencyCode)) {
                    $currencyCode = $order['currency'];
                }

                $amount = ci()->currency->convert($data['Amount'], $currencyCode, $order['currency']);
                $processor->processPayment($json->paymentId, $amount);
            } catch (Exception $e) {
                $this->modx->logEvent(0, 3, 'Payment processing failed: ' . $e->getMessage(), 'Commerce CloudPayments');
                return false;
            }

            echo '{"code":0}';
            return true;
        }

        $this->modx->logEvent(0, 3, 'Not enough data', 'Commerce CloudPayments');
        return false;
    }

    public function getRequestPaymentHash()
    {
        if (isset($_REQUEST['paymentHash']) && is_scalar($_REQUEST['paymentHash'])) {
            return $_REQUEST['paymentHash'];
        }

        return null;
    }
}
