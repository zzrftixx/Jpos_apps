<?php

namespace App\Http\Controllers;

use App\Models\CashierShift;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CashierShiftController extends Controller
{
    /**
     * Menampilkan riwayat seluruh shift kasir.
     */
    public function index(Request $request)
    {
        $from = $request->from ?: now()->startOfMonth()->toDateString();
        $to = $request->to ?: now()->toDateString();

        $shifts = CashierShift::with('user')
            ->whereDate('opened_at', '>=', $from)
            ->whereDate('opened_at', '<=', $to)
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('opened_at')
            ->paginate(15)
            ->withQueryString();

        $users = User::orderBy('name')->get();
        $activeShift = CashierShift::getActiveShift($request->user()->id);
        $shiftKasirSettings = Setting::shiftKasir();
        $shiftKasirEnabled = (bool) ($shiftKasirSettings['enabled'] ?? true);
        $cashDrawerEnabled = Setting::cashDrawerEnabled();
        $defaultStartingCash = Setting::defaultStartingCash();

        return view('transaksi.shift.index', compact('shifts', 'users', 'from', 'to', 'activeShift', 'shiftKasirEnabled', 'cashDrawerEnabled', 'shiftKasirSettings', 'defaultStartingCash'));
    }

    /**
     * Mengambil data shift aktif kasir yang sedang login (JSON).
     */
    public function current(Request $request): JsonResponse
    {
        $shift = CashierShift::getActiveShift($request->user()->id);

        if (!$shift) {
            return response()->json([
                'active' => false,
                'shift' => null,
                'summary' => null,
            ]);
        }

        return response()->json([
            'active' => true,
            'shift' => [
                'id' => $shift->id,
                'opened_at' => $shift->opened_at->format('d/m/Y H:i'),
                'starting_cash' => (float) $shift->starting_cash,
                'notes' => $shift->notes,
            ],
            'summary' => $shift->calculateSummary(),
        ]);
    }

    /**
     * Membuka shift baru dengan modal awal laci.
     */
    public function store(Request $request)
    {
        if (!Setting::shiftKasirEnabled()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fitur Shift Kasir sedang dinonaktifkan di menu Pengaturan.',
                ], 422);
            }
            return back()->with('error', 'Fitur Shift Kasir sedang dinonaktifkan di menu Pengaturan.');
        }

        $existing = CashierShift::getActiveShift($request->user()->id);
        if ($existing) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda masih memiliki shift aktif yang belum ditutup.',
                    'shift_id' => $existing->id,
                ], 422);
            }
            return back()->withErrors(['starting_cash' => 'Anda masih memiliki shift aktif yang belum ditutup.']);
        }

        $shiftSettings = Setting::shiftKasir();
        $cashDrawerEnabled = Setting::cashDrawerEnabled();
        $minStartingCash = ($cashDrawerEnabled && !empty($shiftSettings['require_positive_starting_cash'])) ? 1 : 0;

        $rules = [
            'starting_cash' => [$cashDrawerEnabled ? 'required' : 'nullable', 'numeric', "min:$minStartingCash"],
            'notes' => ['nullable', 'string', 'max:500'],
        ];

        if (($shiftSettings['time_mode'] ?? 'auto') === 'manual') {
            $rules['opened_at'] = ['nullable', 'date'];
        }

        $data = $request->validate($rules);

        $startingCash = $cashDrawerEnabled ? (float) ($data['starting_cash'] ?? 0) : 0.0;
        $openedAt = (($shiftSettings['time_mode'] ?? 'auto') === 'manual' && !empty($data['opened_at']))
            ? Carbon::parse($data['opened_at'])
            : now();

        $shift = CashierShift::create([
            'user_id' => $request->user()->id,
            'opened_at' => $openedAt,
            'starting_cash' => $startingCash,
            'expected_cash' => $startingCash,
            'status' => 'open',
            'notes' => $data['notes'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Shift kasir berhasil dibuka.',
                'shift' => $shift,
            ]);
        }

        $msg = $cashDrawerEnabled
            ? ('Shift kasir berhasil dibuka dengan modal awal Rp ' . number_format($startingCash, 0, ',', '.'))
            : 'Shift kasir berhasil dibuka.';

        return back()->with('success', $msg);
    }

    /**
     * Mengambil ringkasan kalkulasi terkini shift tertentu (JSON).
     */
    public function summary(CashierShift $shift): JsonResponse
    {
        return response()->json([
            'shift' => [
                'id' => $shift->id,
                'cashier_name' => $shift->user->name ?? '-',
                'opened_at' => $shift->opened_at->format('d/m/Y H:i'),
                'closed_at' => $shift->closed_at ? $shift->closed_at->format('d/m/Y H:i') : null,
                'status' => $shift->status,
                'starting_cash' => (float) $shift->starting_cash,
                'actual_cash' => $shift->actual_cash !== null ? (float) $shift->actual_cash : null,
                'difference' => $shift->difference !== null ? (float) $shift->difference : null,
            ],
            'summary' => $shift->calculateSummary(),
        ]);
    }

    /**
     * Menutup shift kasir dan mencatat uang fisik laci.
     */
    public function close(Request $request, CashierShift $shift)
    {
        // Pastikan shift masih open
        if ($shift->status !== 'open') {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Shift ini sudah ditutup sebelumnya.'], 422);
            }
            return back()->withErrors(['actual_cash' => 'Shift ini sudah ditutup sebelumnya.']);
        }

        // Hak akses: admin atau kasir pemilik shift
        $user = $request->user();
        if ($shift->user_id !== $user->id && $user->role?->slug !== 'admin') {
            abort(403, 'Hanya kasir bersangkutan atau admin yang dapat menutup shift ini.');
        }

        $shiftSettings = Setting::shiftKasir();
        $cashDrawerEnabled = Setting::cashDrawerEnabled();

        $rules = [
            'actual_cash' => [$cashDrawerEnabled ? 'required' : 'nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];

        if (($shiftSettings['time_mode'] ?? 'auto') === 'manual') {
            $rules['closed_at'] = ['nullable', 'date'];
        }

        $data = $request->validate($rules);

        $closedAt = (($shiftSettings['time_mode'] ?? 'auto') === 'manual' && !empty($data['closed_at']))
            ? Carbon::parse($data['closed_at'])
            : now();

        $shift->closed_at = $closedAt;
        $shift->actual_cash = ($cashDrawerEnabled || (isset($data['actual_cash']) && $data['actual_cash'] !== null && $data['actual_cash'] !== ''))
            ? (float) ($data['actual_cash'] ?? 0)
            : null;
        if (!empty($data['notes'])) {
            $shift->notes = $shift->notes ? ($shift->notes . ' | ' . $data['notes']) : $data['notes'];
        }

        $shift->syncCalculations();
        $shift->status = 'closed';
        $shift->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Shift berhasil ditutup.',
                'print_url' => route('shift.print', $shift->id),
                'difference' => $shift->difference,
            ]);
        }

        return redirect()->route('shift.index')->with('success', 'Shift kasir berhasil ditutup.');
    }

    /**
     * Cetak slip rekapitulasi shift kasir (Format Termal Z-Report).
     */
    public function print(CashierShift $shift)
    {
        $shift->load('user');
        $summary = $shift->calculateSummary();
        $storeProfile = Setting::get('store_profile', [
            'name' => config('app.name', 'JPOS'),
            'address' => '',
            'phone' => '',
        ]);

        return view('transaksi.shift.cetak', compact('shift', 'summary', 'storeProfile'));
    }
}
