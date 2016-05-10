<?php

namespace SanalPos\Vakifbank;

use SanalPos\SanalPosResponseInterface;
use SimpleXMLElement;

class SanalPosResponseBankAsya implements SanalPosResponseInterface
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
        if (isset($this->xml->Operation->OpData->ActionInfo->HostResponse['ResultCode'])) {
            return (string) $this->xml->Operation->OpData->ActionInfo->HostResponse['ResultCode'] === '0000';
        }

        return false;
    }

    public function errors()
    {
        if ($this->success()) {
            return [];
        }

        return $this->xml->Operation->OpData->ActionInfo->HostResponse['ResultCode'];
    }

    public function response()
    {
        return $this->xml;
    }
}
