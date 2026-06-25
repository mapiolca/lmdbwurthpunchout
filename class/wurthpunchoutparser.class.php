<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/wurthpunchoutconfig.class.php';

/**
 * Parse OCI and cXML payloads into a common line structure.
 */
class WurthPunchoutParser
{
	/**
	 * Parse OCI array payload.
	 *
	 * @param array<string,mixed> $payload POST/GET payload
	 * @return array<int,array<string,mixed>>
	 */
	public function parseOci($payload)
	{
		$items = array();

		foreach ($payload as $key => $value) {
			if (!preg_match('/^NEW_ITEM-([A-Z0-9_:-]+)\[(\d+)\]$/', (string) $key, $matches)) {
				continue;
			}

			$field = $matches[1];
			$index = (int) $matches[2];
			if (!isset($items[$index])) {
				$items[$index] = array();
			}
			$items[$index][$field] = is_array($value) ? reset($value) : $value;
		}

		ksort($items);

		$lines = array();
		foreach ($items as $item) {
			$vendorRef = trim((string) ($item['VENDORMAT'] ?? ($item['EXT_PRODUCT_ID'] ?? '')));
			if ($vendorRef === '') {
				continue;
			}

			$price = self::toFloat($item['PRICE'] ?? 0);
			$priceUnit = self::toFloat($item['PRICEUNIT'] ?? 1);
			if ($priceUnit <= 0) {
				$priceUnit = 1;
			}

			$unitPrice = $price;
			if (WurthPunchoutConfig::getString('PRICEUNIT_MODE', 'divide') === 'divide') {
				$unitPrice = $price / $priceUnit;
			}

			$lines[] = array(
				'vendor_ref' => $vendorRef,
				'external_id' => trim((string) ($item['EXT_PRODUCT_ID'] ?? $vendorRef)),
				'label' => trim((string) ($item['DESCRIPTION'] ?? $vendorRef)),
				'description' => trim((string) ($item['LONGTEXT_1:132'] ?? '')),
				'qty' => self::toFloat($item['QUANTITY'] ?? 0),
				'unit' => trim((string) ($item['UNIT'] ?? '')),
				'price' => $price,
				'price_unit' => $priceUnit,
				'unit_price_ht' => $unitPrice,
				'currency' => strtoupper(trim((string) ($item['CURRENCY'] ?? WurthPunchoutConfig::getExpectedCurrency()))),
				'leadtime_days' => (int) self::toFloat($item['LEADTIME'] ?? 0),
				'vat_rate' => WurthPunchoutConfig::getFloat('DEFAULT_VAT', 20.0),
			);
		}

		return $this->aggregateLines($lines);
	}

	/**
	 * Parse cXML payload.
	 *
	 * @param string $xml Raw cXML
	 * @return array<int,array<string,mixed>>
	 */
	public function parseCxml($xml)
	{
		$doc = new DOMDocument();
		$previous = libxml_use_internal_errors(true);
		$loaded = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		if (!$loaded) {
			throw new InvalidArgumentException('Invalid cXML payload');
		}

		$xpath = new DOMXPath($doc);
		$items = $xpath->query('//*[local-name()="ItemIn"]');
		$lines = array();

		foreach ($items as $item) {
			$vendorRef = $this->xpathText($xpath, './/*[local-name()="SupplierPartID"]', $item);
			if ($vendorRef === '') {
				continue;
			}

			$moneyNode = $xpath->query('.//*[local-name()="UnitPrice"]//*[local-name()="Money"]', $item)->item(0);
			$currency = $moneyNode instanceof DOMElement && $moneyNode->hasAttribute('currency') ? strtoupper($moneyNode->getAttribute('currency')) : WurthPunchoutConfig::getExpectedCurrency();
			$price = $moneyNode ? self::toFloat($moneyNode->textContent) : 0.0;
			$shortName = $this->xpathText($xpath, './/*[local-name()="ShortName"]', $item);
			$description = $this->xpathText($xpath, './/*[local-name()="Description"]', $item);
			$taxNode = $xpath->query('.//*[local-name()="TaxDetail"]', $item)->item(0);
			$vatRate = WurthPunchoutConfig::getFloat('DEFAULT_VAT', 20.0);
			if ($taxNode instanceof DOMElement && $taxNode->hasAttribute('percentageRate')) {
				$vatRate = self::toFloat($taxNode->getAttribute('percentageRate'));
			}

			$lines[] = array(
				'vendor_ref' => trim($vendorRef),
				'external_id' => trim($vendorRef),
				'label' => $shortName !== '' ? $shortName : dol_trunc($description, 255),
				'description' => $description,
				'qty' => self::toFloat(($item instanceof DOMElement && $item->hasAttribute('quantity')) ? $item->getAttribute('quantity') : 1),
				'unit' => $this->xpathText($xpath, './/*[local-name()="UnitOfMeasure"]', $item),
				'price' => $price,
				'price_unit' => 1,
				'unit_price_ht' => $price,
				'currency' => $currency,
				'leadtime_days' => 0,
				'vat_rate' => $vatRate,
			);
		}

		return $this->aggregateLines($lines);
	}

	/**
	 * Aggregate identical basket lines.
	 *
	 * @param array<int,array<string,mixed>> $lines Lines
	 * @return array<int,array<string,mixed>>
	 */
	public function aggregateLines($lines)
	{
		$aggregated = array();
		foreach ($lines as $line) {
			$key = implode('|', array(
				(string) $line['vendor_ref'],
				(string) $line['unit'],
				price2num((float) $line['unit_price_ht'], 'MU'),
				price2num((float) $line['vat_rate'], 'MT'),
				(string) $line['currency'],
			));

			if (!isset($aggregated[$key])) {
				$aggregated[$key] = $line;
				continue;
			}

			$aggregated[$key]['qty'] += (float) $line['qty'];
		}

		return array_values($aggregated);
	}

	/**
	 * Convert scalar value to float.
	 *
	 * @param mixed $value Value
	 * @return float
	 */
	public static function toFloat($value)
	{
		$value = trim((string) $value);
		$value = str_replace(array(' ', ','), array('', '.'), $value);
		return is_numeric($value) ? (float) $value : 0.0;
	}

	/**
	 * Read XPath text.
	 *
	 * @param DOMXPath $xpath XPath object
	 * @param string   $query Query
	 * @param DOMNode  $ctx   Context
	 * @return string
	 */
	private function xpathText($xpath, $query, $ctx)
	{
		$node = $xpath->query($query, $ctx)->item(0);
		return $node ? trim($node->textContent) : '';
	}
}
