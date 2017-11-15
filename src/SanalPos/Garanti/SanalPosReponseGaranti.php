<?php
/**
 * Created by Sinan Taga.
 * User: sinan
 * Date: 31/05/14
 * Time: 21:28.
 */

namespace SanalPos\Garanti;

use SanalPos\SanalPosResponseInterface;
use SimpleXMLElement;

class SanalPosReponseGaranti implements SanalPosResponseInterface
{
    protected $response;
    protected $xml;

    public function __construct($response)
    {
        $this->response = $response;
        $this->xml = new SimpleXMLElement($response);
    }

    public function success()
    {
        // if response code === '00'
        // then the transaction is approved
        // if code is anything other than '00' that means there's an error
        if ($this->xml->Transaction->Response->Code[0]) {
            $code = $this->xml->Transaction->Response->Code[0];
        } else {
            $code = $this->xml->Transaction->Response->Code;
        }

        return (string) $code === '00';
    }

    public function errors()
    {
        if ($this->success()) {
            return [];
        }
        if (@$this->xml->Transaction->Response->ErrorMsg) {
            return $this->xml->Transaction->Response->ErrorMsg;
        } else {
            return @$this->xml->Transaction->Response->SysErrMsg;
        }
    }

    public function response()
    {
        return $this->response;
    }
}
