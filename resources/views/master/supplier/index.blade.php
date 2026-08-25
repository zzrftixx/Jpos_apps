@extends('layouts.app')
@section('title', 'Supplier')

@section('content')
<div x-data="{ showModal: false, editItem: null, openEdit(item){ this.editItem = item; this.showModal = true }, openAdd(){ this.editItem = null; this.showModal = true } }">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <form method="GET" class="flex gap-2" data-live-search>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari supplier..." class="form-input w-64">
            <button class="btn btn-outline">Cari</button>
        </form>
        <button @click="openAdd()" class="btn btn-primary">+ Tambah Supplier</button>
    </div>

    {{-- Isi blok ini yang ditukar saat pencarian langsung; lihat
         public/vendor/jpos-live-search.js --}}
    <div data-live-results>
    <div class="card overflow-hidden">
        <table class="data-table w-full">
            <thead><tr><th>Nama</th><th>Kontak</th><th>Telepon</th><th>Email</th><th>Produk</th><th class="text-right">Aksi</th></tr></thead>
            <tbody>
                @forelse($suppliers as $s)
                <tr>
                    <td class="font-medium">{{ $s->name }}</td>
                    <td>{{ $s->contact_person ?: '-' }}</td>
                    <td>{{ $s->phone ?: '-' }}</td>
                    <td>{{ $s->email ?: '-' }}</td>
                    <td>{{ $s->products_count }}</td>
                    <td class="text-right space-x-2">
                        <button @click='openEdit(@json($s))' class="text-blue-600 text-sm hover:underline">Edit</button>
                        <form method="POST" action="{{ route('supplier.destroy', $s) }}" class="inline" onsubmit="return confirm('Hapus supplier ini?')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 text-sm hover:underline">Hapus</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-slate-400 py-8">Belum ada supplier.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $suppliers->links() }}</div>
    </div>

    <div x-show="showModal" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div @click.outside="showModal=false" class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
            <h3 class="font-semibold text-lg mb-4" x-text="editItem ? 'Edit Supplier' : 'Tambah Supplier'"></h3>
            <form :action="editItem ? '{{ url('master/supplier') }}/' + editItem.id : '{{ route('supplier.store') }}'" method="POST" class="space-y-3">
                @csrf
                <template x-if="editItem"><input type="hidden" name="_method" value="PUT"></template>
                <div>
                    <label class="form-label">Nama Supplier</label>
                    <input type="text" name="name" :value="editItem ? editItem.name : ''" required class="form-input">
                </div>
                <div>
                    <label class="form-label">Kontak Person</label>
                    <input type="text" name="contact_person" :value="editItem ? editItem.contact_person : ''" class="form-input">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">Telepon</label>
                        <input type="text" name="phone" :value="editItem ? editItem.phone : ''" class="form-input">
                    </div>
                    <div>
                        <label class="form-label">Email</label>
                        <input type="email" name="email" :value="editItem ? editItem.email : ''" class="form-input">
                    </div>
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
