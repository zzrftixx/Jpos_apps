@extends('layouts.app')
@section('title', 'Kategori Produk')

@section('content')
<div x-data="{ showModal: false, editItem: null, openEdit(item){ this.editItem = item; this.showModal = true }, openAdd(){ this.editItem = null; this.showModal = true } }">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <form method="GET" class="flex gap-2" data-live-search>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari kategori..." class="form-input w-64">
            <button class="btn btn-outline">Cari</button>
        </form>
        <button @click="openAdd()" class="btn btn-primary">+ Tambah Kategori</button>
    </div>

    {{-- Isi blok ini yang ditukar saat pencarian langsung; lihat
         public/vendor/jpos-live-search.js --}}
    <div data-live-results>
    <div class="card overflow-hidden">
        <table class="data-table w-full">
            <thead><tr><th>Nama</th><th>Deskripsi</th><th>Jumlah Produk</th><th class="text-right">Aksi</th></tr></thead>
            <tbody>
                @forelse($categories as $cat)
                <tr>
                    <td class="font-medium">{{ $cat->name }}</td>
                    <td class="text-slate-500">{{ $cat->description ?: '-' }}</td>
                    <td>{{ $cat->products_count }}</td>
                    <td class="text-right space-x-2">
                        <button @click='openEdit(@json($cat))' class="text-blue-600 text-sm hover:underline">Edit</button>
                        <form method="POST" action="{{ route('kategori.destroy', $cat) }}" class="inline" onsubmit="return confirm('Hapus kategori ini?')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 text-sm hover:underline">Hapus</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center text-slate-400 py-8">Belum ada kategori.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $categories->links() }}</div>
    </div>

    {{-- Modal --}}
    <div x-show="showModal" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div @click.outside="showModal=false" class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
            <h3 class="font-semibold text-lg mb-4" x-text="editItem ? 'Edit Kategori' : 'Tambah Kategori'"></h3>
            <form :action="editItem ? '{{ url('master/kategori') }}/' + editItem.id : '{{ route('kategori.store') }}'" method="POST" class="space-y-3">
                @csrf
                <template x-if="editItem"><input type="hidden" name="_method" value="PUT"></template>
                <div>
                    <label class="form-label">Nama Kategori</label>
                    <input type="text" name="name" :value="editItem ? editItem.name : ''" required class="form-input">
                </div>
                <div>
                    <label class="form-label">Deskripsi</label>
                    <textarea name="description" x-text="editItem ? editItem.description : ''" class="form-textarea" rows="2"></textarea>
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
