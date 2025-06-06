<?php

/**
 * ADOBE CONFIDENTIAL
 *
 * Copyright 2024 Adobe
 * All Rights Reserved.
 *
 * NOTICE: All information contained herein is, and remains
 * the property of Adobe and its suppliers, if any. The intellectual
 * and technical concepts contained herein are proprietary to Adobe
 * and its suppliers and are protected by all applicable intellectual
 * property laws, including trade secret and copyright laws.
 * Dissemination of this information or reproduction of this material
 * is strictly forbidden unless prior written permission is obtained
 * from Adobe.
 */

declare(strict_types=1);

namespace Magento\PaymentServicesPaypalGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\PaymentServicesPaypal\Helper\TextSanitiser;
use Magento\PaymentServicesPaypal\Model\VaultService;
use Magento\PaymentServicesPaypal\Model\Config;
use Magento\Framework\UrlInterface;

/**
 * CreateVaultCardSetupToken resolver, used for GraphQL mutation processing.
 *
 */
class CreateVaultCardSetupToken implements ResolverInterface
{
    /**
     * @var VaultService
     */
    private VaultService $vaultService;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var UrlInterface
     */
    private UrlInterface $urlBuilder;

    /**
     * @var TextSanitiser
     */
    private TextSanitiser $textSanitiser;

    /**
     * @param VaultService $vaultService
     * @param Config $config
     * @param UrlInterface $urlBuilder
     * @param TextSanitiser $textSanitiser
     */
    public function __construct(
        VaultService $vaultService,
        Config $config,
        UrlInterface $urlBuilder,
        TextSanitiser $textSanitiser
    ) {
        $this->vaultService = $vaultService;
        $this->config = $config;
        $this->urlBuilder = $urlBuilder;
        $this->textSanitiser = $textSanitiser;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field       $field,
        $context,
        ResolveInfo $info,
        ?array       $value = null,
        ?array       $args = null
    ) {
        if (empty($args['input']) || !is_array($args['input'])) {
            throw new GraphQlInputException(__('"input" value should be specified'));
        }

        $response =  $this->vaultService->createVaultCardSetupToken(
            $this->buildVaultCardSetupTokenRequestBody($args['input'])
        );

        return $this->formatVaultCardSetupTokenResponse($response);
    }

    /**
     * Build request body
     *
     * Build the request body for the VaultService to create a vault card setup token
     * based on the graphQL input type CreateVaultCardSetupTokenInput
     *
     * @param array $input
     * @return array
     */
    private function buildVaultCardSetupTokenRequestBody(array $input): array
    {
        $paymentSource = $input['setup_token']['payment_source'];

        return [
            'setup_token' => [
                'payment_source' => [
                    'card' => [
                        'name' => $this->textSanitiser->textOnly($paymentSource['card']['name'] ?? ''),
                        'three_ds_mode' => $this->resolve3DSMode($input['three_ds_mode'] ?? ''),
                        'return_url' => $this->urlBuilder->getUrl('vault/cards/listaction'),
                        'cancel_url' => $this->urlBuilder->getUrl('vault/cards/listaction'),
                        'billing_address' => $this->resolveAddress($paymentSource['card']['billing_address'] ?? [])
                    ]
                ]
            ]
        ];
    }

    /**
     * Format the response from the VaultService to match the graphQL output type CreateVaultCardSetupTokenOutput
     *
     * @param array $response
     * @return array
     */
    private function formatVaultCardSetupTokenResponse(array $response): array
    {
        return [
            'setup_token' => $response['setup_token']['id'],
        ];
    }

    /**
     * Resolve 3DS mode
     *
     * @param string $threeDSMode
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function resolve3DSMode(string $threeDSMode): string
    {
        if (!empty($threeDSMode)) {
            return $this->textSanitiser->textOnly($threeDSMode);
        }

        $configThreeDS = $this->config->getThreeDS();
        if (empty($configThreeDS) || $configThreeDS == "0") {
            return "OFF";
        }

        return $configThreeDS;
    }

    /**
     * Resolve address
     *
     * @param array $billingAddress
     * @return array
     */
    public function resolveAddress(array $billingAddress): array
    {
        return [
            'address_line_1' => $this->textSanitiser->textOnly($billingAddress['address_line_1'] ?? ''),
            'address_line_2' => $this->textSanitiser->textOnly($billingAddress['address_line_2'] ?? ''),
            'admin_area_1' => $this->textSanitiser->textOnly($billingAddress['region'] ?? ''),
            'admin_area_2' => $this->textSanitiser->textOnly($billingAddress['city'] ?? ''),
            'postal_code' => $this->textSanitiser->textOnly($billingAddress['postal_code'] ?? ''),
            'country_code' => $this->textSanitiser->textOnly($billingAddress['country_code'] ?? '')
        ];
    }
}
