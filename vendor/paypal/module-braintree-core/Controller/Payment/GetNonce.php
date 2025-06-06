<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);
namespace PayPal\Braintree\Controller\Payment;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use PayPal\Braintree\Gateway\Command\GetPaymentNonceCommand;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Webapi\Exception;
use Psr\Log\LoggerInterface;

class GetNonce extends Action implements ActionInterface, HttpGetActionInterface
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var SessionManagerInterface
     */
    private SessionManagerInterface $session;

    /**
     * @var GetPaymentNonceCommand
     */
    private GetPaymentNonceCommand $command;

    /**
     * @param Context $context
     * @param LoggerInterface $logger
     * @param SessionManagerInterface $session
     * @param GetPaymentNonceCommand $command
     */
    public function __construct(
        Context $context,
        LoggerInterface $logger,
        SessionManagerInterface $session,
        GetPaymentNonceCommand $command
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->session = $session;
        $this->command = $command;
    }

    /**
     * @inheritdoc
     */
    public function execute(): Json|ResultInterface|ResponseInterface
    {
        $response = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        try {
            $publicHash = $this->getRequest()->getParam('public_hash');
            $customerId = $this->session->getCustomerId();
            $result = $this->command->execute(['public_hash' => $publicHash, 'customer_id' => $customerId])->get();
            $response->setData([
                'paymentMethodNonce' => $result['paymentMethodNonce'],
                'details' => $result['details']
            ]);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return $this->processBadRequest($response);
        }

        return $response;
    }

    /**
     * Return response for bad request
     *
     * @param ResultInterface $response
     * @return ResultInterface
     */
    private function processBadRequest(ResultInterface $response): ResultInterface
    {
        $response->setHttpResponseCode(Exception::HTTP_BAD_REQUEST);
        $response->setData([
            'message' => __('Sorry, but something went wrong')
        ]);

        return $response;
    }
}
