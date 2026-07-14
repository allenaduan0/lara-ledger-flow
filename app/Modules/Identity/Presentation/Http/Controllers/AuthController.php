<?php

namespace App\Modules\Identity\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Actions\IssueAccessTokenAction;
use App\Modules\Identity\Application\Actions\RegisterUserAction;
use App\Modules\Identity\Infrastructure\Persistence\Models\User;
use App\Modules\Identity\Presentation\Http\Requests\LoginRequest;
use App\Modules\Identity\Presentation\Http\Requests\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUserAction $register, IssueAccessTokenAction $tokens): JsonResponse
    {
        $data = $request->validated();
        $user = $register->execute($data['name'], strtolower($data['email']), $data['password']);

        return response()->json($this->payload($user, $tokens->execute($user, $data['device_name'] ?? 'api')), 201);
    }

    public function login(LoginRequest $request, IssueAccessTokenAction $tokens): JsonResponse
    {
        $data = $request->validated();
        $user = User::query()->where('email', strtolower($data['email']))->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are invalid.']]);
        }

        return response()->json($this->payload($user->load('roles.permissions'), $tokens->execute($user, $data['device_name'] ?? 'api')));
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->load('roles.permissions')]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(status: 204);
    }

    private function payload(User $user, string $token): array
    {
        return ['data' => ['user' => $user, 'token' => $token, 'token_type' => 'Bearer']];
    }
}
