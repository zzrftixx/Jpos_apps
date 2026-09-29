<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::when($request->q, fn($q) => $q->where(function ($sub) use ($request) {
                $sub->where('name', 'like', "%{$request->q}%")
                    ->orWhere('phone', 'like', "%{$request->q}%");
            }))
            ->when($request->customer_type, fn($q) => $q->where('customer_type', $request->customer_type))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('master.pelanggan.index', compact('customers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'customer_type' => ['nullable', 'string', 'in:UMUM,Reseller,Grosir'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
        ]);
        $data['customer_type'] = $data['customer_type'] ?: 'UMUM';
        Customer::create($data);
        return back()->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'customer_type' => ['nullable', 'string', 'in:UMUM,Reseller,Grosir'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
        ]);
        $data['customer_type'] = $data['customer_type'] ?: 'UMUM';
        $customer->update($data);
        return back()->with('success', 'Pelanggan berhasil diperbarui.');
    }

    public function destroy(Customer $customer)
    {
        if ($customer->sales()->exists()) {
            return back()->with('error', 'Pelanggan memiliki riwayat transaksi, tidak bisa dihapus.');
        }
        $customer->delete();
        return back()->with('success', 'Pelanggan berhasil dihapus.');
    }
}
