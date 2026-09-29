{{-- Dipakai dua kali di halaman Neraca: sebagai peringatan di atas saat belum diisi, dan
     sebagai kartu pengaturan di bawah. Ditulis sekali supaya tidak bisa berbeda. --}}
@php $saran = $saranModalAwal ?? null; @endphp
<form method="POST" action="{{ route('laporan.neraca.pembukuan') }}" class="grid gap-3 md:grid-cols-4"
      x-data="{ isiSaran() { this.$refs.modal.value = {{ (int) ($saran ?? 0) }} } }">
    @csrf
    <div>
        <label class="form-label">Mulai Pembukuan</label>
        <input type="date" name="tanggal_mulai" class="form-input"
               value="{{ $atur['tanggal_mulai'] ?? now()->startOfYear()->toDateString() }}" required>
    </div>
    <div>
        <label class="form-label">Saldo Awal Kas</label>
        <input type="number" name="saldo_awal_kas" class="form-input" min="0" step="1"
               value="{{ (int) ($atur['saldo_awal_kas'] ?? 0) }}" required>
    </div>
    <div>
        <label class="form-label">Modal Awal</label>
        <input type="number" name="modal_awal" x-ref="modal" class="form-input" min="0" step="1"
               value="{{ (int) ($atur['modal_awal'] ?? 0) }}" required>
        @if($saran !== null && $saran > 0 && (int) $saran !== (int) ($atur['modal_awal'] ?? 0))
            <button type="button"
                    class="mt-2 w-full inline-flex items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 text-emerald-800 font-bold text-xs transition shadow-2xs cursor-pointer text-center"
                    @click="isiSaran()">
                <svg width="14" height="14" style="width: 14px; height: 14px; min-width: 14px; flex-shrink: 0;" class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <span>Pakai angka yang membuat neraca seimbang: Rp {{ number_format($saran, 0, ',', '.') }}</span>
            </button>
        @endif
    </div>
    <div class="flex items-end">
        <button class="btn btn-primary w-full">Simpan</button>
    </div>
    <p class="md:col-span-4 text-xs text-slate-500">
        <strong>Saldo awal kas</strong> = uang tunai yang ada di laci pada tanggal itu.
        <strong>Modal awal</strong> = seluruh kekayaan toko yang benar-benar milik sendiri saat itu
        (kas + nilai stok + peralatan, dikurangi hutang). Kalau toko baru mulai dan semuanya
        uang sendiri, dua-duanya sama.
        @if($saran !== null && $saran > 0)
            <br>
            <strong>Kalau bingung mengisi Modal Awal</strong>, klik tombol hijau <em>"Pakai Saran Seimbang"</em> di atas.
            Angka tersebut dihitung otomatis dari nilai persediaan stok barang di rak dan aset toko saat ini.
        @endif
    </p>
</form>
