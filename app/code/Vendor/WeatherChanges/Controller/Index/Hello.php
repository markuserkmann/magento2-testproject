<?php
namespace Vendor\WeatherChanges\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\DeploymentConfig;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;



class Hello extends Action
{
    //Construct the class with the necessary dependencies
    protected $deploymentConfig;
    protected $productCollectionFactory;


    public function __construct(
        Context $context,
        DeploymentConfig $deploymentConfig,
        CollectionFactory $productCollectionFactory
    ) {
        parent::__construct($context);
        $this->deploymentConfig = $deploymentConfig;
        $this->productCollectionFactory = $productCollectionFactory;

    }

    function CallAPI($url, $params = [])
    {
        $curl = curl_init();

        if (is_array($params) && !empty($params)) {
            $url = sprintf("%s?%s", $url, http_build_query($params));
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);

        curl_close($curl);

        return $result;
    }

    public function FetchAllTheProducts() 
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('*');

        $products = [];
        foreach ($collection as $product) {
            $products[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'price' => $product->getPrice()
            ];
        }
        return $products;
    }
    
    // Retrieves all the products, generates a random float between 0.5 and 1.5, and multiplies the product price by the current temperature. Chooses randomly if it increases or decreases the product price. 
    public function ChangePricesBasedOnTemperature($temp)
    {
        $products = $this->FetchAllTheProducts();
        $changedProducts = [];

        foreach ($products as $product) {
            $factor = mt_rand(50, 150) / 100;
            $direction = mt_rand(0, 1) ? 1 : -1;

            $newPrice = $product['price'] + ($direction * $product['price'] * $factor * ($temp / 100));
            $newPrice = round($newPrice, 2);

            $action = $direction === 1 ? '+' : '-';

            $changedProducts[] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'original_price' => $product['price'],
                'new_price' => $newPrice,
                'temp' => $temp,
                'action' => $action
            ];
        }

        return $changedProducts;
    }

    public function execute()
        {
            $url = "https://api.open-meteo.com/v1/forecast?latitude=58.3806&longitude=26.7251&hourly=temperature_2m&forecast_days=1";
            $response = $this->CallAPI($url);
            $data = json_decode($response, true);

            $currentHour = date('Y-m-d\TH:00', time());

            $index = array_search($currentHour, $data['hourly']['time']);

            if ($index !== false) {
                $temp = $data['hourly']['temperature_2m'][$index];
            } else {
                $temp = 'N/A';
            }

            $changedProducts = $this->ChangePricesBasedOnTemperature(is_numeric($temp) ? $temp : 0);

            $output = "Current temperature: {$temp}°C<br><br>";
            $output .= "<strong>Products with changed prices:</strong><br>";
            foreach ($changedProducts as $product) {
                $output .= "ID: {$product['id']}, Name: {$product['name']}, Original Price: {$product['original_price']}, New Price: {$product['new_price']}, Temp: {$product['temp']}°C, Action: {$product['action']}<br>";
            }


            $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
            $result->setContents($output);
            return $result;
        }
}