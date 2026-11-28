<x-mail::message>
# Pesanan Anda sudah kami terima

Halo {{ $name }}, terima kasih sudah berbelanja di {{ $siteName }}. Pesanan akan kami proses setelah pembayaran Anda terverifikasi.

<x-mail::panel>
Nomor pesanan: **{{ $order->order_number }}**

Total pembayaran: **{{ $total }}**
@if($deadline)

Mohon selesaikan pembayaran sebelum {{ $deadline }}.
@endif
</x-mail::panel>

Buka halaman pesanan untuk memilih metode pembayaran dan menyelesaikannya. Pesanan yang belum dibayar sampai batas waktu akan dibatalkan otomatis.

<x-mail::button :url="$url">
Lihat pesanan dan bayar
</x-mail::button>

## Rincian pesanan

<x-mail::table>
| Produk | Jml | Subtotal |
| :----- | :-: | -------: |
@foreach($items as $item)
| {{ $item['name'] }} | {{ $item['qty'] }} | {{ $item['subtotal'] }} |
@endforeach
</x-mail::table>

<x-mail::table>
| Ringkasan | Jumlah |
| :-------- | -----: |
@foreach($totals as [$label, $value])
| {{ $label }} | {{ $value }} |
@endforeach
</x-mail::table>

## Dikirim ke

{{ $recipient }}

{{ $address }}

Ada pertanyaan tentang pesanan ini? Sebutkan nomor pesanan **{{ $order->order_number }}** saat menghubungi kami. Kontak ada di bagian bawah email ini.
</x-mail::message>
