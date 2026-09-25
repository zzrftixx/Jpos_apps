<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Shift #{{ $shift->id }} - {{ $shift->user->name ?? 'Kasir' }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Courier New', Courier, monospace, monospace;
            font-size: 12px;
            color: #000;
            background: #fff;
            padding: 10px;
            max-width: 320px; /* Standar struk thermal 58mm - 80mm */
            margin: 0 auto;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .divider { border-bottom: 1px dashed #000; margin: 8px 0; }
        .double-divider { border-bottom: 2px solid #000; margin: 8px 0; }
        .row { display: flex; justify-content: space-between; margin-bottom: 3px; }
        .header { margin-bottom: 10px; }
        .header h1 { font-size: 15px; font-weight: bold; margin-bottom: 2px; }
        .header p { font-size: 10.5px; }
        .title { font-size: 13px; font-weight: bold; margin: 6px 0; }
        .section-title { font-size: 11px; font-weight: bold; margin-top: 6px; margin-bottom: 3px; text-transform: uppercase; }
        .badge { display: inline-block; padding: 2px 5px; font-size: 10px; font-weight: bold; border: 1px solid #000; }
        .btn-print {
            display: block;
            width: 100%;
            padding: 8px;
            background: #1c6ff0;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            margin-bottom: 12px;
        }
        .signature-box { margin-top: 20px; display: flex; justify-content: space-between; text-align: center; font-size: 10.5px; }
        .signature-line { margin-top: 40px; border-top: 1px solid #000; width: 100px; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">Cetak Slip Tutup Shift (Z-Report)</button>
    </div>

    <div class="header text-center">
        <h1>{{ $storeProfile['name'] ?? config('app.name', 'JPOS') }}</h1>
        @if(!empty($storeProfile['address']))
            <p>{{ $storeProfile['address'] }}</p>
        @endif
        @if(!empty($storeProfile['phone']))
            <p>Telp: {{ $storeProfile['phone'] }}</p>
        @endif
    </div>

    <div class="divider"></div>

    <div class="text-center">
        <div class="title">REKAPITULASI SHIFT KASIR</div>
        <div style="font-size: 11px;">(LAPORAN Z-REPORT)</div>
    </div>

    <div class="divider"></div>

    <div class="row">
        <span>No. Shift</span>
        <span class="font-bold">#{{ $shift->id }}</span>
    </div>
    <div class="row">
        <span>Kasir</span>
        <span class="font-bold">{{ $shift->user->name ?? 'Kasir' }}</span>
    </div>
    <div class="row">
        <span>Buka Shift</span>
        <span>{{ $shift->opened_at->format('d/m/Y H:i') }}</span>
    </div>
    <div class="row">
        <span>Tutup Shift</span>
        <span>{{ $shift->closed_at ? $shift->closed_at->format('d/m/Y H:i') : 'Masih Berjalan' }}</span>
    </div>
    <div class="row">
        <span>Status</span>
        <span class="font-bold">{{ strtoupper($shift->status) }}</span>
    </div>

    <div class="divider"></div>

    <div class="section-title">RINCIAN PENJUALAN</div>
    <div class="row">
        <span>Jumlah Transaksi</span>
        <span class="font-bold">{{ $summary['transaction_count'] }} Nota</span>
    </div>
    <div class="row">
        <span>Penjualan Tunai</span>
        <span class="font-bold">Rp {{ number_format($summary['cash_sales'], 0, ',', '.') }}</span>
    </div>
    <div class="row">
        <span>Penjualan Non-Tunai</span>
        <span class="font-bold">Rp {{ number_format($summary['non_cash_sales'], 0, ',', '.') }}</span>
    </div>
    @if(!empty($summary['payment_breakdown']))
        @foreach($summary['payment_breakdown'] as $method => $val)
            @if($method !== 'tunai' && $val > 0)
                <div class="row" style="font-size: 11px; padding-left: 10px; color: #333;">
                    <span>&bull; {{ \App\Support\MetodeBayar::label($method) }}</span>
                    <span>Rp {{ number_format($val, 0, ',', '.') }}</span>
                </div>
            @endif
        @endforeach
    @endif
    <div class="row" style="margin-top: 4px;">
        <span class="font-bold">TOTAL PENJUALAN</span>
        <span class="font-bold">Rp {{ number_format($summary['total_sales'], 0, ',', '.') }}</span>
    </div>

    @if($summary['cash_refunds'] > 0)
    <div class="row">
        <span>Retur Penjualan (Refund)</span>
        <span class="font-bold" style="color: red;">- Rp {{ number_format($summary['cash_refunds'], 0, ',', '.') }}</span>
    </div>
    @endif

    @if($shift->actual_cash !== null || (float)$summary['starting_cash'] > 0)
    <div class="divider"></div>

    <div class="section-title">PENGHITUNGAN KAS LACI</div>
    <div class="row">
        <span>Modal Awal Kasir</span>
        <span>Rp {{ number_format($summary['starting_cash'], 0, ',', '.') }}</span>
    </div>
    <div class="row">
        <span>(+) Tunai Masuk Penjualan</span>
        <span>Rp {{ number_format($summary['cash_sales'], 0, ',', '.') }}</span>
    </div>
    @if($summary['cash_in'] > 0)
    <div class="row">
        <span>(+) Kas Masuk Tambahan</span>
        <span>Rp {{ number_format($summary['cash_in'], 0, ',', '.') }}</span>
    </div>
    @endif
    @if($summary['cash_refunds'] > 0)
    <div class="row">
        <span>(-) Refund Retur Tunai</span>
        <span>Rp {{ number_format($summary['cash_refunds'], 0, ',', '.') }}</span>
    </div>
    @endif
    @if($summary['cash_out'] > 0)
    <div class="row">
        <span>(-) Kas Keluar / Cash Drop</span>
        <span>Rp {{ number_format($summary['cash_out'], 0, ',', '.') }}</span>
    </div>
    @endif

    <div class="divider"></div>

    <div class="row font-bold" style="font-size: 12.5px;">
        <span>Uang Seharusnya (Sistem)</span>
        <span>Rp {{ number_format($summary['expected_cash'], 0, ',', '.') }}</span>
    </div>
    <div class="row font-bold" style="font-size: 12.5px;">
        <span>Uang Fisik Laci (Aktual)</span>
        <span>Rp {{ number_format($shift->actual_cash ?? $summary['expected_cash'], 0, ',', '.') }}</span>
    </div>

    <div class="double-divider"></div>

    @php
        $diff = $shift->difference ?? ((float) ($shift->actual_cash ?? $summary['expected_cash']) - $summary['expected_cash']);
    @endphp

    <div class="row font-bold" style="font-size: 13px;">
        <span>SELISIH KAS</span>
        @if(abs($diff) < 0.01)
            <span>PAS (Rp 0)</span>
        @elseif($diff > 0)
            <span>LEBIH (+ Rp {{ number_format($diff, 0, ',', '.') }})</span>
        @else
            <span>KURANG (- Rp {{ number_format(abs($diff), 0, ',', '.') }})</span>
        @endif
    </div>
    @else
    <div class="divider"></div>
    <div class="text-center" style="font-size: 10.5px; color: #555; padding: 4px 0;">
        (Mode Kas Laci Dinonaktifkan - Rekapitulasi Penjualan Shift)
    </div>
    @endif

    @if(!empty($shift->notes))
    <div class="divider"></div>
    <div style="font-size: 11px;">
        <span class="font-bold">Catatan:</span> {{ $shift->notes }}
    </div>
    @endif

    <div class="signature-box">
        <div>
            <div>Kasir</div>
            <div class="signature-line"></div>
            <div>{{ $shift->user->name ?? 'Kasir' }}</div>
        </div>
        <div>
            <div>Supervisor / Owner</div>
            <div class="signature-line"></div>
            <div>( ..................... )</div>
        </div>
    </div>

    <div class="divider" style="margin-top: 20px;"></div>
    <div class="text-center" style="font-size: 10px; color: #555;">
        Dicetak: {{ now()->format('d/m/Y H:i:s') }}
    </div>

    <script>
        window.addEventListener('load', () => {
            // Auto open print dialog if directly opened for printing
            setTimeout(() => {
                window.print();
            }, 300);
        });
    </script>
</body>
</html>
