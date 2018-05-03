<?php

namespace SanalPos\KuveytTurk;

use SanalPos\SanalPos3DInterface;
use SanalPos\SanalPosBase;
use SanalPos\SanalPosInterface;

class SanalPosKuveytTurk extends SanalPosBase implements SanalPosInterface, SanalPos3DInterface
{
    protected $xml;
    protected $merchantId;
    protected $customerId;

    protected $banks = [
        'kuveytturk' => 'https://posnet.kuveytturk.com.tr/PosnetWebService/XML',
        'kuveytturk_3d' => 'https://posnet.kuveytturk.com.tr/3DSWebService/YKBPaymentService',
    ];

    protected $testServer = 'https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelPayGate';
    protected $testServer3d = 'https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelPayGate';
    /**
     * @var
     */
    private $bank;
    private $username;
    private $password;

    /**
     * SanalPosKuveytTurk constructor.
     *
     * @param $bank kuveytturk|kuveytturk_3d
     * @param $merchantId
     * @param $customerId
     * @param $username
     * @param $password
     *
     * @throws \Exception
     */
    public function __construct(
        $bank,
        $merchantId,
        $customerId,
        $username,
        $password
    ) {
        if (!array_key_exists($bank, $this->banks)) {
            throw new \Exception('Bilinmeyen Banka');
        } else {
            $this->server = $this->banks[$bank];
        }
        $this->merchantId = $merchantId;
        $this->username = $username;
        $this->password = $password;
        $this->bank = $bank;
        $this->customerId = $customerId;
    }

    public function getServer()
    {
        if ('kuveytturk' === $this->bank) {
            $this->server = 'TEST' == $this->mode ? $this->testServer : $this->banks['kuveytturk'];
        } elseif ('kuveytturk_3d' === $this->bank) {
            $this->server = 'TEST' == $this->mode ? $this->testServer3d : $this->banks['kuveytturk_3d'];
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
        $Name = $_POST['CardHolderName'];

        $Type = 'Sale';
        $CurrencyCode = '0949'; //TL islemleri için
        $MerchantOrderId = $this->order['uniq']; // Siparis Numarasi
        $hashedPassword = base64_encode(sha1($this->password, 'ISO-8859-9')); //md5($Password);
        $HashData = base64_encode(sha1(
            $this->merchantId.
            $MerchantOrderId.
            $this->order['total'].
            $successUrl.
            $failureUrl.
            $this->username.
            $hashedPassword, 'ISO-8859-9'));
        $TransactionSecurity = 3;
        $xml = '<KuveytTurkVPosMessage xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">'
            .'<APIVersion>1.0.0</APIVersion>'
            .'<OkUrl>'.$successUrl.'</OkUrl>'
            .'<FailUrl>'.$failureUrl.'</FailUrl>'
            .'<HashData>'.$HashData.'</HashData>'
            .'<MerchantId>'.$this->merchantId.'</MerchantId>'
            .'<CustomerId>'.$this->customerId.'</CustomerId>'
            .'<UserName>'.$this->username.'</UserName>'
            .'<CardNumber>'.$this->card['number'].'</CardNumber>'
            .'<CardExpireDateYear>'.$this->card['year'].'</CardExpireDateYear>'
            .'<CardExpireDateMonth>'.$this->card['month'].'</CardExpireDateMonth>'
            .'<CardCVV2>'.$this->card['cvv'].'</CardCVV2>'
            .'<CardHolderName>'.$Name.'</CardHolderName>'
            .'<CardType>MasterCard</CardType>'
            .'<BatchID>0</BatchID>'
            .'<TransactionType>'.$Type.'</TransactionType>'
            .'<InstallmentCount>'.($this->order['taksit'] ?: 0).'</InstallmentCount>'
            .'<Amount>'.$this->order['total'].'</Amount>'
            .'<DisplayAmount>'.$this->order['total'].'</DisplayAmount>'
            .'<CurrencyCode>'.$CurrencyCode.'</CurrencyCode>'
            .'<MerchantOrderId>'.$MerchantOrderId.'</MerchantOrderId>'
            .'<TransactionSecurity>3</TransactionSecurity>'
            .'<TransactionSide>Sale</TransactionSide>'
            .'</KuveytTurkVPosMessage>';

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/xml', 'Content-length: '.strlen($xml)));
            curl_setopt($ch, CURLOPT_POST, true); //POST Metodu kullanarak verileri gönder
            curl_setopt($ch, CURLOPT_HEADER, false); //Serverdan gelen Header bilgilerini önemseme.
            curl_setopt($ch, CURLOPT_URL, $this->testServer3d); //Baglanacagi URL
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //Transfer sonuçlarini al.
            $data = curl_exec($ch);
            curl_close($ch);
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }

        dd($data);
        echo $data;

        //$total = 0.01;

        $posnet = new \Posnet();
        $host = ('TEST' == $this->mode) ? 'test' : 'production';
        if ('kuveytturk_3d' === $this->bank) {
            //$host= ($this->mode == 'TEST') ? 'test_3d' : 'production_3d';
            $posnet = new \PosnetOOS(
                $this->posnetId,
                $this->merchantId,
                $this->terminalId,
                $this->username,
                $this->password,
                $this->key
            );
        }

        $pos = new \SanalPos\KuveytTurk\Pos(
            $posnet,
            $this->merchantId,
            $this->terminalId,
            $host
        );

        $pos->siparisAyarlari($this->order['total'], $this->order['orderId'], null);

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
            $this->password,
            $this->key
        );
        //$posnetOOS->SetDebugLevel(1);

        $posnetOOS->SetURL(
            'TEST' === $this->mode ? $this->testServer : $this->banks['kuveytturk']
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
            if ('1' != $posnetOOS->posnetOOSResponse->tds_md_status) {
                $message = $posnetOOS->posnetOOSResponse->tds_md_errormessage;
                if (!$message) {
                    $message = @$posnetOOS->arrayPosnetResponseXML['posnetResponse']['oosResolveMerchantDataResponse']['mdErrorMessage'];
                }

                return [
                    'status' => false,
                    'message' => 'Ödeme işleminde bir hata oluştu: '.$message,
                ];
            }
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

            return [
                'status' => true,
            ];

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
