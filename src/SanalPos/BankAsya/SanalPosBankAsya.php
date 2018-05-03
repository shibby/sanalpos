<?php

namespace SanalPos\Vakifbank;

use SanalPos\SanalPosBase;
use SanalPos\SanalPosInterface;

class SanalPosBankAsya extends SanalPosBase implements SanalPosInterface
{
    protected $xml;
    protected $merchantId;
    protected $password;

    protected $banks = [
        'bankasya' => 'vps.bankasya.com.tr/iposnet/sposnet.aspx',
    ];

    protected $testServer = 'vpstest.bankasya.com.tr/iposnet/sposnet.aspx';

    public function __construct($bank, $merchantId, $password)
    {
        if (!array_key_exists($bank, $this->banks)) {
            throw new \Exception('Bilinmeyen Banka');
        } else {
            $this->server = $this->banks[$bank];
        }
        $this->merchantId = $merchantId;
        $this->password = $password;
    }

    public function getServer()
    {
        $this->server = 'TEST' == $this->mode ? 'https://'.$this->testServer : 'https://'.$this->server;

        return $this->server;
    }

    public function pay($pre = false, $successUrl = null, $failureUrl = null)
    {
        //TODO: domelement ile oluştur.
        $xml = '<?xml version="1.0" encoding="ISO-8859-9" ?>
        <ePaymentMsg VersionInfo="2.0" TT="Request" RM="Direct" CT="Money">
            <Operation ActionType="Sale">
                <OpData>
                    <MerchantInfo MerchantId="'.$this->merchantId.'" MerchantPassword="'.$this->password.'"/>
                    <ActionInfo>
                        <TrnxCommon TrnxID="'.$this->order['orderId'].'">
                            <AmountInfo Amount="'.$this->order['total'].'" Currency="949"/>
                        </TrnxCommon>
                        <PaymentTypeInfo>
                            <InstallmentInfo NumberOfInstallments="'.$this->order['taksit'].'"/>
                        </PaymentTypeInfo>
                    </ActionInfo>
                    <PANInfo PAN="'.$this->card['number'].'" ExpiryDate="'.$this->card['year'].$this->card['month'].'" CVV2="'.$this->card['cvv'].'" BrandID="VISA"/>
                    <OrgTrnxInfo/>
                    <CardHolderIP>'.$this->getIpAddress().'</CardHolderIP>
                </OpData>
            </Operation>
        </ePaymentMsg>
        ';
        $this->xml = $xml;

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
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-type' => 'application/x-www-form-urlencoded']);
        $response = curl_exec($curl);
        curl_close($curl);

        return $response;
    }
}
