<?php
/**
 * Created by Sinan Taga.
 * User: sinan
 * Date: 31/05/14
 * Time: 18:21.
 */

namespace SanalPos;

class SanalPosBase
{
    protected $mode = 'PROD';

    protected $card = [];
    protected $order = [];

    protected $transactionMode = 'Auth';
    protected $currency = 949;

    protected $server;

    public function setCard($number, $expMonth, $expYear, $cvv)
    {
        $this->card['number'] = $number;
        $this->card['month'] = str_pad($expMonth, 2, 0, STR_PAD_LEFT);
        $this->card['year'] = str_pad($expYear, 2, 0, STR_PAD_LEFT);
        $this->card['cvv'] = $cvv;
    }

    public function setOrder($orderId, $customerEmail, $total, $taksit = '', $extra = [])
    {
        $this->order['orderId'] = $orderId;
        $this->order['email'] = $customerEmail;
        $this->order['total'] = $total;
        $this->order['taksit'] = $taksit;
        $this->order['extra'] = $extra;
    }

    /**
     * Gets the operation mode
     * TEST for test mode.
     *
     * @return mixed
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * Gets the operation mode
     * TEST for test mode everything else is production mode.
     *
     * @param $mode
     *
     * @return mixed
     */
    public function setMode($mode)
    {
        $this->mode = $mode;

        return $this->mode;
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * DEPRECATED: use getCurrency method
     */
    public function getCurreny()
    {
        return $this->getCurrency();
    }

    public function setCurrency($currency)
    {
        // 949 TL, 840 USD, 978 EURO, 826 GBP, 392 JPY
        $availableCurrencies = [949, 840, 978, 826, 392];
        if (!in_array($currency, $availableCurrencies)) {
            throw new \Exception('Currency not found!');
        }
        $this->currency = $currency;

        return $this->getCurrency();
    }

    public function check()
    {
        return true;
    }

    public function checkExpiration()
    {
    }

    public function checkCard()
    {
    }

    public function checkCvv()
    {
        // /^[0-9]{3,4}$/
        return preg_match('/^[0-9]{3,4}$/', $this->card['cvv']);
    }

    public function checkLuhn()
    {
    }

    public function getIpAddress()
    {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']) && $_SERVER['HTTP_CLIENT_IP']) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR']) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED']) && $_SERVER['HTTP_X_FORWARDED']) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR']) && $_SERVER['HTTP_FORWARDED_FOR']) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED']) && $_SERVER['HTTP_FORWARDED']) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR']) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }

        return $ipaddress;
    }
}
