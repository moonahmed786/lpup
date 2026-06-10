<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Issue a Passport personal access token in a secure HTTP-only cookie.
     *
     * Password-grant and client-credentials flows remain available via
     * Passport's POST /oauth/token endpoint (see README).
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $cookie = cookie(
            name: Config::string('api.auth_cookie.name'),
            value: $user->createToken('api')->accessToken,
            minutes: Config::integer('api.auth_cookie.ttl_minutes'),
            path: '/',
            domain: config('session.domain'),
            secure: (bool) config('session.secure'),
            httpOnly: true,
            raw: false,
            sameSite: config('session.same_site', 'lax'),
        );

        return response()->json([
            'message' => 'Authenticated.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ])->withCookie($cookie);
    }

    /**
     * Revoke the access token used to authenticate the current request.
     */
    public function logout(Request $request): Response
    {
        $request->user()->token()->revoke();

        return response('', Response::HTTP_NO_CONTENT)
            ->withCookie(cookie()->forget(Config::string('api.auth_cookie.name')));
    }

    /**
     * Return the authenticated user with their roles and permissions.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }
}
