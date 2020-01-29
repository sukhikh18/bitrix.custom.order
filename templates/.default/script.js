jQuery(document).ready(function ($) {
    var $orderForm = $('#order-form');
    var $paymentForm = $('.payment-form');
    var $errors = $('.order-form__errors');

    // Save order
    $orderForm.on('submit', function (event) {
        event.preventDefault();

        var isHasErrors = function (response) {
            return undefined === response.ERRORS || 0 !== response.ERRORS.length;
        };

        var showErrorMessage = function (message) {
            $errors.html(message);
        };

        var showErrors = function (response) {
            if( undefined !== response.ERRORS && response.ERRORS.length ) {
                showErrorMessage(response.ERRORS.join('<br>') + '<br>')
            }
            else {
                showErrorMessage('К сожалению что то пошло не так. Обратитесь к администратору сайта.');
            }
        };

        /**
         * Serialize form data with files
         *
         * @param  [jQueryObject] $form form for serialize.
         * @return [FormData]
         */
        var serializeForm = function( $form ) {
            var formData = new FormData();

            // Append form data.
            var params = $form.serializeArray();
            $.each(params, function (i, val) {
                formData.append(val.name, val.value);
            });

            // Append files.
            $.each($form.find("input[type='file']"), function(i, tag) {
                $.each($(tag)[0].files, function(i, file) {
                    formData.append(tag.name, file);
                });
            });

            // Append is ajax mark.
            formData.append('is_ajax', 'Y');

            return formData;
        }

        $.ajax({
            url: $orderForm.attr('action'),
            type: 'POST',
            dataType: 'json',
            async: false,
            cache: false,
            contentType: false,
            processData: false,
            data: serializeForm($orderForm)
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
                    var href = $paymentForm.find('a').attr('href');
                    if(href) window.location.href = href;

                    // When some go wrong...
                    setTimeout(function() {
                        // Its no bug, its future.
                        $paymentForm.fadeIn();
                    }, 5000);
                });
            })
            .fail(function (data) {
                showErrors();
            });

        return false;
    });
});