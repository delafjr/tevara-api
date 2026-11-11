<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Dela Fajar Mulia'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'dela.43325207@mhs.polines.ac.id'),
        new OA\Property(property: 'phone_number', type: 'string', nullable: true, example: '6288228893279'),
        new OA\Property(property: 'nidn', type: 'string', nullable: true, example: '0000000001'),
        new OA\Property(property: 'department', type: 'string', nullable: true, example: 'Teknik Informatika'),
        new OA\Property(property: 'nim', type: 'string', nullable: true, example: '4.33.25.1.14'),
        new OA\Property(property: 'study_program', type: 'string', nullable: true, example: 'D3 Teknik Informatika'),
        new OA\Property(property: 'company_name', type: 'string', nullable: true, example: 'PT. Maju Bersama Sentosa'),
        new OA\Property(property: 'position', type: 'string', nullable: true, example: 'IT Manager'),
        new OA\Property(
            property: 'roles',
            type: 'array',
            items: new OA\Items(type: 'string', example: 'mahasiswa')
        ),
        new OA\Property(property: 'is_pm', type: 'boolean', example: false),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-10-20T08:00:00.000000Z'),
    ]
)]
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $clientProfile = $this->clientPic?->clientProfile ?? $this->clientProfile;

        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'email'        => $this->email,
            'phone_number' => $this->phone_number,
            'company_name' => $this->companyName(),
            'position'     => $this->position(),
            'nidn'         => $this->lecturerProfile?->nidn,
            'department'   => $this->lecturerProfile?->department,
            'nim'          => $this->studentProfile?->nim,
            'study_program' => $this->studentProfile?->study_program,
            'client_profile_id' => $clientProfile?->id,
            'company_email' => $clientProfile?->user?->email,
            'roles'        => $this->whenLoaded('roles', fn () => $this->getRoleNames()),
            'is_pm'        => $this->isPm(),
            'created_at'   => $this->created_at,
        ];
    }
}
