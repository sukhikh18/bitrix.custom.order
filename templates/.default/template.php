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

?>

<div class="payment-form" style="display: none;"></div>
<form id="order-form" class="order-form" method="post" action="<?= $APPLICATION->GetCurPage() ?>"
      enctype="multipart/form-data">
	<?php
	foreach ($arResult['PROPERTY_FIELD'] as $key => $val) {
		printf('<input type="hidden" name="%s" value="%s">', strtolower($key), $val);
	}
	?>
    <input type="hidden" name="context">
    <input type="hidden" name="action" value="<?= $arParams['ACTION'] ?>">
    <input type="hidden" name="product_id" value="<?= $arParams['PRODUCT_ID'] ?>">
    <input type="hidden" name="payment_id" value="<?= $arParams['PAYMENT_ID'] ?>">
    <input type="hidden" name="delivery_id" value="<?= $arParams['DELIVERY_ID'] ?>">

    <div class="order-form__errors">
		<?php echo implode('<br>', $arResult['ERRORS']); ?>
    </div>

    <label style="overflow: hidden;height: 0;max-height: 0;"><input type="text" name="surname" value="1"></label>
    <label style="overflow: hidden;height: 0;max-height: 0;"><input type="text" name="birthsday" value=""></label>

    <div class="form-group order-form__group">
        <label for="order-name">Ваше имя<span class="req" style="color: red">*</span></label>
        <input class="form-control" id="order-name" type="text" name="fio" required="">
    </div>
    <div class="form-group order-form__group">
        <label for="order-phone">Номер телефона<span class="req" style="color: red">*</span></label>
        <input class="form-control" id="order-phone" type="tel" name="phone" aria-describedby="phoneHelp" required="">
        <small class="form-text text-muted" id="phoneHelp">Мы не передаем ваши персональные данные третьим лицам</small>
    </div>
    <div class="form-group order-form__group">
        <label for="order-email">Электронная почта<span class="req" style="color: red">*</span></label>
        <input class="form-control" id="order-email" type="email" name="email" required="">
    </div>
    <div class="form-group order-form__group">
        <label for="order-address">Адресс доставки</label>
        <input class="form-control" id="order-address" type="text" name="address" aria-describedby="addressHelp">
        <small class="form-text text-muted" id="addressHelp">Укажите адресс если нужна доставка</small>
    </div>
    <div class="form-group order-form__group">
        <label for="order-comment">Комментарий</label>
        <textarea class="form-control" id="order-comment" name="comment"></textarea>
    </div>
    <?php/* @todo How release it?
    <div class="form-group order-form__group">
        <label for="order-file">Прикрепить файл</label>
        <input class="form-control" id="order-file" type="file" name="file[]" multiple="">
    </div>
    */?>

    <div class="order-form__actions">
        <button type="submit" class="btn btn-primary"><?= Loc::getMessage("CUSTOM_ORDER_BUY_BUTTON_LABEL") ?></button>
    </div>
</form>
