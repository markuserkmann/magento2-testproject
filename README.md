# WeatherChanges Magento 2 Module

This module dynamically updates product prices based on the current temperature in Tartu.

## Installation

1. Download the Magento module from this GitHub link: https://github.com/markuserkmann/magento-test-module.git
2. Go into your Magento 2 installation directory.
3. Place the module folder into the `app/code/Vendor` directory (so the path is `app/code/Vendor/WeatherChanges`).
4. Run the following commands in your Magento root directory:

   ```sh
   php bin/magento setup:upgrade
   php bin/magento module:enable Vendor_WeatherChanges
   php bin/magento setup:di:compile
   php bin/magento cache:flush
   ```

## Usage

1. To trigger the price changes manually, visit:

   ```
   http://yourdomain.xx/weatherchanges/index/pricechanger?limit=HowManyItemsYouWantToUpdate
   ```

   Replace `yourdomain.xx` with your actual domain and `HowManyItemsYouWantToUpdate` with the number of products you want to update at once.

2. To automate price updates, a cron job is included that updates 10 products every minute based on the current temperature. 

## Notes

- The module fetches the current temperature from the Open-Meteo API for Tartu, Estonia.
- Product prices are updated according to the temperature and a random factor.
- Use with caution on production stores, as prices will change frequently.
