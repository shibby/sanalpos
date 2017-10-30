<?php

namespace SanalPos\Vakifbank;

use SanalPos\SanalPos3DResponseInterface;
use SanalPos\SanalPosResponseInterface;
use SimpleXMLElement;

class SanalPosResponseVakifbank implements SanalPosResponseInterface, SanalPos3DResponseInterface
{
    protected $response;
    protected $xml;
    private $is3D = false;

    public function __construct($response)
    {
        $this->response = $response;
        $this->xml = new SimpleXMLElement($response);
    }

    public function success()
    {
        if (@$this->xml->Message) {
            $this->is3D = true;

            return $this->xml->Message->VERes->Status && $this->xml->Message->VERes->Status->__toString() === 'Y';
        }
        // if response code === '00'
        // then the transaction is approved
        // if code is anything other than '00' that means there's an error
        return (string) $this->xml->ResultCode === '0000';
    }

    public function errors()
    {
        if ($this->success()) {
            return [];
        }
        if ($this->is3D) {
            return $this->xml->ErrorMessage;
        }

        return $this->xml->ResultDetail;
    }

    public function threeD()
    {
        return $this->get3DHtml();
    }

    public function response()
    {
        return $this->xml;
    }

    /**
     * işlemin başarılı olması durumunda, buradaki html kodu ekrana basılacak.
     * bu ekrana basılan kod, otomatik olarak 3d doğrulama sayfasına yönlendirecek bizi.
     *
     * @return string
     */
    public function get3DHtml()
    {
        $acsUrl = $this->xml->Message->VERes->ACSUrl;
        $paReq = $this->xml->Message->VERes->PaReq;
        $termUrl = $this->xml->Message->VERes->TermUrl;
        $md = $this->xml->Message->VERes->MD;
        $html = '<form name="downloadForm" action="'.$acsUrl.'" method="POST">';
        $html .= '<input type="hidden" name="PaReq" value="'.$paReq.'">';
        $html .= '<input type="hidden" name="TermUrl" value="'.$termUrl.'">';
        $html .= '<input type="hidden" name="MD" value="'.$md.'">';
        $html .= '</form>';
        $html .= '<SCRIPT LANGUAGE="Javascript">document.downloadForm.submit();</SCRIPT>';

        return $html;
    }
}
