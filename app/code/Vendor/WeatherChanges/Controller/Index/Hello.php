<?php
namespace Vendor\WeatherChanges\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\DeploymentConfig;


class Hello extends Action
{
    protected $deploymentConfig;

    public function __construct(
        Context $context,
        DeploymentConfig $deploymentConfig
    ) {
        parent::__construct($context);
        $this->deploymentConfig = $deploymentConfig;
    }

    public function execute()
    {
        $cryptKey = $this->deploymentConfig->get('api/WeatherApiKey');
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setContents('Hello World! The Weather API Key is: ' . $cryptKey);
        return $result;
    }
}