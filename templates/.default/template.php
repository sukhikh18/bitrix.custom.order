<? if ( ! defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */

/** @var customOrderComponent $component */

use Bitrix\Main\Localization\Loc;

$required_label = '<span class="req" style="color: red">*</span>';
?>

<div class="payment-form" style="display: none;"></div>
<form id="order-form" class="order-form" method="post" action="<?= $APPLICATION->GetCurPage() ?>"
      enctype="multipart/form-data">
    <div class="order-form__errors">
		<?php echo implode('<br>', $arResult['ERRORS']); ?>
    </div>
    <input type="hidden" name="context">
    <input type="hidden" name="action" value="<?= $arParams['ACTION'] ?>">
    <input type="hidden" name="product_id" value="<?= $arParams['PRODUCT_ID'] ?>">
    <input type="hidden" name="payment_id" value="<?= $arParams['PAYMENT_ID'] ?>">
    <input type="hidden" name="delivery_id" value="<?= $arParams['DELIVERY_ID'] ?>">
	<?php
	foreach ($arResult['PROPERTY_FIELD']['HIDDEN'] as $code => $arField) {
		printf('<input type="%s" name="%s" value="%s"%s>',
			$arField['TYPE'],
			strtolower($arField['NAME']),
			$arField['VALUE'],
			$arField['REQUIRED'] ? ' required="true"' : ''
		);
	}

	foreach ($arResult['PROPERTY_FIELD']['VISIBLE'] as $code => $arField) {
		printf('
            <div class="form-group order-form__group">
                <label for="order-%1$s">%2$s%3$s</label>
                <input class="form-control" id="order-%1$s" type="text" name="%5$s"%4$s>
                %6$s
            </div>',
			$code,
			$arField['LABEL'],
			$arField['REQUIRED'] ? $required_label : '',
			$arField['REQUIRED'] ? ' required="true"' : '',
			$arField['NAME'],
			$arField['DESC'] ? '<small class="form-text text-muted">' . $arField['DESC'] . '</small>' : ''
		);
	}
	?>

    <label style="margin:0;overflow:hidden;height:0;max-height:0;"><input type="text" name="surname" value="1"></label>
    <label style="margin:0;overflow:hidden;height:0;max-height:0;"><input type="text" name="birthsday" value=""></label>

    <div class="order-form__actions">
        <button type="submit" class="btn btn-primary"><?= Loc::getMessage("CUSTOM_ORDER_BUY_BUTTON_LABEL") ?></button>
    </div>
</form>
