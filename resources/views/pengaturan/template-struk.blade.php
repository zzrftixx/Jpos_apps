@extends('layouts.app')
@section('title', 'Template Struk')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6" x-data="{
    layout: @js($settings['layout'] ?? 'simple'),
    showLogo: {{ ($settings['show_logo'] ?? false) ? 'true' : 'false' }},
    showAddress: {{ ($settings['show_address'] ?? true) ? 'true' : 'false' }},
    showPhone: {{ ($settings['show_phone'] ?? true) ? 'true' : 'false' }},
    showCashier: {{ ($settings['show_cashier'] ?? true) ? 'true' : 'false' }},
    showCustomer: {{ ($settings['show_customer'] ?? true) ? 'true' : 'false' }},
    headerNote: @js($settings['header_note'] ?? ''),
    footerNote: @js($settings['footer_note'] ?? 'Terima kasih telah berbelanja!'),
    pilihDokumen: {{ ($settings['pilih_dokumen'] ?? false) ? 'true' : 'false' }},
    dokumenDefault: @js($settings['dokumen_default'] ?? 'struk'),
}">
    <div class="card p-6">
        <h3 class="font-semibold mb-4">Desain Struk</h3>
        <form method="POST" action="{{ route('pengaturan.template-struk.update') }}" class="space-y-3">
            @csrf
            <div>
                <label class="form-label">Template Layout</label>
                <div class="flex rounded-lg border overflow-hidden text-sm">
                    <label class="flex-1 text-center py-2 cursor-pointer" :class="layout === 'simple' ? 'bg-brand-500 text-white' : 'bg-white text-slate-600'">
                        <input type="radio" name="layout" value="simple" x-model="layout" class="hidden"> Ringkas
                    </label>
                    <label class="flex-1 text-center py-2 cursor-pointer border-l" :class="layout === 'normal' ? 'bg-brand-500 text-white' : 'bg-white text-slate-600'">
                        <input type="radio" name="layout" value="normal" x-model="layout" class="hidden"> Normal
                    </label>
                    <label class="flex-1 text-center py-2 cursor-pointer border-l" :class="layout === 'tabel' ? 'bg-brand-500 text-white' : 'bg-white text-slate-600'">
                        <input type="radio" name="layout" value="tabel" x-model="layout" class="hidden"> Tabel (Nota)
                    </label>
                    <label class="flex-1 text-center py-2 cursor-pointer border-l" :class="layout === 'invoice' ? 'bg-brand-500 text-white' : 'bg-white text-slate-600'">
                        <input type="radio" name="layout" value="invoice" x-model="layout" class="hidden"> Invoice (Dot Matrix)
                    </label>
                </div>
                <p class="text-xs text-slate-400 mt-1">
                    "Ringkas" = nama produk di baris sendiri, lalu qty x harga & subtotal di baris berikutnya. "Normal" = nama & subtotal sejajar 1 baris, rincian qty x harga di bawahnya lebih kecil. "Tabel (Nota)" = grid tabel Banyaknya/Nama/Harga/Jumlah ala nota, paling cocok untuk kertas 80mm. "Invoice (Dot Matrix)" = satu halaman penuh berkop toko, tabel bergaris, dan kolom tanda tangan - untuk printer dot matrix dengan kertas continuous form.
                </p>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="show_logo" value="1" x-model="showLogo"> Tampilkan Logo Toko
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="show_address" value="1" x-model="showAddress"> Tampilkan Alamat
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="show_phone" value="1" x-model="showPhone"> Tampilkan No. Telepon
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="show_cashier" value="1" x-model="showCashier"> Tampilkan Nama Kasir
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="show_customer" value="1" x-model="showCustomer"> Tampilkan Nama Pelanggan
            </label>
            <div>
                <label class="form-label">Catatan Header (opsional)</label>
                <input type="text" name="header_note" x-model="headerNote" class="form-input">
            </div>
            <div>
                <label class="form-label">Catatan Footer</label>
                <input type="text" name="footer_note" x-model="footerNote" class="form-input">
            </div>

            {{-- PILIHAN DOKUMEN SAAT BAYAR.

                 Ditaruh di halaman ini, bukan di Printer Struk, karena yang diputuskan di
                 sini adalah BENTUK dokumen - sama seperti pemilih layout di atasnya. Printer
                 Struk mengurus ukuran kertas. Bersebelahan begini, hubungan antara "layout
                 yang dipilih" dan "yang dicetak sebagai Struk" langsung terlihat. --}}
            <div class="border-t border-slate-200 pt-4 mt-4 space-y-3">
                <label class="flex items-start gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="pilih_dokumen" value="1" x-model="pilihDokumen" class="mt-0.5">
                    <span>
                        <span class="font-medium">Tanya bentuk dokumen setiap kali bayar</span>
                        <span class="block text-xs text-slate-500 mt-0.5">
                            Kasir memilih sendiri: <strong>Struk</strong> atau <strong>Invoice</strong>,
                            saat bayar maupun saat mencetak ulang pesanan. Berguna kalau sebagian
                            pelanggan cuma perlu struk, sementara yang belanja banyak minta rincian.
                        </span>
                    </span>
                </label>

                <div x-show="pilihDokumen" x-cloak class="pl-6 space-y-2">
                    <label class="form-label">Pilihan yang sudah tersorot saat kasir membuka layar bayar</label>
                    <div class="flex rounded-lg border overflow-hidden text-sm">
                        <label class="flex-1 text-center py-2 cursor-pointer" :class="dokumenDefault === 'struk' ? 'bg-brand-500 text-white' : 'bg-white text-slate-600'">
                            <input type="radio" name="dokumen_default" value="struk" x-model="dokumenDefault" class="hidden"> Struk
                        </label>
                        <label class="flex-1 text-center py-2 cursor-pointer border-l" :class="dokumenDefault === 'invoice' ? 'bg-brand-500 text-white' : 'bg-white text-slate-600'">
                            <input type="radio" name="dokumen_default" value="invoice" x-model="dokumenDefault" class="hidden"> Invoice
                        </label>
                    </div>

                    <p class="text-xs text-slate-500">
                        <strong>Struk</strong> memakai layout yang dipilih di atas dan mengikuti ukuran
                        kertas di Printer Struk. <strong>Invoice</strong> selalu memakai bentuk Dot Matrix
                        22 x 16 cm dengan kop toko, tabel bergaris, dan kolom tanda tangan.
                    </p>

                    {{-- Peringatan yang hanya muncul kalau memang perlu: kalau layoutnya sendiri
                         sudah Invoice, tombol "Struk" dan "Invoice" akan mencetak hal yang sama. --}}
                    <p x-show="layout === 'invoice'" x-cloak
                       class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        Layout di atas sedang disetel <strong>Invoice (Dot Matrix)</strong>. Supaya tombol
                        <strong>Struk</strong> tidak mencetak berkas yang sama persis, ia akan memakai
                        <strong>Tabel (Nota)</strong>. Pilih layout selain Invoice di atas kalau Anda ingin
                        bentuk struknya yang lain.
                    </p>
                </div>
            </div>

            <button class="btn btn-primary">Simpan Template</button>
        </form>
        <p class="text-xs text-slate-400 mt-3" x-show="layout !== 'invoice'">Lebar cetak &amp; font diatur di menu Printer Struk (saat ini: {{ $printerStruk['print_width'] }}mm cetak dari kertas {{ $printerStruk['paper_size'] ?? 80 }}mm).</p>
        <p class="text-xs text-slate-400 mt-3" x-show="layout === 'invoice'" x-cloak>Template Invoice memakai ukuran kertas tetap 22 x 16 cm dan TIDAK mengikuti pengaturan Printer Struk - yang diatur di sana adalah lebar kertas roll termal, dan memakainya di sini akan memotong tabel invoice.</p>
    </div>

    <div class="card p-6">
        <h3 class="font-semibold mb-4">Preview Live</h3>

        {{-- Pratinjau invoice --}}
        <div class="bg-slate-50 rounded-lg p-4 overflow-x-auto" x-show="layout === 'invoice'" x-cloak>
            <div class="bg-white border font-mono mx-auto" style="width:560px; font-size:11px; padding:14px;">
                <div class="flex justify-between items-start border-b-2 border-black pb-2 mb-2">
                    <div class="flex items-center">
                        <template x-if="showLogo">
                            <span>
                                @if(!empty($storeProfile['logo']))
                                    <img src="{{ url('media/' . $storeProfile['logo']) }}" style="max-width:44px; max-height:44px; margin-right:8px; filter:grayscale(1) contrast(1.5);">
                                @else
                                    <span class="text-slate-400 text-[10px] mr-2">(logo toko belum diupload)</span>
                                @endif
                            </span>
                        </template>
                        <div>
                            <div class="font-bold text-[13px]">{{ $storeProfile['name'] ?? 'JPOS by JaylaTech' }}</div>
                            <div x-show="showAddress">{{ $storeProfile['address'] ?? '' }}</div>
                            <div x-show="showPhone">Telp: {{ $storeProfile['phone'] ?? '' }}</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="font-bold text-[16px] tracking-[3px]">INVOICE</div>
                        <div>No: {{ $sample->invoice_no }}</div>
                        <div>Tanggal: {{ $sample->created_at->format('d/m/Y H:i') }}</div>
                    </div>
                </div>

                <div class="flex justify-between mb-2">
                    <div>
                        <div class="font-bold">Kepada Yth:</div>
                        <div>{{ $sample->customer->name ?? 'Pelanggan Umum' }}</div>
                    </div>
                    <div class="text-right" x-show="showCashier">
                        <div>Kasir: {{ $sample->cashier->name }}</div>
                    </div>
                </div>

                <table style="width:100%; border-collapse:collapse; margin:6px 0;">
                    <thead>
                        <tr>
                            <th style="width:6%; border:1px solid #000; padding:3px; background:rgba(0,0,0,0.06);">No</th>
                            <th style="border:1px solid #000; padding:3px; background:rgba(0,0,0,0.06);">Nama Barang</th>
                            <th style="width:12%; border:1px solid #000; padding:3px; background:rgba(0,0,0,0.06);">Qty</th>
                            <th style="width:20%; border:1px solid #000; padding:3px; background:rgba(0,0,0,0.06);">Harga Satuan</th>
                            <th style="width:20%; border:1px solid #000; padding:3px; background:rgba(0,0,0,0.06);">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sample->items as $i => $item)
                        <tr>
                            <td style="border:1px solid #000; padding:3px; text-align:center;">{{ $i + 1 }}</td>
                            <td style="border:1px solid #000; padding:3px;">{{ $item->product_name }}</td>
                            <td style="border:1px solid #000; padding:3px; text-align:center;">@qty($item->qty)</td>
                            <td style="border:1px solid #000; padding:3px; text-align:right;">{{ number_format($item->price,0,',','.') }}</td>
                            <td style="border:1px solid #000; padding:3px; text-align:right;">{{ number_format($item->subtotal,0,',','.') }}</td>
                        </tr>
                        @endforeach
                        @for($i = count($sample->items); $i < 5; $i++)
                        <tr><td style="border:1px solid #000; padding:3px;">&nbsp;</td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td><td style="border:1px solid #000;"></td></tr>
                        @endfor
                    </tbody>
                </table>

                <table style="width:60%; margin-left:auto;">
                    <tr><td style="padding:1px 3px;">Pajak</td><td style="padding:1px 3px; text-align:right;">{{ number_format($sample->tax_amount,0,',','.') }}</td></tr>
                    <tr class="font-bold" style="border-top:1px solid #000;"><td style="padding:1px 3px;">TOTAL</td><td style="padding:1px 3px; text-align:right;">Rp {{ number_format($sample->total,0,',','.') }}</td></tr>
                    <tr><td style="padding:1px 3px;">Bayar</td><td style="padding:1px 3px; text-align:right;">{{ number_format($sample->paid_amount,0,',','.') }}</td></tr>
                    <tr><td style="padding:1px 3px;">Kembali</td><td style="padding:1px 3px; text-align:right;">{{ number_format($sample->change_amount,0,',','.') }}</td></tr>
                </table>

                <div class="flex justify-between mt-6">
                    <div style="width:40%; text-align:center;">
                        <div>Penerima,</div>
                        <div style="margin-top:34px; border-top:1px solid #000; padding-top:2px;">( ..................... )</div>
                    </div>
                    <div style="width:40%; text-align:center;">
                        <div>Hormat Kami,</div>
                        <div style="margin-top:34px; border-top:1px solid #000; padding-top:2px;">( {{ $sample->cashier->name }} )</div>
                    </div>
                </div>

                <div class="text-center mt-3 text-[10px]" x-text="footerNote"></div>
            </div>
        </div>

        <div class="flex items-center justify-center bg-slate-50 rounded-lg p-6" x-show="layout !== 'invoice'">
            <div class="bg-white border font-mono" :style="'width:{{ $printerStruk['print_width'] * 2.75 }}px; font-size:' + (layout === 'tabel' ? 12 : {{ $printerStruk['font_size'] ?? 12 }}) + 'px; padding:' + (layout === 'tabel' ? '8px 0' : '12px')">
                <div class="text-center" x-show="showLogo">
                    @if(!empty($storeProfile['logo']))
                        <img src="{{ url('media/' . $storeProfile['logo']) }}" style="max-width:60px; margin:0 auto 4px; display:block; filter:grayscale(1) contrast(1.5);">
                    @else
                        <span class="text-slate-400 text-[10px]">(logo toko belum diupload)</span>
                    @endif
                </div>
                <div class="text-center font-bold">{{ $storeProfile['name'] ?? 'JPOS by JaylaTech' }}</div>
                <div class="text-center" x-show="showAddress">{{ $storeProfile['address'] ?? '' }}</div>
                <div class="text-center" x-show="showPhone">{{ $storeProfile['phone'] ?? '' }}</div>
                <div class="text-center" x-show="headerNote" x-text="headerNote"></div>
                <hr class="my-1 border-dashed">
                <div class="flex justify-between"><span>No</span><span>{{ $sample->invoice_no }}</span></div>
                <div class="flex justify-between"><span>Tanggal</span><span>{{ $sample->created_at->format('d/m/Y H:i') }}</span></div>
                <div class="flex justify-between" x-show="showCashier"><span>Kasir</span><span>{{ $sample->cashier->name }}</span></div>
                <div class="flex justify-between" x-show="showCustomer"><span>Pelanggan</span><span>{{ $sample->customer->name }}</span></div>
                <hr class="my-1 border-dashed">
                <template x-if="layout === 'simple'">
                    <div>
                        @foreach($sample->items as $item)
                        <div>{{ $item->product_name }}</div>
                        <div class="flex justify-between"><span>@qty($item->qty) x {{ number_format($item->price,0,',','.') }}</span><span>{{ number_format($item->subtotal,0,',','.') }}</span></div>
                        @endforeach
                    </div>
                </template>
                <template x-if="layout === 'normal'">
                    <div>
                        @foreach($sample->items as $item)
                        <div class="flex justify-between"><span>{{ $item->product_name }}</span><span>{{ number_format($item->subtotal,0,',','.') }}</span></div>
                        <div style="font-size:0.85em; opacity:0.75;">@qty($item->qty) x {{ number_format($item->price,0,',','.') }}</div>
                        @endforeach
                    </div>
                </template>
                <template x-if="layout === 'tabel'">
                    <div>
                        <div>Kepada: {{ $sample->customer->name ?? 'Umum' }}</div>
                        <div x-show="showCashier">Kasir: {{ $sample->cashier->name }}</div>
                        <div style="font-weight:bold;">NOTA NO. {{ $sample->invoice_no }}</div>
                        <table style="width:100%; table-layout:fixed; border-collapse:collapse; border:1px solid #000; margin:4px 0;">
                            <thead>
                                <tr>
                                    <th style="width:14%; border:1px solid #000; padding:2px; text-align:center; background:rgba(0,0,0,0.06);">Qty</th>
                                    <th style="width:40%; border:1px solid #000; padding:2px; text-align:center; background:rgba(0,0,0,0.06);">Barang</th>
                                    <th style="width:23%; border:1px solid #000; padding:2px; text-align:center; background:rgba(0,0,0,0.06);">Harga</th>
                                    <th style="width:23%; border:1px solid #000; padding:2px; text-align:center; background:rgba(0,0,0,0.06);">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sample->items as $item)
                                <tr>
                                    <td style="border:1px solid #000; padding:2px; text-align:center; overflow-wrap:break-word;">@qty($item->qty)</td>
                                    <td style="border:1px solid #000; padding:2px; overflow-wrap:break-word;">{{ $item->product_name }}</td>
                                    <td style="border:1px solid #000; padding:2px; text-align:right; overflow-wrap:break-word;">{{ number_format($item->price,0,',','.') }}</td>
                                    <td style="border:1px solid #000; padding:2px; text-align:right; overflow-wrap:break-word;">{{ number_format($item->subtotal,0,',','.') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </template>
                <hr class="my-1 border-dashed">
                <div class="flex justify-between" x-show="layout !== 'tabel'"><span>Subtotal</span><span>{{ number_format($sample->subtotal,0,',','.') }}</span></div>
                <div class="flex justify-between"><span>Pajak</span><span>{{ number_format($sample->tax_amount,0,',','.') }}</span></div>
                <div class="flex justify-between font-bold"><span x-text="layout === 'tabel' ? 'Jumlah Rp.' : 'TOTAL'"></span><span>{{ number_format($sample->total,0,',','.') }}</span></div>
                <div class="flex justify-between"><span>Bayar</span><span>{{ number_format($sample->paid_amount,0,',','.') }}</span></div>
                <div class="flex justify-between"><span>Kembali</span><span>{{ number_format($sample->change_amount,0,',','.') }}</span></div>
                <div class="text-center" x-text="footerNote"></div>
            </div>
        </div>
    </div>
</div>
@endsection
