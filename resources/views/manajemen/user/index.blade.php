@extends('layouts.app')
@section('title', 'Manajemen User')

@section('content')
<div x-data="{ showModal: false, editItem: null, openEdit(item){ this.editItem = item; this.showModal = true }, openAdd(){ this.editItem = null; this.showModal = true } }">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <form method="GET" class="flex gap-2" data-live-search>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari user..." class="form-input w-64">
            <button class="btn btn-outline">Cari</button>
        </form>
        <button @click="openAdd()" class="btn btn-primary">+ Tambah User</button>
    </div>

    {{-- Isi blok ini yang ditukar saat pencarian langsung; lihat
         public/vendor/jpos-live-search.js --}}
    <div data-live-results>
    <div class="card overflow-hidden">
        <table class="data-table w-full">
            <thead><tr><th>Nama</th><th>Username</th><th>Role</th><th>Status</th><th class="text-right">Aksi</th></tr></thead>
            <tbody>
                @forelse($users as $u)
                <tr>
                    <td class="font-medium">{{ $u->name }}</td>
                    <td>{{ $u->username }}</td>
                    <td>{{ $u->role->name ?? '-' }}</td>
                    <td>
                        <span class="px-2 py-0.5 rounded-full text-xs {{ $u->is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                            {{ $u->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                    <td class="text-right space-x-2">
                        <button @click='openEdit(@json($u))' class="text-blue-600 text-sm hover:underline">Edit</button>
                        @if($u->id !== auth()->id())
                        <form method="POST" action="{{ route('user.destroy', $u) }}" class="inline" onsubmit="return confirm('Hapus user ini?')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 text-sm hover:underline">Hapus</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center text-slate-400 py-8">Belum ada user.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
    </div>

    <div x-show="showModal" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
        <div @click.outside="showModal=false" class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
            <h3 class="font-semibold text-lg mb-4" x-text="editItem ? 'Edit User' : 'Tambah User'"></h3>
            <form :action="editItem ? '{{ url('manajemen/user') }}/' + editItem.id : '{{ route('user.store') }}'" method="POST" class="space-y-3">
                @csrf
                <template x-if="editItem"><input type="hidden" name="_method" value="PUT"></template>
                <div>
                    <label class="form-label">Nama</label>
                    <input type="text" name="name" :value="editItem ? editItem.name : ''" required class="form-input">
                </div>
                <div>
                    <label class="form-label">Username</label>
                    <input type="text" name="username" :value="editItem ? editItem.username : ''" required pattern="[A-Za-z0-9_\-]+" title="Hanya huruf, angka, - dan _" class="form-input">
                </div>
                <div>
                    <label class="form-label" x-text="editItem ? 'Password (kosongkan jika tidak diubah)' : 'Password'"></label>
                    <input type="password" name="password" :required="!editItem" class="form-input">
                </div>
                <div>
                    <label class="form-label">Role</label>
                    <select name="role_id" required class="form-select">
                        @foreach($roles as $r)
                            <option value="{{ $r->id }}" x-bind:selected="editItem && editItem.role_id == {{ $r->id }}">{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Telepon</label>
                    <input type="text" name="phone" :value="editItem ? editItem.phone : ''" class="form-input">
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" x-bind:checked="!editItem || editItem.is_active"> Aktif
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showModal=false" class="btn btn-outline">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
