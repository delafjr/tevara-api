<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'TEVARA API Documentation',
    description: 'Dokumentasi REST API TEVARA - Project Based Learning',
    contact: new OA\Contact(name: 'TEVARA Dev Team')
)]
#[OA\Server(
    url: '/api/v1',
    description: 'API Base URL'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    name: 'Authorization',
    in: 'header',
    bearerFormat: 'Sanctum',
    scheme: 'bearer',
    description: 'Masukkan token Sanctum dengan format: Bearer <token>'
)]
#[OA\Tag(name: 'Auth', description: 'Autentikasi (Register, Login, Logout, Profile)')]
#[OA\Tag(name: 'Users', description: 'Manajemen User (CRUD)')]
#[OA\Tag(name: 'Dashboard', description: 'Dashboard per Role')]
#[OA\Tag(name: 'F-01: Pengajuan Proposal', description: 'Klien mengajukan proposal proyek baru')]
#[OA\Tag(name: 'F-02: Review & Validasi', description: 'Pengurus TeFa mereview kelayakan proposal')]
#[OA\Tag(name: 'F-03: SPK & Kontrak', description: 'Pembuatan dan penandatanganan SPK / kontrak kerja serta assign PM')]
#[OA\Tag(name: 'F-04: Penugasan Tim', description: 'Penugasan dan manajemen tim mahasiswa proyek')]
#[OA\Tag(name: 'Master Data', description: 'Data master kategori, status, role tim proyek, dan kategori task')]
abstract class Controller
{
    //
}
