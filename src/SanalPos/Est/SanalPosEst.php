<?php
/**
 * Created by Sinan Taga.
 * User: sinan
 * Date: 31/05/14
 * Time: 21:39.
 */

namespace SanalPos\Est;

use SanalPos\SanalPos3DInterface;
use SanalPos\SanalPosBase;
use SanalPos\SanalPosInterface;
use DOMDocument;

class SanalPosEst extends SanalPosBase implements SanalPosInterface, SanalPos3DInterface
{
    protected $xml;
    protected $clientId;
    protected $username;
    protected $password;
    private $storeKey;

    private $isThreeD = false;
    private $postData = [];

    protected $banks = [
        'isbank' => 'spos.isbank.com.tr',
        'akbank' => 'www.sanalakpos.com',
        'akbank_3d' => 'www.sanalakpos.com',
        'finansbank' => 'www.fbwebpos.com',
        'halkbank' => 'sanalpos.halkbank.com.tr',
        'anadolubank' => 'anadolusanalpos.est.com.tr',
    ];

    protected $testServer = 'entegrasyon.asseco-see.com.tr';
    protected $testServer3D = 'entegrasyon.asseco-see.com.tr';

    public function __construct($bank, $clientId, $username, $password, $storeKey)
    {
        if (!array_key_exists($bank, $this->banks)) {
            throw new \Exception('Bilinmeyen Banka');
        } else {
            $this->server = $this->banks[$bank];
        }
        $this->clientId = $clientId;
        $this->username = $username;
        $this->password = $password;
        $this->storeKey = $storeKey;
        if (str_contains($bank, '_3d')) {
            $this->isThreeD = true;
        }
    }

    public function getServer()
    {
        if ($this->isThreeD) {
            $this->server = 'TEST' == $this->mode ? 'https://'.$this->testServer3D.'/fim/est3Dgate' : 'https://'.$this->server.'/fim/est3Dgate';
        } else {
            $this->server = 'TEST' == $this->mode ? 'https://'.$this->testServer.'/fim/api' : 'https://'.$this->server.'/fim/api';
        }

        $this->server = str_replace('https://https://', 'https://', $this->server);

        return $this->server;
    }

    public function pay($pre = false, $successUrl = null, $failureUrl = null)
    {
        $mode = $pre ? 'PreAuth' : 'Auth';
        // Prepare XML CC5Request request
        $dom = new DOMDocument('1.0', 'ISO-8859-9');
        $root = $dom->createElement('CC5Request');

        // First level elements

        if ($this->isThreeD) {
            $rnd = microtime();    //Tarih veya her seferinde degisen bir deger güvenlik amaçli
            $storekey = $this->storeKey;  //isyeri anahtari

            $hashstr = $this->clientId.$this->order['orderId'].$this->order['total'].$successUrl.$failureUrl.$rnd.$storekey;
            $hash = base64_encode(pack('H*', sha1($hashstr)));

            $x['storetype'] = $dom->createElement('storetype', '3d');
            $x['okUrl'] = $dom->createElement('okUrl', $successUrl);
            $x['failUrl'] = $dom->createElement('failUrl', $failureUrl);
            $x['oid'] = $dom->createElement('oid', $this->order['orderId']);
            $x['rnd'] = $dom->createElement('rnd', $rnd);
            $x['hash'] = $dom->createElement('hash', $hash);

            $x['pan'] = $dom->createElement('pan', $this->card['number']);
            $x['Ecom_Payment_Card_ExpDate_Year'] = $dom->createElement('Ecom_Payment_Card_ExpDate_Year', $this->card['year']);
            $x['Ecom_Payment_Card_ExpDate_Month'] = $dom->createElement('Ecom_Payment_Card_ExpDate_Month', $this->card['month']);
            $x['cv2'] = $this->card['cvv'];
            $x['cardType'] = $dom->createElement('cardType', ($this->card['number'][0] === 4) ? 1 : 2);
            $x['amount'] = $dom->createElement('amount', $this->order['total']);
            $x['clientid'] = $dom->createElement('clientid', $this->clientId);
        } else {
            $x['islemtipi'] = $dom->createElement('islemtipi', 'Auth');
            $x['number'] = $dom->createElement('Number', $this->card['number']);
            $x['expires'] = $dom->createElement('Expires', $this->card['month'].$this->card['year']);
            $x['cvv'] = $dom->createElement('Cvv2Val', $this->card['cvv']);
            $x['orderId'] = $dom->createElement('OrderId', $this->order['orderId']);
            $x['type'] = $dom->createElement('Type', $mode);
            $x['mode'] = $dom->createElement('Mode', 'P');
            $x['transId'] = $dom->createElement('TransId', '');
            $x['taksit'] = $dom->createElement('Taksit', $this->order['taksit']);
            $x['name'] = $dom->createElement('Name', $this->username);
            $x['password'] = $dom->createElement('Password', $this->password);
            $x['total'] = $dom->createElement('Total', $this->order['total']);
            $x['clientId'] = $dom->createElement('ClientId', $this->clientId);
        }
        $x['currency'] = $dom->createElement('Currency', $this->getCurreny());

        $x['email'] = $dom->createElement('Email', $this->order['email']);

        $x['ip'] = $dom->createElement('IPAddress', $this->getIpAddress());
        /*$x['billTo']    = $dom->createElement('BillTo');
        $x['shipTo']    = $dom->createElement('ShipTo');*/

        foreach ($x as $node) {
            if ($node instanceof \DOMElement) {
                $root->appendChild($node);
            }
        }
        $dom->appendChild($root);

        if ($this->isThreeD) {
            $this->postData = [];
            /**
             * @var string
             * @var \DOMElement $node
             */
            foreach ($x as $k => $node) {
                $this->postData[$k] = $node->nodeValue ?? $node;
            }
        }

        $this->xml = $dom->saveXML();

        return $this->send();
    }

    public function postAuth($orderId)
    {
        $dom = new DOMDocument('1.0', 'ISO-8859-9');
        $root = $dom->createElement('CC5Request');

        $x['name'] = $dom->createElement('Name', $this->username);
        $x['$password'] = $dom->createElement('Password', $this->password);
        $x['clientId'] = $dom->createElement('ClientId', $this->clientId);
        $x['type'] = $dom->createElement('Type', 'PostAuth');
        $x['orderId'] = $dom->createElement('OrderId', $orderId);

        foreach ($x as $node) {
            $root->appendChild($node);
        }
        $dom->appendChild($root);

        $this->xml = $dom->saveXML();

        return $this->send();
    }

    public function cancel($orderId)
    {
        $dom = new DOMDocument('1.0', 'ISO-8859-9');
        $root = $dom->createElement('CC5Request');

        $x['name'] = $dom->createElement('Name', $this->username);
        $x['$password'] = $dom->createElement('Password', $this->password);
        $x['clientId'] = $dom->createElement('ClientId', $this->clientId);
        $x['type'] = $dom->createElement('Type', 'Void');
        $x['orderId'] = $dom->createElement('OrderId', $orderId);

        foreach ($x as $node) {
            $root->appendChild($node);
        }
        $dom->appendChild($root);

        $this->xml = $dom->saveXML();

        return $this->send();
    }

    public function refund($orderId, $amount = null)
    {
        $dom = new DOMDocument('1.0', 'ISO-8859-9');
        $root = $dom->createElement('CC5Request');

        $x['name'] = $dom->createElement('Name', $this->username);
        $x['$password'] = $dom->createElement('Password', $this->password);
        $x['clientId'] = $dom->createElement('ClientId', $this->clientId);
        $x['type'] = $dom->createElement('Type', 'Credit');
        if ($amount) {
            $x['amount'] = $dom->createElement('Total', $amount);
        }
        $x['orderId'] = $dom->createElement('OrderId', $orderId);

        foreach ($x as $node) {
            $root->appendChild($node);
        }
        $dom->appendChild($root);

        $this->xml = $dom->saveXML();

        return $this->send();
    }

    public function send()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->getServer());
        if ($this->isThreeD) {
            //dump($this->postData);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($this->postData));
        } else {
            curl_setopt($curl, CURLOPT_POSTFIELDS, 'data='.$this->xml);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
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
        $hashparams = $postData['HASHPARAMS'];
        $hashparamsval = $postData['HASHPARAMSVAL'];
        $hashparam = $postData['HASH'];
        $paramsval = '';
        $index1 = 0;
        $index2 = 0;

        while ($index1 < strlen($hashparams)) {
            $index2 = strpos($hashparams, ':', $index1);
            $vl = $postData[substr($hashparams, $index1, $index2 - $index1)];
            if (null == $vl) {
                $vl = '';
            }
            $paramsval = $paramsval.$vl;
            $index1 = $index2 + 1;
        }
        $hashval = $paramsval.$this->storeKey;

        $hash = base64_encode(pack('H*', sha1($hashval)));

        if ($paramsval != $hashparamsval || $hashparam != $hash) {
            return [
                'status' => false,
                'message' => 'Güvenlik Uyarisi. Sayisal Imza Geçerli Degil',
            ];
        }

//             ÖDEME ISLEMI ALANLARI
        $name = $this->username;       		//is yeri kullanic adi
        $password = $this->password;    		//Is yeri sifresi
        $clientid = $this->clientId;  		//Is yeri numarasi

        $mode = 'P';                            //P olursa gerçek islem, T olursa test islemi yapar
        if ('TEST' === $this->mode) {
            $mode = 'T';
        }
        $type = 'Auth';   			//Auth: Satýþ PreAuth: Ön Otorizasyon
        $expires = $postData['Ecom_Payment_Card_ExpDate_Month'].'/'.$postData['Ecom_Payment_Card_ExpDate_Year']; //Kredi Karti son kullanim tarihi mm/yy formatindan olmali
        $cv2 = $postData['cv2'] ?? $this->card['cvv'];                     //Kart guvenlik kodu
        $tutar = $postData['amount'];                // Islem tutari
        $taksit = $this->order['taksit'] ? $this->order['taksit'] : '';           			//Taksit sayisi Pesin satislarda bos gonderilmelidir, "0" gecerli sayilmaz.
        $oid = $postData['oid'];			//Siparis numarasy her islem icin farkli olmalidir ,
        //bos gonderilirse sistem bir siparis numarasi üretir.

        $lip = $this->getIpAddress();  	//Son kullanici IP adresi
        $email = $this->order['email'];  				//Email

        //Provizyon alinamadigi durumda taksit sayisi degistirilirse sipari numarasininda
        //degistirilmesi gerekir.
        $mdStatus = $postData['mdStatus'];       // 3d Secure iþleminin sonucu mdStatus 1,2,3,4 ise baþarýlý 5,6,7,8,9,0 baþarýsýzdýr
        // 3d Decure iþleminin sonucu baþarýsýz ise iþlemi provizyona göndermeyiniz (XML göndermeyiniz).
        $xid = $postData['xid'];                 // 3d Secure özel alani PayerTxnId
        $eci = $postData['eci'];                 // 3d Secure özel alani PayerSecurityLevel
        $cavv = $postData['cavv'];               // 3d Secure özel alani PayerAuthenticationCode
        $md = $postData['md'];                   // Eðer 3D iþlembaþarýlýsya provizyona kart numarasý yerine md deðeri gönderilir.
        // Son kullanma tarihi ve cvv2 gönderilmez.

        if ('1' == $mdStatus || '2' == $mdStatus || '3' == $mdStatus || '4' == $mdStatus) {
            //echo "<h5>3D Islemi Basarili</h5><br/>";

            // XML request sablonu
            $request = 'DATA=<?xml version="1.0" encoding="ISO-8859-9"?>'.
                '<CC5Request>'.
                '<Name>{NAME}</Name>'.
                '<Password>{PASSWORD}</Password>'.
                '<ClientId>{CLIENTID}</ClientId>'.
                '<IPAddress>{IP}</IPAddress>'.
                '<Email>{EMAIL}</Email>'.
                '<Mode>'.$mode.'</Mode>'.
                '<OrderId>{OID}</OrderId>'.
                '<GroupId></GroupId>'.
                '<TransId></TransId>'.
                '<UserId></UserId>'.
                '<Type>{TYPE}</Type>'.
                '<Number>{MD}</Number>'.
                '<Expires>'.$expires.'</Expires>'.
                '<Cvv2Val>'.$cv2.'</Cvv2Val>'.
                '<Total>{TUTAR}</Total>'.
                '<Currency>949</Currency>'.
                '<Taksit>{TAKSIT}</Taksit>'.
                '<PayerTxnId>{XID}</PayerTxnId>'.
                '<PayerSecurityLevel>{ECI}</PayerSecurityLevel>'.
                '<PayerAuthenticationCode>{CAVV}</PayerAuthenticationCode>'.
                '<CardholderPresentCode>13</CardholderPresentCode>'.
                '<BillTo>'.
                '<Name></Name>'.
                '<Street1></Street1>'.
                '<Street2></Street2>'.
                '<Street3></Street3>'.
                '<City></City>'.
                '<StateProv></StateProv>'.
                '<PostalCode></PostalCode>'.
                '<Country></Country>'.
                '<Company></Company>'.
                '<TelVoice></TelVoice>'.
                '</BillTo>'.
                '<ShipTo>'.
                '<Name></Name>'.
                '<Street1></Street1>'.
                '<Street2></Street2>'.
                '<Street3></Street3>'.
                '<City></City>'.
                '<StateProv></StateProv>'.
                '<PostalCode></PostalCode>'.
                '<Country></Country>'.
                '</ShipTo>'.
                '<Extra></Extra>'.
                '</CC5Request>';

            $request = str_replace('{NAME}', $name, $request);
            $request = str_replace('{PASSWORD}', $password, $request);
            $request = str_replace('{CLIENTID}', $clientid, $request);
            $request = str_replace('{IP}', $lip, $request);
            $request = str_replace('{OID}', $oid, $request);
            $request = str_replace('{TYPE}', $type, $request);
            $request = str_replace('{XID}', $xid, $request);
            $request = str_replace('{ECI}', $eci, $request);
            $request = str_replace('{CAVV}', $cavv, $request);
            $request = str_replace('{MD}', $md, $request);
            $request = str_replace('{TUTAR}', $tutar, $request);
            $request = str_replace('{TAKSIT}', $taksit, $request);
            $request = str_replace('{EMAIL}', $email, $request);

            // Sanal pos adresine baglanti kurulmasi

            //dd($request);
            $url = $this->getServer();  //TEST
            $ch = curl_init();    // initialize curl handle

            curl_setopt($ch, CURLOPT_URL, $url); // set url to post to
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            //curl_setopt($ch, CURLOPT_SSLVERSION, 4);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return into a variable
            curl_setopt($ch, CURLOPT_TIMEOUT, 90); // times out after 90s
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request); // add POST fields

            // Buraya mdStatusa göre bir kontrol koymalisiniz.
            // 3d Secure iþleminin sonucu mdStatus 1,2,3,4 ise baþarýlý 5,6,7,8,9,0 baþarýsýzdýr
            // 3d Decure iþleminin sonucu baþarýsýz ise iþlemi provizyona göndermeyiniz (XML göndermeyiniz).

            $result = curl_exec($ch); // run the whole process
            //echo htmlspecialchars($result);

            if (curl_errno($ch)) {
                return [
                    'status' => false,
                    'message' => curl_error($ch),
                ];
            } else {
                curl_close($ch);
            }

            $TransId = '';

            $response_tag = 'Response';
            $posf = strpos($result, ('<'.$response_tag.'>'));
            $posl = strpos($result, ('</'.$response_tag.'>'));
            $posf = $posf + strlen($response_tag) + 2;
            $Response = substr($result, $posf, $posl - $posf);

            $response_tag = 'OrderId';
            $posf = strpos($result, ('<'.$response_tag.'>'));
            $posl = strpos($result, ('</'.$response_tag.'>'));
            $posf = $posf + strlen($response_tag) + 2;
            $OrderId = substr($result, $posf, $posl - $posf);

            $response_tag = 'AuthCode';
            $posf = strpos($result, '<'.$response_tag.'>');
            $posl = strpos($result, '</'.$response_tag.'>');
            $posf = $posf + strlen($response_tag) + 2;
            $AuthCode = substr($result, $posf, $posl - $posf);

            $response_tag = 'ProcReturnCode';
            $posf = strpos($result, '<'.$response_tag.'>');
            $posl = strpos($result, '</'.$response_tag.'>');
            $posf = $posf + strlen($response_tag) + 2;
            $ProcReturnCode = substr($result, $posf, $posl - $posf);

            $response_tag = 'ErrMsg';
            $posf = strpos($result, '<'.$response_tag.'>');
            $posl = strpos($result, '</'.$response_tag.'>');
            $posf = $posf + strlen($response_tag) + 2;
            $ErrMsg = substr($result, $posf, $posl - $posf);

            $response_tag = 'HostRefNum';
            $posf = strpos($result, '<'.$response_tag.'>');
            $posl = strpos($result, '</'.$response_tag.'>');
            $posf = $posf + strlen($response_tag) + 2;
            $HostRefNum = substr($result, $posf, $posl - $posf);

            $response_tag = 'TransId';
            $posf = strpos($result, '<'.$response_tag.'>');
            $posl = strpos($result, '</'.$response_tag.'>');
            $posf = $posf + strlen($response_tag) + 2;
            $$TransId = substr($result, $posf, $posl - $posf);

            if ('Approved' === $Response) {
                return [
                    'success' => true,
                    'message' => 'Ödeme isleminiz basariyla gerçeklestirildi',
                ];
            } else {
                return [
                    'status' => false,
                    'message' => 'Ödeme isleminiz basariyla gerçeklestirilmedi. Hata: '.$ErrMsg,
                ];
            }
        }

        return [
            'status' => true,
            'message' => '3D islemi onay almadi',
        ];
    }
}
