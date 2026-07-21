<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'email_verified_at' => $this->email_verified_at,
            'is_active' => $this->is_active,
            'last_login_at' => $this->last_login_at,
            'role' => new RoleResource($this->whenLoaded('role')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}