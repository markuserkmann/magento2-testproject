<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Gateway\Request;

use PayPal\Braintree\Gateway\Config\Config;
use PayPal\Braintree\Observer\DataAssignObserver;
use PayPal\Braintree\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Helper\Formatter;

class PaymentDataBuilder implements BuilderInterface
{
    use Formatter;

    /**
     * The billing amount of the request. This value must be greater than 0,
     * and must match the currency format of the merchant account.
     */
    public const AMOUNT = 'amount';

    /**
     * One-time-use token that references a payment method provided by your customer,
     * such as a credit card or PayPal account.
     *
     * The nonce serves as proof that the user has authorized payment (e.g. credit card number or PayPal details).
     * This should be sent to your server and used with any of Braintree's server-side client libraries
     * that accept new or saved payment details.
     * This can be passed instead of a payment_method_token parameter.
     */
    public const PAYMENT_METHOD_NONCE = 'paymentMethodNonce';

    /**
     * The merchant account ID used to create a transaction.
     * Currency is also determined by merchant account ID.
     * If no merchant account ID is specified, Braintree will use your default merchant account.
     */
    public const MERCHANT_ACCOUNT_ID = 'merchantAccountId';

    /**
     * The Braintree vaulted customer ID used to create a transaction.
     * A customer ID should only be provided if making a transaction using an existing vaulted payment method.
     */
    public const CUSTOMER_ID = 'customerId';

    /**
     * Order ID Key
     */
    public const ORDER_ID = 'orderId';

    /**
     * @var Config $config
     */
    private Config $config;

    /**
     * @var SubjectReader $subjectReader
     */
    private SubjectReader $subjectReader;

    /**
     * Constructor
     *
     * @param Config $config
     * @param SubjectReader $subjectReader
     */
    public function __construct(Config $config, SubjectReader $subjectReader)
    {
        $this->config = $config;
        $this->subjectReader = $subjectReader;
    }

    /**
     * @inheritdoc
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = $this->subjectReader->readPayment($buildSubject);

        $payment = $paymentDO->getPayment();
        $order = $paymentDO->getOrder();

        $result = [
            self::AMOUNT => $this->formatPrice($this->subjectReader->readAmount($buildSubject)),
            self::PAYMENT_METHOD_NONCE => $payment->getAdditionalInformation(
                DataAssignObserver::PAYMENT_METHOD_NONCE
            ),
            self::ORDER_ID => $order->getOrderIncrementId()
        ];

        $merchantAccountId = $this->config->getMerchantAccountId();
        if (!empty($merchantAccountId)) {
            $result[self::MERCHANT_ACCOUNT_ID] = $merchantAccountId;
        }

        return $result;
    }
}
