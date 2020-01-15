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

?>
<form id="checkout-form" method="post" action="">
	<?php
	foreach ($arResult['PROPERTY_FIELD'] as $key => $val) {
		printf('<input type="hidden" name="%s" value="%s">', strtolower($key), $val);
	}
	?>

    <div class="summary__errors">
		<?php echo implode('<br>', $arResult['errors']); ?>
    </div>

	<?php /*<div class="summary__address">
        <label class="address" title="Отправить сертификат на физический адрес">
            <input type="checkbox"><span>Адрес</span>
            <input class="form-control" type="text" name="address" placeholder="Адрес доставки сертификата" value="<?=$arResult['PROPERTY_FIELD']['ADDRESS'];?>">
        </label>
    </div>*/ ?>

    <div class="summary__payment">
        <div class="cart-sidebar--title">Оплата:<br><span>Visa\MC</span></div>
    </div>

    <div class="summary__gift">
        <label class="gift-form" title="Подарить этот заказ и отправить подарочный сертификат на физический адрес">
            <input type="checkbox"><span>Подарок</span>
            <input class="form-control" type="text" name="gift_address" placeholder="Email получателя подарка"
                   value="<?= $arResult['PROPERTY_FIELD']['GIFT_ADDRESS']; ?>">
        </label>
    </div>

    <div class="summary__accept order-accept">
        <input type="submit" class="btn btn-red" value="Купить">
    </div>

    <input type="hidden" name="action" value="save">
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
        }

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
