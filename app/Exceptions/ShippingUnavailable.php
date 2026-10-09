<?php

namespace App\Exceptions;

use DomainException;

/** Layanan pengiriman tidak tersedia untuk tujuan/layanan yang dipilih. Pesannya aman ditampilkan ke customer. */
class ShippingUnavailable extends DomainException
{
}
