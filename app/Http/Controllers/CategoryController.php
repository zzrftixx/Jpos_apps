<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::withCount('products')
            ->when($request->q, fn($q) => $q->where('name', 'like', "%{$request->q}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('master.kategori.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
        $data['slug'] = Str::slug($data['name']) . '-' . Str::random(4);
        Category::create($data);
        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
        $category->update($data);
        return back()->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'Kategori masih dipakai produk, tidak bisa dihapus.');
        }
        $category->delete();
        return back()->with('success', 'Kategori berhasil dihapus.');
    }
}
