<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Customer;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $existingCustomer = Customer::where('phone_number', $data['phone_number'])->first();
        $name = $existingCustomer->name ?? $data['phone_number'];

        $user = User::create([
            'name' => $name,
            'phone_number' => $data['phone_number'],
            'password' => $data['password'],
        ]);

        $user->assignRole('customer');

        Customer::updateOrCreate(
            ['phone_number' => $data['phone_number']],
            ['user_id' => $user->id, 'name' => $name],
        );

        $token = $user->createToken('customer-api')->plainTextToken;

        return $this->success([
            'token' => $token,
            'user' => new UserResource($user->load('customer')),
        ], 'تم إنشاء الحساب بنجاح', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('phone_number', $data['phone_number'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'phone_number' => [__('auth.failed')],
            ]);
        }

        $token = $user->createToken('customer-api')->plainTextToken;

        return $this->success([
            'token' => $token,
            'user' => new UserResource($user->load('customer')),
        ], 'تم تسجيل الدخول بنجاح');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'تم تسجيل الخروج بنجاح');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(
            new UserResource($request->user()->load('customer')),
        );
    }
}
