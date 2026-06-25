<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

require_once __DIR__.'/lmdbwurthpunchoutconfig.class.php';

/**
 * cXML PunchOut setup client.
 */
class LmdbWurthPunchoutCxmlClient
{
	/**
	 * Build cXML setup request.
	 *
	 * @param string $returnUrl   Browser return URL
	 * @param string $buyerCookie Buyer cookie
	 * @return string
	 */
	public function buildSetupRequest($returnUrl, $buyerCookie)
	{
		$payloadId = uniqid('lmdbwurthpunchout-', true).'@dolibarr';
		$timestamp = gmdate('Y-m-d\TH:i:s\Z');

		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->formatOutput = true;

		$cxml = $doc->createElement('cXML');
		$cxml->setAttribute('payloadID', $payloadId);
		$cxml->setAttribute('timestamp', $timestamp);
		$doc->appendChild($cxml);

		$header = $cxml->appendChild($doc->createElement('Header'));
		$this->appendCredential($doc, $header, 'From', LmdbWurthPunchoutConfig::getString('CXML_CUSTOMER_DOMAIN'), LmdbWurthPunchoutConfig::getString('CXML_CUSTOMER_IDENTITY'));
		$this->appendCredential($doc, $header, 'To', LmdbWurthPunchoutConfig::getString('CXML_SUPPLIER_DOMAIN'), LmdbWurthPunchoutConfig::getString('CXML_SUPPLIER_IDENTITY'));
		$sender = $header->appendChild($doc->createElement('Sender'));
		$this->appendCredentialContent($doc, $sender, LmdbWurthPunchoutConfig::getString('CXML_CUSTOMER_DOMAIN'), LmdbWurthPunchoutConfig::getString('CXML_CUSTOMER_IDENTITY'));
		$secret = $sender->appendChild($doc->createElement('SharedSecret'));
		$secret->appendChild($doc->createTextNode(LmdbWurthPunchoutConfig::getSecret('CXML_SHARED_SECRET')));
		$sender->appendChild($doc->createElement('UserAgent', 'Dolibarr WURTH Punchout'));

		$request = $cxml->appendChild($doc->createElement('Request'));
		$request->setAttribute('deploymentMode', LmdbWurthPunchoutConfig::getString('CXML_MODE', 'production') === 'test' ? 'test' : 'production');
		$punchout = $request->appendChild($doc->createElement('PunchOutSetupRequest'));
		$punchout->setAttribute('operation', 'create');
		$buyerCookieNode = $punchout->appendChild($doc->createElement('BuyerCookie'));
		$buyerCookieNode->appendChild($doc->createTextNode($buyerCookie));
		$browserPost = $punchout->appendChild($doc->createElement('BrowserFormPost'));
		$browserPostUrl = $browserPost->appendChild($doc->createElement('URL'));
		$browserPostUrl->appendChild($doc->createTextNode($returnUrl));
		$supplierSetup = $punchout->appendChild($doc->createElement('SupplierSetup'));
		$supplierSetupUrl = $supplierSetup->appendChild($doc->createElement('URL'));
		$supplierSetupUrl->appendChild($doc->createTextNode(LmdbWurthPunchoutConfig::getString('CXML_URL')));

		return (string) $doc->saveXML();
	}

	/**
	 * Send setup request and return StartPage URL.
	 *
	 * @param string $returnUrl   Browser return URL
	 * @param string $buyerCookie Buyer cookie
	 * @return string
	 */
	public function getStartPageUrl($returnUrl, $buyerCookie)
	{
		if (!function_exists('curl_init')) {
			throw new RuntimeException('cURL is required for cXML Punchout setup');
		}

		$xml = $this->buildSetupRequest($returnUrl, $buyerCookie);
		$ch = curl_init(LmdbWurthPunchoutConfig::getString('CXML_URL'));
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml; charset=UTF-8'));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		$response = curl_exec($ch);
		$error = curl_error($ch);
		$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($response === false || $response === '' || $httpCode >= 400) {
			throw new RuntimeException('cXML Punchout setup failed'.($error !== '' ? ': '.$error : ''));
		}

		return $this->parseStartPageUrl($response);
	}

	/**
	 * Parse cXML setup response.
	 *
	 * @param string $response cXML response
	 * @return string
	 */
	public function parseStartPageUrl($response)
	{
		$doc = new DOMDocument();
		$previous = libxml_use_internal_errors(true);
		$loaded = $doc->loadXML($response, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);
		if (!$loaded) {
			throw new RuntimeException('Invalid cXML setup response');
		}

		$xpath = new DOMXPath($doc);
		$status = $xpath->query('//*[local-name()="Status"]')->item(0);
		if ($status instanceof DOMElement && $status->hasAttribute('code') && (int) $status->getAttribute('code') >= 400) {
			throw new RuntimeException('cXML setup rejected: '.$status->textContent);
		}

		$url = $xpath->query('//*[local-name()="StartPage"]/*[local-name()="URL"]')->item(0);
		if (!$url || trim($url->textContent) === '') {
			throw new RuntimeException('cXML setup response has no StartPage URL');
		}

		return trim($url->textContent);
	}

	/**
	 * Append cXML credential.
	 *
	 * @param DOMDocument $doc      Document
	 * @param DOMNode     $parent   Parent
	 * @param string      $nodeName Node name
	 * @param string      $domain   Domain
	 * @param string      $identity Identity
	 * @return void
	 */
	private function appendCredential($doc, $parent, $nodeName, $domain, $identity)
	{
		$node = $parent->appendChild($doc->createElement($nodeName));
		$this->appendCredentialContent($doc, $node, $domain, $identity);
	}

	/**
	 * Append cXML credential content.
	 *
	 * @param DOMDocument $doc      Document
	 * @param DOMNode     $parent   Parent
	 * @param string      $domain   Domain
	 * @param string      $identity Identity
	 * @return void
	 */
	private function appendCredentialContent($doc, $parent, $domain, $identity)
	{
		$credential = $parent->appendChild($doc->createElement('Credential'));
		$credential->setAttribute('domain', $domain);
		$identityNode = $credential->appendChild($doc->createElement('Identity'));
		$identityNode->appendChild($doc->createTextNode($identity));
	}
}
