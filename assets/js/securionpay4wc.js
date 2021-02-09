jQuery(function($) {

	Securionpay.setPublicKey(securionpay4wc_data.publicKey);

	var $form = $('form.checkout, form#order_review');
	var savedFieldValues = {};
	var $ccForm, $ccNumber, $ccExpiry, $ccCvc;

	function formInit() {
		$ccForm = $('#securionpay4wc-cc-form, #wc-securionpay4wc-cc-form');
		$ccNumber = $ccForm.find('#securionpay4wc-card-number');
		$ccExpiry = $ccForm.find('#securionpay4wc-card-expiry');
		$ccCvc = $ccForm.find('#securionpay4wc-card-cvc');

		if ($('input[name="securionpay4wc-card"]').length && $('input[name="securionpay4wc-card"]:checked').val() !== 'new') {
			$ccForm.hide();
		}
        $form.on('change', 'input[name="securionpay4wc-card"]', function () {
            if ($('input[name="securionpay4wc-card"]:checked').val() === 'new') {
                $ccForm.slideDown(200);
            } else {
                $ccForm.slideUp(200);
            }
        });

        // Add in lost data
        if (savedFieldValues.number) {
            $ccNumber.val(savedFieldValues.number.val).attr('class', savedFieldValues.number.classes);
        }
        if (savedFieldValues.expiry) {
            $ccExpiry.val(savedFieldValues.expiry.val);
        }
        if (savedFieldValues.cvc) {
            $ccCvc.val(savedFieldValues.cvc.val);
        }
    }

    function formSubmit(event) {

	    // New card
        if (
            $('#payment_method_securionpay4wc').is(':checked') &&
            (!$('input[name="securionpay4wc-card"]').length || $('input[name="securionpay4wc-card"]:checked').val() === 'new')
        ) {

            if (!$('.securionpay4wc-token, .securionpay4wc-error').length) {
                var cardExpiry = $ccExpiry.payment('cardExpiryVal');
                var name = ($('#billing_first_name').val() || $('#billing_last_name').val()) ? $('#billing_first_name').val() + ' ' + $('#billing_last_name').val() : securionpay4wc_data.billing_name;

                var request = {
                    number         : $ccNumber.val() || '',
                    cvc            : $ccCvc.val() || '',
                    expMonth       : cardExpiry.month || '',
                    expYear        : cardExpiry.year || '',
                    cardholderName : $('.securionpay4wc-billing-name').val() || name || '',
                    addressLine1   : $('#billing_address_1').val() || securionpay4wc_data.billing_address_1 || '',
                    addressLine2   : $('#billing_address_2').val() || securionpay4wc_data.billing_address_2 || '',
                    addressCity    : $('#billing_city').val() || securionpay4wc_data.billing_city || '',
                    addressState   : $('#billing_state').val() || securionpay4wc_data.billing_state || '',
                    addressZip     : $('.securionpay4wc-billing-zip').val() || $('#billing_postcode').val() || securionpay4wc_data.billing_postcode || '',
                    addressCountry : $('#billing_country').val() || securionpay4wc_data.billing_country || ''
                };

                if (validate(request)) {
                    $form.block({
                        message: null,
                        overlayCSS: {
                            background: '#fff',
                            opacity: 0.6
                        }
                    });

                    if (securionpay4wc_data.threedsecure === 'yes') {
                        Securionpay.createCardToken(request, responseHandlerFor3DSecure);
                    } else {
                        Securionpay.createCardToken(request, responseHandler);
                    }

                    event.stopImmediatePropagation();
                    return false;
                }
            }
        }

        // Saved card
        if (
            $('#payment_method_securionpay4wc').is(':checked') &&
            $('input[name="securionpay4wc-card"]').length > 0 &&
            $('input[name="securionpay4wc-card"]:checked').val() !== 'new' &&
            securionpay4wc_data.threedsecure === 'yes'
        ) {
            if (!$('.securionpay4wc-token, .securionpay4wc-error').length) {
                var cardIndex = parseInt($('input[name="securionpay4wc-card"]:checked').val());

                if (cardIndex >= 0) {
                    $.ajax({
                        method: 'POST',
                        url: securionpay4wc_data.ajax_url,
                        data: {
                            action: 'get3DSecureCardToken',
                            card_index: cardIndex
                        }
                    })
                    .done(function (result) {
                        responseHandlerFor3DSecure({
                            id: result
                        });
                    })
                    .fail(function () {
                        responseHandlerFor3DSecure({
                            id: ''
                        });
                    });

                    event.stopImmediatePropagation();
                    return false;
                }
            }
        }

        return true;
    }

    function validate(request) {
        var errors = validateFields(request);
        if (errors.length) {
        	apprendFormErrors(errors);
            return false;
        } else {
            $form.find('.woocommerce-error').remove();
            return true;
        }
    }

    function validateFields(request) {
        var errors = [];

        if (!request.number) {
            errors.push({
                'field' : 'securionpay4wc-card-number',
                'value' : 'undefined'
            });
        } else if (!$.payment.validateCardNumber(request.number)) {
            errors.push({
                'field' : 'securionpay4wc-card-number',
                'value'  : 'invalid'
            });
        }

        if (!request.expMonth || !request.expYear) {
            errors.push({
                'field' : 'securionpay4wc-card-expiry',
                'value'  : 'undefined'
            });
        } else if (!$.payment.validateCardExpiry(request.expMonth, request.expYear)) {
            errors.push({
                'field' : 'securionpay4wc-card-expiry',
                'value'  : 'invalid'
            });
        }

        if (!request.cvc) {
            errors.push({
                'field' : 'securionpay4wc-card-cvc',
                'value'  : 'undefined'
            });
        } else if (!$.payment.validateCardCVC(request.cvc, $.payment.cardType(request.number))) {
            errors.push({
                'field' : 'securionpay4wc-card-cvc',
                'value'  : 'invalid'
            });
        }

        return errors;
    }

    function responseHandlerFor3DSecure(response) {
        if (!response.error) {
            $.ajax({
                method: 'POST',
                url: securionpay4wc_data.ajax_url,
                data: {
                    action: 'get3DSecureCartData'
                }
            })
            .done(function(result) {
                result = JSON.parse(result);
                if (typeof result.currency !== 'undefined' && typeof result.amount !== 'undefined' && result.amount > 0) {
                    Securionpay.verifyThreeDSecure({
                        amount: result.amount,
                        currency: result.currency,
                        card: response.id
                    }, responseHandler);
                } else {
                    responseHandler(response);
                }
            })
            .fail(function () {
                responseHandler(response);
            });

        } else {
            var errors = [{
                'field' : 'securionpay4wc-error-type',
                'value' : response.error.type
            }, {
                'field' : 'securionpay4wc-error-code',
                'value' : response.error.code
            }, {
                'field' : 'securionpay4wc-error-message',
                'value' : response.error.message
            }];

        	apprendFormErrors(errors);
        }
    }

    function responseHandler(response) {
        if (!response.error) {
            $form.append('<input type="hidden" class="securionpay4wc-token" name="securionpay4wc-token" value="' + response.id + '"/>');
        } else {
            var errors = [{
                'field' : 'securionpay4wc-error-type',
                'value' : response.error.type
            }, {
                'field' : 'securionpay4wc-error-code',
                'value' : response.error.code
            }, {
                'field' : 'securionpay4wc-error-message',
                'value' : response.error.message
            }];

            apprendFormErrors(errors);
        }

        $form.submit();
    }
    
    function apprendFormErrors(errors) {
        $('.securionpay4wc-token, .securionpay4wc-error').remove();

        for (var i = 0; i < errors.length; i++) {
            var field = errors[i].field;
            var value = errors[i].value;
            if (value) {
            	$form.append('<input type="hidden" class="securionpay4wc-error" name="' + field + '" value="' + value + '">');
        	}
        }

        $form.append('<input type="hidden" class="securionpay4wc-error" name="securionpay4wc-error" value="1">');
        $form.unblock();
    }

    function formError() {
    	$('.securionpay4wc-error').remove();
		$('.securionpay4wc-token').remove();
    }

    $('body').on('updated_checkout.securionpay4wc', formInit).trigger('updated_checkout.securionpay4wc');
    $('body').on('checkout_error', formError);

    // Checkout Form
    $('form.checkout').on('checkout_place_order', formSubmit);

    // Pay Page Form
    $('form#order_review').on('submit', formSubmit);

    // Both Forms
    $form.on('keyup change', '#securionpay4wc-card-number, #securionpay4wc-card-expiry, #securionpay4wc-card-cvc, input[name="securionpay4wc-card"], input[name="payment_method"]', function () {

        // Save credit card details in case the address changes (or something else)
    	savedFieldValues.card = {
    		'id' : $('input[name="securionpay4wc-card"]:checked').attr('id')
    	}
        savedFieldValues.number = {
            'val'     : $ccNumber.val(),
            'classes' : $ccNumber.attr('class')
        };
        savedFieldValues.expiry = {
            'val' : $ccExpiry.val()
        };
        savedFieldValues.cvc = {
            'val' : $ccCvc.val()
        };

        $('.woocommerce_error, .woocommerce-error, .woocommerce-message, .woocommerce_message').remove();
        $('.securionpay4wc-token, .securionpay4wc-error').remove();
    });
});
