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
			'shipping_skipped_reason' => '',
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

		$this->importCxmlShippingLine($session, $order, $lines, $summary);

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
	 * Add cXML shipping fees as a supplier order line when requested.
	 *
	 * @param LmdbWurthPunchoutSession  $session Session
	 * @param CommandeFournisseur       $order   Supplier order
	 * @param array<int,array<string,mixed>> $lines Normalized article lines
	 * @param array<string,mixed>       $summary Import summary
	 * @return void
	 */
	private function importCxmlShippingLine($session, $order, $lines, &$summary)
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

		$summary['shipping_detected'] = true;
		$summary['shipping_amount'] = $shippingAmount;
		$summary['shipping_currency'] = $shippingCurrency;

		if ($shippingAmount <= 0) {
			$summary['shipping_skipped_reason'] = 'zero_amount';
			return;
		}

		if ($shippingCurrency !== $expectedCurrency) {
			throw new RuntimeException('Unexpected cXML shipping currency: '.$shippingCurrency.' (expected '.$expectedCurrency.')');
		}

		if (!LmdbWurthPunchoutConfig::getInt('CXML_IMPORT_SHIPPING', 1)) {
			$summary['shipping_skipped_reason'] = 'disabled';
			$summary['warnings'][] = $this->trans('LmdbWurthPunchoutShippingImportDisabled', 'cXML shipping fees were not imported because the option is disabled');
			return;
		}

		$productId = LmdbWurthPunchoutConfig::getInt('CXML_SHIPPING_FK_PRODUCT');
		if ($productId > 0) {
			$product = new Product($this->db);
			if ($product->fetch($productId) <= 0) {
				throw new RuntimeException('Configured cXML shipping product/service not found: #'.$productId);
			}
		}

		$result = $order->addline(
			$this->buildShippingDescription($shipping),
			$shippingAmount,
			1,
			$this->getShippingVatRate($lines),
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
			throw new RuntimeException($order->error ?: 'Unable to add cXML shipping supplier order line');
		}

		$summary['lines_added']++;
		$summary['shipping_lines_added'] = 1;
		$summary['shipping_order_line_id'] = (int) $result;
		$summary['shipping_skipped_reason'] = '';
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
