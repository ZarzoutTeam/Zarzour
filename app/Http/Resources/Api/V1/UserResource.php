<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'customer' => $this->whenLoaded('customer', function () {
                $customer = $this->customer;

                return $customer instanceof Customer ? [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone_number' => $customer->phone_number,
                    'address' => $customer->address,
                ] : null;
            }),
        ];
    }
}
