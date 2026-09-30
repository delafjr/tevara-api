<?php

declare(strict_types=1);

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this['id'] ?? $this->resource['id'] ?? null,
            'name'  => $this['name'] ?? $this->resource['name'] ?? null,
            'email' => $this['email'] ?? $this->resource['email'] ?? null,
            'role'  => $this['role'] ?? $this->resource['role'] ?? 'member',
        ];
    }
}
