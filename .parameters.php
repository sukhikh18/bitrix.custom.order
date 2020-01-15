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
		"DO_NOT_REGISTER"    =>  array(
			"PARENT"    =>  "BASE",
			"NAME"      =>  "User must be authorized",
			"TYPE"      =>  "CHECKBOX",
			"DEFAULT"   =>  "N"
		),
		"NEW_USER_ACTIVATE"    =>  array(
			"PARENT"    =>  "BASE",
			"NAME"      =>  "Activate user after order registration",
			"TYPE"      =>  "CHECKBOX",
			"DEFAULT"   =>  "N"
		),
		"PRODUCT_ID"    =>  array(
			"PARENT"    =>  "BASE",
			"NAME"      =>  "Insert product to custom order (Must be product ID / int)",
			"TYPE"      =>  "STRING",
			"DEFAULT"   =>  ""
		),
	),
);
