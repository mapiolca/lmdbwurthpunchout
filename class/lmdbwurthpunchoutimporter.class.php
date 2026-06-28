<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once __DIR__.'/lmdbwurthpunchoutconfig.class.php';
require_once __DIR__.'/lmdbwurthpunchoutparser.class.php';
require_once __DIR__.'/lmdbwurthpunchoutsecurity.class.php';
require_once __DIR__.'/lmdbwurthpunchoutsession.class.php';

/**
 * Import normalized Punchout lines into Dolibarr.
 */
class LmdbWurthPunchoutImporter
{
	/** @var DoliDB */
	private $db;

	/** @var array<int,string> */
	public $warnings = array();

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Import a stored returned session transactionally.
	 *
	 * @param LmdbWurthPunchoutSession $session Session
	 * @param User                     $user    User
	 * @return array<string,mixed>
	 */
	public function importStoredSession($session, $user)
	{
		if ($session->status !== LmdbWurthPunchoutSession::STATUS_RETURNED) {
			throw new RuntimeException('Punchout session is not importable');
		}

		$this->db->begin();
		try {
			$summary = $this->importSession($session, $user);
			if ($session->markImported($summary) < 0) {
				throw new RuntimeException($session->error);
			}
			$this->db->commit();

			return $summary;
		} catch (Exception $e) {
			$this->db->rollback();
			throw $e;
		}
	}

	/**
	 * Import a returned session.
	 *
	 * @param LmdbWurthPunchoutSession $session Session
	 * @param User                 $user    User
	 * @return array<string,mixed>
	 */
	public function importSession($session, $user)
	{
		global $conf;

		$summary = array(
			'lines_added' => 0,
			'shipping_lines_added' => 0,
			'shipping_detected' => false,
			'shipping_amount' => 0.0,
			'shipping_currency' => '',
			'shipping_inferred_from_tax_delta' => false,
			'shipping_tax_delta' => 0.0,
			'shipping_skipped_reason' => '',
			'rep_lines_added' => 0,
			'rep_amount' => 0.0,
			'rep_tax_amount' => 0.0,
			'rep_tax_delta' => 0.0,
			'rep_source' => '',
			'rep_rule_matches' => 0,
			'rep_unmatched_refs' => array(),
			'rep_skipped_reason' => '',
			'products_created' => 0,
			'supplier_prices_updated' => 0,
			'warnings' => array(),
		);

		$order = new CommandeFournisseur($this->db);
		if ($order->fetch((int) $session->fk_commandefourn) <= 0) {
			throw new RuntimeException('Supplier order not found');
		}
		$order->fetch_thirdparty();

		if ((int) $order->entity !== (int) $conf->entity) {
			throw new RuntimeException('Supplier order belongs to another entity');
		}
		if ((int) $order->socid !== (int) $session->fk_soc || (int) $session->fk_soc !== LmdbWurthPunchoutConfig::getInt('FK_SOC')) {
			throw new RuntimeException('Supplier order does not match configured WURTH supplier');
		}
		if ((int) $order->statut !== CommandeFournisseur::STATUS_DRAFT) {
			throw new RuntimeException('Supplier order is not draft');
		}

		$supplier = new Societe($this->db);
		if ($supplier->fetch((int) $session->fk_soc) <= 0) {
			throw new RuntimeException('WURTH supplier not found');
		}

		$lines = $session->fetchLines();
		if (empty($lines)) {
			throw new RuntimeException('No Punchout line to import');
		}

		foreach ($lines as $line) {
			$this->validateLine($line);
			$unitId = $this->findUnit((string) $line['unit_code'], (int) $session->entity);
			$warning = '';
			if (!empty($line['unit_code']) && $unitId <= 0) {
				$warning = 'UnitNotMapped';
				$summary['warnings'][] = $line['vendor_ref'].': UnitNotMapped';
			}

			$productId = $this->findProductBySupplierRef((int) $session->fk_soc, (string) $line['vendor_ref']);
			if ($productId <= 0) {
				$productId = $this->findProductByGeneratedRef((string) $line['vendor_ref']);
			}
			if ($productId <= 0) {
				if (!LmdbWurthPunchoutConfig::getInt('CREATE_PRODUCTS', 1)) {
					throw new RuntimeException('Product not found and product creation is disabled: '.$line['vendor_ref']);
				}
				$productId = $this->createProduct($line, $user);
				$summary['products_created']++;
			}

			$supplierPriceId = $this->upsertSupplierPrice($productId, $supplier, $line, $user);
			$summary['supplier_prices_updated']++;

			$description = $this->buildLineDescription($line);
			$result = $order->addline(
				$description,
				(float) $line['unit_price_ht'],
				(float) $line['qty'],
				(float) $line['vat_rate'],
				0,
				0,
				$productId,
				$supplierPriceId,
				(string) $line['vendor_ref'],
				0,
				'HT',
				0,
				0,
				0,
				0,
				null,
				null,
				array(),
				$unitId > 0 ? $unitId : null
			);

			if ($result <= 0) {
				throw new RuntimeException($order->error ?: 'Unable to add supplier order line');
			}

			if ($session->updateLineImport((int) $line['rowid'], $productId, $supplierPriceId, (int) $result, $unitId, $warning) < 0) {
				throw new RuntimeException($session->error ?: 'Unable to update Punchout import line');
			}

			$summary['lines_added']++;
		}

		$this->importCxmlAdditionalLines($session, $order, $lines, $summary);

		$order->update_price(1, 'auto', 0, $order->thirdparty);

		return $summary;
	}

	/**
	 * Validate normalized line.
	 *
	 * @param array<string,mixed> $line Line
	 * @return void
	 */
	private function validateLine($line)
	{
		if ((string) $line['vendor_ref'] === '') {
			throw new RuntimeException('Missing WURTH supplier reference');
		}
		if ((float) $line['qty'] <= 0) {
			throw new RuntimeException('Invalid quantity for '.$line['vendor_ref']);
		}
		if ((float) $line['unit_price_ht'] <= 0 && !LmdbWurthPunchoutConfig::getInt('ALLOW_ZERO_PRICE', 0)) {
			throw new RuntimeException('Zero price refused for '.$line['vendor_ref']);
		}
		if (strtoupper((string) $line['currency']) !== LmdbWurthPunchoutConfig::getExpectedCurrency()) {
			throw new RuntimeException('Unexpected currency for '.$line['vendor_ref']);
		}
	}

	/**
	 * Find unit mapping.
	 *
	 * @param string $unitCode Unit code
	 * @param int    $entity   Entity
	 * @return int
	 */
	private function findUnit($unitCode, $entity)
	{
		if ($unitCode === '') {
			return 0;
		}

		$sql = 'SELECT fk_unit FROM '.MAIN_DB_PREFIX.'lmdbwurthpunchout_unitmap';
		$sql .= " WHERE wurth_unit = '".$this->db->escape($unitCode)."'";
		$sql .= ' AND entity IN ('.((int) $entity).', 1)';
		$sql .= ' ORDER BY entity DESC';
		$resql = $this->db->query($sql);
		if (!$resql) {
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		return $obj && $obj->fk_unit > 0 ? (int) $obj->fk_unit : 0;
	}

	/**
	 * Find product by supplier reference.
	 *
	 * @param int    $supplierId Supplier id
	 * @param string $vendorRef  Supplier reference
	 * @return int
	 */
	private function findProductBySupplierRef($supplierId, $vendorRef)
	{
		$sql = 'SELECT pfp.fk_product';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'product_fournisseur_price AS pfp';
		$sql .= ' WHERE pfp.entity IN ('.getEntity('productsupplierprice').')';
		$sql .= ' AND pfp.fk_soc = '.((int) $supplierId);
		$sql .= " AND pfp.ref_fourn = '".$this->db->escape($vendorRef)."'";
		$sql .= ' ORDER BY pfp.rowid DESC';
		$sql .= $this->db->plimit(1);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		return $obj ? (int) $obj->fk_product : 0;
	}

	/**
	 * Find product by generated Dolibarr reference.
	 *
	 * @param string $vendorRef Supplier reference
	 * @return int
	 */
	private function findProductByGeneratedRef($vendorRef)
	{
		$product = new Product($this->db);
		$result = $product->fetch(0, $this->buildProductRef($vendorRef));
		return $result > 0 ? (int) $product->id : 0;
	}

	/**
	 * Create product.
	 *
	 * @param array<string,mixed> $line Line
	 * @param User                $user User
	 * @return int
	 */
	private function createProduct($line, $user)
	{
		global $conf;

		$product = new Product($this->db);
		$product->ref = $this->buildProductRef((string) $line['vendor_ref']);
		$product->label = dol_trunc((string) $line['label'], 255);
		$product->description = (string) ($line['description'] ?: $line['label']);
		$product->type = 0;
		$product->status = 0;
		$product->status_buy = 1;
		$product->entity = (int) $conf->entity;

		$result = $product->create($user);
		if ($result <= 0) {
			throw new RuntimeException($product->error ?: 'Unable to create product');
		}

		return (int) $product->id;
	}

	/**
	 * Create/update supplier price.
	 *
	 * @param int                 $productId Product id
	 * @param Societe             $supplier  Supplier
	 * @param array<string,mixed> $line      Line
	 * @param User                $user      User
	 * @return int
	 */
	private function upsertSupplierPrice($productId, $supplier, $line, $user)
	{
		$productFourn = new ProductFournisseur($this->db);
		if ($productFourn->fetch($productId) <= 0) {
			throw new RuntimeException('Unable to load product supplier price object');
		}

		$qtyForSupplierPrice = max(1, (float) ($line['price_unit'] ?? 1));
		$priceForSupplierQty = (float) ($line['price'] ?? $line['unit_price_ht']);
		if (LmdbWurthPunchoutConfig::getString('PRICEUNIT_MODE', 'divide') !== 'divide') {
			$qtyForSupplierPrice = 1;
			$priceForSupplierQty = (float) $line['unit_price_ht'];
		}

		$result = $productFourn->update_buyprice(
			$qtyForSupplierPrice,
			$priceForSupplierQty,
			$user,
			'HT',
			$supplier,
			0,
			(string) $line['vendor_ref'],
			(float) $line['vat_rate'],
			0,
			0,
			0,
			0,
			(int) $line['leadtime_days'],
			'',
			array(),
			'',
			0,
			'HT',
			1,
			(string) $line['currency'],
			(string) ($line['description'] ?: $line['label'])
		);

		if ($result < 0) {
			throw new RuntimeException($productFourn->error ?: 'Unable to update supplier price');
		}

		return $this->findSupplierPriceId($productId, (int) $supplier->id, (string) $line['vendor_ref'], $qtyForSupplierPrice);
	}

	/**
	 * Find supplier price row id.
	 *
	 * @param int    $productId Product id
	 * @param int    $supplierId Supplier id
	 * @param string $vendorRef Supplier ref
	 * @param float  $qty       Min quantity
	 * @return int
	 */
	private function findSupplierPriceId($productId, $supplierId, $vendorRef, $qty)
	{
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'product_fournisseur_price';
		$sql .= ' WHERE entity IN ('.getEntity('productsupplierprice').')';
		$sql .= ' AND fk_product = '.((int) $productId);
		$sql .= ' AND fk_soc = '.((int) $supplierId);
		$sql .= " AND ref_fourn = '".$this->db->escape($vendorRef)."'";
		$sql .= ' AND quantity = '.price2num($qty, 'MS');
		$sql .= ' ORDER BY rowid DESC';
		$sql .= $this->db->plimit(1);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		return $obj ? (int) $obj->rowid : 0;
	}

	/**
	 * Build product reference.
	 *
	 * @param string $vendorRef Supplier ref
	 * @return string
	 */
	private function buildProductRef($vendorRef)
	{
		return LmdbWurthPunchoutConfig::getString('PRODUCT_REF_PREFIX', 'WURTH-').LmdbWurthPunchoutSecurity::normalizeSupplierReference($vendorRef);
	}

	/**
	 * Build supplier order line description.
	 *
	 * @param array<string,mixed> $line Line
	 * @return string
	 */
	private function buildLineDescription($line)
	{
		$description = (string) ($line['label'] ?: $line['vendor_ref']);
		if (!empty($line['description']) && $line['description'] !== $description) {
			$description .= "\n".$line['description'];
		}

		return $description;
	}

	/**
	 * Add cXML additional fees as supplier order lines when requested.
	 *
	 * @param LmdbWurthPunchoutSession  $session Session
	 * @param CommandeFournisseur       $order   Supplier order
	 * @param array<int,array<string,mixed>> $lines Normalized article lines
	 * @param array<string,mixed>       $summary Import summary
	 * @return void
	 */
	private function importCxmlAdditionalLines($session, $order, $lines, &$summary)
	{
		if (strtoupper((string) $session->protocol) !== 'CXML') {
			return;
		}

		$basket = $this->decodeBasketPayload($session);
		$shipping = isset($basket['header']) && is_array($basket['header']) && isset($basket['header']['shipping']) && is_array($basket['header']['shipping']) ? $basket['header']['shipping'] : array();
		$shippingDetected = !empty($shipping['has_value']) || array_key_exists('amount', $shipping) || array_key_exists('currency', $shipping);
		if (!$shippingDetected) {
			$summary['shipping_skipped_reason'] = 'not_present';
			return;
		}

		$shippingAmount = (float) ($shipping['amount'] ?? 0);
		$expectedCurrency = LmdbWurthPunchoutConfig::getExpectedCurrency();
		$shippingCurrency = strtoupper((string) ($shipping['currency'] ?? $expectedCurrency));
		$shippingVatRate = $this->getShippingVatRate($lines);
		$repAmount = 0.0;
		$repVatRate = $this->getRepVatRate($lines);

		$summary['shipping_detected'] = true;
		$summary['shipping_amount'] = $shippingAmount;
		$summary['shipping_currency'] = $shippingCurrency;

		if ($shippingAmount <= 0) {
			$additionalFees = $this->inferCxmlAdditionalFeesFromTaxDelta($basket, $lines, $shippingVatRate, $repVatRate, (int) $session->entity, $summary);
			$inferredShippingAmount = (float) $additionalFees['shipping_amount'];
			$repAmount = (float) $additionalFees['rep_amount'];
			if ($inferredShippingAmount <= 0) {
				$summary['shipping_skipped_reason'] = 'zero_amount';
			}

			$shippingAmount = $inferredShippingAmount;
			$summary['shipping_amount'] = $shippingAmount;
			if ($shippingAmount > 0) {
				$summary['shipping_inferred_from_tax_delta'] = true;
			}
		}

		if ($shippingCurrency !== $expectedCurrency) {
			throw new RuntimeException('Unexpected cXML shipping currency: '.$shippingCurrency.' (expected '.$expectedCurrency.')');
		}

		if ($shippingAmount > 0 && !LmdbWurthPunchoutConfig::getInt('CXML_IMPORT_SHIPPING', 1)) {
			$summary['shipping_skipped_reason'] = 'disabled';
			$summary['warnings'][] = $this->trans('LmdbWurthPunchoutShippingImportDisabled', 'cXML shipping fees were not imported because the option is disabled');
			$shippingAmount = 0.0;
		}

		if ($shippingAmount > 0) {
			$result = $this->addCxmlChargeLine(
				$order,
				$this->buildShippingDescription($shipping),
				$shippingAmount,
				$shippingVatRate,
				LmdbWurthPunchoutConfig::getInt('CXML_SHIPPING_FK_PRODUCT'),
				'Configured cXML shipping product/service not found: #',
				'Unable to add cXML shipping supplier order line'
			);

			$summary['lines_added']++;
			$summary['shipping_lines_added'] = 1;
			$summary['shipping_order_line_id'] = (int) $result;
			$summary['shipping_skipped_reason'] = '';
		}

		if ($repAmount > 0) {
			$this->importCxmlRepLine($order, $repAmount, $repVatRate, $summary);
		}
	}

	/**
	 * Add a cXML REP fee line.
	 *
	 * @param CommandeFournisseur  $order      Supplier order
	 * @param float                $repAmount  REP amount without tax
	 * @param float                $repVatRate REP VAT rate
	 * @param array<string,mixed>  $summary    Import summary
	 * @return void
	 */
	private function importCxmlRepLine($order, $repAmount, $repVatRate, &$summary)
	{
		$summary['rep_amount'] = $repAmount;
		$summary['rep_tax_amount'] = $this->calculateVatAmount($repAmount, $repVatRate);

		if (!LmdbWurthPunchoutConfig::getInt('CXML_IMPORT_REP', 1)) {
			$summary['rep_skipped_reason'] = 'disabled';
			return;
		}

		$result = $this->addCxmlChargeLine(
			$order,
			$this->trans('LmdbWurthPunchoutRepLineLabel', 'REP Taxe n/w'),
			$repAmount,
			$repVatRate,
			LmdbWurthPunchoutConfig::getInt('CXML_REP_FK_PRODUCT'),
			'Configured cXML REP product/service not found: #',
			'Unable to add cXML REP supplier order line'
		);

		$summary['lines_added']++;
		$summary['rep_lines_added'] = 1;
		$summary['rep_order_line_id'] = (int) $result;
		$summary['rep_skipped_reason'] = '';
	}

	/**
	 * Add a supplier order charge line.
	 *
	 * @param CommandeFournisseur $order                 Supplier order
	 * @param string              $description           Line description
	 * @param float               $amount                Amount without tax
	 * @param float               $vatRate               VAT rate
	 * @param int                 $productId             Optional product/service id
	 * @param string              $missingProductMessage Missing product error prefix
	 * @param string              $addlineErrorMessage   Addline error fallback
	 * @return int
	 */
	private function addCxmlChargeLine($order, $description, $amount, $vatRate, $productId, $missingProductMessage, $addlineErrorMessage)
	{
		if ($productId > 0) {
			$product = new Product($this->db);
			if ($product->fetch($productId) <= 0) {
				throw new RuntimeException($missingProductMessage.$productId);
			}
		}

		$result = $order->addline(
			$description,
			$amount,
			1,
			$vatRate,
			0,
			0,
			$productId > 0 ? $productId : 0,
			0,
			'',
			0,
			'HT',
			0,
			0,
			0,
			0,
			null,
			null,
			array(),
			null
		);

		if ($result <= 0) {
			throw new RuntimeException($order->error ?: $addlineErrorMessage);
		}

		return (int) $result;
	}

	/**
	 * Decode the stored cXML basket metadata.
	 *
	 * @param LmdbWurthPunchoutSession $session Session
	 * @return array<string,mixed>
	 */
	private function decodeBasketPayload($session)
	{
		$json = (string) ($session->basket_payload ?? '');
		if ($json === '') {
			return array();
		}

		$payload = json_decode($json, true);
		return is_array($payload) ? $payload : array();
	}

	/**
	 * Build the supplier order description for shipping fees.
	 *
	 * @param array<string,mixed> $shipping Shipping metadata
	 * @return string
	 */
	private function buildShippingDescription($shipping)
	{
		$label = $this->trans('LmdbWurthPunchoutShippingLineLabel', 'Frais de port WURTH');
		$description = trim((string) ($shipping['description'] ?? ''));
		if ($description !== '' && $description !== $label) {
			$label .= "\n".$description;
		}

		return $label;
	}

	/**
	 * Infer missing WURTH cXML additional fees from the header tax delta.
	 *
	 * Some WURTH cXML returns send Shipping/Money at 0 while the header tax
	 * includes VAT for ancillary fees displayed in the WURTH basket. The inferred
	 * amounts remain normal Dolibarr supplier order lines; order totals are not
	 * copied from cXML.
	 *
	 * @param array<string,mixed>            $basket          Basket metadata
	 * @param array<int,array<string,mixed>> $lines           Normalized article lines
	 * @param float                          $shippingVatRate Shipping VAT rate
	 * @param float                          $repVatRate      REP VAT rate
	 * @param int                            $entity          Entity
	 * @param array<string,mixed>            $summary         Import summary
	 * @return array{shipping_amount:float,rep_amount:float}
	 */
	private function inferCxmlAdditionalFeesFromTaxDelta($basket, $lines, $shippingVatRate, $repVatRate, $entity, &$summary)
	{
		$emptyFees = array('shipping_amount' => 0.0, 'rep_amount' => 0.0);

		if (!LmdbWurthPunchoutConfig::getInt('CXML_INFER_SHIPPING_FROM_TAX_DELTA', 1)) {
			return $emptyFees;
		}
		if ($shippingVatRate <= 0) {
			return $emptyFees;
		}
		if (!isset($basket['header']) || !is_array($basket['header']) || !isset($basket['header']['tax']) || !is_array($basket['header']['tax'])) {
			return $emptyFees;
		}

		$headerTax = $basket['header']['tax'];
		if (empty($headerTax['has_value'])) {
			return $emptyFees;
		}

		$headerTaxAmount = (float) ($headerTax['amount'] ?? 0);
		$lineTaxAmount = 0.0;
		foreach ($lines as $line) {
			$lineTaxAmount += (float) ($line['tax_amount'] ?? 0);
		}

		$taxDelta = round($headerTaxAmount - $lineTaxAmount, 6);
		$summary['shipping_tax_delta'] = $taxDelta;
		$summary['rep_tax_delta'] = $taxDelta;
		if ($taxDelta <= 0.01) {
			return $emptyFees;
		}

		$repAmount = $this->getRepAmountForTaxDelta($lines, $entity, $summary);
		$repTaxAmount = 0.0;
		if ($repAmount > 0 && LmdbWurthPunchoutConfig::getInt('CXML_IMPORT_REP', 1)) {
			$repTaxAmount = $this->calculateVatAmount($repAmount, $repVatRate);
			if (empty($summary['rep_source'])) {
				$summary['rep_source'] = 'tax_delta';
			}
			$summary['rep_amount'] = $repAmount;
			$summary['rep_tax_amount'] = $repTaxAmount;
		} elseif ($repAmount > 0) {
			if (empty($summary['rep_source'])) {
				$summary['rep_source'] = 'tax_delta';
			}
			$summary['rep_amount'] = $repAmount;
			$summary['rep_tax_amount'] = $this->calculateVatAmount($repAmount, $repVatRate);
			$summary['rep_skipped_reason'] = 'disabled';
			$repAmount = 0.0;
		} else {
			$summary['rep_skipped_reason'] = 'zero_amount';
		}

		$shippingTaxDelta = round($taxDelta - $repTaxAmount, 6);
		$shippingAmount = $shippingTaxDelta > 0.01 ? round($shippingTaxDelta * 100 / $shippingVatRate, 2) : 0.0;

		return array(
			'shipping_amount' => $shippingAmount,
			'rep_amount' => $repAmount,
		);
	}

	/**
	 * Resolve VAT rate for cXML shipping fees.
	 *
	 * @param array<int,array<string,mixed>> $lines Normalized article lines
	 * @return float
	 */
	private function getShippingVatRate($lines)
	{
		$configuredVat = trim(LmdbWurthPunchoutConfig::getString('CXML_SHIPPING_VAT_RATE'));
		if ($configuredVat !== '') {
			return LmdbWurthPunchoutParser::toFloat($configuredVat);
		}

		return $this->getCommonVatRate($lines);
	}

	/**
	 * Resolve VAT rate for REP fees.
	 *
	 * @param array<int,array<string,mixed>> $lines Normalized article lines
	 * @return float
	 */
	private function getRepVatRate($lines)
	{
		$configuredVat = trim(LmdbWurthPunchoutConfig::getString('CXML_REP_VAT_RATE'));
		if ($configuredVat !== '') {
			return LmdbWurthPunchoutParser::toFloat($configuredVat);
		}

		return $this->getCommonVatRate($lines);
	}

	/**
	 * Resolve the common line VAT rate, or the default VAT when lines differ.
	 *
	 * @param array<int,array<string,mixed>> $lines Normalized article lines
	 * @return float
	 */
	private function getCommonVatRate($lines)
	{
		$commonVat = null;
		foreach ($lines as $line) {
			if (!isset($line['vat_rate'])) {
				continue;
			}
			$vatRate = (float) $line['vat_rate'];
			if ($commonVat === null) {
				$commonVat = $vatRate;
				continue;
			}
			if (abs($commonVat - $vatRate) > 0.000001) {
				return LmdbWurthPunchoutConfig::getFloat('DEFAULT_VAT', 20.0);
			}
		}

		return $commonVat !== null ? $commonVat : LmdbWurthPunchoutConfig::getFloat('DEFAULT_VAT', 20.0);
	}

	/**
	 * Return REP amount for WURTH tax-delta fallback.
	 *
	 * @param array<int,array<string,mixed>> $lines   Normalized article lines
	 * @param int                            $entity  Entity
	 * @param array<string,mixed>            $summary Import summary
	 * @return float
	 */
	private function getRepAmountForTaxDelta($lines, $entity, &$summary)
	{
		$mappedAmount = $this->getRepAmountFromRules($lines, $entity, $summary);
		if ($mappedAmount > 0) {
			return $mappedAmount;
		}

		$this->addRepMissingRuleWarning($summary);
		return 0.0;
	}

	/**
	 * Add an import warning when cXML REP cannot be mapped.
	 *
	 * @param array<string,mixed> $summary Import summary
	 * @return void
	 */
	private function addRepMissingRuleWarning(&$summary)
	{
		if (empty($summary['rep_unmatched_refs']) || !is_array($summary['rep_unmatched_refs'])) {
			return;
		}

		$refs = array_values(array_unique(array_map('strval', $summary['rep_unmatched_refs'])));
		if (empty($refs)) {
			return;
		}

		$summary['warnings'][] = sprintf(
			$this->trans('LmdbWurthPunchoutRepRuleMissingWarning', 'cXML REP was not imported because no REP rule matches: %s'),
			implode(', ', $refs)
		);
	}

	/**
	 * Sum REP amounts from configured supplier-reference rules.
	 *
	 * @param array<int,array<string,mixed>> $lines   Normalized article lines
	 * @param int                            $entity  Entity
	 * @param array<string,mixed>            $summary Import summary
	 * @return float
	 */
	private function getRepAmountFromRules($lines, $entity, &$summary)
	{
		$total = 0.0;
		$matches = 0;

		foreach ($lines as $line) {
			$vendorRef = trim((string) ($line['vendor_ref'] ?? ''));
			if ($vendorRef === '') {
				continue;
			}

			$amountPerUnit = $this->findRepAmountPerUnit($vendorRef, $entity);
			if ($amountPerUnit <= 0) {
				$summary['rep_unmatched_refs'][] = $vendorRef;
				continue;
			}

			$total += $amountPerUnit * max(0.0, (float) ($line['qty'] ?? 0));
			$matches++;
		}

		$summary['rep_rule_matches'] = $matches;
		if ($total > 0) {
			$summary['rep_source'] = 'product_rule_tax_delta';
		}

		return round($total, 2);
	}

	/**
	 * Find REP amount without tax per unit for a WURTH supplier reference.
	 *
	 * @param string $vendorRef Supplier reference
	 * @param int    $entity    Entity
	 * @return float
	 */
	private function findRepAmountPerUnit($vendorRef, $entity)
	{
		$sql = 'SELECT entity, vendor_ref, amount_ht';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'lmdbwurthpunchout_repmap';
		$sql .= ' WHERE entity IN ('.((int) $entity).', 1)';
		$sql .= ' ORDER BY entity DESC, LENGTH(vendor_ref) DESC';

		$resql = $this->db->query($sql);
		if (!$resql) {
			if (function_exists('dol_syslog')) {
				dol_syslog('LmdbWurthPunchout REP mapping SQL error: '.$this->db->lasterror(), LOG_ERR);
			}
			return 0.0;
		}

		$incomingRef = trim($vendorRef);
		$incomingNormalizedRef = $this->normalizeRepReference($incomingRef);
		$bestAmount = 0.0;
		$bestSpecificity = 0;

		while ($obj = $this->db->fetch_object($resql)) {
			$ruleRef = trim((string) $obj->vendor_ref);
			$ruleNormalizedRef = $this->normalizeRepReference($ruleRef);
			if ($ruleRef === '' || $ruleNormalizedRef === '') {
				continue;
			}

			$specificity = 0;
			if ($ruleRef === $incomingRef) {
				$specificity = 100000 + strlen($ruleNormalizedRef);
			} elseif ($ruleNormalizedRef === $incomingNormalizedRef) {
				$specificity = 50000 + strlen($ruleNormalizedRef);
			} elseif (strlen($ruleNormalizedRef) >= 8 && strpos($incomingNormalizedRef, $ruleNormalizedRef) === 0) {
				$specificity = strlen($ruleNormalizedRef);
			}

			if ($specificity > $bestSpecificity) {
				$bestSpecificity = $specificity;
				$bestAmount = max(0.0, (float) $obj->amount_ht);
			}
		}

		return $bestAmount;
	}

	/**
	 * Normalize a WURTH reference for REP rule matching.
	 *
	 * @param string $reference Reference
	 * @return string
	 */
	private function normalizeRepReference($reference)
	{
		return LmdbWurthPunchoutSecurity::normalizeSupplierReference($reference);
	}

	/**
	 * Calculate VAT amount rounded as a line-level tax amount.
	 *
	 * @param float $amount  Amount without tax
	 * @param float $vatRate VAT rate
	 * @return float
	 */
	private function calculateVatAmount($amount, $vatRate)
	{
		return round($amount * $vatRate / 100, 2);
	}

	/**
	 * Translate a label with a stable fallback for public callbacks.
	 *
	 * @param string $key      Translation key
	 * @param string $fallback Fallback value
	 * @return string
	 */
	private function trans($key, $fallback)
	{
		global $langs;

		if (is_object($langs)) {
			$value = $langs->transnoentitiesnoconv($key);
			if ($value !== $key) {
				return $value;
			}
		}

		return $fallback;
	}
}
