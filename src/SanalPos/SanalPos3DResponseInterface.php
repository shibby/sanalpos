<?php

namespace SanalPos;

/**
 * Interface SanalPos3DResponseInterface.
 */
interface SanalPos3DResponseInterface
{
    /**
     * işlemin başarılı olması durumunda, buradaki html kodu ekrana basılacak.
     * bu ekrana basılan kod, otomatik olarak 3d doğrulama sayfasına yönlendirecek bizi.
     *
     * @return string
     */
    public function get3DHtml();
}
