<?php

namespace SanalPos\YapiKredi;

use DOMDocument;
use SanalPos\SanalPos3DResponseInterface;
use SanalPos\SanalPosResponseInterface;

class SanalPosResponseIsbankInnova implements SanalPosResponseInterface, SanalPos3DResponseInterface
{
    /**
     * @var Sonuc
     */
    protected $response;
    protected $xml;
    private $is3D = false;

    private $decodedResponse;

    public function __construct($response)
    {
        $this->response = $response;
    }

    public function success()
    {
        if (!$this->response) {
            return false;
        }

        return $this->decodeResponse()['Status'] === 'Y';
    }

    public function errors()
    {
        $this->decodeResponse();
        if (!$this->response) {
            return 'No response';
        }
        if ($this->success()) {
            return [];
        }

        if ($this->decodedResponse['ErrorMessage']) {
            return $this->decodedResponse['ErrorMessage'];
        }

        return 'unknown';
    }

    public function threeD()
    {
        return $this->get3DHtml();
    }

    public function response()
    {
        return $this->xml;
    }

    public function decodeResponse()
    {
        if ($this->decodedResponse) {
            return $this->decodedResponse;
        }
        $resultDocument = new DOMDocument();
        $resultDocument->loadXML($this->response);

        //Status Bilgisi okunuyor
        $statusNode = $resultDocument->getElementsByTagName('Status')->item(0);
        $status = '';
        if ($statusNode != null) {
            $status = $statusNode->nodeValue;
        }

        //PAReq Bilgisi okunuyor
        $PAReqNode = $resultDocument->getElementsByTagName('PaReq')->item(0);
        $PaReq = '';
        if ($PAReqNode != null) {
            $PaReq = $PAReqNode->nodeValue;
        }

        //ACSUrl Bilgisi okunuyor
        $ACSUrlNode = $resultDocument->getElementsByTagName('ACSUrl')->item(0);
        $ACSUrl = '';
        if ($ACSUrlNode != null) {
            $ACSUrl = $ACSUrlNode->nodeValue;
        }

        //Term Url Bilgisi okunuyor
        $TermUrlNode = $resultDocument->getElementsByTagName('TermUrl')->item(0);
        $TermUrl = '';
        if ($TermUrlNode != null) {
            $TermUrl = $TermUrlNode->nodeValue;
        }

        //MD Bilgisi okunuyor
        $MDNode = $resultDocument->getElementsByTagName('MD')->item(0);
        $MD = '';
        if ($MDNode != null) {
            $MD = $MDNode->nodeValue;
        }

        //MessageErrorCode Bilgisi okunuyor
        $messageErrorCodeNode = $resultDocument->getElementsByTagName('MessageErrorCode')->item(0);
        $messageErrorCode = '';
        if ($messageErrorCodeNode != null) {
            $messageErrorCode = $messageErrorCodeNode->nodeValue;
        }
        $errorMessageNode = $resultDocument->getElementsByTagName('ErrorMessage')->item(0);
        $errorMessage = null;
        if ($errorMessageNode != null) {
            $errorMessage = $errorMessageNode->nodeValue;
        }

        // Sonuç dizisi oluşturuluyor
        $result = array(
            'Status' => $status,
            'PaReq' => $PaReq,
            'ACSUrl' => $ACSUrl,
            'TermUrl' => $TermUrl,
            'MerchantData' => $MD,
            'MessageErrorCode' => $messageErrorCode,
            'ErrorMessage' => $errorMessage,
        );

        $this->decodedResponse = $result;

        return $result;
    }

    /**
     * işlemin başarılı olması durumunda, buradaki html kodu ekrana basılacak.
     * bu ekrana basılan kod, otomatik olarak 3d doğrulama sayfasına yönlendirecek bizi.
     *
     * @return string
     */
    public function get3DHtml()
    {
        $html = '<form name="downloadForm" action="'.$this->decodedResponse['ACSUrl'].'" method="POST">';
        $html .= '
        <input name="PaReq" type="hidden" value="'.$this->decodedResponse['PaReq'].'">
        <input name="TermUrl" type="hidden" value="'.$this->decodedResponse['TermUrl'].'">
        <input name="MD" type="hidden" value="'.$this->decodedResponse['MerchantData'].'">
      ';
        $html .= '</form>';
        $html .= '<SCRIPT LANGUAGE="Javascript">document.downloadForm.submit();</SCRIPT>';

        return $html;
    }
}
