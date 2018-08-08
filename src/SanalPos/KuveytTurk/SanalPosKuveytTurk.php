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
        'kuveytturk' => 'https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelPayGate',
        'kuveytturk_3d' => 'https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelPayGate',
    ];

    protected $provisionServer = 'https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelProvisionGate';
    protected $provisionServerTest = 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelProvisionGate';

    protected $testServer = 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelPayGate';
    protected $testServer3d = 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelPayGate';
    /**
     * @var
     */
    private $bank;
    private $username;
    private $password;
    private $successUrl;
    private $failureUrl;

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

    public function setCardOwner($name)
    {
        $this->card['card_holder_name'] = $name;

        return $this;
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
        $this->order['total'] = (int) $this->order['total'] * 100;
        $orderId = $this->order['orderId'];
        $HashedPassword = base64_encode(sha1($this->password, 'ISO-8859-9')); //md5($Password);
        $HashData = base64_encode(sha1($this->merchantId.$orderId.$this->order['total'].$successUrl.$failureUrl.$this->username.$HashedPassword, 'ISO-8859-9'));

        $expiryYear = 4 === strlen($this->card['year']) ? substr($this->card['year'], 2, 2) : $this->card['year'];

        $xml = '<KuveytTurkVPosMessage xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">'
            .'<APIVersion>1.0.0.</APIVersion>'
            .'<OkUrl>'.$successUrl.'</OkUrl>'
            .'<FailUrl>'.$failureUrl.'</FailUrl>'
            .'<HashData>'.$HashData.'</HashData>'
            .'<MerchantId>'.$this->merchantId.'</MerchantId>'
            .'<CustomerId>'.$this->customerId.'</CustomerId>'
            .'<UserName>'.$this->username.'</UserName>'
            .'<CardType>'.(starts_with($this->card['number'], 5) ? 'MasterCard' : 'VISA').'</CardType>'
            .'<CardHolderName>'.$this->card['card_holder_name'].'</CardHolderName>'
            .'<CardNumber>'.$this->card['number'].'</CardNumber>'
            .'<CardExpireDateYear>'.$expiryYear.'</CardExpireDateYear>'
            .'<CardExpireDateMonth>'.$this->card['month'].'</CardExpireDateMonth>'
            .'<CardCVV2>'.$this->card['cvv'].'</CardCVV2>'
            .'<TransactionType>Sale</TransactionType>'
            .'<InstallmentCount>'.($this->order['taksit'] ?: 0).'</InstallmentCount>'
            .'<Amount>'.$this->order['total'].'</Amount>'
            .'<DisplayAmount>'.$this->order['total'].'</DisplayAmount>'
            .'<CurrencyCode>0949</CurrencyCode>'
            .'<MerchantOrderId>'.$this->order['orderId'].'</MerchantOrderId>'
            .'<TransactionSecurity>3</TransactionSecurity>'
            .'<TransactionSide>Sale</TransactionSide>'
            .'<BatchID>0</BatchID>'
            .'</KuveytTurkVPosMessage>';

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/xml', 'Content-length: '.strlen($xml)));
            curl_setopt($ch, CURLOPT_POST, true); //POST Metodu kullanarak verileri gönder
            curl_setopt($ch, CURLOPT_HEADER, false); //Serverdan gelen Header bilgilerini önemseme.
            curl_setopt($ch, CURLOPT_URL, $this->getServer()); //Baglanacagi URL
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //Transfer sonuçlarini al.
            $data = curl_exec($ch);
            curl_close($ch);
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => $e->getMessage(),
            ];
        }

        return [
            'status' => true,
            'html' => $data,
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
        $this->order['total'] = (int) $this->order['total'] * 100;
        $orderId = $this->order['orderId'];

        $HashedPassword = base64_encode(sha1($this->password, 'ISO-8859-9'));
//        $HashData = base64_encode(sha1($this->merchantId.$orderId.$this->order['total'].$this->successUrl.$this->failureUrl.$this->username.$HashedPassword, 'ISO-8859-9'));
        $HashData = base64_encode(sha1($this->merchantId.$orderId.$this->order['total'].$this->username.$HashedPassword, 'ISO-8859-9'));

//        $HashData = $postData['HashData'];

        $xml = '<KuveytTurkVPosMessage xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
				<APIVersion>1.0.0</APIVersion>
				<HashData>'.$HashData.'</HashData>
				<MerchantId>'.$this->merchantId.'</MerchantId>
				<CustomerId>'.$this->customerId.'</CustomerId>
				<UserName>'.$this->username.'</UserName>
				<TransactionType>Sale</TransactionType>
				<InstallmentCount>0</InstallmentCount>
				<Amount>'.$postData['VPosMessage']->Amount.'</Amount>
				<MerchantOrderId>'.$this->order['orderId'].'</MerchantOrderId>
				<TransactionSecurity>3</TransactionSecurity>
				<KuveytTurkVPosAdditionalData>
				<AdditionalData>
					<Key>MD</Key>
					<Data>'.$postData['MD'].'</Data>
				</AdditionalData>
			</KuveytTurkVPosAdditionalData>
			</KuveytTurkVPosMessage>';

        \Log::debug($xml);
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/xml', 'Content-length: '.strlen($xml)));
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_URL, 'TEST' === $this->mode ? $this->provisionServerTest : $this->provisionServer);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $data = curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }

        try {
            $xxml = @simplexml_load_string($data);
            if (!$xxml) {
                return [
                    'success' => false,
                    'message' => 'XML Hatası',
                ];
            }
        } catch (\Exception $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }

        return $xxml;
    }

    /**
     * @param $successUrl
     * @param $failureUrl
     *
     * @return mixed
     */
    private function send3d($successUrl, $failureUrl)
    {
    }
}
