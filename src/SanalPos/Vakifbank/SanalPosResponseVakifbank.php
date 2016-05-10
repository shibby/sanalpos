<?php

namespace SanalPos\Vakifbank;

use SanalPos\SanalPosResponseInterface;
use SimpleXMLElement;

class SanalPosResponseVakifbank implements SanalPosResponseInterface
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
        return (string) $this->xml->ResultCode === '0000';
    }

    public function errors()
    {
        if ($this->success()) {
            return [];
        }

        return $this->xml->ResultDetail;
    }

    public function response()
    {
        return $this->xml;
    }
}
