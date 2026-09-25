<?php

namespace App\Models;

use App\Support\Angka;
use App\Support\MetodeBayar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierShift extends Model
{
    protected $fillable = [
        'user_id',
        'opened_at',
        'closed_at',
        'starting_cash',
        'cash_sales',
        'non_cash_sales',
        'cash_refunds',
        'cash_in',
        'cash_out',
        'expected_cash',
        'actual_cash',
        'difference',
        'status',
        'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'starting_cash' => 'decimal:2',
        'cash_sales' => 'decimal:2',
        'non_cash_sales' => 'decimal:2',
        'cash_refunds' => 'decimal:2',
        'cash_in' => 'decimal:2',
        'cash_out' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'actual_cash' => 'decimal:2',
        'difference' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'cashier_shift_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    public static function getActiveShift(int $userId): ?self
    {
        return self::where('user_id', $userId)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    /**
     * Menghitung rincian real-time penjualan dan pergerakan kas selama shift ini.
     */
    public function calculateSummary(): array
    {
        $closedAt = $this->closed_at ?? now();

        // 1. Ambil pembayaran dari transaksi penjualan yang diterima selama rentang shift ini.
        // Baik dari transaksi baru yang dibuat di shift ini, maupun pelunasan pesanan waiting list.
        $payments = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.order_status', '<>', 'cancelled')
            ->where('sale_payments.created_at', '>=', $this->opened_at)
            ->where('sale_payments.created_at', '<=', $closedAt)
            ->where(function ($q) {
                $q->where('sale_payments.user_id', $this->user_id)
                    ->orWhere(function ($sub) {
                        $sub->whereNull('sale_payments.user_id')
                            ->where('sales.cashier_shift_id', $this->id);
                    });
            })
            ->select('sale_payments.method', 'sale_payments.amount')
            ->get();

        $cashSales = 0.0;
        $nonCashSales = 0.0;
        $paymentBreakdown = [];

        foreach ($payments as $p) {
            $canonical = MetodeBayar::normal($p->method);
            $amount = (float) $p->amount;

            $paymentBreakdown[$canonical] = ($paymentBreakdown[$canonical] ?? 0.0) + $amount;

            if ($canonical === 'tunai') {
                $cashSales += $amount;
            } else {
                $nonCashSales += $amount;
            }
        }

        // 2. Refund / Retur penjualan tunai selama shift
        $cashRefunds = (float) SaleReturn::query()
            ->where('user_id', $this->user_id)
            ->where('created_at', '>=', $this->opened_at)
            ->where('created_at', '<=', $closedAt)
            ->sum('total');

        // 3. Kas Masuk / Kas Keluar selama shift (selain kategori pembelian/aset)
        $cashTransactions = CashTransaction::query()
            ->where('user_id', $this->user_id)
            ->where('created_at', '>=', $this->opened_at)
            ->where('created_at', '<=', $closedAt)
            ->whereNotIn('category', ['pembelian', 'aset_tetap'])
            ->get();

        $cashIn = (float) $cashTransactions->where('type', 'in')->sum('amount');
        $cashOut = (float) $cashTransactions->where('type', 'out')->sum('amount');

        $startingCash = (float) $this->starting_cash;
        $expectedCash = Angka::bulat($startingCash + $cashSales - $cashRefunds + $cashIn - $cashOut);

        $trxCount = Sale::where('cashier_shift_id', $this->id)
            ->where('order_status', '<>', 'cancelled')
            ->count();

        return [
            'starting_cash' => $startingCash,
            'cash_sales' => Angka::bulat($cashSales),
            'non_cash_sales' => Angka::bulat($nonCashSales),
            'total_sales' => Angka::bulat($cashSales + $nonCashSales),
            'cash_refunds' => Angka::bulat($cashRefunds),
            'cash_in' => Angka::bulat($cashIn),
            'cash_out' => Angka::bulat($cashOut),
            'expected_cash' => $expectedCash,
            'payment_breakdown' => $paymentBreakdown,
            'transaction_count' => $trxCount,
        ];
    }

    /**
     * Memperbarui akumulasi nilai saat tutup shift.
     */
    public function syncCalculations(): void
    {
        $summary = $this->calculateSummary();

        $this->cash_sales = $summary['cash_sales'];
        $this->non_cash_sales = $summary['non_cash_sales'];
        $this->cash_refunds = $summary['cash_refunds'];
        $this->cash_in = $summary['cash_in'];
        $this->cash_out = $summary['cash_out'];
        $this->expected_cash = $summary['expected_cash'];

        if ($this->actual_cash !== null) {
            $this->difference = Angka::bulat((float) $this->actual_cash - (float) $this->expected_cash);
        }
    }
}
