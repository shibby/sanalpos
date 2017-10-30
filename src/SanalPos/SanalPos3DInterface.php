<?php

namespace SanalPos;

/**
 * Interface SanalPos3DInterface.
 */
interface SanalPos3DInterface
{
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
    public function provision3d(array $postData);
}
