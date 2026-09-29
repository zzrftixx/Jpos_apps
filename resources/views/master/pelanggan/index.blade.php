@extends('layouts.app')
@section('title', 'Pelanggan')

@section('content')
<div x-data="{ showModal: false, editItem: null, openEdit(item){ this.editItem = item; this.showModal = true }, openAdd(){ this.editItem = null; this.showModal = true } }">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <form method="GET" class="flex gap-2" data-live-search>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari pelanggan..." class="form-input w-64">
            <select name="customer_type" class="form-select text-xs w-36" onchange="this.form.submit()">
                <option value="">Semua Kategori</option>
                <option value="UMUM" {{ request('customer_type') === 'UMUM' ? 'selected' : '' }}>UMUM</option>
                <option value="Reseller" {{ request('customer_type') === 'Reseller' ? 'selected' : '' }}>Reseller</option>
                <option value="Grosir" {{ request('customer_type') === 'Grosir' ? 'selected' : '' }}>Grosir</option>
            </select>
            <button class="btn btn-outline">Cari</button>
        </form>
        <button @click="openAdd()" class="btn btn-primary">+ Tambah Pelanggan</button>
    </div>

    {{-- Isi blok ini yang ditukar saat pencarian langsung; lihat
         public/vendor/jpos-live-search.js --}}
    <div data-live-results>
    <div class="card overflow-hidden">
        <table class="data-table w-full">
            <thead><tr><th>Nama</th><th>Kategori</th><th>Telepon</th><th>Email</th><th>Poin</th><th class="text-right">Aksi</th></tr></thead>
            <tbody>
                @forelse($customers as $c)
                <tr>
                    <td class="font-medium">{{ $c->name }}</td>
                    <td>
                        @if(strtoupper($c->customer_type) === 'RESELLER')
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-700 border border-blue-200">Reseller</span>
                        @elseif(strtoupper($c->customer_type) === 'GROSIR')
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-700 border border-amber-200">Grosir</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">UMUM</span>
                        @endif
                    </td>
                    <td>{{ $c->phone ?: '-' }}</td>
                    <td>{{ $c->email ?: '-' }}</td>
                    <td>{{ $c->points }}</td>
                    <td class="text-right space-x-2">
                        <button @click='openEdit(@json($c))' class="text-blue-600 text-sm hover:underline">Edit</button>
                        <form method="POST" action="{{ route('pelanggan.destroy', $c) }}" class="inline" onsubmit="return confirm('Hapus pelanggan ini?')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 text-sm hover:underline">Hapus</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-slate-400 py-8">Belum ada pelanggan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $customers->links() }}</div>
    </div>

    <div x-show="showModal" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div @click.outside="showModal=false" class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
            <h3 class="font-semibold text-lg mb-4" x-text="editItem ? 'Edit Pelanggan' : 'Tambah Pelanggan'"></h3>
            <form :action="editItem ? '{{ url('master/pelanggan') }}/' + editItem.id : '{{ route('pelanggan.store') }}'" method="POST" class="space-y-3">
                @csrf
                <template x-if="editItem"><input type="hidden" name="_method" value="PUT"></template>
                <div>
                    <label class="form-label">Nama Pelanggan <span class="text-red-500">*</span></label>
                    <input type="text" name="name" :value="editItem ? editItem.name : ''" required class="form-input">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Kategori Pelanggan</label>
                        <select name="customer_type" class="form-select" :value="editItem ? (editItem.customer_type || 'UMUM') : 'UMUM'">
                            <option value="UMUM">UMUM (Retail)</option>
                            <option value="Reseller">Reseller</option>
                            <option value="Grosir">Grosir</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Telepon</label>
                        <input type="text" name="phone" :value="editItem ? editItem.phone : ''" class="form-input">
                    </div>
                </div>
                <div>
                    <label class="form-label">Email</label>
                    <input type="email" name="email" :value="editItem ? editItem.email : ''" class="form-input">
                </div>
                <div>
                    <label class="form-label">Alamat</label>
                    <textarea name="address" x-text="editItem ? editItem.address : ''" class="form-textarea" rows="2"></textarea>
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
