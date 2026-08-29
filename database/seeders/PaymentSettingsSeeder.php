<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'method'         => 'jazzcash',
                'account_number' => '03193009345',
                'account_name'   => 'Bashir Ahmed',
                'instructions'   => 'Send via JazzCash mobile app',
                'is_active'      => true,
            ],
            [
                'method'         => 'easypaisa',
                'account_number' => '03193009345',
                'account_name'   => 'Bashir Ahmed',
                'instructions'   => 'Send via Easypaisa mobile app',
                'is_active'      => true,
            ],
            [
                'method'         => 'bank_transfer',
                'account_number' => 'PK36HABB0000123456789',
                'account_name'   => 'Bashir Ahmed',
                'instructions'   => 'Direct bank transfer - HBL',
                'is_active'      => true,
            ],
        ];

        foreach ($settings as $s) {
            DB::table('payment_settings')->updateOrInsert(
                ['method' => $s['method']],
                array_merge($s, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}