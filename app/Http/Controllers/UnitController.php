<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $units = Unit::withCount('productUnits')
            ->when($request->q, fn($q) => $q->where('name', 'like', "%{$request->q}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('master.satuan.index', compact('units'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:units,name'],
            'is_weighable' => ['nullable', 'boolean'],
        ]);
        $data['is_weighable'] = $request->boolean('is_weighable');
        Unit::create($data);
        return back()->with('success', 'Satuan berhasil ditambahkan.');
    }

    public function update(Request $request, Unit $unit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:units,name,' . $unit->id],
            'is_weighable' => ['nullable', 'boolean'],
        ]);
        $data['is_weighable'] = $request->boolean('is_weighable');
        $unit->update($data);
        return back()->with('success', 'Satuan berhasil diperbarui.');
    }

    public function destroy(Unit $unit)
    {
        if ($unit->productUnits()->exists()) {
            return back()->with('error', 'Satuan masih dipakai produk, tidak bisa dihapus.');
        }
        $unit->delete();
        return back()->with('success', 'Satuan berhasil dihapus.');
    }
}
