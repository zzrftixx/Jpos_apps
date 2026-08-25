@extends('layouts.app')
@section('title', 'Backup & Restore')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card p-6">
        <h3 class="font-semibold mb-3">Buat Backup Database</h3>
        <p class="text-sm text-slate-500 mb-4">
            Salinan database disimpan di komputer ini dan bisa diunduh kapan saja.
            Aplikasi juga membuat salinan otomatis sekali sehari, dan setiap kali akan
            melakukan sesuatu yang berisiko terhadap data.
        </p>
        <form method="POST" action="{{ route('pengaturan.backup-restore.backup') }}">
            @csrf
            <button class="btn btn-primary">📦 Buat Backup Sekarang</button>
        </form>

        <div class="flex items-baseline justify-between mt-6 mb-2">
            <h4 class="font-medium text-sm">Riwayat Backup <span class="text-slate-400 font-normal">(terbaru di atas)</span></h4>
            @if(count($backups))
                <span class="text-xs text-slate-400">{{ count($backups) }} berkas &middot; {{ number_format($totalKb, 1, ',', '.') }} KB</span>
            @endif
        </div>

        <div class="space-y-2 max-h-96 overflow-y-auto">
            @forelse($backups as $b)
            <div class="flex items-center justify-between gap-3 text-sm border rounded-lg px-3 py-2 {{ $loop->first ? 'border-blue-200 bg-blue-50/50' : '' }}">
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-medium truncate">{{ $b['nama'] }}</span>
                        @if($loop->first)
                            <span class="text-[10px] uppercase tracking-wide bg-blue-100 text-blue-700 rounded px-1.5 py-0.5">Terbaru</span>
                        @endif
                        @if($b['pengaman'])
                            <span class="text-[10px] uppercase tracking-wide bg-amber-100 text-amber-700 rounded px-1.5 py-0.5">Pengaman</span>
                        @endif
                    </div>
                    <div class="text-xs text-slate-400">
                        {{ $b['jenis'] }} &middot; {{ number_format($b['ukuran_kb'], 1, ',', '.') }} KB
                        &middot; {{ date('d/m/Y H:i', $b['waktu']) }}
                    </div>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <a href="{{ route('pengaturan.backup-restore.download', $b['nama']) }}" class="text-blue-600 hover:underline text-xs">Unduh</a>
                    @if(count($backups) > 1)
                    <form method="POST" action="{{ route('pengaturan.backup-restore.hapus') }}"
                          onsubmit="return confirm('Hapus backup {{ $b['nama'] }}?\n\nBerkas ini akan hilang permanen dari komputer. Kalau belum pernah diunduh, tidak ada salinan lain.')">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="nama" value="{{ $b['nama'] }}">
                        <button class="text-red-600 hover:underline text-xs">Hapus</button>
                    </form>
                    @endif
                </div>
            </div>
            @empty
            <p class="text-sm text-slate-400">Belum ada backup.</p>
            @endforelse
        </div>

        @if(count($backups))
        <p class="text-xs text-slate-400 mt-3">
            Aplikasi menyimpan sampai {{ $batasSimpan }} backup terbaru; yang lebih lama dibuang sendiri.
            Salinan bertanda <span class="font-medium">Pengaman</span> disimpan lebih lama karena dibuat
            tepat sebelum tindakan berisiko. Unduh dan simpan di flashdisk untuk berjaga-jaga kalau
            komputer ini rusak.
        </p>
        @endif
    </div>

    <div class="card p-6">
        <h3 class="font-semibold mb-3">Restore Database</h3>
        <p class="text-sm text-red-500 mb-4">⚠ Restore akan mengganti seluruh data saat ini dengan isi file backup yang diunggah. Backup otomatis akan dibuat sebelum proses restore dijalankan.</p>
        <form method="POST" action="{{ route('pengaturan.backup-restore.restore') }}" enctype="multipart/form-data" class="space-y-3" onsubmit="return confirm('Yakin ingin restore database? Data saat ini akan diganti.')">
            @csrf
            <input type="file" name="backup_file" accept=".sqlite" required class="form-input">
            <button class="btn btn-danger">Restore Database</button>
        </form>

        <h4 class="font-medium mt-6 mb-2 text-sm">Kalau tidak bisa masuk aplikasi</h4>
        <p class="text-sm text-slate-500">
            Aplikasi ini sengaja tidak punya tombol "lupa password" di halaman login — tombol seperti itu
            bisa dipakai siapa saja yang membuka aplikasi. Sebagai gantinya, di folder aplikasi ada berkas
            <span class="font-mono text-xs bg-slate-100 rounded px-1">PULIHKAN-LOGIN.bat</span>.
            Menjalankannya mengembalikan password admin ke bawaan, dan hanya bisa dilakukan orang yang
            memegang komputer toko ini. Setiap pemulihan tercatat dan diberitahukan di Dashboard.
        </p>
    </div>
</div>
@endsection
