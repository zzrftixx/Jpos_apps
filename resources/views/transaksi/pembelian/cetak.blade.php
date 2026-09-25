<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faktur Pembelian {{ $purchase->purchase_no }} - {{ $storeProfile['name'] ?? config('app.name', 'JPOS') }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 15mm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 13px;
            color: #1e293b;
            background-color: #f8fafc;
            line-height: 1.45;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .page-container {
            max-width: 860px;
            margin: 24px auto;
            background: #ffffff;
            padding: 36px 40px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
            border-radius: 12px;
        }
        .action-bar {
            max-width: 860px;
            margin: 16px auto 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            border: 1px solid transparent;
        }
        .btn-back {
            background-color: #ffffff;
            border-color: #cbd5e1;
            color: #475569;
        }
        .btn-back:hover {
            background-color: #f1f5f9;
            color: #1e293b;
        }
        .btn-print {
            background-color: #2563eb;
            color: #ffffff;
        }
        .btn-print:hover {
            background-color: #1d4ed8;
        }

        /* Invoice Document Header */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 24px;
        }
        .brand-info {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .brand-logo {
            max-width: 68px;
            max-height: 68px;
            object-fit: contain;
        }
        .brand-text h1 {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
        }
        .brand-text p {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }
        .doc-title-box {
            text-align: right;
        }
        .doc-badge {
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 3px 8px;
            border-radius: 4px;
            margin-bottom: 4px;
        }
        .badge-lunas {
            background-color: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        .badge-hutang {
            background-color: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .doc-title {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.3px;
        }
        .doc-no {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 14px;
            font-weight: 700;
            color: #2563eb;
            margin-top: 2px;
        }

        /* Meta Grid */
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
            background: #f8fafc;
            padding: 16px 20px;
            border-radius: 8px;
            border: 1px solid #f1f5f9;
        }
        .meta-col h3 {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .meta-row {
            display: flex;
            font-size: 12px;
            margin-bottom: 4px;
        }
        .meta-label {
            width: 120px;
            color: #64748b;
            flex-shrink: 0;
        }
        .meta-val {
            font-weight: 600;
            color: #1e293b;
        }

        /* Table */
        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .table-items th {
            background: #f1f5f9;
            color: #334155;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 12px;
            border-top: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }
        .table-items td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            font-size: 12px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .item-name {
            font-weight: 600;
            color: #0f172a;
        }
        .item-sub {
            font-size: 11px;
            color: #64748b;
            font-family: ui-monospace, monospace;
            margin-top: 1px;
        }

        /* Summary Section */
        .summary-wrap {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            margin-bottom: 32px;
        }
        .note-box {
            flex: 1;
            background: #f8fafc;
            padding: 14px 16px;
            border-radius: 8px;
            border: 1px dashed #cbd5e1;
            font-size: 12px;
        }
        .note-title {
            font-weight: 700;
            color: #475569;
            margin-bottom: 4px;
            font-size: 11px;
            text-transform: uppercase;
        }
        .calc-box {
            width: 340px;
        }
        .calc-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            font-size: 12px;
            color: #475569;
        }
        .calc-row.grand-total {
            border-top: 2px solid #e2e8f0;
            border-bottom: 2px solid #e2e8f0;
            padding: 8px 0;
            margin: 6px 0;
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
        }
        .calc-row.sisa-hutang {
            font-weight: 700;
            font-size: 13px;
            color: #b45309;
        }

        /* Payments Table */
        .section-sub {
            margin-bottom: 24px;
        }
        .section-sub h4 {
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table-payments {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .table-payments th, .table-payments td {
            padding: 7px 10px;
            border: 1px solid #e2e8f0;
        }
        .table-payments th {
            background: #f8fafc;
            font-weight: 600;
            color: #475569;
        }

        /* Signatures */
        .signatures-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-top: 40px;
            text-align: center;
        }
        .sig-box {
            font-size: 12px;
        }
        .sig-title {
            color: #64748b;
            font-weight: 600;
            margin-bottom: 60px;
        }
        .sig-line {
            border-top: 1px solid #94a3b8;
            padding-top: 6px;
            font-weight: 700;
            color: #1e293b;
        }
        .sig-sub {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }

        /* Footer */
        .doc-footer {
            margin-top: 36px;
            padding-top: 12px;
            border-top: 1px solid #f1f5f9;
            font-size: 11px;
            color: #94a3b8;
            display: flex;
            justify-content: space-between;
        }

        @media print {
            body {
                background: none;
            }
            .action-bar {
                display: none !important;
            }
            .page-container {
                max-width: 100%;
                margin: 0;
                padding: 0;
                box-shadow: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>

    {{-- Bar Aksi Cetak & Navigasi (Sembunyi saat cetak) --}}
    <div class="action-bar">
        <a href="{{ route('pembelian.index') }}" class="btn btn-back">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Kembali ke Pembelian</span>
        </a>
        <button onclick="window.print()" class="btn btn-print">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            <span>Cetak Faktur (Print / PDF)</span>
        </button>
    </div>

    <div class="page-container">
        {{-- Header Dokumen --}}
        <div class="doc-header">
            <div class="brand-info">
                @if(!empty($storeProfile['logo']))
                    <img src="{{ asset('storage/' . $storeProfile['logo']) }}" alt="Logo" class="brand-logo">
                @endif
                <div class="brand-text">
                    <h1>{{ $storeProfile['name'] ?? config('app.name', 'JPOS STORE') }}</h1>
                    <p>{{ $storeProfile['address'] ?? 'Alamat Toko / Gudang Operasional' }}</p>
                    @if(!empty($storeProfile['phone']))
                        <p>Telp / WA: {{ $storeProfile['phone'] }}</p>
                    @endif
                </div>
            </div>

            <div class="doc-title-box">
                <div>
                    @if($purchase->sudahLunas())
                        <span class="doc-badge badge-lunas">LUNAS</span>
                    @else
                        <span class="doc-badge badge-hutang">HUTANG / TEMPO</span>
                    @endif
                </div>
                <div class="doc-title">FAKTUR PEMBELIAN</div>
                <div class="doc-no">{{ $purchase->purchase_no }}</div>
            </div>
        </div>

        {{-- Meta Info Grid --}}
        <div class="meta-grid">
            <div class="meta-col">
                <h3>Informasi Pemasok (Supplier)</h3>
                <div class="meta-row">
                    <span class="meta-label">Nama Pemasok</span>
                    <span class="meta-val">{{ $purchase->supplier ? $purchase->supplier->name : 'Tanpa Pemasok (Umum)' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">No. Telepon</span>
                    <span class="meta-val">{{ $purchase->supplier?->phone ?? '-' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Alamat</span>
                    <span class="meta-val">{{ $purchase->supplier?->address ?? '-' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">No. Faktur Pemasok</span>
                    <span class="meta-val" style="font-family: ui-monospace, monospace;">{{ $purchase->supplier_invoice_no ?: '-' }}</span>
                </div>
            </div>

            <div class="meta-col">
                <h3>Rincian Dokumen &amp; Penerimaan</h3>
                <div class="meta-row">
                    <span class="meta-label">Tanggal Beli</span>
                    <span class="meta-val">{{ $purchase->purchase_date->translatedFormat('d F Y') }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Petugas / Kasir</span>
                    <span class="meta-val">{{ $purchase->user?->name ?? 'Administrator' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Status Bayar</span>
                    <span class="meta-val">{{ $purchase->sudahLunas() ? 'Lunas Penuh' : 'Belum Lunas' }}</span>
                </div>
                @if($purchase->due_date)
                    <div class="meta-row">
                        <span class="meta-label">Jatuh Tempo</span>
                        <span class="meta-val" style="color: #dc2626;">{{ $purchase->due_date->translatedFormat('d F Y') }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Tabel Daftar Barang Masuk --}}
        <table class="table-items">
            <thead>
                <tr>
                    <th class="text-center" style="width: 36px;">#</th>
                    <th>Nama Barang &amp; Kode</th>
                    <th class="text-center" style="width: 100px;">Satuan</th>
                    <th class="text-right" style="width: 70px;">Qty</th>
                    <th class="text-right" style="width: 120px;">Harga Beli</th>
                    <th class="text-right" style="width: 130px;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($purchase->items as $idx => $item)
                <tr>
                    <td class="text-center" style="color: #64748b;">{{ $idx + 1 }}</td>
                    <td>
                        <div class="item-name">{{ $item->product_name }}</div>
                        @if($item->product && ($item->product->barcode || $item->product->sku))
                            <div class="item-sub">
                                {{ $item->product->barcode ? 'Barcode: ' . $item->product->barcode : '' }}
                                {{ $item->product->sku ? ' (SKU: ' . $item->product->sku . ')' : '' }}
                            </div>
                        @endif
                    </td>
                    <td class="text-center">
                        <span>{{ $item->unit_label }}</span>
                        @if($item->unit_conversion > 1)
                            <div style="font-size: 10px; color: #94a3b8;">(x@qty($item->unit_conversion) dasar)</div>
                        @endif
                    </td>
                    <td class="text-right" style="font-weight: 600; font-family: ui-monospace, monospace;">
                        @qty($item->qty)
                    </td>
                    <td class="text-right" style="font-family: ui-monospace, monospace;">
                        Rp {{ number_format($item->price, 0, ',', '.') }}
                    </td>
                    <td class="text-right" style="font-weight: 700; font-family: ui-monospace, monospace;">
                        Rp {{ number_format($item->subtotal, 0, ',', '.') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Ringkasan Biaya & Catatan --}}
        <div class="summary-wrap">
            <div class="note-box">
                <div class="note-title">Catatan Penerimaan:</div>
                <p>{{ $purchase->note ?: 'Tidak ada catatan transaksi.' }}</p>
                @if($purchase->other_cost > 0)
                    <p style="margin-top: 8px; font-size: 11px; color: #64748b;">
                        * Biaya tambahan sebesar Rp {{ number_format($purchase->other_cost, 0, ',', '.') }} dialokasikan ke harga modal pokok (HPP) setiap produk sebanding nilainya.
                    </p>
                @endif
            </div>

            <div class="calc-box">
                <div class="calc-row">
                    <span>Subtotal Barang</span>
                    <span>Rp {{ number_format($purchase->subtotal, 0, ',', '.') }}</span>
                </div>
                @if($purchase->other_cost > 0)
                    <div class="calc-row">
                        <span>Biaya Lain (Ongkir / Bongkar)</span>
                        <span>Rp {{ number_format($purchase->other_cost, 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="calc-row grand-total">
                    <span>TOTAL FAKTUR</span>
                    <span>Rp {{ number_format($purchase->total, 0, ',', '.') }}</span>
                </div>
                <div class="calc-row">
                    <span>Sudah Dibayar</span>
                    <span style="color: #047857; font-weight: 600;">Rp {{ number_format($purchase->paid_amount, 0, ',', '.') }}</span>
                </div>
                <div class="calc-row sisa-hutang">
                    <span>Sisa Hutang</span>
                    <span>Rp {{ number_format($purchase->sisa_hutang, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        {{-- Riwayat Pembayaran / Cicilan (Jika Ada) --}}
        @if($purchase->payments->isNotEmpty())
        <div class="section-sub">
            <h4>Riwayat Pembayaran / Angsuran</h4>
            <table class="table-payments">
                <thead>
                    <tr>
                        <th style="width: 40px;" class="text-center">#</th>
                        <th>Tanggal Bayar</th>
                        <th>Diterima Oleh</th>
                        <th>Catatan</th>
                        <th class="text-right">Jumlah Dibayar</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchase->payments as $pIdx => $pay)
                    <tr>
                        <td class="text-center">{{ $pIdx + 1 }}</td>
                        <td>{{ $pay->paid_at->translatedFormat('d F Y') }}</td>
                        <td>{{ $pay->user?->name ?? '-' }}</td>
                        <td style="color: #64748b;">{{ $pay->note ?: '-' }}</td>
                        <td class="text-right" style="font-weight: 700; color: #047857; font-family: ui-monospace, monospace;">
                            Rp {{ number_format($pay->amount, 0, ',', '.') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Tanda Tangan Serah Terima --}}
        <div class="signatures-grid">
            <div class="sig-box">
                <div class="sig-title">Pengirim / Supplier</div>
                <div class="sig-line">{{ $purchase->supplier ? $purchase->supplier->name : '( ............................ )' }}</div>
                <div class="sig-sub">Nama Jelas &amp; Tanda Tangan</div>
            </div>
            <div class="sig-box">
                <div class="sig-title">Petugas Penerima Gudang</div>
                <div class="sig-line">{{ $purchase->user?->name ?? 'Petugas Gudang' }}</div>
                <div class="sig-sub">Pemeriksa Fisik Barang</div>
            </div>
            <div class="sig-box">
                <div class="sig-title">Penanggung Jawab / Pemilik</div>
                <div class="sig-line">( ............................ )</div>
                <div class="sig-sub">Verifikasi Pembayaran &amp; Stok</div>
            </div>
        </div>

        {{-- Footer Nota --}}
        <div class="doc-footer">
            <span>Dicetak secara otomatis oleh sistem JPOS pada {{ now()->translatedFormat('d F Y, H:i') }}</span>
            <span>Halaman 1 / 1</span>
        </div>
    </div>

</body>
</html>
