# PrestaShop-webservice-lib
PHP library for PrestaShop Webservices

This repository is only because people want to use the webservice with PrestShop 1.7 also.
The difference between the originally and this is only the max version number.

You can find the originnal documentation here: http://doc.prestashop.com/display/PS16/Web+service+one-page+documentation

```php
// Here we define constants /!\ You need to replace this parameters
define('DEBUG', true);											// Debug mode
define('PS_SHOP_PATH', 'http://www.myshop.com/');		// Root path of your PrestaShop store
define('PS_WS_AUTH_KEY', 'ZQ88PRJX5VWQHCWE4EE7SQ7HPNX00RAJ');	// Auth key (Get it in your Back Office)
require_once('./PSWebServiceLibrary.php');

// Here we make the WebService Call
try
{
	$webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, DEBUG);

	// Here we set the option array for the Webservice : we want customers resources
	$opt['resource'] = 'customers';

	// Call
	$xml = $webService->get($opt);
	// Here we get the elements from children of customers markup "customer"
	$resources = $xml->customers->children();
}
```
