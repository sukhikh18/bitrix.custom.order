<? if ( ! defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * @global $arCurrentValues
 */

$arComponentParameters = array(
	"GROUPS" => array(
//		"MAIN"    =>  array(
//			"NAME"  =>  "Main",
//			"SORT"  =>  "300",
//		),
	),
	"PARAMETERS" => array(
		"PERSON_TYPE_ID" => array(
			"PARENT" => "BASE",
			"NAME" => "Buyer person type ID",
			"TYPE" => "STRING",
			"DEFAULT" => "1"
		),
		"GROUP_ID" => array(
			"PARENT" => "BASE",
			"NAME" => "Register user in group with ID",
			"TYPE" => "STRING",
			"DEFAULT" => "1"
		),
		"DO_NOT_REGISTER" => array(
			"PARENT" => "BASE",
			"NAME" => "User must be authorized",
			"TYPE" => "CHECKBOX",
			"DEFAULT" => "N"
		),
		"NEW_USER_ACTIVATE" => array(
			"PARENT" => "BASE",
			"NAME" => "Activate user after order registration",
			"TYPE" => "CHECKBOX",
			"DEFAULT" => "N"
		),
		"PRODUCT_ID" => array(
			"PARENT" => "BASE",
			"NAME" => "Insert product to custom order (Must be product ID / int)",
			"TYPE" => "STRING",
			"DEFAULT" => ""
		),
		"PAYMENT_ID" => array(
			"PARENT" => "BASE",
			"NAME" => "Default payment ID",
			"TYPE" => "STRING",
			"DEFAULT" => "1"
		),
		"DELIVERY_ID" => array(
			"PARENT" => "BASE",
			"NAME" => "Default delivery ID",
			"TYPE" => "STRING",
			"DEFAULT" => ""
		),
	),
);
