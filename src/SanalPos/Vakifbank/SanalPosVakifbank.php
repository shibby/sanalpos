<?php

namespace SanalPos\Vakifbank;

use SanalPos\SanalPos3DInterface;
use SanalPos\SanalPosBase;
use SanalPos\SanalPosInterface;
use DOMDocument;

class SanalPosVakifbank extends SanalPosBase implements SanalPosInterface, SanalPos3DInterface
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

    /**
     * SanalPosVakifbank constructor.
     *
     * @param $bank vakifbank|vakifbank_3d
     * @param $merchantId
     * @param $terminalNo
     * @param $password
     *
     * @throws \Exception
     */
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

    /**
     * @param bool        $pre        bu değişken kullanılmıyor ve ne işe yarıyor inan hiç bilmiyorum
     * @param null|string $successUrl yalnızca 3d ödeme yapılacaksa gerekli
     * @param null|string $failureUrl yalnızca 3d ödeme yapılacaksa gerekli
     *
     * @return mixed
     */
    public function pay($pre = false, $successUrl = null, $failureUrl = null)
    {
        if ($this->bank === 'vakifbank_3d') {
            return $this->send3d($successUrl, $failureUrl);
        }

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

        if ($successUrl) {
            $x['SuccessUrl'] = $dom->createElement('SuccessUrl', $successUrl);
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

    /**
     * 3d formunu ekrana bastıktan sonra kullanıcı sms doğrulamasını gireceği alana yönlendirilir.
     * SanalPos3DResponseInterface dosyasını kontrol edin.
     *
     * SMS kodunu girdikten sonra $successUrl ile belirlediğimiz adrese yönlendirilir.
     * İşte bu noktada, gelen post datayı kontrol ettikten sonra, çekim işlemini tamamlamak için
     * bu fonksiyon çalıştırılır.
     *
     * @param array $postData
     *
     * @return mixed
     */
    public function provision3d(array $postData)
    {
        $dom = new DOMDocument('1.0', 'ISO-8859-9');
        $root = $dom->createElement('VposRequest');
        $x['MerchantId'] = $dom->createElement('MerchantId', $this->merchantId);
        $x['TerminalNo'] = $dom->createElement('TerminalNo', $this->terminalNo);
        $x['Password'] = $dom->createElement('Password', $this->password);

        $x['TransactionType'] = $dom->createElement('TransactionType', 'Sale');
        $x['TransactionId'] = $dom->createElement('TransactionId', $this->order['orderId']);

        if ($this->order['taksit']) {
            $x['NumberOfInstallments'] = $dom->createElement('NumberOfInstallments', $this->order['taksit']);
        }
        if (!empty($postData['Cvv'])) {
            $x['Cvv'] = $dom->createElement('Cvv', $postData['Cvv']);
        }
        if (!empty($postData['PurchAmount'])) {
            $x['CurrencyAmount'] = $dom->createElement('CurrencyAmount', $postData['PurchAmount']);
            $x['CurrencyCode'] = $dom->createElement('CurrencyCode', 949); //TODO: set currencycode parameter
        }
        if (!empty($postData['Pan'])) {
            $x['Pan'] = $dom->createElement('Pan', $postData['Pan']);
        }
        if (!empty($postData['Expiry'])) {
            $x['Expiry'] = $dom->createElement('Expiry', '20'.$postData['Expiry']);
        }

        $x['TransactionDeviceSource'] = $dom->createElement('TransactionDeviceSource', 0);
        $x['MpiTransactionId'] = $dom->createElement('MpiTransactionId', $postData['VerifyEnrollmentRequestId']);

        $x['ECI'] = $dom->createElement('ECI', $postData['Eci']);
        $x['CAVV'] = $dom->createElement('CAVV', $postData['Cavv']);
        $x['ClientIp'] = $dom->createElement('ClientIp', $this->getIpAddress());

        foreach ($x as $node) {
            $root->appendChild($node);
        }
        $dom->appendChild($root);

        $this->xml = $dom->saveXML();

        return $this->send();
    }

    /**
     * @param $successUrl
     * @param $failureUrl
     *
     * @return mixed
     */
    private function send3d($successUrl, $failureUrl)
    {
        //$kartTipi = $_POST['BrandName'];
        //$islemNumarasi = $_POST['VerifyEnrollmentRequestId'];
        $islemNumarasi = str_random();

        $total = (float) $this->order['total'];
        $total = number_format($total, 2, '.', '');

        $this->card['year'] = substr($this->card['year'], -2, 2);

        $brandName = 200; //mastercard
        if ($this->card['number'][0] === 4) {
            //ilk rakamı 4 ise
            $brandName = 100; //visa
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

        $resultXml = curl_exec($ch);
        curl_close($ch);

        return $resultXml;
    }
}
