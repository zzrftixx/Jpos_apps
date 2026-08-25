<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount('users')->orderBy('name')->get();
        $menuKeys = Role::allMenuKeys();

        return view('manajemen.role.index', compact('roles', 'menuKeys'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
        ]);

        Role::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']) . '-' . Str::random(4),
            'permissions' => $data['permissions'] ?? [],
        ]);

        return back()->with('success', 'Role berhasil ditambahkan.');
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
        ]);

        if ($role->slug === 'admin') {
            $role->update(['name' => $data['name']]);
            return back()->with('success', 'Nama role Administrator diperbarui (akses tetap penuh).');
        }

        $role->update([
            'name' => $data['name'],
            'permissions' => $data['permissions'] ?? [],
        ]);

        return back()->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return back()->with('error', 'Role sistem tidak bisa dihapus.');
        }
        if ($role->users()->exists()) {
            return back()->with('error', 'Role masih dipakai user, tidak bisa dihapus.');
        }
        $role->delete();
        return back()->with('success', 'Role berhasil dihapus.');
    }
}
