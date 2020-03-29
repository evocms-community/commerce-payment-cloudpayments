//<?php
/**
 * Payment CloudPayments
 *
 * CloudPayments payments processing
 *
 * @category    plugin
 * @version     0.1.1
 * @author      mnoskov
 * @internal    @events OnRegisterPayments,OnBeforeOrderSending,OnManagerBeforeOrderRender
 * @internal    @properties &title=Title;text; &public_id=Идентификатор сайта;text; &api_password=Пароль для API;text; &currency=Валюта;list;Использовать валюту заказа==||Российский рубль==RUB||Евро==EUR||Доллар США==USD||Фунт стерлингов==GBP||Украинская гривна==UAH||Белорусский рубль==BYN||Казахский тенге==KZT||Азербайджанский манат==AZN||Швейцарский франк==CHF||Чешская крона==CZK||Канадский доллар==CAD||Польский злотый==PLN||Шведская крона==SEK||Турецкая лира==TRY||Китайский юань==CNY||Индийская рупия==INR||Бразильский реал==BRL||Южноафриканский рэнд==ZAR||Узбекский сум==UZS||Болгарский лев==BGL; &vat=Ставка НДС;list;0%==0||10%==10||20%==20;20 &skin=Дизайн виджета;list;Классический==classic||Современный==modern||Минималистичный==mini;classic &language=Язык интерфейса;list;Русский==ru-RU||Английский==en-US||Латышский==lv||Азербайджанский==az||Русский==kk||Казахский==kk-KZ||Украинский==uk||Польский==pl||Португальский==pt||Чешский==cs-CZ||Вьетнамский==vi-VN||Турецкий==tr-TR||Испанский==es-ES;ru-RU &debug=Отладка;list;Нет==0||Да==1;1
 * @internal    @modx_category Commerce
 * @internal    @installset base
*/

if (empty($modx->commerce) && !defined('COMMERCE_INITIALIZED')) {
    return;
}

$isSelectedPayment = !empty($order['fields']['payment_method']) && $order['fields']['payment_method'] == 'cloudpayments';
$commerce = ci()->commerce;
$lang = $commerce->getUserLanguage('cloudpayments');

switch ($modx->event->name) {
    case 'OnRegisterPayments': {
        $class = new \Commerce\Payments\CloudPaymentsPayment($modx, $params);

        if (empty($params['title'])) {
            $params['title'] = $lang['cloudpayments.caption'];
        }

        $commerce->registerPayment('cloudpayments', $params['title'], $class);
        break;
    }

    case 'OnBeforeOrderSending': {
        if ($isSelectedPayment) {
            $FL->setPlaceholder('extra', $FL->getPlaceholder('extra', '') . $commerce->loadProcessor()->populateOrderPaymentLink());
        }

        break;
    }

    case 'OnManagerBeforeOrderRender': {
        if (isset($params['groups']['payment_delivery']) && $isSelectedPayment) {
            $params['groups']['payment_delivery']['fields']['payment_link'] = [
                'title'   => $lang['cloudpayments.link_caption'],
                'content' => function($data) use ($commerce) {
                    return $commerce->loadProcessor()->populateOrderPaymentLink('@CODE:<a href="[+link+]" target="_blank">[+link+]</a>');
                },
                'sort' => 50,
            ];
        }

        break;
    }
}
