<?php

namespace SanalPos\YapiKredi;

use SanalPos\SanalPos3DInterface;
use SanalPos\SanalPosBase;
use SanalPos\SanalPosInterface;

class SanalPosYapiKredi extends SanalPosBase implements SanalPosInterface, SanalPos3DInterface
{
    protected $xml;
    protected $merchantId;
    protected $terminalId;
    protected $posnetId;

    protected $banks = [
        'yapikredi' => 'http://posnet.ykb.com/PosnetWebService/XML',
        'yapikredi_3d' => 'http://posnet.ykb.com/3DSWebService/YKBPaymentService',
    ];

    protected $testServer = 'http://setmpos.ykb.com/PosnetWebService/XML';
    protected $testServer3d = 'http://setmpos.ykb.com/3DSWebService/YKBPaymentService';
    /**
     * @var
     */
    private $bank;
    private $username;
    private $password;

    /**
     * SanalPosYapiKredi constructor.
     *
     * @param $bank yapikredi|yapikredi_3d
     * @param $merchantId
     * @param $terminalId
     * @param $posnetId
     *
     * @throws \Exception
     */
    public function __construct($bank, $merchantId, $terminalId, $posnetId, $username, $password)
    {
        if (!array_key_exists($bank, $this->banks)) {
            throw new \Exception('Bilinmeyen Banka');
        } else {
            $this->server = $this->banks[$bank];
        }
        $this->merchantId = $merchantId;
        $this->terminalId = $terminalId;
        $this->posnetId = $posnetId;
        $this->username = $username;
        $this->password = $password;
        $this->bank = $bank;
    }

    public function getServer()
    {
        if ($this->bank === 'yapikredi') {
            $this->server = $this->mode == 'TEST' ? 'https://'.$this->testServer : 'https://'.$this->server;
        } elseif ($this->bank === 'yapikredi_3d') {
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
        $orderIdLength = strlen($this->order['orderId']);
        if ($orderIdLength < 24) {
            $missingLength = 24 - $orderIdLength;
        }
        $this->order['orderId'] .= str_random($missingLength); //todo: laravel.
        $total = $this->order['total'];
        //$total = 0.01;

        $posnet = new \Posnet();
        $host = ($this->mode == 'TEST') ? 'test' : 'production';
        if ($this->bank === 'yapikredi_3d') {
            //$host= ($this->mode == 'TEST') ? 'test_3d' : 'production_3d';
            $posnet = new \PosnetOOS(
                $this->posnetId, //todo:
                $this->merchantId,
                $this->terminalId,
                $this->username,
                $this->password
            );
        }

        $pos = new \SanalPos\YapiKredi\Pos(
            $posnet,
            $this->merchantId,
            $this->terminalId,
            $host
        );

        $pos->krediKartiAyarlari($this->card['number'], $this->card['month'].$this->card['year'], $this->card['cvv']);
        $pos->siparisAyarlari($total, $this->order['orderId'], $this->order['taksit'], null);

        return $pos->odeme();

        return [
            'result' => $pos->odeme(),
            'posnet' => $posnet,
        ];
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
        curl_setopt($curl, CURLOPT_POSTFIELDS, 'xmldata='.$this->xml);
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
        $merchantPacket = $postData['MerchantPacket'];
        $bankPacket = $postData['BankPacket'];
        $sign = $postData['Sign'];
        $tranType = $postData['TranType'];

        $posnetOOS = new \PosnetOOS(
            $this->posnetId,
            $this->merchantId,
            $this->terminalId,
            $this->username,
            $this->password
        );
        //$posnetOOS->SetDebugLevel(1);

        $posnetOOS->SetURL(
            $this->mode === 'TEST' ? $this->testServer : $this->server
        );

        if (!$posnetOOS->CheckAndResolveMerchantData(
            $merchantPacket,
            $bankPacket,
            $sign
        )) {
            return [
                'status' => false,
                'message' => $posnetOOS->GetLastErrorMessage(),
            ];
        } else {
            $availablePoint = $posnetOOS->GetTotalPointAmount();

            if (!$posnetOOS->ConnectAndDoTDSTransaction($merchantPacket,
                $bankPacket,
                $sign
            )) {
                if ($posnetOOS->GetLastErrorMessage()) {
                    return [
                        'status' => false,
                        'message' => $posnetOOS->GetLastErrorMessage(),
                    ];
                }
            }
            if ($posnetOOS->arrayPosnetResponseXML['posnetResponse']['approved']) {
                return [
                    'status' => true,
                ];
            }

            return [
                'status' => false,
                'message' => 'Bilinmeyen hata oluştu',
            ];
        }
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
            "&MerchantPassword={$this->posnetId}".
            "&TerminalNo={$this->terminalId}".
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
