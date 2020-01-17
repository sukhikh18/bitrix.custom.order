jQuery(document).ready(function ($) {
    var $orderForm = $('#order-form');
    var $paymentForm = $('.payment-form');
    var $errors = $('.order-form__errors');

    // Save order
    $orderForm.on('submit', function (event) {
        event.preventDefault();

        var isHasErrors = function (response) {
            return undefined !== response.ERRORS && !!response.ERRORS.length;
        };

        var showErrorMessage = function (message) {
            $errors.html(message);
        };

        var showErrors = function (response) {
            showErrorMessage(response.ERRORS.join('<br>') + '<br>')
        };

        $.ajax({
            url: $orderForm.attr('action'),
            type: 'POST',
            dataType: 'json',
            data: $orderForm.serialize() + '&is_ajax=Y',
        })
            .done(function (data) {
                if (isHasErrors(data)) {
                    return showErrors(data);
                }

                $.ajax({
                    url: '/user/order/payment/',
                    type: 'GET',
                    dataType: 'HTML',
                    data: {
                        'ORDER_ID': data.ORDER_ID,
                    },
                }).done(function (payForm) {
                    $paymentForm.html(payForm);
                    $paymentForm.find('form').removeAttr('target').submit();
                });
            })
            .fail(function (data) {
                showErrorMessage('К сожалению что то пошло не так. Обратитесь к администратору сайта.');
            });

        return false;
    });
});