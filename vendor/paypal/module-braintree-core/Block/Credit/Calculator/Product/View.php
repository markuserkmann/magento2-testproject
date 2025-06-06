<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace PayPal\Braintree\Block\Credit\Calculator\Product;

use PayPal\Braintree\Api\CreditPriceRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use PayPal\Braintree\Gateway\Config\PayPalCredit\Config as PayPalCreditConfig;

/**
 * @api
 * @since 100.0.2
 */
class View extends Template
{
    /**
     * @var CreditPriceRepositoryInterface
     */
    protected CreditPriceRepositoryInterface $creditPriceRepository;

    /**
     * @var Registry
     */
    protected Registry $coreRegistry;

    /**
     * @var PayPalCreditConfig
     */
    protected PayPalCreditConfig $config;

    /**
     * View constructor.
     * @param Template\Context $context
     * @param PayPalCreditConfig $config
     * @param Registry $registry
     * @param CreditPriceRepositoryInterface $creditPriceRepository
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        PayPalCreditConfig $config,
        Registry $registry,
        CreditPriceRepositoryInterface $creditPriceRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->creditPriceRepository = $creditPriceRepository;
        $this->coreRegistry = $registry;
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml(): string
    {
        if ($this->config->isCalculatorEnabled()) {
            return parent::_toHtml();
        }

        return '';
    }

    /**
     * Retrieve current product model
     *
     * @return Product
     */
    public function getProduct(): Product
    {
        return $this->coreRegistry->registry('product');
    }

    /**
     * Get price data
     *
     * @return string|bool
     */
    public function getPriceData(): bool|string
    {
        if ($this->getProduct()) {
            $results = $this->creditPriceRepository->getByProductId((int) $this->getProduct()->getId());
            if (null !== $results) {
                $options = [];
                foreach ($results as $option) {
                    $options[] = [
                        'term' => $option->getTerm(),
                        'monthlyPayment' => $option->getMonthlyPayment(),
                        'apr' => $option->getInstalmentRate(),
                        'cost' => $option->getCostOfPurchase(),
                        'costIncInterest' => $option->getTotalIncInterest()
                    ];
                }

                return json_encode($options);
            }
        }

        return false;
    }

    /**
     * Get merchant name
     *
     * @return string|null
     */
    public function getMerchantName(): ?string
    {
        return $this->config->getMerchantName();
    }
}
