<?php
namespace Vendor\WeatherChanges\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\DeploymentConfig;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;

class PriceChanger extends Action
{
    protected $config;
    protected $productCollection;
    protected $productFactory;
    protected $indexer;
    protected $categoryCollection;
    protected $categoryRepo;
    protected $cacheTypes;
    protected $cachePool;

    public function __construct(
        Context $context,
        DeploymentConfig $config,
        CollectionFactory $productCollection,
        ProductFactory $productFactory,
        IndexerRegistry $indexer,
        CategoryCollectionFactory $categoryCollection,
        CategoryRepository $categoryRepo,
        TypeListInterface $cacheTypes,
        Pool $cachePool
    ) {
        parent::__construct($context);
        $this->config = $config;
        $this->productCollection = $productCollection;
        $this->productFactory = $productFactory;
        $this->indexer = $indexer;
        $this->categoryCollection = $categoryCollection;
        $this->categoryRepo = $categoryRepo;
        $this->cacheTypes = $cacheTypes;
        $this->cachePool = $cachePool;
    }

    private function getApiData($url, $params = [])
    {
        $curl = curl_init();
        
        if (!empty($params)) {
            $url = sprintf("%s?%s", $url, http_build_query($params));
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($curl);
        curl_close($curl);

        return $result;
    }

    private function getAllProducts() 
    {
        $products = $this->productCollection->create()->addAttributeToSelect('*');
        
        $productList = [];
        foreach ($products as $item) {
            $productList[] = [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'price' => $item->getPrice()
            ];
        }
        return $productList;
    }
    
    private function updatePricesByTemperature($temperature, $limit = 0)
    {
        $categories = $this->categoryCollection->create()
            ->addAttributeToSelect('*')
            ->addIsActiveFilter();

        $updatedProducts = [];
        $productsProcessed = 0;
        
        foreach ($categories as $category) {
            if ($limit > 0 && $productsProcessed >= $limit) {
                break;
            }
            
            $products = $this->productCollection->create()
                ->addAttributeToSelect('*')
                ->addCategoriesFilter(['in' => [$category->getId()]]);

            foreach ($products as $product) {
                if ($limit > 0 && $productsProcessed >= $limit) {
                    break;
                }
                
                $priceFactor = mt_rand(50, 150) / 100;
                $priceDirection = mt_rand(0, 1) ? 1 : -1;

                $oldPrice = $product->getPrice();
                $newPrice = $oldPrice + ($priceDirection * $oldPrice * $priceFactor * ($temperature / 100));
                $newPrice = max(0.01, round($newPrice, 2));
                $changeSymbol = $priceDirection === 1 ? '+' : '-';

                $productItem = $this->productFactory->create()->load($product->getId());
                if (!$productItem->getSpecialPrice()) {
                    $productItem->setSpecialPrice($oldPrice);
                }
                $productItem->setPrice($newPrice);
                $productItem->getResource()->saveAttribute($productItem, 'price');
                $productItem->getResource()->saveAttribute($productItem, 'special_price');

                $updatedProducts[] = [
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'original_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'temp' => $temperature,
                    'action' => $changeSymbol,
                    'category' => $category->getName()
                ];
                
                $productsProcessed++;
            }
        }

        $this->indexer->get('catalog_product_price')->reindexAll();
        $this->clearCache();

        return $updatedProducts;
    }
    
    private function clearCache()
    {
        $types = ['config', 'layout', 'block_html', 'collections', 'reflection', 
                  'db_ddl', 'eav', 'config_integration', 'config_integration_api', 
                  'full_page', 'translate', 'config_webservice'];
                  
        foreach ($types as $type) {
            $this->cacheTypes->cleanType($type);
        }
        
        foreach ($this->cachePool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
    }

    public function executeCron($limit = 10)
    {
        $weatherApi = "https://api.open-meteo.com/v1/forecast?latitude=58.3806&longitude=26.7251&hourly=temperature_2m&forecast_days=1";
        $response = $this->getApiData($weatherApi);
        $weatherData = json_decode($response, true);

        $currentHour = date('Y-m-d\TH:00', time());
        $timeIndex = array_search($currentHour, $weatherData['hourly']['time']);

        $currentTemp = ($timeIndex !== false) ? $weatherData['hourly']['temperature_2m'][$timeIndex] : '0';

        if (is_numeric($currentTemp)) {
            $this->updatePricesByTemperature($currentTemp, $limit);
        }
    }

    public function execute()
    {
        $weatherApi = "https://api.open-meteo.com/v1/forecast?latitude=58.3806&longitude=26.7251&hourly=temperature_2m&forecast_days=1";
        $response = $this->getApiData($weatherApi);
        $weatherData = json_decode($response, true);

        $currentHour = date('Y-m-d\TH:00', time());
        $timeIndex = array_search($currentHour, $weatherData['hourly']['time']);

        $currentTemp = ($timeIndex !== false) ? $weatherData['hourly']['temperature_2m'][$timeIndex] : '0';

        $limit = (int)$this->getRequest()->getParam('limit', 0);
        
        $output = "Current temperature: {$currentTemp}°C<br><br>";
        
        if (is_numeric($currentTemp)) {
            $updatedProducts = $this->updatePricesByTemperature($currentTemp, $limit);
            
            $output .= "<strong>Products with changed prices: " . count($updatedProducts) . "</strong><br>";
            $output .= $limit > 0 ? "(Limited to {$limit} products)<br><br>" : "<br>";
            
            foreach ($updatedProducts as $item) {
                $output .= "ID: {$item['id']}, Name: {$item['name']}, Original Price: {$item['original_price']}, " .
                          "New Price: {$item['new_price']}, Temp: {$item['temp']}°C, Action: {$item['action']}<br>";
            }
        } else {
            $output .= "Error.";
        }

        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setContents($output);
        return $result;
    }
}