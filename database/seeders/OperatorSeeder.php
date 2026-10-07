<?php

namespace Database\Seeders;

use App\Enums\OperatorRole;
use App\Enums\OperatorStatus;
use App\Models\Operator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OperatorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $operators = [
            [
                'name' => 'Admin Margodadi',
                'email' => 'admin@margodadi.desa.id',
                'password' => Hash::make('123'),
                'phone' => '0811-0000-0001',
                'avatar_url' => 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=150&auto=format&fit=crop&q=80',
                'role' => OperatorRole::ADMIN,
                'status' => OperatorStatus::ONLINE,
                'is_active' => true,
            ],
            [
                'name' => 'Operator Margodadi',
                'email' => 'operator@margodadi.desa.id',
                'password' => Hash::make('123'),
                'phone' => '0812-0000-0002',
                'avatar_url' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=150&auto=format&fit=crop&q=80',
                'role' => OperatorRole::OPERATOR,
                'status' => OperatorStatus::ONLINE,
                'is_active' => true,
            ],
            [
                'name' => 'Siti Aminah',
                'email' => 'siti.aminah@margodadi.desa.id',
                'password' => Hash::make('123'),
                'phone' => '0812-3456-7890',
                'avatar_url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&auto=format&fit=crop&q=80',
                'role' => OperatorRole::OPERATOR,
                'status' => OperatorStatus::ONLINE,
                'is_active' => true,
            ],
            [
                'name' => 'Ahmad Fauzi',
                'email' => 'ahmad.fauzi@margodadi.desa.id',
                'password' => Hash::make('123'),
                'phone' => '0813-9876-5432',
                'avatar_url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80',
                'role' => OperatorRole::OPERATOR,
                'status' => OperatorStatus::ONLINE,
                'is_active' => true,
            ],
            [
                'name' => 'Budi Santoso',
                'email' => 'budi.santoso@margodadi.desa.id',
                'password' => Hash::make('123'),
                'phone' => '0811-2233-4455',
                'avatar_url' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80',
                'role' => OperatorRole::ADMIN,
                'status' => OperatorStatus::OFFLINE,
                'is_active' => true,
            ],
            [
                'name' => 'Nurul Hidayah',
                'email' => 'nurul.hidayah@margodadi.desa.id',
                'password' => Hash::make('123'),
                'phone' => '0852-7788-9900',
                'avatar_url' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&auto=format&fit=crop&q=80',
                'role' => OperatorRole::OPERATOR,
                'status' => OperatorStatus::ONLINE,
                'is_active' => true,
            ],
        ];

        foreach ($operators as $operatorData) {
            Operator::updateOrCreate(
                ['email' => $operatorData['email']],
                $operatorData
            );
        }
    }
}
