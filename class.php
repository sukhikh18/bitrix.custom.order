<? if ( ! defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * New bitrix checkout component
 * maybe @todo
 *     - Set $USER as object property
 *     - fill arProperties and arPropertyValues on onPrepareComponentParams method
 */

use Bitrix\Main\Context as ContextAlias;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Sale\Basket;
use Bitrix\Sale\BasketItem;
use Bitrix\Sale\Delivery;
use Bitrix\Sale\Order;
use Bitrix\Sale\PropertyValue;
use Bitrix\Sale\PropertyValueCollection;
use Bitrix\Sale\PropertyValueCollectionBase;

class customOrderComponent extends CBitrixComponent
{
	/**
	 * @var Order
	 */
	private $order;

	public function __construct($component = null)
	{
		parent::__construct($component);

		$this->arResult['ERRORS'] = array();

		if ( ! Loader::includeModule('sale')) {
			$this->arResult['ERRORS'][] = 'No sale module';
		};

		if ( ! Loader::includeModule('catalog')) {
			$this->arResult['ERRORS'][] = 'No catalog module';
		};
	}

	public function onPrepareComponentParams($arParams)
	{
		if (isset($arParams['PERSON_TYPE_ID']) && intval($arParams['PERSON_TYPE_ID']) > 0) {
			$arParams['PERSON_TYPE_ID'] = intval($arParams['PERSON_TYPE_ID']);
		}
		elseif (intval($this->request['payer']['person_type_id']) > 0) {
			$arParams['PERSON_TYPE_ID'] = intval($this->request['payer']['person_type_id']);
		}
		else {
			$arParams['PERSON_TYPE_ID'] = 1;
		}

		/**
		 * If is ACTION param exists, strval to define
		 */
		if (isset($arParams['ACTION']) && strlen($arParams['ACTION']) > 0) {
			$arParams['ACTION'] = strval($arParams['ACTION']);
		}
		elseif (isset($this->request['action'])) {
			$arParams['ACTION'] = strval($this->request['action']);
		}
		else {
			$arParams['ACTION'] = '';
		}

		/**
		 * If is IS_AJAX param exists, check the true defined
		 */
		if (isset($arParams['IS_AJAX']) && in_array($arParams['IS_AJAX'], array('Y', 'N'))) {
			$arParams['IS_AJAX'] = $arParams['IS_AJAX'] == 'Y';
		}
		// Same as param with request.
		elseif (isset($this->request['is_ajax']) && in_array($this->request['is_ajax'], array('Y', 'N'))) {
			$arParams['IS_AJAX'] = $this->request['is_ajax'] == 'Y';
		}
		else {
			$arParams['IS_AJAX'] = false;
		}

		return $arParams;
	}

	public function executeComponent()
	{
		global $APPLICATION;

		$this->createVirtualOrder();

		switch (strtoupper($this->arParams['ACTION'])) {
			case "SAVE":
				$this->validatePropertiesList();
				$this->updateUserAccount();
				$this->insertNewOrder();
				break;
		}

		/** @var Int */
		$this->arResult['ORDER_ID'] = $this->order->GetId();
		/** @var array[CODE]<VALUE> */
		$this->arResult['PROPERTY_FIELD'] = $this->getPropertiesList();

		if ($this->arParams['IS_AJAX']) {
			// bad practice
			$APPLICATION->RestartBuffer();

			header('Content-Type: application/json');
			echo json_encode($this->arResult);
			$APPLICATION->FinalActions();
			die();
		}
		else {
			// if( $this->getTemplateName() !== '' )
			$this->includeComponentTemplate();
		}
	}

	private function getPropertiesList()
	{
		$res = array();
		$propertyCollection = $this->order->getPropertyCollection();
		if ( ! $propertyCollection) $propertyCollection = array();

		/** @var \Bitrix\Sale\PropertyValue $property */
		foreach ($propertyCollection as $property) {
			if ($property->isUtil()) continue;

			$res[$property->getField('CODE')] = $property->getValue();
		}

		return $res;
	}

	private function validatePropertiesList()
	{
		$propertyCollection = $this->order->getPropertyCollection();
		if ( ! $propertyCollection) $propertyCollection = array();

		/** @var \Bitrix\Sale\PropertyValue $propertyValue */
		foreach ($propertyCollection as $propertyValue) {
			$property = $propertyValue->getProperty();
			$value = $propertyValue->getValue();

			if (empty($value) && $propertyValue->isRequired()) {
				$this->arResult["ERRORS"][] = sprintf(
					Loc::getMessage("CUSTOM_ORDER_FIELD_IS_REQUIRED_ERROR"),
					'<strong>' . $propertyValue->getField('NAME') . '</strong>'
				);

				continue;
			}

			/**
			 * @todo check $property['PATTERN'] instead getField('PATTERN')
			 */
			if ( ! $pattern = $propertyValue->getField('PATTERN')) {
				switch ('Y') {
					case $property['IS_EMAIL']:
						$pattern = '^([a-z0-9_-]+\.)*[a-z0-9_-]+@[a-z0-9_-]+(\.[a-z0-9_-]+)*\.[a-z]{2,6}$';
						break;

					case $property['IS_LOCATION']:
					case $property['IS_ZIP']:
					case $property['IS_PHONE']:
					case $property['IS_ADDRESS']:
						$pattern = '';
						break;
				}
			}

			if ( ! empty($value) && $pattern && ! preg_match("/{$pattern}/", $value)) {
				$this->arResult["ERRORS"][] = sprintf(
					Loc::getMessage("CUSTOM_ORDER_FIELD_IS_CORRUPTED_ERROR"),
					'<strong>' . $propertyValue->getField('NAME') . '</strong>'
				);
			}

			/** @todo Check: TYPE, MINLENGTH, MAXLENGTH, MULTILINE */
		}
	}

	private function createVirtualOrder()
	{
		global $USER;

		$siteId = \Bitrix\Main\Context::getCurrent()->getSite();
		$fUserId = \CSaleBasket::GetBasketUserID();

		if ( ! $this->arParams['PRODUCT_ID']) {
			/**
			 * items from user basket
			 *
			 * @var Basket $basket
			 * @var Basket $basketItems
			 */
			$basket = Basket::loadItemsForFUser(
				$fUserId,
				$siteId
			);
			$basketItems = $basket->getOrderableItems();
		}
		else {
			/**
			 * Set item to virtual basket
			 *
			 * @var Basket $basket
			 * @var BasketItem $basketItem
			 * @var Basket $basketItems
			 */
			$basket = Basket::create($siteId);
			$basket->setFUserId($fUserId);
			if ($this->arParams['IS_AJAX']) {
				$productID = intval($this->request->get('product_id'));
				// Insert product to basket by ID
				$basketItem = $basket->createItem('catalog', $productID);
				$basketItem->setFields(array(
					'QUANTITY' => 1,
					'LID' => $siteId,
					'PRODUCT_PROVIDER_CLASS' => 'CCatalogProductProvider',
				));
			}
			$basketItems = $basket->getOrderableItems();
		}

		/**
		 * Create and fill order
		 */
		$this->order = Order::create($siteId, $USER->GetID());
		$this->order->setPersonTypeId($this->arParams['PERSON_TYPE_ID']);

		$this->order->setField('STATUS_ID', 'N'); // Accepted, payment is expected.

		$this->order->setBasket($basketItems);

		$this->setOrderProperties();

		$delivery_id = $this->request->get('delivery_id');
		$this->setOrderShipment($delivery_id);

		$payment_id = $this->request->get('payment_id');
		$this->setOrderPayment($payment_id);

		$this->order->doFinalAction();
	}

	private function setOrderProperties()
	{
		global $USER;

		$userID = intval($USER->GetID());
		$arUser = $USER->GetByID($userID)->Fetch();

		/** Fill user virtual data */
		if (is_array($arUser)) {
			$arUser['FIO'] = "{$arUser['LAST_NAME']} {$arUser['NAME']} {$arUser['SECOND_NAME']}";
			$arUser['ADDRESS'] = ! empty($arUser['UF_PERSONAL_ADDRESS']) ? $arUser['UF_PERSONAL_ADDRESS'] : "{$arUser['PERSONAL_CITY']}, {$arUser['PERSONAL_STREET']}";
		}

		$propertyCollection = $this->order->getPropertyCollection();
		if ( ! $propertyCollection) $propertyCollection = array();

		/** @var PropertyValue $prop */
		foreach ($propertyCollection as $prop) {
			/** @var \Bitrix\Sale\Property $property */
			$property = $prop->getProperty();
			/** @var string $propertyCode */
			$propertyCode = $prop->getField('CODE');
			/** @var string $propertyValue */
			$propertyValue = $property['DEFAULT_VALUE'];

			/**
			 * Insert value from request
			 *
			 * @var \Bitrix\Main\Request
			 */
			foreach ($this->request as $key => $val) {
				// No case sensitive
				if (strtolower($key) == strtolower($propertyCode)) {
					$propertyValue = strip_tags(is_array($val) ? implode(', ', $val) : $val);
				}
			}

			/**
			 * Try insert value from user data when value is empty
			 */
			if ('' === $propertyValue) {
				switch ($propertyCode) {
					case 'FIO':
						if ( ! empty($arUser['FIO'])) $propertyValue = $arUser['FIO'];
						break;

					case 'EMAIL':
					case 'ADDRESS':
						if (isset($arUser[$propertyCode])) $propertyValue = $arUser[$propertyCode];
						break;

					case 'PHONE':
					default:
						if (isset($arUser['PERSONAL_' . $propertyCode])) $propertyValue = $arUser['PERSONAL_' . $propertyCode];
						break;
				}
			}

			// Save to collection.
			if ($propertyValue || $propertyValue !== $property['DEFAULT_VALUE']) {
				$prop->setValue($propertyValue);
			}
		}

		/**
		 * @note use $this->order->getAvailableFields() for check all fields
		 */
		if ($userID) $this->order->setField('USER_ID', $userID);
		if ($comment = $this->request->get('comment')) {
			$this->order->setField('USER_DESCRIPTION', strip_tags($comment));
		}

		$this->order->setField('CURRENCY', $this->order->getCurrency());
		$this->order->setField('COMMENTS', 'From custom order component.');
	}

	private function setOrderShipment($delivery_id = 0)
	{
		if (($delivery_id = intval($delivery_id)) < 1) {
			$delivery_id = Delivery\Services\EmptyDeliveryService::getEmptyDeliveryServiceId();
		}

		/** @var \Bitrix\Sale\ShipmentCollection */
		$shipmentCollection = $this->order->getShipmentCollection();
		/** @var Bitrix\Sale\Delivery\Services\Base $service */
		$service = Delivery\Services\Manager::getObjectById($delivery_id);
		/** @var \Bitrix\Sale\Shipment $shipment */
		$shipment = $shipmentCollection->createItem($service);
		/** @var \Bitrix\Sale\ShipmentItemCollection */
		$shipmentItemCollection = $shipment->getShipmentItemCollection();

//		$shipment->setFields(array(
//			 'ALLOW_DELIVERY' => 'Y',
//			// 'PRICE_DELIVERY' => 0,
//			// 'CUSTOM_PRICE_DELIVERY' => 'Y'
//		));

		foreach ($this->order->getBasket()->getOrderableItems() as $item) {
			/**
			 * @var $item \Bitrix\Sale\BasketItem
			 * @var $shipmentItem \Bitrix\Sale\ShipmentItem
			 * @var $item \Bitrix\Sale\BasketItem
			 */
			$shipmentItem = $shipmentItemCollection->createItem($item);
			$shipmentItem->setQuantity($item->getQuantity());
		}

		$shipment->setField('CURRENCY', $this->order->getCurrency());
	}

	private function setOrderPayment($payment_id = 0)
	{
		if (($payment_id = intval($payment_id)) < 1) return;

		/** @var \Bitrix\Sale\PaymentCollection $paymentCollection */
		$paymentCollection = $this->order->getPaymentCollection();
		/** @var \Bitrix\Sale\PaySystem\Service */
		$service = Bitrix\Sale\PaySystem\Manager::getObjectById($payment_id);
		/** @var \Bitrix\Sale\Payment */
		$payment = $paymentCollection->createItem($service);

		$payment->setField("SUM", $this->order->getPrice());
		$payment->setField("CURRENCY", $this->order->getCurrency());
	}

	private function updateUserAccount()
	{
		global $USER;

		if ( ! empty($this->arResult['ERRORS'])) return false;

		$propertyCollection = $this->order->getPropertyCollection();
		if ( ! $propertyCollection) return false;

		$obUser = new CUser;
		$arUserFields = array(
			"PERSONAL_STREET" => $propertyCollection->getAddress()->getValue(),
		);

		if ($USER->IsAuthorized()) {
			$obUser->Update(intval($USER->GetID()), $arUserFields);
		}
		elseif ('Y' !== $this->arParams['DO_NOT_REGISTER']) {
			$newUserPassword = randString(12);

			$arUserFields = array_merge($arUserFields, array(
				"LID" => 'ru',
				"ACTIVE" => 'Y' === $this->arParams['NEW_USER_ACTIVATE'] ? 'Y' : 'N',
				"GROUP_ID" => array($this->arParams['GROUP_ID']),
				"PASSWORD" => $newUserPassword,
				"CONFIRM_PASSWORD" => $newUserPassword,
				"NAME" => $propertyCollection->getPayerName()->getValue(),
				"EMAIL" => $propertyCollection->getUserEmail()->getValue(),
				"PERSONAL_PHONE" => $propertyCollection->getPhone()->getValue(),
				"ADMIN_NOTES" => "User created by Custom Order component.",
			));

			list($arUserFields['LOGIN']) = explode('@', $arUserFields['EMAIL']);

			$userID = $obUser->Add($arUserFields);

			if (intval($userID) > 0) {
//				$arAuthResult = $USER->Login($arUserFields['LOGIN'], $newUserPassword, "Y");
//				$APPLICATION->arAuthResult = $arAuthResult;
				$this->order->setFieldNoDemand('USER_ID', $userID);
			}
			else {
				$this->arResult['ERRORS'][] = $obUser->LAST_ERROR;
			}
		}
	}

	/**
	 * @return bool
	 * @throws \Bitrix\Main\ArgumentException
	 * @throws \Bitrix\Main\ArgumentNullException
	 * @throws \Bitrix\Main\ArgumentOutOfRangeException
	 */
	private function insertNewOrder()
	{
		global $USER;

		if (empty($this->arResult['ERRORS'])) {
			// Insert new order.
			$r = $this->order->save();

			if ($r->isSuccess()) {
				$userID = intval($USER->GetID());
				// Clear user order.
				(new CSaleBasket)->DeleteAll($userID);
				// $this->setTemplateName('done');
				// $this->updateUser();
				return true;
			}
			else {
				$this->arResult['ERRORS'] = array_merge($this->arResult['ERRORS'], $r->getErrorMessages());
			}
		}

		return false;
	}
}
