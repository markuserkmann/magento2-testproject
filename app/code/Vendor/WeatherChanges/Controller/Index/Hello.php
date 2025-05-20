<?php
namespace Vendor\WeatherChanges\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\DeploymentConfig;


class Hello extends Action
{
    //Construct the class with the necessary dependencies
    protected $deploymentConfig;

    public function __construct(
        Context $context,
        DeploymentConfig $deploymentConfig
    ) {
        parent::__construct($context);
        $this->deploymentConfig = $deploymentConfig;
    }

    function CallAPI($url) {
        $curl = curl_init();

        $url = sprintf("%s?%s", $url, http_build_query($data));

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);

        curl_close($curl);

        return $result;
    }

    public function execute()
        {
            $url = "https://api.open-meteo.com/v1/forecast?latitude=58.3806&longitude=26.7251&hourly=temperature_2m&forecast_days=1";
            $response = $this->CallAPI('GET', $url);
            $data = json_decode($response, true);

            $currentHour = date('Y-m-d\TH:00', time());

            $index = array_search($currentHour, $data['hourly']['time']);

            if ($index !== false) {
                $temp = $data['hourly']['temperature_2m'][$index];
            } else {
                $temp = 'N/A';
            }

            $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
            $result->setContents("Current temperature: {$temp}°C");
            return $result;
        }
}