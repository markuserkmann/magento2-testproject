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
    public function ChangePricesBasedOnTemperature () 
    {

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

            $products = $this->FetchAllTheProducts();

            $output = "Current temperature: {$temp}°C<br><br>";
            $output .= "<strong>Products:</strong><br>";
            foreach ($products as $product) {
                $output .= "ID: {$product['id']},  Name: {$product['name']}, Price: {$product['price']}<br>";
            }


            $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
            $result->setContents($output);
            return $result;
        }
}