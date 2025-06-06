<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Model\Paypal\Helper;

use Magento\Quote\Model\Quote;
use Magento\Checkout\Helper\Data;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\Session;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Checkout\Api\AgreementsValidatorInterface;
use PayPal\Braintree\Model\Paypal\OrderCancellationService;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class OrderPlace extends AbstractHelper
{
    /**
     * @var CartManagementInterface
     */
    private $cartManagement;

    /**
     * @var AgreementsValidatorInterface
     */
    private $agreementsValidator;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var Data
     */
    private $checkoutHelper;

    /**
     * @var OrderCancellationService
     */
    private $orderCancellationService;

    /**
     * Constructor
     *
     * @param CartManagementInterface $cartManagement
     * @param AgreementsValidatorInterface $agreementsValidator
     * @param Session $customerSession
     * @param Data $checkoutHelper
     * @param OrderCancellationService $orderCancellationService
     */
    public function __construct(
        CartManagementInterface $cartManagement,
        AgreementsValidatorInterface $agreementsValidator,
        Session $customerSession,
        Data $checkoutHelper,
        OrderCancellationService $orderCancellationService
    ) {
        $this->cartManagement = $cartManagement;
        $this->agreementsValidator = $agreementsValidator;
        $this->customerSession = $customerSession;
        $this->checkoutHelper = $checkoutHelper;
        $this->orderCancellationService = $orderCancellationService;
    }

    /**
     * Execute operation
     *
     * @param Quote $quote
     * @param array $agreement
     * @return void
     * @throws LocalizedException
     */
    public function execute(Quote $quote, array $agreement)
    {
        if (!$this->agreementsValidator->isValid($agreement)) {
            throw new LocalizedException(__('Please agree to all the terms and conditions before placing the order.'));
        }

        if ($this->getCheckoutMethod($quote) === Onepage::METHOD_GUEST) {
            $this->prepareGuestQuote($quote);
        }

        $this->disabledQuoteAddressValidation($quote);

        $quote->collectTotals();
        try {
            $this->cartManagement->placeOrder($quote->getId());
        } catch (\Exception $e) {
            if ($quote->getReservedOrderId()) {
                $this->orderCancellationService->execute($quote->getReservedOrderId());
            }
            throw $e;
        }
    }

    /**
     * Get checkout method
     *
     * @param Quote $quote
     * @return string
     */
    private function getCheckoutMethod(Quote $quote): string
    {
        if ($this->customerSession->isLoggedIn()) {
            return Onepage::METHOD_CUSTOMER;
        }
        if (!$quote->getCheckoutMethod()) {
            if ($this->checkoutHelper->isAllowedGuestCheckout($quote)) {
                $quote->setCheckoutMethod(Onepage::METHOD_GUEST);
            } else {
                $quote->setCheckoutMethod(Onepage::METHOD_REGISTER);
            }
        }

        return $quote->getCheckoutMethod();
    }

    /**
     * Prepare quote for guest checkout order submit
     *
     * @param Quote $quote
     * @return void
     */
    private function prepareGuestQuote(Quote $quote)
    {
        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(Group::NOT_LOGGED_IN_ID);
    }
}
