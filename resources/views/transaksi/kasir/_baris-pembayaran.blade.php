{{-- Rincian pembayaran pada struk.

     Ditulis SEKALI dan dipakai KETIGA tata letak (Simple, Tabel/Nota, Invoice). Sebelumnya
     baris ini disalin dua kali dengan isi persis sama. Menambah rincian metode ke salah
     satunya saja akan membuat transaksi yang sama mencetak keterangan berbeda tergantung
     kertas yang dipilih di Pengaturan - dan tidak akan ada yang membandingkan keduanya.

     KENAPA NOMINALNYA BEDA ANTAR CABANG.

     Pembayaran sekali jalan mencetak `paid_amount` - uang yang BENAR-BENAR DISERAHKAN
     pembeli - supaya barisnya tetap cocok dengan baris "Kembali" di bawahnya. Struk yang
     menulis "Bayar 43.000 / Kembali 7.000" untuk uang Rp 50.000 akan dibantah pembeli di
     tempat, dan mereka benar.

     Pembayaran bertahap mencetak nominal tiap penerimaan apa adanya: di sana tidak ada
     kembalian yang perlu dicocokkan, dan yang ingin dilihat justru rinciannya. --}}
@php
    $daftarBayar = $sale->relationLoaded('payments') ? $sale->payments : collect();
@endphp

@if($sale->order_status === 'waiting')
    @forelse($daftarBayar as $bayar)
        <tr><td>DP ({{ $bayar->label_metode }})</td><td class="right">{{ number_format($bayar->amount, 0, ',', '.') }}</td></tr>
    @empty
        <tr><td>DP Dibayar</td><td class="right">{{ number_format($sale->paid_amount, 0, ',', '.') }}</td></tr>
    @endforelse
    <tr style="font-weight:bold;"><td>SISA BAYAR</td><td class="right">{{ number_format($sale->remaining, 0, ',', '.') }}</td></tr>
@else
    @if($daftarBayar->count() > 1)
        {{-- Pesanan yang dibayar bertahap: DP dulu, dilunasi belakangan - sering dengan
             cara yang berbeda. Inilah satu-satunya tempat pembeli bisa melihat bahwa
             DP-nya tunai sementara pelunasannya lewat QRIS. --}}
        @foreach($daftarBayar as $bayar)
            <tr>
                <td>{{ $bayar->kind === 'dp' ? 'DP' : ($bayar->kind === 'pelunasan' ? 'Lunas' : 'Bayar') }} ({{ $bayar->label_metode }})</td>
                <td class="right">{{ number_format($bayar->amount, 0, ',', '.') }}</td>
            </tr>
        @endforeach
    @elseif($daftarBayar->count() === 1)
        {{-- Metode diambil dari baris pembayarannya, BUKAN dari sales.payment_method:
             pesanan tanpa DP menyimpan metode yang dipilih saat memesan, sedangkan yang
             benar-benar terjadi adalah cara pelunasannya. --}}
        <tr><td>Bayar ({{ $daftarBayar->first()->label_metode }})</td><td class="right">{{ number_format($sale->paid_amount, 0, ',', '.') }}</td></tr>
    @else
        {{-- Transaksi dari sebelum buku penerimaan uang ada (migrasi 000350), atau yang
             nilai bersihnya nol. Kolom lamanya masih ada dan tetap terbaca. --}}
        <tr><td>Bayar ({{ \App\Support\MetodeBayar::label($sale->payment_method) }})</td><td class="right">{{ number_format($sale->paid_amount, 0, ',', '.') }}</td></tr>
    @endif
    <tr><td>Kembali</td><td class="right">{{ number_format($sale->change_amount, 0, ',', '.') }}</td></tr>
@endif
