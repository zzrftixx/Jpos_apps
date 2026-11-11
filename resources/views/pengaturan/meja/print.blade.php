<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Barcode & QR Code Meja</title>
    <style>
        @page {
            size: auto;
            margin: 8mm;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 16px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .no-print {
            max-width: 900px;
            margin: 0 auto 20px auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 14px 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .no-print h2 {
            margin: 0;
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
        }

        .no-print p {
            margin: 3px 0 0 0;
            font-size: 12px;
            color: #64748b;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            font-size: 12px;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
        }

        .btn-primary {
            background: #d97706;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #b45309;
        }

        .btn-outline {
            background: #ffffff;
            border-color: #cbd5e1;
            color: #334155;
        }

        .btn-outline.active {
            background: #eff6ff;
            border-color: #3b82f6;
            color: #1d4ed8;
        }

        /* CONTAINER LAYOUTS */
        .print-container {
            max-width: 900px;
            margin: 0 auto;
        }

        /* 1. TENT CARD (Kartu Akrilik / Meja Berdiri A6) */
        .layout-tent_card {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 320px));
            gap: 20px;
            justify-content: center;
        }

        .card-tent {
            background: #ffffff;
            border: 2px solid #0f172a;
            border-radius: 16px;
            padding: 24px 20px;
            text-align: center;
            box-sizing: border-box;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.07);
            page-break-inside: avoid;
            break-inside: avoid;
            position: relative;
        }

        .card-tent .store-header {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #b45309;
            margin-bottom: 2px;
        }

        .card-tent .table-title {
            font-size: 30px;
            font-weight: 900;
            color: #0f172a;
            line-height: 1.1;
            margin: 4px 0;
            text-transform: uppercase;
            letter-spacing: -0.5px;
        }

        .card-tent .table-subtitle {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 14px;
        }

        .card-tent .qr-wrapper {
            background: #ffffff;
            display: inline-block;
            padding: 10px;
            border: 3px solid #0f172a;
            border-radius: 14px;
            margin: 6px auto 12px auto;
        }

        .card-tent .qr-callout {
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .card-tent .steps {
            font-size: 10px;
            color: #475569;
            line-height: 1.4;
            max-width: 250px;
            margin: 0 auto 14px auto;
            border-top: 1px dashed #cbd5e1;
            padding-top: 10px;
        }

        .card-tent .barcode-footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            margin-top: 6px;
        }

        /* 2. STICKER LAYOUT */
        .layout-sticker {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 240px));
            gap: 12px;
            justify-content: center;
        }

        .card-sticker {
            background: #ffffff;
            border: 1px solid #0f172a;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            box-sizing: border-box;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .card-sticker .table-title {
            font-size: 20px;
            font-weight: 900;
            margin: 0 0 6px 0;
        }

        /* 3. SHEET (Lembar A4 Kisi 2x3) */
        .layout-sheet {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .card-sheet {
            background: #ffffff;
            border: 1.5px dashed #94a3b8;
            border-radius: 12px;
            padding: 18px;
            text-align: center;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .card-tent, .card-sticker, .card-sheet {
                box-shadow: none !important;
            }
        }
    </style>
    <script src="@aset('vendor/jsbarcode-3.11.6.all.min.js')"></script>
    <script src="@aset('vendor/qrcode.min.js')"></script>
</head>
<body>

    {{-- Toolbar Kontrol Cetak --}}
    <div class="no-print">
        <div>
            <h2>🖨️ Cetak Barcode &amp; QR Code Meja ({{ $tables->count() }} Meja)</h2>
            <p>Pilih format cetak di bawah, lalu klik Cetak atau tekan Ctrl+P.</p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <a href="?layout=tent_card{{ request('ids') ? '&ids=' . request('ids') : '' }}"
               class="btn btn-outline {{ $layout === 'tent_card' ? 'active' : '' }}">
                📋 Kartu Meja (Tent Card)
            </a>
            <a href="?layout=sheet{{ request('ids') ? '&ids=' . request('ids') : '' }}"
               class="btn btn-outline {{ $layout === 'sheet' ? 'active' : '' }}">
                📄 Lembar A4 (Grid)
            </a>
            <a href="?layout=sticker{{ request('ids') ? '&ids=' . request('ids') : '' }}"
               class="btn btn-outline {{ $layout === 'sticker' ? 'active' : '' }}">
                🏷️ Stiker Meja Kompak
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                🖨️ Cetak Sekarang
            </button>
        </div>
    </div>

    {{-- Wadah Kartu Meja --}}
    <div class="print-container layout-{{ $layout }}">
        @foreach($tables as $table)
            @if($layout === 'tent_card')
                {{-- Format 1: Tent Card (Kartu Akrilik / Meja Berdiri) --}}
                <div class="card-tent">
                    <div class="store-header">{{ $storeProfile['name'] ?? 'RESTORAN & CAFE' }}</div>
                    <div class="table-title">{{ $table->name }}</div>
                    <div class="table-subtitle">
                        @if($table->capacity) {{ $table->capacity }} Kursi @endif
                        @if($table->description) &bull; {{ $table->description }} @endif
                    </div>

                    {{-- QR Code untuk Scan Pelanggan --}}
                    <div class="qr-wrapper">
                        <div class="table-qrcode" data-url="{{ $table->order_url }}"></div>
                    </div>

                    <div class="qr-callout">📲 SCAN UNTUK PESAN MENU</div>
                    <div class="steps">
                        1. Arahkan kamera HP ke QR Code<br>
                        2. Pilih menu makanan &amp; minuman favoritmu<br>
                        3. Klik <strong>Kirim Pesanan</strong> &mdash; makanan segera diantar!
                    </div>

                    {{-- Barcode 1D untuk Kasir / Waiter Handheld --}}
                    <div class="barcode-footer">
                        <svg class="table-barcode" data-code="{{ $table->code }}" style="max-height: 32px;"></svg>
                        <div style="font-size: 9px; font-family: monospace; color: #64748b; margin-top: 2px;">
                            Kode Meja: {{ $table->code }}
                        </div>
                    </div>
                </div>

            @elseif($layout === 'sticker')
                {{-- Format 2: Stiker Meja Kompak --}}
                <div class="card-sticker">
                    <div style="font-size: 10px; font-weight: 800; color: #b45309; text-transform: uppercase;">
                        {{ $storeProfile['name'] ?? 'RESTORAN' }}
                    </div>
                    <div class="table-title">{{ $table->name }}</div>
                    <div class="table-qrcode" data-url="{{ $table->order_url }}" style="display: inline-block; margin: 4px 0;"></div>
                    <div style="font-size: 10px; font-weight: 800; margin-top: 3px;">SCAN UNTUK PESAN</div>
                    <svg class="table-barcode" data-code="{{ $table->code }}" style="max-height: 25px; margin-top: 4px;"></svg>
                </div>

            @else
                {{-- Format 3: Lembar A4 Kisi Grid --}}
                <div class="card-sheet">
                    <div style="font-size: 11px; font-weight: 800; color: #b45309; text-transform: uppercase;">
                        {{ $storeProfile['name'] ?? 'RESTORAN' }}
                    </div>
                    <div style="font-size: 26px; font-weight: 900; margin: 3px 0; text-transform: uppercase;">
                        {{ $table->name }}
                    </div>
                    <div style="font-size: 11px; color: #64748b; margin-bottom: 8px;">
                        @if($table->description) {{ $table->description }} &bull; @endif Kode: <strong>{{ $table->code }}</strong>
                    </div>
                    <div style="display: flex; align-items: center; justify-content: center; gap: 16px;">
                        <div style="padding: 6px; border: 2px solid #0f172a; border-radius: 8px; display: inline-block;">
                            <div class="table-qrcode" data-url="{{ $table->order_url }}"></div>
                        </div>
                        <div style="text-align: left; max-width: 170px;">
                            <div style="font-size: 12px; font-weight: 800; color: #0f172a;">📲 PESAN DARI MEJA</div>
                            <div style="font-size: 10px; color: #475569; margin-top: 4px; line-height: 1.3;">
                                Pindai QR dengan kamera HP Anda untuk memesan menu langsung tanpa perlu antre di kasir.
                            </div>
                        </div>
                    </div>
                    <div style="margin-top: 10px; border-top: 1px dashed #cbd5e1; padding-top: 6px;">
                        <svg class="table-barcode" data-code="{{ $table->code }}" style="max-height: 28px;"></svg>
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Render QR Code untuk setiap kartu meja
            document.querySelectorAll('.table-qrcode').forEach(function (el) {
                var url = el.dataset.url;
                if (url && typeof QRCode === 'function') {
                    var size = {{ $layout === 'sticker' ? 100 : ($layout === 'sheet' ? 120 : 140) }};
                    el.innerHTML = '';
                    new QRCode(el, {
                        text: url,
                        width: size,
                        height: size,
                        correctLevel: QRCode.CorrectLevel.M
                    });
                }
            });

            // Render Barcode Code128 untuk kasir
            document.querySelectorAll('.table-barcode').forEach(function (el) {
                var code = el.dataset.code;
                if (code && typeof JsBarcode === 'function') {
                    try {
                        JsBarcode(el, code, {
                            format: 'CODE128',
                            displayValue: false,
                            height: {{ $layout === 'sticker' ? 22 : 28 }},
                            margin: 2
                        });
                    } catch (e) {
                        console.error('JsBarcode error:', e);
                    }
                }
            });
        });
    </script>
</body>
</html>
