<?php
/**
 * Lightweight parser examples.
 *
 * Run from a Dolibarr-aware PHP context or with equivalent stubs for helper
 * functions. This file is intentionally simple and does not mutate data.
 */

if (!function_exists('price2num')) {
	function price2num($value, $mode = '')
	{
		return (string) $value;
	}
}
if (!function_exists('dol_trunc')) {
	function dol_trunc($value, $length)
	{
		return substr($value, 0, $length);
	}
}
if (!function_exists('getDolGlobalString')) {
	function getDolGlobalString($key, $default = '')
	{
		$defaults = array(
			'WURTHPUNCHOUT_DEFAULT_VAT' => '20',
			'WURTHPUNCHOUT_CURRENCY' => 'EUR',
			'WURTHPUNCHOUT_PRICEUNIT_MODE' => 'divide',
		);
		return $defaults[$key] ?? $default;
	}
}
if (!function_exists('getDolGlobalInt')) {
	function getDolGlobalInt($key, $default = 0)
	{
		return (int) getDolGlobalString($key, (string) $default);
	}
}

require_once __DIR__.'/../class/wurthpunchoutparser.class.php';

$parser = new WurthPunchoutParser();

$oci = array(
	'NEW_ITEM-QUANTITY[1]' => '100.000',
	'NEW_ITEM-DESCRIPTION[1]' => 'ECR-H-DIN934-I8I-CLE17-(A2K)-M10',
	'NEW_ITEM-UNIT[1]' => 'PCE',
	'NEW_ITEM-PRICE[1]' => '10.000',
	'NEW_ITEM-PRICEUNIT[1]' => '100',
	'NEW_ITEM-CURRENCY[1]' => 'EUR',
	'NEW_ITEM-LEADTIME[1]' => '1',
	'NEW_ITEM-VENDORMAT[1]' => '031710    005  100',
	'NEW_ITEM-EXT_PRODUCT_ID[1]' => '031710    005  100',
);

$ociLines = $parser->parseOci($oci);
if (count($ociLines) !== 1 || abs($ociLines[0]['unit_price_ht'] - 0.1) > 0.000001) {
	throw new RuntimeException('OCI parser test failed');
}

$cxml = '<?xml version="1.0"?><cXML><Message><PunchOutOrderMessage><PunchOutOrderMessageHeader><Total><Money currency="EUR">0</Money></Total></PunchOutOrderMessageHeader><ItemIn quantity="2"><ItemID><SupplierPartID>0890108715063</SupplierPartID></ItemID><ItemDetail><UnitPrice><Money currency="EUR">3.50</Money></UnitPrice><Description xml:lang="fr"><ShortName>Nettoyant freins</ShortName>BIDON NETTOYANT FREINS 5 L</Description><UnitOfMeasure>EA</UnitOfMeasure><Classification domain="UNSPSC">47132101</Classification></ItemDetail><Tax><TaxDetail category="FullTax" percentageRate="20.000"></TaxDetail></Tax></ItemIn></PunchOutOrderMessage></Message></cXML>';
$cxmlLines = $parser->parseCxml($cxml);
if (count($cxmlLines) !== 1 || $cxmlLines[0]['vendor_ref'] !== '0890108715063' || abs($cxmlLines[0]['unit_price_ht'] - 3.5) > 0.000001) {
	throw new RuntimeException('cXML parser test failed');
}

echo "Parser examples OK\n";
