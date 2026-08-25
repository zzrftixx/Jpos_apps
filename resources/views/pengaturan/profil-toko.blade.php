@extends('layouts.app')
@section('title', 'Profil Toko')

@section('content')
<div class="card p-6 max-w-2xl">
    <form method="POST" action="{{ route('pengaturan.profil-toko.update') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        <div x-data="{ logoPreview: null }" class="flex items-center gap-4">
            <img :src="logoPreview ? logoPreview : '{{ !empty($profile['logo']) ? url('media/'.$profile['logo']) : asset('images/no-image.svg') }}'" class="w-16 h-16 object-cover bg-slate-100 border rounded-lg">
            <div class="flex-1">
                <label class="form-label">Logo Toko</label>
                <input type="file" name="logo" accept="image/*" @change="logoPreview = URL.createObjectURL($event.target.files[0])" class="form-input">
                <p class="text-xs text-slate-400 mt-1">Format gambar (JPG/PNG/WEBP), dapat berupa foto berukuran berapa saja (otomatis di-scale & dioptimasi).</p>
            </div>
        </div>
        <div>
            <label class="form-label">Nama Toko</label>
            <input type="text" name="name" value="{{ $profile['name'] ?? '' }}" required class="form-input">
        </div>
        <div>
            <label class="form-label">Alamat</label>
            <textarea name="address" class="form-textarea" rows="2">{{ $profile['address'] ?? '' }}</textarea>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="form-label">Telepon</label>
                <input type="text" name="phone" value="{{ $profile['phone'] ?? '' }}" class="form-input">
            </div>
            <div>
                <label class="form-label">Email</label>
                <input type="email" name="email" value="{{ $profile['email'] ?? '' }}" class="form-input">
            </div>
        </div>
        <div>
            <label class="form-label">Catatan Kaki Struk</label>
            <input type="text" name="footer_note" value="{{ $profile['footer_note'] ?? '' }}" class="form-input">
        </div>
        <button class="btn btn-primary">Simpan Profil</button>
    </form>
</div>
@endsection
