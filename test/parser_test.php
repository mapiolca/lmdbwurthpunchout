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
			'LMDBWURTHPUNCHOUT_DEFAULT_VAT' => '20',
			'LMDBWURTHPUNCHOUT_CURRENCY' => 'EUR',
			'LMDBWURTHPUNCHOUT_PRICEUNIT_MODE' => 'divide',
			'LMDBWURTHPUNCHOUT_CXML_CUSTOMER_DOMAIN' => 'NetworkID',
			'LMDBWURTHPUNCHOUT_CXML_CUSTOMER_IDENTITY' => 'buyer',
			'LMDBWURTHPUNCHOUT_CXML_SUPPLIER_DOMAIN' => 'DUNS',
			'LMDBWURTHPUNCHOUT_CXML_SUPPLIER_IDENTITY' => 'supplier',
			'LMDBWURTHPUNCHOUT_CXML_SHARED_SECRET' => 'secret',
			'LMDBWURTHPUNCHOUT_CXML_URL' => 'https://example.invalid/cxml',
			'LMDBWURTHPUNCHOUT_CXML_MODE' => 'test',
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

require_once __DIR__.'/../class/lmdbwurthpunchoutparser.class.php';
require_once __DIR__.'/../class/lmdbwurthpunchoutcxmlclient.class.php';
require_once __DIR__.'/../class/lmdbwurthpunchoutcxmlpayload.class.php';

$parser = new LmdbWurthPunchoutParser();

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

$ociPhpPostShape = array(
	'NEW_ITEM-QUANTITY' => array(1 => '100.000'),
	'NEW_ITEM-DESCRIPTION' => array(1 => 'ECR-H-DIN934-I8I-CLE17-(A2K)-M10'),
	'NEW_ITEM-UNIT' => array(1 => 'PCE'),
	'NEW_ITEM-PRICE' => array(1 => '10.000'),
	'NEW_ITEM-PRICEUNIT' => array(1 => '100'),
	'NEW_ITEM-CURRENCY' => array(1 => 'EUR'),
	'NEW_ITEM-LEADTIME' => array(1 => '1'),
	'NEW_ITEM-VENDORMAT' => array(1 => '031710    005  100'),
	'NEW_ITEM-EXT_PRODUCT_ID' => array(1 => '031710    005  100'),
);

$ociPhpPostShapeLines = $parser->parseOci($ociPhpPostShape);
if (count($ociPhpPostShapeLines) !== 1 || $ociPhpPostShapeLines[0]['vendor_ref'] !== '031710    005  100') {
	throw new RuntimeException('OCI parser PHP POST shape test failed');
}

$cxml = '<?xml version="1.0"?><cXML><Message><PunchOutOrderMessage><PunchOutOrderMessageHeader><Total><Money currency="EUR">0</Money></Total></PunchOutOrderMessageHeader><ItemIn quantity="2"><ItemID><SupplierPartID>0890108715063</SupplierPartID></ItemID><ItemDetail><UnitPrice><Money currency="EUR">3.50</Money></UnitPrice><Description xml:lang="fr"><ShortName>Nettoyant freins</ShortName>BIDON NETTOYANT FREINS 5 L</Description><UnitOfMeasure>EA</UnitOfMeasure><Classification domain="UNSPSC">47132101</Classification></ItemDetail><Tax><TaxDetail category="FullTax" percentageRate="20.000"></TaxDetail></Tax></ItemIn></PunchOutOrderMessage></Message></cXML>';
$cxmlLines = $parser->parseCxml($cxml);
if (count($cxmlLines) !== 1 || $cxmlLines[0]['vendor_ref'] !== '0890108715063' || abs($cxmlLines[0]['unit_price_ht'] - 3.5) > 0.000001) {
	throw new RuntimeException('cXML parser test failed');
}

$client = new LmdbWurthPunchoutCxmlClient();
$setupXml = $client->buildSetupRequest('https://dolibarr.example/custom/lmdbwurthpunchout/public/return_cxml.php?entity=1&token=test', 'buyer-cookie');
$setupDoc = new DOMDocument();
if (!$setupDoc->loadXML($setupXml)) {
	throw new RuntimeException('cXML setup request is not valid XML');
}
$setupXPath = new DOMXPath($setupDoc);
if ($setupXPath->query('/cXML/Header/Sender/Credential/SharedSecret')->length !== 1) {
	throw new RuntimeException('cXML setup request missing Sender Credential SharedSecret');
}
if ($setupXPath->query('/cXML/Header/Sender/SharedSecret')->length !== 0) {
	throw new RuntimeException('cXML setup request has SharedSecret at Sender level');
}

$rejectedSetupXml = '<?xml version="1.0"?><cXML><Response><Status code="401" text="Unauthorized"/></Response></cXML>';
try {
	$client->parseStartPageUrl($rejectedSetupXml, 200, 'text/xml', 'https://example.invalid/cxml');
	throw new RuntimeException('cXML rejected setup response test failed');
} catch (RuntimeException $exception) {
	if (strpos($exception->getMessage(), 'cXML setup rejected: 401 Unauthorized') === false) {
		throw $exception;
	}
}

$urlencodedPayload = LmdbWurthPunchoutCxmlPayload::extract(array('cXML-urlencoded' => $cxml));
if ($urlencodedPayload !== $cxml) {
	throw new RuntimeException('cXML-urlencoded return extraction failed');
}

$base64Payload = LmdbWurthPunchoutCxmlPayload::extract(array('CXML-BASE64' => base64_encode($cxml)));
if ($base64Payload !== $cxml) {
	throw new RuntimeException('cXML-base64 return extraction failed');
}

$base64FirstPayload = LmdbWurthPunchoutCxmlPayload::extract(array('cXML-urlencoded' => '<invalid/>', 'cXML-base64' => base64_encode($cxml)));
if ($base64FirstPayload !== $cxml) {
	throw new RuntimeException('cXML-base64 return extraction priority failed');
}

echo "Parser examples OK\n";
