<?php

namespace SanalPos\YapiKredi;

use SanalPos\SanalPos3DResponseInterface;
use SanalPos\SanalPosResponseInterface;

class SanalPosResponseYapiKredi implements SanalPosResponseInterface, SanalPos3DResponseInterface
{
    /**
     * @var Sonuc
     */
    protected $response;
    protected $xml;
    private $is3D = false;
    /**
     * @var
     */
    private $merchantReturnUrl;
    /**
     * @var
     */
    private $mode;

    public function __construct($response, $merchantReturnUrl, $mode)
    {
        $this->response = $response;
        $this->merchantReturnUrl = $merchantReturnUrl;
        $this->mode = $mode;
    }

    public function success()
    {
        if (!$this->response) {
            return false;
        }

        if ($this->response instanceof \PosnetOOS) {
            $this->is3D = true;

            return @$this->response->arrayPosnetResponseXML['posnetResponse']['approved'];
        }

        if (!$this->response->posnet->posnetResponse->approved) {
            return false;
        }

        /*if (@$this->xml->Message) {
            $this->is3D = true;

            return $this->xml->Message->VERes->Status && $this->xml->Message->VERes->Status->__toString() === 'Y';
        }*/

        return true;
    }

    public function errors()
    {
        if (!$this->response) {
            return 'No response';
        }
        if ($this->success()) {
            return [];
        }
        if ($this->is3D) {
            return @$this->response->respText;
        }

        $message = $this->response->posnet->arrayPosnetResponseXML['posnetResponse']['respText'];

        return substr(mb_convert_encoding($message, 'UTF-8'), 0, 50);
    }

    public function threeD()
    {
        return $this->get3DHtml();
    }

    public function response()
    {
        return $this->xml;
    }

    /**
     * işlemin başarılı olması durumunda, buradaki html kodu ekrana basılacak.
     * bu ekrana basılan kod, otomatik olarak 3d doğrulama sayfasına yönlendirecek bizi.
     *
     * @return string
     */
    public function get3DHtml()
    {
        $data1 = ($this->response->posnetOOSResponse->data1);
        $data2 = ($this->response->posnetOOSResponse->data2);
        $sign = ($this->response->posnetOOSResponse->sign);

        $mid = $this->response->merchantInfo->mid;
        $posnetid = $this->response->merchantInfo->posnetid;

        $postUrl = 'http://setmpos.ykb.com/3DSWebService/YKBPaymentService';
        if ($this->mode !== 'TEST') {
            $postUrl = 'https://posnet.ykb.com/3DSWebService/YKBPaymentService';
        }
        //todo: make config these urls.

        $html = '<form name="downloadForm" action="'.$postUrl.'" method="POST">';
        $html .= '
        <input name="posnetData" type="hidden" id="posnetData" value="'.$data1.'">
      <input name="posnetData2" type="hidden" id="posnetData2" value="'.$data2.'">
      <input name="mid" type="hidden" id="mid" value="'.$mid.'">
      <input name="posnetID" type="hidden" id="posnetID" value="'.$posnetid.'">
      <input name="digest" type="hidden" id="sign" value="'.$sign.'">
      <!--<input name="vftCode" type="hidden" id="vftCode" value="<?php echo $vftCode; ?>">-->
      <input name="merchantReturnURL" type="hidden" id="merchantReturnURL" value="'.$this->merchantReturnUrl.'">';
        $html .= '</form>';
        $html .= '<SCRIPT LANGUAGE="Javascript">document.downloadForm.submit();</SCRIPT>';

        //dd($html);

        return $html;
    }
}
