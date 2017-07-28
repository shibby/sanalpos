<?php

namespace SanalPos\Vakifbank;

use SanalPos\SanalPosBase;
use SanalPos\SanalPosInterface;
use DOMDocument;

class SanalPosVakifbank extends SanalPosBase implements SanalPosInterface
{
    protected $xml;
    protected $merchantId;
    protected $terminalNo;
    protected $password;

    protected $banks = [
        'vakifbank' => 'onlineodeme.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx',
        'vakifbank_3d' => '3dsecure.vakifbank.com.tr:4443/MPIAPI/MPI_Enrollment.aspx',
    ];

    protected $testServer = 'onlineodemetest.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx';
    protected $testServer3d = '3dsecuretest.vakifbank.com.tr:4443/MPIAPI/MPI_Enrollment.aspx';
    /**
     * @var
     */
    private $bank;

    public function __construct($bank, $merchantId, $terminalNo, $password)
    {
        if (!array_key_exists($bank, $this->banks)) {
            throw new \Exception('Bilinmeyen Banka');
        } else {
            $this->server = $this->banks[$bank];
        }
        $this->merchantId = $merchantId;
        $this->terminalNo = $terminalNo;
        $this->password = $password;
        $this->bank = $bank;
    }

    public function getServer()
    {
        if ($this->bank === 'vakifbank') {
            $this->server = $this->mode == 'TEST' ? 'https://'.$this->testServer : 'https://'.$this->server;
        } elseif ($this->bank === 'vakifbank_3d') {
            $this->server = $this->mode == 'TEST' ? 'https://'.$this->testServer3d : 'https://'.$this->server;
        }

        return $this->server;
    }

    public function pay($pre = false, $successURL = null, $failureUrl = null)
    {
        $dom = new DOMDocument('1.0', 'ISO-8859-9');
        $root = $dom->createElement('VposRequest');
        $x['MerchantId'] = $dom->createElement('MerchantId', $this->merchantId);
        $x['TerminalNo'] = $dom->createElement('TerminalNo', $this->terminalNo);
        $x['Password'] = $dom->createElement('Password', $this->password);

        $x['TransactionType'] = $dom->createElement('TransactionType', 'Sale');
        $x['TransactionId'] = $dom->createElement('TransactionId', $this->order['orderId']);

        $x['CurrencyAmount'] = $dom->createElement('CurrencyAmount', $this->order['total']);
        $x['CurrencyCode'] = $dom->createElement('CurrencyCode', 949); //TODO: set currencycode parameter
        if ($this->order['taksit']) {
            $x['NumberOfInstallments'] = $dom->createElement('NumberOfInstallments', $this->order['taksit']);
        }

        $x['Pan'] = $dom->createElement('Pan', $this->card['number']);
        $x['Cvv'] = $dom->createElement('Cvv', $this->card['cvv']);
        $x['Expiry'] = $dom->createElement('Expiry', $this->card['year'].$this->card['month']);
        $x['TransactionDeviceSource'] = $dom->createElement('TransactionDeviceSource', 0);
        if ($this->bank === 'vakifbank_3d') {
            $x['ECI'] = $dom->createElement('ECI', 05);
            $x['CAVV'] = $dom->createElement('CAVV', 'asfa435redf');
            $x['MpiTransactionId'] = $dom->createElement('MpiTransactionId', '5d6b951b06fa043379458dc835b71d0c8');
        }

        if ($successURL) {
            $x['SuccessUrl'] = $dom->createElement('SuccessUrl', $successURL);
        }
        if ($failureUrl) {
            $x['FailureURL'] = $dom->createElement('FailureURL', $failureUrl);
        }

        $x['ClientIp'] = $dom->createElement('ClientIp', $this->getIpAddress());

        foreach ($x as $node) {
            $root->appendChild($node);
        }
        $dom->appendChild($root);

        $this->xml = $dom->saveXML();

        if ($this->bank === 'vakifbank_3d') {
            return $this->send3d($successURL, $failureUrl);
        }

        return $this->send();
    }

    public function postAuth($orderId)
    {
    }

    public function cancel($orderId)
    {
    }

    public function refund($orderId, $amount = null)
    {
    }

    public function send()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->getServer());
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, 'prmstr='.$this->xml);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-type' => 'application/x-www-form-urlencoded'));
        $response = curl_exec($curl);
        curl_close($curl);

        return $response;
    }

    private function send3d($successUrl, $failureUrl)
    {
        $this->password = 'Sf46DdWt'; //TODO:
        //$kartTipi = $_POST['BrandName'];
        //$islemNumarasi = $_POST['VerifyEnrollmentRequestId'];
        $islemNumarasi = str_random();

        $total = (float) $this->order['total'];
        $total = number_format($total, 2, '.', '');

        $this->card['year'] = substr($this->card['year'], -2, 2);

        if (starts_with($this->card['number'], 4)) {
            $brandName = 100;
        } else {
            $brandName = 200;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getServer());
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type' => 'application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS,
            "Pan={$this->card['number']}".
            "&ExpiryDate={$this->card['year']}{$this->card['month']}".
            "&Cvv={$this->card['cvv']}".
            "&PurchaseAmount={$total}".
            "&CurrencyAmount={$total}".
            '&Currency=949'.
            "&VerifyEnrollmentRequestId=$islemNumarasi".
            "&MerchantId={$this->merchantId}".
            "&MerchantPassword={$this->password}".
            "&TerminalNo={$this->terminalNo}".
            "&SuccessUrl=$successUrl".
            "&FailureUrl=$failureUrl".
            "&NumberOfInstallments={$this->order['taksit']}".
            '&TransactionType=Sale'.
            "&BrandName={$brandName}"
        );
        //BrandName=$kartTipi

        // ��lem iste�i MPI'a g�nderiliyor
        $resultXml = curl_exec($ch);
        curl_close($ch);

        return $resultXml;
        dd($resultXml);
    }
}
