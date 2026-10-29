<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Minimum duration of a failed login, longer than a bcrypt check.
     */
    private const FAILED_LOGIN_MICROSECONDS = 400_000;

    /**
     * Issue a Bearer token for valid credentials, with the abilities of the user's role.
     *
     * Wrong email and wrong password return the same error and, thanks to the
     * timebox, take the same time, so the endpoint cannot be used to discover
     * registered emails.
     *
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = (new Timebox)->call(function (Timebox $timebox) use ($request): ?User {
            $user = User::firstWhere('email', $request->validated('email'));

            if ($user && Hash::check($request->validated('password'), $user->password)) {
                $timebox->returnEarly();

                return $user;
            }

            return null;
        }, self::FAILED_LOGIN_MICROSECONDS);

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $token = $user->createToken(
            $request->validated('device_name', 'api'),
            $user->role->abilities(),
            now()->addMinutes(config('sanctum.expiration')),
        );

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'abilities' => $token->accessToken->abilities,
                'expires_at' => $token->accessToken->expires_at,
                'user' => new UserResource($user),
            ],
        ]);
    }

    /**
     * Revoke the token used in the current request.
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * Display the user that owns the current token.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
