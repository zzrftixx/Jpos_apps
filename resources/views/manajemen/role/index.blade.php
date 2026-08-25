@extends('layouts.app')
@section('title', 'Role Akses')

@section('content')
<div x-data="{ showModal: false, editItem: null,
    openEdit(item){ this.editItem = item; this.showModal = true },
    openAdd(){ this.editItem = { name: '', permissions: [] }; this.showModal = true } }">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h2 class="text-sm text-slate-500">Kelola role dan hak akses menu untuk setiap peran pengguna.</h2>
        <button @click="openAdd()" class="btn btn-primary">+ Tambah Role</button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($roles as $r)
        <div class="card p-4">
            <div class="flex items-center justify-between">
                <div>
                    <div class="font-semibold">{{ $r->name }}</div>
                    <div class="text-xs text-slate-400">{{ $r->users_count }} user</div>
                </div>
                @if($r->slug === 'admin')
                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Akses Penuh</span>
                @endif
            </div>
            <div class="flex flex-wrap gap-1 mt-3">
                @php $perms = $r->slug === 'admin' ? array_keys($menuKeys) : ($r->permissions ?? []); @endphp
                @foreach(array_slice($perms, 0, 5) as $p)
                    <span class="text-[11px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded">{{ $menuKeys[$p] ?? $p }}</span>
                @endforeach
                @if(count($perms) > 5)
                    <span class="text-[11px] text-slate-400">+{{ count($perms) - 5 }} lainnya</span>
                @endif
            </div>
            <div class="flex justify-end gap-3 mt-4 text-sm">
                <button @click='openEdit(@json($r))' class="text-blue-600 hover:underline">Edit</button>
                @if(!$r->is_system)
                <form method="POST" action="{{ route('role.destroy', $r) }}" onsubmit="return confirm('Hapus role ini?')">
                    @csrf @method('DELETE')
                    <button class="text-red-600 hover:underline">Hapus</button>
                </form>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- Modal --}}
    <div x-show="showModal" x-cloak class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4 overflow-y-auto">
        <div @click.outside="showModal=false" class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 my-8">
            <h3 class="font-semibold text-lg mb-4" x-text="editItem && editItem.id ? 'Edit Role' : 'Tambah Role'"></h3>
            <form :action="editItem && editItem.id ? '{{ url('manajemen/role') }}/' + editItem.id : '{{ route('role.store') }}'" method="POST" class="space-y-3">
                @csrf
                <template x-if="editItem && editItem.id"><input type="hidden" name="_method" value="PUT"></template>
                <div>
                    <label class="form-label">Nama Role</label>
                    <input type="text" name="name" x-model="editItem.name" required class="form-input" :readonly="editItem && editItem.slug === 'admin'">
                </div>

                <template x-if="!(editItem && editItem.slug === 'admin')">
                    <div>
                        <label class="form-label">Hak Akses Menu</label>
                        <div class="grid grid-cols-2 gap-2 border rounded-lg p-3 max-h-64 overflow-y-auto">
                            @foreach($menuKeys as $key => $label)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="permissions[]" value="{{ $key }}"
                                    x-bind:checked="editItem && editItem.permissions && editItem.permissions.includes('{{ $key }}')">
                                {{ $label }}
                            </label>
                            @endforeach
                        </div>
                    </div>
                </template>
                <template x-if="editItem && editItem.slug === 'admin'">
                    <p class="text-sm text-slate-500">Role Administrator selalu memiliki akses penuh ke semua menu.</p>
                </template>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showModal=false" class="btn btn-outline">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
