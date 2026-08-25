<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $oldKeys = ['laporan_penjualan', 'laporan_stok', 'laporan_terlaris'];

        foreach (DB::table('roles')->get() as $role) {
            $permissions = json_decode($role->permissions ?? '[]', true) ?: [];

            if (array_intersect($oldKeys, $permissions)) {
                $permissions = array_diff($permissions, $oldKeys);
                $permissions[] = 'laporan';
                $permissions = array_values(array_unique($permissions));

                DB::table('roles')->where('id', $role->id)->update([
                    'permissions' => json_encode($permissions),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Old per-report keys are intentionally not restored.
    }
};
