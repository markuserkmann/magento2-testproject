<?php
namespace Vendor\WeatherChanges\Cron;

use Vendor\WeatherChanges\Controller\Index\PriceChanger;

class PriceUpdate
{
    protected $priceChanger;

    public function __construct(PriceChanger $priceChanger)
    {
        $this->priceChanger = $priceChanger;
    }

    public function execute()
    {
        $this->priceChanger->executeCron(10);
    }
}