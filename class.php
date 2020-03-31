<? if ( ! defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Context;
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

interface iCustomOrderComponent {
	function onPrepareComponentParams($arParams);
	function executeComponent();
	function getPropertiesList(); // private
	function validatePropertiesList(); // private
	function getCurrentBasketObject($siteId = null, $fUserId = null); // private
	function getEmptyBasketObject($siteId = null, $fUserId = null); // private
	function initOrder($basket, $siteId); // private
	function setOrderProperties(); // private
	function setOrderShipment($delivery_id = 0); // private
	function setOrderPayment($payment_id = 0); // private
	function updateUserAccount(); // private
	function insertNewOrder(); // private
}

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
		if ($personTypeId = $this->request->get('person_type_id')) $arParams['PERSON_TYPE_ID'] = intval($personTypeId);
		if ($action = $this->request->get('action')) $arParams['ACTION'] = strval($action);
		if ($productId = $this->request->get('product_id')) $arParams['PRODUCT_ID'] = intval($productId);
		if ($paymentId = $this->request->get('payment_id')) $arParams['PAYMENT_ID'] = intval($paymentId);
		if ($deliveryId = $this->request->get('delivery_id')) $arParams['DELIVERY_ID'] = intval($deliveryId);

		$arComponentParameters = include __DIR__ . '/.parameters.php';
		foreach ($arComponentParameters['PARAMETERS'] as $code => $arParameter) {
			if (empty($arParams[$code]) && isset($arParameter['DEFAULT'])) {
				$arParams[$code] = $arParameter['DEFAULT'];
			}
		}

		$arParams['IS_AJAX'] = false;
		if (in_array($this->request->get('is_ajax'), array('Y', 'N'), true)) {
			$arParams['IS_AJAX'] = 'Y' === $this->request['is_ajax'];
		}

		return $arParams;
	}

	public function executeComponent()
	{
		global $APPLICATION;

		$siteId = Context::getCurrent()->getSite();
		$fUserId = CSaleBasket::GetBasketUserID();

		$basket = $this->getCurrentBasketObject($siteId, $fUserId);

		if ($action = $this->request->get('action')) $action = strtoupper(strval($action));

		if ('INSERT_NEW_ORDER' === $action) {
			$basket = $this->getEmptyBasketObject($siteId, $fUserId);
			// Insert product to basket by ID
			$basketItem = $basket->createItem('catalog', $this->arParams['PRODUCT_ID']);
			$r = $basketItem->setFields(array(
				'QUANTITY' => 1,
				'LID' => $siteId,
				'PRODUCT_PROVIDER_CLASS' => 'CCatalogProductProvider',
			));

			if ( ! $r->isSuccess()) {
				$this->arResult["ERRORS"][] = 'Не удалось добавить товар';
			}
		}

		$this->initOrder($basket, $siteId);
		$this->setOrderProperties();

		$delivery_id = $this->request->get('delivery_id');
		$this->setOrderShipment($delivery_id);

		$payment_id = $this->request->get('payment_id');
		$this->setOrderPayment($payment_id);

		$this->order->doFinalAction();

		if (in_array($action, array('SAVE_BASKET', 'INSERT_NEW_ORDER'), true)) {
			// @todo check empty basket.
			$this->validatePropertiesList();
			$this->updateUserAccount();
			$this->insertNewOrder();
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
		$propertyCollection = $this->order->getPropertyCollection();
		if ( ! $propertyCollection) $propertyCollection = array();

		$arProperties = array(
			'HIDDEN' => array(),
			'VISIBLE' => array(),
		);

		/** @var \Bitrix\Sale\PropertyValue $property */
		foreach ($propertyCollection as $property) {
			if ($property->isUtil()) continue;

			$arProperty = $property->getProperty();
			$code = $property->getField('CODE');

			$type = 'text';
			switch ($code) {
				case 'ZIP':
				case 'LOCATION':
				case 'CITY':
					$type = 'hidden';
					break;
			}

			$arProperties['hidden' === $type ? 'HIDDEN' : 'VISIBLE'][$code] = array(
				'NAME' => strtolower($code), // 'order_' .
				'LABEL' => $arProperty['NAME'],
				'TYPE' => $type,
				'VALUE' => trim($property->getValue()),
				'REQUIRED' => 'Y' === $arProperty['REQUIRED'],
				'DESC' => $arProperty['DESCRIPTION'],
			);

			if ('EMAIL' === $code) {
				$arProperties['VISIBLE'][$code]['TYPE'] = 'email';
			}
		}

		return $arProperties;
	}

	private function setOrderProperties()
	{
		global $USER;

		$userID = intval($USER->GetID());
		$arUser = $USER->GetByID($userID)->Fetch();

		/** Fill user virtual data */
		if (is_array($arUser)) {
			$arUser['FIO'] = "{$arUser['LAST_NAME']} {$arUser['NAME']} {$arUser['SECOND_NAME']}";
			if( ! empty($arUser['UF_PERSONAL_ADDRESS'])) {
				$arUser['ADDRESS'] = $arUser['UF_PERSONAL_ADDRESS'];
			}
			else {
				$address = array();
				if($arUser['PERSONAL_CITY']) array_push($address, $arUser['PERSONAL_CITY']);
				if($arUser['PERSONAL_STREET']) array_push($address, $arUser['PERSONAL_STREET']);
				$arUser['ADDRESS'] = implode(', ', $address);
			}
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

	/**
	 * @param null $siteId
	 * @param null $fUserId
	 * @return \Bitrix\Sale\BasketBase
	 */
	private function getCurrentBasketObject($siteId = null, $fUserId = null)
	{
		return Basket::loadItemsForFUser($fUserId, $siteId);
	}

	private function getEmptyBasketObject($siteId = null, $fUserId = null)
	{
		$basket = Basket::create($siteId);
		$basket->setFUserId($fUserId);

		return $basket;
	}

	/**
	 * @param \Bitrix\Sale\BasketBase $basket
	 * @param $siteId
	 */
	private function initOrder($basket, $siteId)
	{
		global $USER;

		$basketItems = $basket->getOrderableItems();

		/**
		 * Create and fill order
		 */
		$this->order = Order::create($siteId, $USER->GetID());
		$this->order->setPersonTypeId($this->arParams['PERSON_TYPE_ID']);

		$this->order->setField('STATUS_ID', 'N'); // Accepted, payment is expected.

		$this->order->setBasket($basketItems);
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

		if ( ! empty($this->arResult['ERRORS'])) return;

		$propertyCollection = $this->order->getPropertyCollection();
		if ( ! $propertyCollection) return;

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
//				// Required for sale.order.paymend find.
				$USER->SetParam('USER_ID', $userID);
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
