<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ClientProfile;
use App\Models\ClientPic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $clients = [
            [
                'company' => [
                    'name'        => 'PT. Maju Bersama Sentosa',
                    'email'       => 'owner@majubersamasentosa.co.id',
                    'address'     => 'Jl. Pandanaran No. 12, Semarang',
                    'client_type' => 'EXTERNAL',
                ],
                'pics' => [
                    [
                        'name'     => 'Budi Santoso',
                        'email'    => 'budi.santoso@majubersamasentosa.co.id',
                        'password' => 'password',
                        'phone'    => '6281234567890',
                        'position' => 'IT Manager',
                    ],
                ],
            ],
            [
                'company' => [
                    'name'        => 'CV. Teknologi Nusantara',
                    'email'       => 'owner@teknologinusantara.id',
                    'address'     => 'Jl. Pemuda No. 45, Semarang',
                    'client_type' => 'EXTERNAL',
                ],
                'pics' => [
                    [
                        'name'     => 'Dewi Rahayu',
                        'email'    => 'dewi.rahayu@teknologinusantara.id',
                        'password' => 'password',
                        'phone'    => '6289876543210',
                        'position' => 'Direktur Operasional',
                    ],
                ],
            ],
            [
                'company' => [
                    'name'        => 'Dinas Komunikasi dan Informatika Kota Semarang',
                    'email'       => 'admin@diskominfo.semarangkota.go.id',
                    'address'     => 'Jl. Pemuda No. 148, Semarang',
                    'client_type' => 'EXTERNAL',
                ],
                'pics' => [
                    [
                        'name'     => 'Agus Widodo',
                        'email'    => 'agus.widodo@diskominfo.semarangkota.go.id',
                        'password' => 'password',
                        'phone'    => '6281122334455',
                        'position' => 'Kepala Bidang Aplikasi',
                    ],
                ],
            ],
            [
                'company' => [
                    'name'        => 'Unit Bisnis Polines (Internal)',
                    'email'       => 'admin@bisnis.polines.ac.id',
                    'address'     => 'Jl. Prof. Soedarto, Tembalang, Semarang',
                    'client_type' => 'INTERNAL',
                ],
                'pics' => [
                    [
                        'name'     => 'Susanto Nugroho',
                        'email'    => 'susanto.nugroho@polines.ac.id',
                        'password' => 'password',
                        'phone'    => '6281333444555',
                        'position' => 'Kepala Unit Bisnis',
                    ],
                    [
                        'name'     => 'Retno Wulandari',
                        'email'    => 'retno.wulandari@polines.ac.id',
                        'password' => 'password',
                        'phone'    => '6285566778899',
                        'position' => 'Staff IT',
                    ],
                ],
            ],
            [
                'company' => [
                    'name'        => 'PT. Digital Solusi Indonesia',
                    'email'       => 'owner@digitalsolusi.id',
                    'address'     => 'Jl. Ahmad Yani No. 88, Semarang',
                    'client_type' => 'EXTERNAL',
                ],
                'pics' => [
                    [
                        'name'     => 'Hendra Kusuma',
                        'email'    => 'hendra.kusuma@digitalsolusi.id',
                        'password' => 'password',
                        'phone'    => '6287811223344',
                        'position' => 'CEO',
                    ],
                ],
            ],
        ];

        foreach ($clients as $clientData) {
            $ownerUser = User::firstOrCreate(
                ['email' => $clientData['company']['email']],
                [
                    'name'     => $clientData['company']['name'],
                    'password' => Hash::make('password'),
                ]
            );
            $ownerUser->syncRoles(['klien']);

            $clientProfile = ClientProfile::firstOrCreate(
                ['user_id' => $ownerUser->id],
                [
                    'address'     => $clientData['company']['address'],
                    'client_type' => $clientData['company']['client_type'],
                ]
            );

            foreach ($clientData['pics'] as $picData) {
                $picUser = User::firstOrCreate(
                    ['email' => $picData['email']],
                    [
                        'name'         => $picData['name'],
                        'password'     => Hash::make($picData['password']),
                        'phone_number' => $picData['phone'],
                    ]
                );
                $picUser->syncRoles(['klien']);

                ClientPic::firstOrCreate(
                    ['user_id' => $picUser->id],
                    [
                        'client_profile_id' => $clientProfile->id, // ← fix
                        'position'          => $picData['position'],
                    ]
                );
            }
        }
    }
}