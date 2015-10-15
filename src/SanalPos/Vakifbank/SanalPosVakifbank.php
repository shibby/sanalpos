<?php
namespace SanalPos\Vakifbank;

use SanalPos\SanalPosBase;
use SanalPos\SanalPosInterface;
use DOMDocument;

class SanalPosVakifbank extends SanalPosBase implements SanalPosInterface{
    protected $xml;
    protected $merchantId;
    protected $terminalNo;
    protected $password;

    protected $banks = [
        'vakifbank'    => 'onlineodeme.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx',
    ];

    protected $testServer = 'onlineodeme.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx';

    public function __construct($bank, $merchantId, $terminalNo, $password)
    {
        if(!array_key_exists($bank, $this->banks)){
            throw new \Exception('Bilinmeyen Banka');
        }else{
            $this->server = $this->banks[$bank];
        }
        $this->merchantId = $merchantId;
        $this->terminalNo   = $terminalNo;
        $this->password   = $password;
    }

    public function getServer()
    {
        $this->server = $this->mode == 'TEST' ? 'https://'.$this->testServer : 'https://'.$this->server;
        return $this->server;
    }

    public function pay($pre = false)
    {
        $dom = new DOMDocument('1.0', 'ISO-8859-9');
        $root = $dom->createElement('VposRequest');
        $x['MerchantId'] = $dom->createElement('MerchantId', $this->merchantId);
        $x['TerminalNo'] = $dom->createElement('TerminalNo', $this->terminalNo);
        $x['Password'] = $dom->createElement('Password', $this->password);

        $x['TransactionType'] = $dom->createElement('TransactionType', 'Sale'); //
        $x['TransactionId'] = $dom->createElement('TransactionId', $this->order['orderId']);

        $x['CurrencyAmount'] = $dom->createElement('CurrencyAmount', $this->order['total']);
        $x['CurrencyCode'] = $dom->createElement('CurrencyCode', 949); //TODO: currencycode parameter

        $x['Pan'] = $dom->createElement('Pan', $this->card['number']);
        $x['Cvv'] = $dom->createElement('Cvv', $this->card['cvv']);
        $x['Expiry'] = $dom->createElement('Expiry', $this->card['year'].$this->card['month']);
        $x['TransactionDeviceSource'] = $dom->createElement('TransactionDeviceSource', 0);

        foreach($x as $node)
        {
            $root->appendChild($node);
        }
        $dom->appendChild($root);

        $this->xml = $dom->saveXML();
        return $this->send();
    }

    public function postAuth($orderId)
    {

    }

    public function cancel($orderId)
    {

    }

    public function refund($orderId, $amount = NULL)
    {

    }

    public function send()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->getServer());
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, "prmstr=" . $this->xml);
        curl_setopt ($curl, CURLOPT_HEADER, FALSE);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type" => "application/x-www-form-urlencoded"));
        $response= curl_exec($curl);
        curl_close($curl);

        return $response;
    }
}