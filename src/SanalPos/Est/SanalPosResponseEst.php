<?php
/**
 * Created by Sinan Taga.
 * User: sinan
 * Date: 01/06/14
 * Time: 00:38.
 */

namespace SanalPos\Est;

use SanalPos\SanalPos3DResponseInterface;
use SanalPos\SanalPosResponseInterface;
use SimpleXMLElement;

class SanalPosResponseEst implements SanalPosResponseInterface, SanalPos3DResponseInterface
{
    protected $response;
    protected $xml;

    private $is3D = false;

    public function __construct($response)
    {
        $this->response = $response;

        try {
            $html = new \DOMDocument();
            $html->loadHTML($response);
            $this->is3D = true;
        } catch (\Exception $exception) {
        }

        try {
            $this->xml = new SimpleXMLElement($response);
        } catch (\Exception $exception) {
            $this->is3D = true;
        }
    }

    public function success()
    {
        if ($this->is3D) {
            return true;
        }

        // if response code === '00'
        // then the transaction is approved
        // if code is anything other than '00' that means there's an error
        return '00' === (string) $this->xml->ProcReturnCode;
    }

    public function errors()
    {
        if ($this->success()) {
            return [];
        }

        return $this->xml->ErrMsg;
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
        return $this->response;
    }

    public function threeD()
    {
        return $this->get3DHtml();
    }
}
