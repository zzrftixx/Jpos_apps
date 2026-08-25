@extends('layouts.app')
@section('title', 'Satuan')

@section('content')
<div x-data="{ showModal: false, editItem: null, openEdit(item){ this.editItem = item; this.showModal = true }, openAdd(){ this.editItem = null; this.showModal = true } }">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <form method="GET" class="flex gap-2" data-live-search>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari satuan..." class="form-input w-64">
            <button class="btn btn-outline">Cari</button>
        </form>
        <button @click="openAdd()" class="btn btn-primary">+ Tambah Satuan</button>
    </div>

    {{-- Isi blok ini yang ditukar saat pencarian langsung; lihat
         public/vendor/jpos-live-search.js --}}
    <div data-live-results>
    <div class="card overflow-hidden">
        <table class="data-table w-full">
            <thead><tr><th>Nama Satuan</th><th>Timbangan</th><th>Dipakai di Produk</th><th class="text-right">Aksi</th></tr></thead>
            <tbody>
                @forelse($units as $unit)
                <tr>
                    <td class="font-medium">{{ $unit->name }}</td>
                    <td>
                        @if($unit->is_weighable)
                            <span class="px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-700">&#9878;&#65039; Boleh pecahan</span>
                        @else
                            <span class="text-slate-400 text-xs">Bilangan bulat</span>
                        @endif
                    </td>
                    <td>{{ $unit->product_units_count }}</td>
                    <td class="text-right space-x-2">
                        <button @click='openEdit(@json($unit))' class="text-blue-600 text-sm hover:underline">Edit</button>
                        <form method="POST" action="{{ route('satuan.destroy', $unit) }}" class="inline" onsubmit="return confirm('Hapus satuan ini?')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 text-sm hover:underline">Hapus</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center text-slate-400 py-8">Belum ada satuan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $units->links() }}</div>
    </div>

    {{-- Modal --}}
    <div x-show="showModal" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div @click.outside="showModal=false" class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
            <h3 class="font-semibold text-lg mb-4" x-text="editItem ? 'Edit Satuan' : 'Tambah Satuan'"></h3>
            <form :action="editItem ? '{{ url('master/satuan') }}/' + editItem.id : '{{ route('satuan.store') }}'" method="POST" class="space-y-3">
                @csrf
                <template x-if="editItem"><input type="hidden" name="_method" value="PUT"></template>
                <div>
                    <label class="form-label">Nama Satuan</label>
                    <input type="text" name="name" :value="editItem ? editItem.name : ''" required class="form-input" placeholder="mis. Dus, Lusin, Kg">
                </div>
                <div>
                    {{-- Hidden pendamping: checkbox yang tidak dicentang tidak dikirim browser
                         sama sekali, jadi tanpa ini melepas centangnya tidak pernah tersimpan. --}}
                    <label class="flex items-start gap-2 text-sm">
                        <input type="hidden" name="is_weighable" value="0">
                        <input type="checkbox" name="is_weighable" value="1" class="mt-0.5" x-bind:checked="editItem ? !!editItem.is_weighable : false">
                        <span>
                            <span class="block font-medium">&#9878;&#65039; Satuan timbangan</span>
                            <span class="block text-xs text-slate-400">Boleh dijual dengan qty pecahan di Kasir, mis. 2,5 Kg. Satuan hitung seperti Pcs atau Dus sebaiknya dibiarkan tidak dicentang &mdash; setengah dus tidak ada wujudnya.</span>
                        </span>
                    </label>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showModal=false" class="btn btn-outline">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
