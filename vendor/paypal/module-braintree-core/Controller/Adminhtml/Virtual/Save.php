<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Controller\Adminhtml\Virtual;

use Braintree\Result\Error;
use Braintree\Result\Successful;
use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use PayPal\Braintree\Model\Adapter\BraintreeAdapter;
use PayPal\Braintree\Gateway\Request\ChannelDataBuilder;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action
{
    public const ADMIN_RESOURCE = 'Magento_Sales::create';

    /**
     * @var BraintreeAdapter
     */
    protected BraintreeAdapter $braintreeAdapter;

    /**
     * @var ChannelDataBuilder
     */
    protected ChannelDataBuilder $channelDataBuilder;

    /**
     * Save constructor.
     * @param Context $context
     * @param BraintreeAdapter $braintreeAdapter
     * @param ChannelDataBuilder $channelDataBuilder
     */
    public function __construct(
        Context $context,
        BraintreeAdapter $braintreeAdapter,
        ChannelDataBuilder $channelDataBuilder
    ) {
        parent::__construct($context);
        $this->braintreeAdapter = $braintreeAdapter;
        $this->channelDataBuilder = $channelDataBuilder;
    }

    /**
     * Attempt to take the payment through the braintree api
     *
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $request = [
            'paymentMethodNonce' => $this->getRequest()->getParam('payment_method_nonce'),
            'amount' => $this->getRequest()->getParam('amount'),
            'transactionSource' => 'moto',
            'options' => [
                'submitForSettlement' => true
            ]
        ];
        $request = array_merge($request, $this->channelDataBuilder->build([]));

        try {
            $response = $this->braintreeAdapter->sale($request);
            if ($response instanceof Successful) {
                $message = sprintf(
                    'A payment has been made on the %s card ending %s for %s %s (Braintree Transaction ID: %s)',
                    $response->transaction->creditCard['cardType'],
                    $response->transaction->creditCard['last4'],
                    $response->transaction->currencyIsoCode,
                    $response->transaction->amount,
                    $response->transaction->id
                );

                $this->messageManager->addSuccessMessage($message);
            } elseif ($response instanceof Error) {
                throw new LocalizedException(__($response->message));
            } else {
                throw new LocalizedException(
                    __('The response from the Braintree server was incorrect. Please try again.')
                );
            }
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('braintree/virtual/index');
        return $resultRedirect;
    }
}
