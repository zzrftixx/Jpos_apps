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
            <button type="button" class="text-xs text-brand-600 hover:underline mt-1" @click="isiSaran()">
                Pakai angka yang membuat neraca seimbang: Rp {{ number_format($saran, 0, ',', '.') }}
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
            <strong>Kalau bingung mengisi Modal Awal</strong>, pakai angka yang disarankan di atas.
            Itu dihitung mundur dari kekayaan toko yang ada sekarang, dan sebagian besar isinya
            adalah nilai stok yang sudah di rak sebelum pembukuan dimulai &mdash; barang yang
            memang tidak pernah tercatat asal uangnya karena dulu belum ada menu Pembelian.
        @endif
    </p>
</form>
