<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Model\ApplePay;

class PaymentDetailsHandler extends \PayPal\Braintree\Gateway\Response\PaymentDetailsHandler
{
    /**
     * List of additional details
     * @var array
     */
    protected $additionalInformationMapping = [
        self::PROCESSOR_AUTHORIZATION_CODE,
        self::PROCESSOR_RESPONSE_CODE,
        self::PROCESSOR_RESPONSE_TEXT,
    ];
}
