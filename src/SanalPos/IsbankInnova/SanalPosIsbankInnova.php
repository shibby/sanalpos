<?php

namespace SanalPos\IsbankInnova;

use SanalPos\SanalPos3DInterface;
use SanalPos\SanalPosBase;
use SanalPos\SanalPosInterface;
use SimpleXMLElement;

class SanalPosIsbankInnova extends SanalPosBase implements SanalPosInterface, SanalPos3DInterface
{
    protected $xml;
    protected $merchantId;

    protected $banks = [
        'isbank' => 'https://trx.vpos.isbank.com.tr/v3/Vposreq.aspx',
        'isbank_3d' => 'https://mpi.vpos.isbank.com.tr/MPIEnrollment.aspx',
    ];

    protected $testServer = 'https://sanalpos.innova.com.tr/ISBANK_v4/VposWeb/v3/Vposreq.aspx';
    protected $testServer3d = 'https://sanalpos.innova.com.tr/ISBANK/MpiWeb/Enrollment.aspx';
    /**
     * @var
     */
    private $bank;
    private $password;

    /**
     * SanalPosIsbankInnova constructor.
     *
     * @param $bank isbank|isbank_3d
     * @param $merchantId
     * @param $password
     *
     * @throws \Exception
     */
    public function __construct(
        $bank,
        $merchantId,
        $password
    ) {
        if (!array_key_exists($bank, $this->banks)) {
            throw new \Exception('Bilinmeyen Banka');
        } else {
            $this->server = $this->banks[$bank];
        }
        $this->merchantId = $merchantId;
        $this->password = $password;
        $this->bank = $bank;
    }

    public function getServer()
    {
        if ($this->bank === 'isbank') {
            $this->server = $this->mode == 'TEST' ? $this->testServer : $this->banks['isbank'];
        } elseif ($this->bank === 'isbank_3d') {
            $this->server = $this->mode == 'TEST' ? $this->testServer3d : $this->banks['isbank_3d'];
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
        if ($this->bank === 'isbank_3d') {
            return $this->send3d($successUrl, $failureUrl);
        }
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
        if ($postData['Status'] !== 'Y') {
            return [
                'status' => false,
                'message' => $postData['ErrorMessage'],
            ];
        }

        $total = (float) $this->order['total'];
        $total = number_format($total, 2, '.', '');
        $tutar = $total;
        $krediKartiNumarasi = $this->card['number'];
        $sonKullanmaTarihi = $this->card['year'].$this->card['month'];
        $kartCvv = $this->card['cvv'];

        $PosXML = 'prmstr=<VposRequest><ECI>'.$postData['Eci'].'</ECI>
         <CAVV>'.$postData['Cavv'].'</CAVV>
         <MpiTransactionId>'.$postData['VerifyEnrollmentRequestId'].'</MpiTransactionId>
         <MerchantId>'.$this->merchantId.'</MerchantId>
         <Password>'.$this->password.'</Password>
         <TransactionType>Sale</TransactionType>
         <TransactionId>'.$this->order['orderId'].'</TransactionId>
         <CurrencyAmount>'.$tutar.'</CurrencyAmount>
         <Pan>'.$krediKartiNumarasi.'</Pan>
         <Cvv>'.$kartCvv.'</Cvv>
         <Expiry>'.$sonKullanmaTarihi.'</Expiry>
         <CurrencyCode>949</CurrencyCode>
         <TransactionDeviceSource>0</TransactionDeviceSource>
         <ClientIp>'.\Request::getClientIp().'</ClientIp>
         </VposRequest>';

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->mode === 'TEST' ? $this->testServer : $this->banks['isbank']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $PosXML);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 59);
        //curl_setopt($ch, curl.options,array("CURLOPT_SSLVERSION"=>"CURL_SSLVERSION_DEFAULT"));
        curl_setopt($ch, CURLOPT_SSLVERSION, 6);
        //curl_setopt($ch, CURLOPT_CAINFO, 'C:/wamp64/www/cacert.pem');

        $result = curl_exec($ch);
        // Check for errors and display the error message
        if ($errno = curl_errno($ch)) {
            $error_message = curl_strerror($errno);
            //echo "cURL error ({$errno}):\n"; // {$error_message}";
            return [
                'status' => false,
                'message' => $error_message,
            ];
        }
        curl_close($ch);

        $this->xml = new SimpleXMLElement($result);
        if ($this->xml->ResultCode == '0000') {
            return [
                'status' => true,
            ];
        }

        if (!empty($this->xml->ErrorMessage)) {
            $message = $this->xml->ErrorMessage;
        } elseif (!empty($this->xml->ResultDetail)) {
            $message = $this->xml->ResultCode.': '.$this->xml->ResultDetail;
        } elseif (!empty($this->xml->ResultCode)) {
            $message = 'Hata: '.$this->errorCode((string) $this->xml->ResultCode);
        }

        return [
            'status' => false,
            'message' => $message,
        ];
    }

    private function send3d($successUrl, $failureUrl)
    {
        $krediKartiNumarasi = $this->card['number'];
        $sonKullanmaTarihi = mb_substr($this->card['year'], 2, 2).$this->card['month'];
        $kartCvv = $this->card['cvv'];
        $kartTipi = mb_substr($this->card['number'], 0, 1) == 4 ? 100 : 200;
        $total = (float) $this->order['total'];
        $total = number_format($total, 2, '.', '');
        $tutar = $total;
        $paraKodu = 949;
        $taksitSayisi = $this->order['taksit'] ?: null;
        $islemNumarasi = $this->order['orderId'];
        $uyeIsyeriNumarasi = $this->merchantId;
        $uyeIsYeriSifresi = $this->password;
        $SuccessURL = $successUrl;
        $FailureURL = $failureUrl;
        //$ekVeri = $_POST['SessionInfo']; // Optional

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getServer());
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type' => 'application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURLOPT_CAINFO, __DIR__.'/cacert.pem');
        curl_setopt($ch, CURLOPT_POSTFIELDS, "Pan=$krediKartiNumarasi&Cvv=$kartCvv&ExpiryDate=$sonKullanmaTarihi&PurchaseAmount=$tutar&Currency=$paraKodu&BrandName=$kartTipi&VerifyEnrollmentRequestId=$islemNumarasi&MerchantId=$uyeIsyeriNumarasi&MerchantPassword=$uyeIsYeriSifresi&SuccessUrl=$SuccessURL&FailureUrl=$FailureURL&InstallmentCount=$taksitSayisi");
        //İhtiyaç olması halinde aşağıdaki gibi proxy açabilirsiniz.
        /*$proxy = "iproxy:8080";
        $proxy = explode(':', $proxy);
        curl_setopt($ch, CURLOPT_PROXY, $proxy[0]);
        curl_setopt($ch, CURLOPT_PROXYPORT, $proxy[1]);*/

        // İşlem isteği MPI'a gönderiliyor
        $resultXml = curl_exec($ch);
        // Check for errors and display the error message
        if ($errno = curl_errno($ch)) {
            curl_close($ch);

            return [
                'status' => 'error',
                'message' => "cURL error ({$errno}):",
            ];
        }
        curl_close($ch);

        return $resultXml;
    }

    public function errorCode($code)
    {
        $string = '1001 Sistem hatası.
1006 Bu transactionId ile daha önce başarılı bir işlem gerçekleştirilmiş
1007 Referans transaction alınamadı
1046 İade işleminde tutar hatalı.
1047 İşlem tutarı geçersizdir.
1049 Geçersiz tutar.
1050 CVV hatalı.
1051 Kredi kartı numarası hatalıdır.
1052 Kredi kartı son kullanma tarihi hatalı.
1054 İşlem numarası hatalıdır.
1059 Yeniden iade denemesi.
1060 Hatalı taksit sayısı.
2200 İş yerinin işlem için gerekli hakkı yok.
2202 İşlem iptal edilemez. ( Batch Kapalı )
5001 İş yeri şifresi yanlış.
5002 İş yeri aktif değil.
1073 Terminal üzerinde aktif olarak bir batch bulunamadı
1074 İşlem henüz sonlanmamış yada referans işlem henüz tamamlanmamış.
1075 Sadakat puan tutarı hatalı
1076 Sadakat puan kodu hatalı
1077 Para kodu hatalı
1078 Geçersiz sipariş numarası
1079 Geçersiz sipariş açıklaması
1080 Sadakat tutarı ve para tutarı gönderilmemiş.
1061 Aynı sipariş numarasıyla daha önceden başarılı işlem yapılmış
1065 Ön provizyon daha önceden kapatılmış
1082 Geçersiz işlem tipi
1083 Referans işlem daha önceden iptal edilmiş.1084 Geçersiz poaş kart numarası
7777 Banka tarafında gün sonu yapıldığından işlem gerçekleştirilemedi
1087 Yabancı para birimiyle taksitli provizyon kapama işlemi yapılamaz
1088 Önprovizyon iptal edilmiş
1089 Referans işlem yapılmak istenen işlem için uygun değil
1091 Recurring işlemin toplam taksit sayısı hatalı
1092 Recurring işlemin tekrarlama aralığı hatalı
1093 Sadece Satış (Sale) işlemi recurring olarak işaretlenebilir
1001 Sistem hatası.
1006 Bu transactionId ile daha önce başarılı bir işlem gerçekleştirilmiş
1095 Lütfen geçerli bir email adresi giriniz
1096 Lütfen geçerli bir IP adresi giriniz
1097 Lütfen geçerli bir CAVV değeri giriniz
1098 Lütfen geçerli bir ECI değeri giriniz.
1099 Lütfen geçerli bir Kart Sahibi ismi giriniz.
1100 Lütfen geçerli bir brand girişi yapın.
1102 Recurring işlem aralık tipi hatalı bir değere sahip
1101 Referans transaction reverse edilmiş.';

        $codes = [];
        $rows = explode("\n", $string);
        foreach ($rows as $row) {
            $row = explode(' ', $row);
            $key = $row[0];
            unset($row[0]);

            $codes[$key] = implode(' ', $row);
        }

        return @$codes[$code];
    }
}
