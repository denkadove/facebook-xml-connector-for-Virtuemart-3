<?php
/*
 * version 0.1 alfa
 * author Denis Dove (denkadove@gmail.com) * 
 * Donate:
 * 410016386867827 яндекс деньги
 * 5368290002523472 mastercard
*/

define('NAME', 'Организация»'); // название организации (не должно превышать 20 символов)
define('DESC', 'описание организации'); // описание организации
define('CURRENCY', 'RUB'); // валюта магазина (RUB, USD, EUR, UAH, KZT)
define('DELIVERY', 'true'); // наличие доставки в магазине (true - есть, false - нет)
define('EXCLUDE_CAT', '0'); // id категорий которые нужно исключить из выгрузки, перечислить через запятую, например define('EXCLUDE_CAT', '2,8,54,5')
define('EXCLUDE_PROD', '0'); // id товаров которые нужно исключить из выгрузки, перечислить через запятую, например define('EXCLUDE_PROD', '2,8,54,5')
define('FILE', 0); // cоздать файл vm2_market.xml (define('FILE', 1)) или генерировать данные динамически (define('FILE', 0)), если define('FILE', 0), то в настройках якдеса нужно указать ссылку http://ваш_сайт/market/vm2_market.php, если define('FILE', 1), то http://ваш_сайт/market/vm2_market.xml, также, если define('FILE', 1), то после каждого обновления товаров в магазине, нужно в браузере набрать адрес http://ваш_сайт/market/vm2_market.php и запустить скрипт, чтоб сгенерировать файл vm2_market.xml

define('_JEXEC', 1);
define('DS', DIRECTORY_SEPARATOR);
define('JPATH_BASE', dirname(__FILE__).DS.'..');

require_once(JPATH_BASE.DS.'includes'.DS.'defines.php');
require_once(JPATH_BASE.DS.'includes'.DS.'framework.php');

$app = JFactory::getApplication('site');
$app->initialise();

require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_virtuemart'.DS.'helpers'.DS.'config.php');
require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_virtuemart'.DS.'helpers'.DS.'calculationh.php');

$version = substr(vmVersion::$RELEASE, 0, 1);

VmConfig::loadConfig();

if ($version == 3) {
	require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_virtuemart'.DS.'models'.DS.'product.php');
	$model = new VirtueMartModelProduct();
	$lang = VmConfig::$defaultLang;
} else {	
	$lang = VmConfig::get('vmlang', 'en_gb');
}

$db = JFactory::getDBO();
$live_site = trim(str_replace('fb-market/', '', JURI::base()), '/').'/';
$calculator = calculationHelper::getInstance();

function getImages($id) {
	
	global $db, $live_site;
	
	$query = 'SELECT a.file_url FROM #__virtuemart_medias a JOIN #__virtuemart_product_medias b ON b.virtuemart_media_id = a.virtuemart_media_id WHERE a.published = 1 AND b.virtuemart_product_id = '.$id.' ORDER BY b.ordering, b.id LIMIT 10';
	$db->setQuery($query);
	$rows = $db->loadObjectList();
	
	$media = '';
	
	if ($rows) {
		foreach ($rows as $row) {
			$media .= '<g:image_link>'.$live_site.htmlspecialchars(str_replace(' ', '%20', $row->file_url)).'</g:image_link>'."\n";
		}
	}
	
	return $media;
}

function urlMarketEncode($url) {
	
	$url_arr = explode('/', $url);
	$url_st = '';
	
	foreach ($url_arr as $st) {
		$url_st .= '/'.urlencode($st);
	}
	
	return $url_st;
}

if (!FILE) {
	ob_start('ob_gzhandler', 9);
	header('Content-Type: application/xml; charset=utf-8');
} else {
	header('Content-Type: text/html; charset=UTF-8');
}

$xml = '<?xml version="1.0" encoding="utf-8"?>'."\n";
$xml .= '<feed xmlns="http://www.w3.org/2005/Atom" xmlns:g="http://base.google.com/ns/1.0">'."\n";

	$xml .= '<title>'.htmlspecialchars(mb_substr(NAME, 0, 20, 'UTF-8')).'</title>'."\n";	
	$xml .= '<link rel="self" href="http://gazkit.ru"/>'."\n";
	$query = 'SELECT DISTINCT a.virtuemart_product_id, a.product_parent_id, a.product_sku, a.virtuemart_vendor_id, a.product_in_stock, b.product_name, b.product_desc, d.product_tax_id, d.product_discount_id, d.product_price, d.product_override_price, d.override, d.product_currency, e.mf_name, e.virtuemart_manufacturer_id, g.virtuemart_category_id FROM (#__virtuemart_product_categories g LEFT JOIN (#__virtuemart_product_prices d RIGHT JOIN ((#__virtuemart_product_manufacturers f RIGHT JOIN #__virtuemart_products a ON f.virtuemart_product_id = a.virtuemart_product_id) LEFT JOIN #__virtuemart_manufacturers_'.$lang.' e ON f.virtuemart_manufacturer_id = e.virtuemart_manufacturer_id LEFT JOIN #__virtuemart_products_'.$lang.' b ON b.virtuemart_product_id = a.virtuemart_product_id) ON d.virtuemart_product_id = a.virtuemart_product_id) ON g.virtuemart_product_id = a.virtuemart_product_id) WHERE a.published = 1 AND d.product_price > 0 AND b.product_name <> \'\' AND g.virtuemart_category_id NOT IN ('.EXCLUDE_CAT.') AND a.virtuemart_product_id NOT IN ('.EXCLUDE_PROD.') GROUP BY a.virtuemart_product_id';
	$db->setQuery($query);
	$rows = $db->loadObjectList();

	foreach ($rows as $row) {
			
		
		$product_name = htmlspecialchars(trim(strip_tags($row->product_name)));	
		$product_id = $row->virtuemart_product_id;
		$product_cat_id = $row->virtuemart_category_id;
		if ($version == 3) {
			$model->getRawProductPrices($row, 0, array(1), 1);
		}
		
		$prices = $calculator->getProductPrices($row);
			
		$type = $row->mf_name ? ' type="vendor.model"' : '';
		$url = str_replace(array('/fb-market/', '//', 'http:/', 'https:/'), array('', '/', 'http://', 'https://'), $live_site.JRoute::_('index.php?option=com_virtuemart&view=productdetails&virtuemart_product_id='.$product_id.'&virtuemart_category_id='.$product_cat_id));	
		
		$available = $row->product_in_stock > 0 ? 'in stock' : 'available for order';
		$xml .= '<entry>'."\n";
		$xml .= '<g:id>'.$product_id.'</g:id>'."\n";
		$xml .= '<g:mpn>'.$product_id.'</g:mpn>'."\n";
		$xml .= '<g:title>'.$product_name.'</g:title>'."\n";		
		$xml .= '<g:link>'.$url.'</g:link>'."\n";
		if ($row->mf_name) {
			$xml .= '<g:brand>'.htmlspecialchars($row->mf_name).'</g:brand>'."\n";			
		} else {
			//$xml .= '<g:brand>'.$product_name.'<g:brand>'."\n";
		}		
		
		if ($row->product_desc) {
			$xml .= '<g:description>'."\n".htmlspecialchars(mb_substr(strip_tags($row->product_desc), 0, 174))."\n".'</g:description>'."\n";
		}
		
		$xml .= getImages($product_id);
		$xml .= '<g:condition> new </g:condition>'."\n";
		$xml .= '<g:availability>'.$available.'</g:availability>'."\n";
		$xml .= '<g:price>'.$prices['salesPrice'].' RUB'.'</g:price>'."\n";
		$xml .= '</entry>'."\n";
	}

$xml .= '</feed>'."\n";

if (FILE) {
	$xml_file = fopen('vm2_market.xml', 'w+');
	
	if (!$xml_file) {
		echo 'Ошибка открытия файла';
	} else {
		ftruncate($xml_file, 0);
		fputs($xml_file, $xml);
		
		echo 'Файл создан, url - <a href="'.$live_site.'market/vm2_market.xml">'.$live_site.'market/vm2_market.xml</a>';
	}
		
	fclose($xml_file);
} else {
	echo $xml;
}
?>
