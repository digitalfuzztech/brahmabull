<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Wallet;

class WalletSeeder extends Seeder
{
    public function run(): void
    {
        $wallets = [
            [
                'type' => 'cashapp',
                'name' => 'John Cena',
                'account_identifier' => '$johncena123',
                'qr_image' => 'wallets/cashapp1.png',
                'is_active' => true,
            ],
            [
                'type' => 'cashapp',
                'name' => 'Mike Tyson',
                'account_identifier' => '$miketyson99',
                'qr_image' => 'wallets/cashapp2.png',
                'is_active' => true,
            ],
            [
                'type' => 'paypal',
                'name' => 'Elon Musk',
                'account_identifier' => 'elon@paypal.com',
                'qr_image' => 'wallets/paypal1.png',
                'is_active' => true,
            ],
            [
                'type' => 'chime',
                'name' => 'John Wick',
                'account_identifier' => 'wick.chime',
                'qr_image' => 'wallets/chime1.png',
                'is_active' => true,
            ],
        ];

        foreach ($wallets as $wallet) {
            Wallet::create($wallet);
        }
    }
}
