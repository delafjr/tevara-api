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
#[OA\Tag(name: 'Master Data', description: 'Data master kategori proposal, status, role tim proyek, dan kategori task')]
#[OA\Tag(name: 'F-01: Pengajuan Proposal', description: 'Klien mengajukan proposal proyek baru')]
#[OA\Tag(name: 'F-02: Review & Validasi', description: 'Pengurus TeFa mereview kelayakan proposal')]
#[OA\Tag(name: 'F-03: SPK & Kontrak', description: 'Pembuatan dan penandatanganan SPK / kontrak kerja serta assign PM')]
#[OA\Tag(name: 'F-04: Penugasan Tim', description: 'Penugasan dan manajemen tim mahasiswa proyek')]
#[OA\Tag(name: 'F-05: Tasks & Progress', description: 'Manajemen task dan laporan progres sprint oleh mahasiswa, review oleh dosen PM')]
#[OA\Tag(name: 'F-06: UAT', description: 'User Acceptance Testing — buat sesi, isi item, sign-off klien')]
#[OA\Tag(name: 'F-07: BAST', description: 'Berita Acara Serah Terima dan persetujuan klien setelah UAT PASSED')]
#[OA\Tag(name: 'F-09: Pembayaran', description: 'Catat termin pembayaran, upload bukti transfer oleh klien, verifikasi oleh dosen/PM')]
abstract class Controller
{
    //
}
