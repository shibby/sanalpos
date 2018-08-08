<?php

namespace SanalPos\KuveytTurk;

use SanalPos\SanalPos3DResponseInterface;
use SanalPos\SanalPosResponseInterface;

class SanalPosResponseKuveytTurk implements SanalPosResponseInterface, SanalPos3DResponseInterface
{
    /**
     * @var Sonuc
     */
    protected $response;
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

        $this->is3D = true;
    }

    public function success()
    {
        if (!$this->response || !$this->response['status']) {
            return false;
        }

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

        return $this->response['message'];
    }

    public function threeD()
    {
        return $this->get3DHtml();
    }

    public function response()
    {
        return $this->response;
    }

    /**
     * işlemin başarılı olması durumunda, buradaki html kodu ekrana basılacak.
     * bu ekrana basılan kod, otomatik olarak 3d doğrulama sayfasına yönlendirecek bizi.
     *
     * @return string
     */
    public function get3DHtml()
    {
        return $this->response['html'];
    }
}
