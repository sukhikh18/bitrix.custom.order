<? if ( ! defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * New bitrix checkout component
 * maybe @todo
 *     - Set $USER as object property
 *     - fill arProperties and arPropertyValues on onPrepareComponentParams method
 */

use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\Loader;
use \Bitrix\Sale\Delivery;

class customOrderComponent extends CBitrixComponent
{
	/**
	 * @var \Bitrix\Sale\Order
	 */
	protected $order;

	/**
	 * @var array
	 */
	protected $errors = array();

	/**
	 * @var array Field for ajax request data
	 */
	protected $arResponse = array(
		'errors' => array(),
		'html' => ''
	);

	/**
	 * @var array with \Bitrix\Sale\Property (key equal CODE)
	 * Filled after setOrderProps() method
	 */
	protected $arProperties = array();

	/**
	 * @var array with Strings
	 * Filled after setOrderProps() method
	 */
	protected $arPropertyValues = array();

	/**
	 * @var array
	 */
	protected $allowActions = array('save');

	/**
	 * @param string $code
	 *
	 * @return false | object
	 * @note Use after setOrderProps only
	 */
	protected function getPropByCode($code)
	{
		$result = false;

		/**
		 * @todo check is a \Bitrix\Sale\Property object
		 */
		if (isset($this->arProperties[$code])) {
			$result = $this->arProperties[$code];
		}

		return $result;
	}

	/**
	 * @return boolean | string | array $result
	 * @note Use after setOrderProps only
	 */
	protected function getPropValueByCode($code)
	{
		$result = false;

		if (isset($arPropertyValues[$code])) {
			$result = $arPropertyValues[$code];
		}
		/** @var \Bitrix\Sale\Property */
		elseif ($property = $this->getPropByCode($code)) {
			/** @var array */
			$values = $property->getFieldValues();

			if (isset($values['VALUE'])) {
				$result = $values['VALUE'];
			}
		}

		return $result;
	}

	function __construct($component = null)
	{
		parent::__construct($component);

		if ( ! Loader::includeModule('sale')) {
			$this->errors[] = 'No sale module';
		};

		if ( ! Loader::includeModule('catalog')) {
			$this->errors[] = 'No catalog module';
		};
	}

	function onPrepareComponentParams($arParams)
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
		elseif (isset($this->request['action']) && in_array($this->request['action'], $this->allowActions)) {
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
		/**
		 * Same as param with request
		 */
		elseif (isset($this->request['is_ajax']) && in_array($this->request['is_ajax'], array('Y', 'N'))) {
			$arParams['IS_AJAX'] = $this->request['is_ajax'] == 'Y';
		}
		else {
			$arParams['IS_AJAX'] = false;
		}

		return $arParams;
	}

	protected function createVirtualOrder()
	{
		global $USER;

		try {
			/**
			 * Get basket items
			 */
			$siteId = \Bitrix\Main\Context::getCurrent()->getSite();
			$basketItems = \Bitrix\Sale\Basket::loadItemsForFUser(
				\CSaleBasket::GetBasketUserID(),
				$siteId
			)
				->getOrderableItems();

			/**
			 * Redirect if cart is empty
			 */
			// if (count($basketItems) == 0) {
			//     LocalRedirect(PATH_TO_BASKET);
			// }

			/**
			 * Create and fill order
			 */
			$this->order = \Bitrix\Sale\Order::create($siteId, $USER->GetID());
			$this->order->setPersonTypeId($this->arParams['PERSON_TYPE_ID']);
			$this->order->setBasket($basketItems);

			/**
			 * @todo check needed
			 */
			// $this->order->doFinalAction(true);

			$this->setOrderProps();

			$delivery_id = isset($this->request['delivery_id']) ? $this->request['delivery_id'] : 0;
			$payment_id = isset($this->request['payment_id']) ? $this->request['payment_id'] : 0;

			$this->setOrderShipment($delivery_id);
			$this->setOrderPayment($payment_id);

			/**
			 * @todo Check required delivery/payment
			 */
		} catch (\Exception $e) {
			$this->errors[] = $e->getMessage();
		}
	}

	protected function setOrderProps()
	{
		global $USER;

		$arUser = $USER->GetByID(intval($USER->GetID()))->Fetch();

		if (is_array($arUser)) {
			$arUser['FIO'] = trim(trim($arUser['LAST_NAME']) . ' ' . trim($arUser['NAME']) . ' ' . trim($arUser['SECOND_NAME']));
			$arUser['ADDRESS'] = ! empty($arUser['UF_PERSONAL_ADDRESS']) ? $arUser['UF_PERSONAL_ADDRESS'] : $arUser['PERSONAL_CITY'] . $arUser['PERSONAL_STREET'];
		}

		/** @var \Bitrix\Sale\Property */
		foreach ($this->order->getPropertyCollection() as $prop) {
			/** List of properties by code */
			$this->arProperties[$prop->getField('CODE')] = $prop;

			/** @var string */
			$propVal = '';

			/** @var string */
			$code = $prop->getField('CODE');

			/**
			 * Get from request
			 */
			foreach ($this->request as $key => $val) {
				// No case sensitive
				if (strtolower($key) == strtolower($code)) {
					$propVal = strip_tags(is_array($val) ? implode(', ', $val) : $val);
				}
			}

			/**
			 * Get from another data
			 */
			if ('' === $propVal) {
				switch ($code) {
					case 'PAYER_NAME':
						if (empty($propVal) && ! empty($arUser['FIO'])) {
							$propVal = $arUser['FIO'];
						}
						break;

					case 'EMAIL':
					case 'ADDRESS':
						if (isset($arUser[$code])) {
							$propVal = $arUser[$code];
						}
						break;

					case 'PHONE':
						if (isset($arUser['PERSONAL_' . $code])) {
							$propVal = $arUser['PERSONAL_' . $code];
						}
						break;
				}
			}

			/** if is not found */
			if ('' === $propVal) {
				$property = $prop->getProperty();
				$propVal = $property['DEFAULT_VALUE'];
			}

			/** if is not empty (may be default string) */
			if ('' !== $propVal) {
				$prop->setValue($propVal);
			}

			/**
			 * Fill all order properties
			 */
			$this->arPropertyValues[$code] = $propVal;
		}

		/**
		 * @todo check it
		 */
		// $this->order->setField('CURRENCY', $CURRENCY_CODE);
		// $this->order->setField('USER_DESCRIPTION', 'Комментарии пользователя');
		// $this->order->setField('COMMENTS', 'Комментарии менеджера');
	}

	protected function setOrderShipment($delivery_id = 0)
	{
		/* @var \Bitrix\Sale\ShipmentCollection */
		$shipmentCollection = $this->order->getShipmentCollection();

		if (0 >= ($delivery_id = intval($delivery_id))) {
			$delivery_id = Delivery\Services\EmptyDeliveryService::getEmptyDeliveryServiceId();
		}

		$shipment = $shipmentCollection->createItem(
			Delivery\Services\Manager::getObjectById($delivery_id)
		);

		/** @var \Bitrix\Sale\ShipmentItemCollection */
		$shipmentItemCollection = $shipment->getShipmentItemCollection();
		$shipment->setField('CURRENCY', $this->order->getCurrency());

		foreach ($this->order->getBasket()->getOrderableItems() as $item) {
			/**
			 * @var $item \Bitrix\Sale\BasketItem
			 * @var $shipmentItem \Bitrix\Sale\ShipmentItem
			 * @var $item \Bitrix\Sale\BasketItem
			 */
			$shipmentItem = $shipmentItemCollection->createItem($item);
			$shipmentItem->setQuantity($item->getQuantity());
		}
	}

	protected function setOrderPayment($payment_id = 0)
	{
		if (0 < ($payment_id = intval($payment_id))) {
			$paymentCollection = $this->order->getPaymentCollection();

			/** @var \Bitrix\Sale\PaySystem\Service */
			$service = Bitrix\Sale\PaySystem\Manager::getObjectById($payment_id);
			/** @var \Bitrix\Sale\Payment */
			$payment = $paymentCollection->createItem($service);

			$payment->setField("SUM", $this->order->getPrice());
			$payment->setField("CURRENCY", $this->order->getCurrency());
		}
	}

	protected function setNewUserProperties()
	{
		global $USER;

		$oUser = new CUser;

		$aFields = array();

		/**
		 * Set user last delivery address
		 */
		if ( ! empty($this->arPropertyValues['ADDRESS'])) {
			$aFields["UF_PERSONAL_ADDRESS"] = $this->arPropertyValues['ADDRESS'];
		}

		// list($aFields['LAST_NAME'], $aFields["NAME"], $aFields['SECOND_NAME']) = explode(' ', $this->arResult['PROPERTY_FIELD']['PAYER_NAME']);

		/**
		 * Update user fields
		 */
		if ( ! empty($aFields)) {
			$oUser->Update(intval($USER->GetID()), $aFields);
		}
	}

	protected function validateOrderProperties()
	{
		foreach ($this->arProperties as $code => $prop) {
			$property = $prop->getProperty();

			if ($isFilled = ! empty($this->arPropertyValues[$code])) {
				$value = $this->arPropertyValues[$code];
			}

			if ( ! $property['PATTERN']) {
				if ('Y' === $property['IS_EMAIL']) {
					$property['PATTERN'] = '^([a-z0-9_-]+\.)*[a-z0-9_-]+@[a-z0-9_-]+(\.[a-z0-9_-]+)*\.[a-z]{2,6}$';
				}
				elseif ('Y' === $property['IS_LOCATION']) {
				}
				elseif ('Y' === $property['IS_ZIP']) {
				}
				elseif ('Y' === $property['IS_PHONE']) {
				}
				elseif ('Y' === $property['IS_ADDRESS']) {
				}
			}

			if ($prop->isRequired() && ! $isFilled) {
				$this->errors[] = "Поле <strong>" . $prop->getField('NAME') . "</strong> обязательно к заполнению.";
			}

			if ($isFilled && $property['PATTERN'] && ! preg_match("/{$property['PATTERN']}/", $value)) {
				$this->errors[] = "Поле <strong>" . $prop->getField('NAME') . "</strong> заполнено не верно.";
			}

			/**
			 * @todo Check:
			 *       TYPE
			 *       MINLENGTH
			 *       MAXLENGTH
			 *       MULTILINE
			 */
		}
	}

	protected function saveAction()
	{
		global $APPLICATION;
		global $USER;

		$this->validateOrderProperties();

		if (empty($this->errors)) {
			/**
			 * Insert new order
			 */
			$r = $this->order->save();

			if ($r->isSuccess()) {
				// $this->setTemplateName('done');
				$this->setNewUserProperties();
				CSaleBasket::DeleteAll(intval($USER->GetID()));
			}
			else {
				if ($ex = $APPLICATION->GetException()) {
					echo $ex->GetString();
				}

				// print_r($r->getErrors());
				$this->errors = array_merge($this->errors, $r->getErrorMessages());
			}

			// $r->getWarnings()
			// $warnings = $r->getWarningMessages();
			// if( !empty( $warnings ) ) {
			//     $this->errors = array_merge($this->errors, $r->getErrorMessages());
			// }

			// if( empty($this->errors) ) {
			//     LocalRedirect('/user/orders/?thankyou=1');
			// }
		}
	}

	function executeComponent()
	{
		global $APPLICATION;

		// bad practice
		if ($this->arParams['IS_AJAX']) {
			$APPLICATION->RestartBuffer();
		}

		$this->createVirtualOrder();

		if ( ! empty($this->arParams['ACTION'])) {
			if (is_callable(array($this, $this->arParams['ACTION'] . "Action"))) {
				try {
					call_user_func(array($this, $this->arParams['ACTION'] . "Action"));
				} catch (\Exception $e) {
					$this->errors[] = $e->getMessage();
				}
			}
		}

		/** @var Int */
		$order_id = $this->order->GetId();

		/**
		 * @todo get payment class from registry
		 */
		// $paymentClassName = $this->registry->getPaymentClassName();
		/** @var Main\DB\Result $listPayments */
		// $listPayments = \Bitrix\Sale\PAYMENT::getList(array(
		//     'select' => array('ID'), // , 'PAY_SYSTEM_NAME', 'PAY_SYSTEM_ID', 'ACCOUNT_NUMBER', 'ORDER_ID', 'PAID', 'SUM', 'CURRENCY', 'DATE_BILL'
		//     'filter' => array('ORDER_ID' => array($order_id))
		// ));

		// $payment_id = 0;
		// while ($payment = $listPayments->fetch())
		// {
		//     /**
		//      * Get payment ID
		//      */
		//     if( !empty($payment['ID']) ) {
		//         $payment_id = $payment['ID'];
		//         break;
		//     }
		// }

		if ($this->arParams['IS_AJAX']) {
			// if ($this->getTemplateName() != '') {
			//     ob_start();
			//     $this->includeComponentTemplate();
			//     $this->arResponse['html'] = ob_get_contents();
			//     ob_end_clean();
			// }

			$this->arResponse['ORDER_ID'] = $order_id;
			$this->arResponse['errors'] = $this->errors;

			header('Content-Type: application/json');
			echo json_encode($this->arResponse);
			$APPLICATION->FinalActions();
			die();
		}
		else {
			$this->arResult['ORDER_ID'] = $order_id;
			$this->arResult['errors'] = $this->errors;

			/**
			 * Fill all (not util/service) properties value
			 */
			$this->arResult['PROPERTY_FIELD'] = array();
			foreach ($this->arProperties as $code => $prop) {
				if ($prop->isUtil()) {
					continue;
				}

				$this->arResult['PROPERTY_FIELD'][$code] =
					( ! empty($this->arPropertyValues[$code])) ? $this->arPropertyValues[$code] : '';
			}

			$this->includeComponentTemplate();
		}
	}
}