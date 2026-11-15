<?php

namespace App\Exceptions;

use RuntimeException;

/** Promo berakhir / kuota habis / harga promo berubah antara keranjang dan checkout. */
class FlashSaleChanged extends RuntimeException
{
}
