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
<form id="checkout-form" class="order-form" method="post" action="" enctype="multipart/form-data">
	<?php
	foreach ($arResult['PROPERTY_FIELD'] as $key => $val) {
		printf('<input type="hidden" name="%s" value="%s">', strtolower($key), $val);
	}
	?>
    <input type="hidden" name="context">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="product_id" value="1">
    <input type="hidden" name="payment_id" value="1">
    <input type="hidden" name="delivery_id" value="1">

    <div class="order-form__errors">
		<?php echo implode('<br>', $arResult['ERRORS']); ?>
    </div>

    <div class="form-group order-form__group">
        <label for="order-name">Ваше имя<span class="req" style="color: red">*</span></label>
        <input class="form-control" id="order-name" type="text" name="fio" required="">
        <label><input type="text" name="surname" value="1" style="display: none;"></label>
        <label><input type="text" name="birthsday" value="" style="display: none;"></label>
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
<div class="payment-form" style="display: none;"></div>
<script type="text/javascript">
    jQuery(document).ready(function ($) {
        var $checkoutForm = $('#checkout-form');
        var $paymentForm = $('.payment-form');
        var $errors = $('.summary__errors');

        var $address = $('.summary__address', $checkoutForm);
        var $gift = $('.summary__gift', $checkoutForm);

        /**
         * @param  {[type]}  $target   [description]
         * @param  {Boolean} isChecked [description]
         * @return {[type]}            [description]
         */
        var clearInputs = function ($target, isChecked) {
            var $input = $target.find('input[type="text"]');

            if (isChecked) {
                var val = $input.attr('data-val');
                // restore data from data-val and undisable
                if (val) $input.val(val)
                $input.removeAttr('disabled');
            } else {
                $input
                    .attr('data-val', $input.val())
                    .val('')
                    .attr('disabled', 'disabled');
            }
        }

        /**
         * [checkOutErrors description]
         * @param  {JSON}   data Response from ajax
         * @return {[type]}      [description]
         */
        var checkOutErrors = function (data) {
            console.log(data.errors);
            var htmlErrors = $.map(data.errors, function (item, index) {
                return item + '<br>';
            });

            $errors.html(htmlErrors);
        };

        $address.on('change', 'input[type="checkbox"]', function (event) {
            event.preventDefault();

            clearInputs($address, $(this).is(':checked'));
        });

        $('input[type="checkbox"]', $address).trigger('change');

        $gift.on('change', 'input[type="checkbox"]', function (event) {
            event.preventDefault();

            clearInputs($gift, $(this).is(':checked'));
        });

        $('input[type="checkbox"]', $gift).trigger('change');

        function getPaymentForm(data) {
            try {
                if (data.errors.length == 0) {
                    $.ajax({
                        url: '/user/payment/',
                        type: 'GET',
                        dataType: 'HTML',
                        data: {
                            'ORDER_ID': data.ORDER_ID,
                        },
                    }).done(function (payForm) {
                        $paymentForm.html(payForm);
                        $paymentForm.find('form').removeAttr('target').submit();
                    });
                } else {
                    checkOutErrors(data);
                }
            } catch (e) {
                checkOutErrors(data);
                console.log(e);
            }
        }

        // Save order
        $checkoutForm.on('submit', function (event) {
            event.preventDefault();

            $.ajax({
                url: $checkoutForm.attr('action'),
                type: 'POST',
                dataType: 'json',
                data: $checkoutForm.serialize() + '&is_ajax=Y',
            })
                .done(getPaymentForm)
                .fail(function (data) {
                    checkOutErrors(data);
                });

            return false;
        });
    });
</script>
