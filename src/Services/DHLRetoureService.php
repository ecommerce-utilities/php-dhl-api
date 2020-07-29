<?php
namespace EcommerceUtilities\DHL\Services;

use DOMDocument;
use DOMXPath;
use EcommerceUtilities\DHL\Common\DHLApiCredentials;
use EcommerceUtilities\DHL\Common\DHLApiException;
use EcommerceUtilities\DHL\Services\DHLRetoureService\DHLRetoureServiceResponse;

class DHLRetoureService {
	/** @var DHLApiCredentials */
	private $credentials;

	/**
	 * @param DHLApiCredentials $credentials
	 */
	public function __construct(DHLApiCredentials $credentials) {
		$this->credentials = $credentials;
	}

	/**
	 * @param string $name1
	 * @param string $name2
	 * @param string $street
	 * @param string $streetNumber
	 * @param string $zip
	 * @param string $city
	 * @param string $voucherNr
	 * @return DHLRetoureServiceResponse
	 * @throws DHLApiException
	 */
	public function getRetourePdf($name1, $name2, $street, $streetNumber, $zip, $city, $voucherNr = '') {
		$xmlRequest = $this->getRequestXml($name1, $name2, $street, $streetNumber, $zip, $city, $voucherNr);
		$xmlResponse = $this->curlSoapRequest($xmlRequest);
		$pdf = $this->getPdfFromResponse($xmlResponse);
		return $pdf;
	}

	/**
	 * @param string $name1
	 * @param string $name2
	 * @param string $street
	 * @param string $streetNumber
	 * @param string $zip
	 * @param string $city
	 * @param string $voucherNr
	 * @return string
	 */
	private function getRequestXml($name1, $name2, $street, $streetNumber, $zip, $city, $voucherNr = '') {
		$doc = new \DOMDocument();
		$doc->loadXML('<?xml version="1.0" encoding="UTF-8" ?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:var="https://amsel.dpwn.net/abholportal/gw/lp/schema/1.0/var3bl"></soapenv:Envelope>');
		$doc->formatOutput = true;

		$createNode = static function ($tagName, $value = null, array $attributes, array $children = []) use ($doc) {
			$elem = $doc->createElement($tagName);
			if($value !== null) {
				$elem->appendChild($doc->createTextNode($value));
			}
			foreach($attributes as $attributeKey => $attributeValue) {
				$elem->setAttribute($attributeKey, $attributeValue);
			}
			foreach($children as $child) {
				$elem->appendChild($child);
			}
			return $elem;
		};

		$doc->documentElement->appendChild(
			$createNode('soapenv:Header', null, [], [
				$createNode('wsse:Security', null, ['soapenv:mustUnderstand' => 1, 'xmlns:wsse' => 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd'], [
					$createNode('wsse:UsernameToken', null, [], [
						$createNode('wsse:Username', $this->credentials->getUsername(), [], []),
						$createNode('wsse:Password', $this->credentials->getPassword(), ['Type' => 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText'], [])
					])
				])
			])
		);

		$doc->documentElement->appendChild(
			$createNode('soapenv:Body', null, [], [
				$createNode('var:BookLabelRequest', null, [
					'portalId' => $this->credentials->getPortalId(),
					'deliveryName' => $this->credentials->getWarehouseName(),
					'shipmentReference' => $voucherNr,
					'customerReference' => $voucherNr,
					'labelFormat' => 'PDF',
					'senderName1' => $name1,
					'senderName2' => $name2,
					'senderCareOfName' => 'CareofName',
					'senderContactPhone' => '',
					'senderStreet' => $street,
					'senderStreetNumber' => $streetNumber,
					'senderBoxNumber' => '',
					'senderPostalCode' => $zip,
					'senderCity' => $city
				], [])
			])
		);

		return $doc->saveXML();
	}

	/**
	 * @param string $xmlRequest
	 * @return string
	 * @throws \Exception
	 */
	private function curlSoapRequest($xmlRequest) {
		$header = array(
			'Content-type: text/xml;charset="utf-8"',
			'Accept: text/xml',
			'Cache-Control: no-cache',
			'Pragma: no-cache',
			'Content-length: ' . strlen($xmlRequest),
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->credentials->getEndpoint());
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlRequest);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		$response = curl_exec($ch);
		$error = curl_errno($ch);

		if($error) {
			throw new DHLApiException(curl_error($ch));
		}

		curl_close($ch);
		return $response;
	}

	/**
	 * @param string $response
	 * @return DHLRetoureServiceResponse
	 * @throws DHLApiException
	 */
	private function getPdfFromResponse($response) {
		$responseDoc = new DOMDocument();
		$responseDoc->loadXML($response);
		$xpath = new DOMXPath($responseDoc);
		$xpath->registerNamespace('env', 'http://schemas.xmlsoap.org/soap/envelope/');
		$xpath->registerNamespace('var3bl', 'https://amsel.dpwn.net/abholportal/gw/lp/schema/1.0/var3bl');

		$errorCode = $xpath->evaluate('string(/*/env:Body/env:Fault/faultcode)');
		$errorText = $xpath->evaluate('string(/*/env:Body/env:Fault/faultstring)');

		if($errorCode) {
			throw new DHLApiException("{$errorText} ({$errorCode})");
		}

		$base64PDF = $xpath->evaluate('string(/*/env:Body/var3bl:BookLabelResponse/var3bl:label)');
		$trackingNumber = $xpath->evaluate('string(/*/env:Body/var3bl:BookLabelResponse/@idc)');
		$pdf = base64_decode($base64PDF);
		return new DHLRetoureServiceResponse($trackingNumber, $pdf, $response);
	}
}
