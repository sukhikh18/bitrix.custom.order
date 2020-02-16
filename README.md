```php
executeComponent(function () {
    $basket = $this->getCurrentBasketObject();

    if ('INSERT_NEW_ORDER' === $action) {
        $basket = $this->getEmptyBasketObject();
        // Insert product to basket by ID
    }

    $this->initOrder($basket, $siteId);
    // @todo
    $this->setOrderProperties();
    $this->setOrderShipment($delivery_id);
    $this->setOrderPayment($payment_id);

    if (in_array($action, array('SAVE_BASKET', 'INSERT_NEW_ORDER'), true)) {
        // @todo
        $this->validatePropertiesList();
        // @todo
        $this->updateUserAccount();
        $this->insertNewOrder();
    }

    /** @var Int */
    $this->arResult['ORDER_ID'] = $this->order->GetId();
    /** @var array[CODE]<VALUE> */
    $this->arResult['PROPERTY_FIELD'] = $this->getPropertiesList();

    $this->arParams['IS_AJAX'] ? json_encode($this->arResult) : $this->includeComponentTemplate();
});
```