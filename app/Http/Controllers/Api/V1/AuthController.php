<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\GoogleLoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

class AuthController extends Controller
{
    private const PASSWORD_RESET_CODE_EXPIRY_MINUTES = 15;

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
        ]);

        $user->profile()->create([]);

        $token = $user->createToken($request->userAgent() ?: 'mobile-app')->plainTextToken;

        $user->load('profile');

        return response()->json([
            'success' => true,
            'message' => 'Account registered successfully.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
                'errors' => [
                    'email' => ['The provided credentials are incorrect.'],
                ],
            ], 422);
        }

        /** @var \App\Models\User $user */
        $user = User::query()->where('email', $request->string('email'))->firstOrFail();

        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
                'errors' => [
                    'email' => ['The provided credentials are incorrect.'],
                ],
            ], 422);
        }

        $deviceName = $request->string('device_name')->toString() ?: ($request->userAgent() ?: 'mobile-app');

        $token = $user->createToken($deviceName)->plainTextToken;

        $user->load('profile');

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ],
        ]);
    }

    public function google(GoogleLoginRequest $request, GoogleIdTokenVerifier $googleIdTokenVerifier): JsonResponse
    {
        try {
            $payload = $googleIdTokenVerifier->verify($request->string('id_token')->toString());
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Google sign-in is temporarily unavailable.',
            ], 503);
        }

        if (! is_array($payload)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Google ID token.',
            ], 422);
        }

        $googleId = $payload['sub'] ?? null;
        $email = $payload['email'] ?? null;
        $emailVerified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOL);

        if (! is_string($googleId) || ! is_string($email) || ! $emailVerified) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Google ID token.',
            ], 422);
        }

        $email = Str::lower($email);
        $user = User::query()->where('google_id', $googleId)->first();
        $isNewUser = false;

        if (! $user) {
            $user = User::query()->where('email', $email)->first();

            if ($user && $user->google_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'This email address is already connected to another Google account.',
                ], 409);
            }

            if ($user) {
                $user->forceFill([
                    'google_id' => $googleId,
                    'email_verified_at' => $user->email_verified_at ?: now(),
                ])->save();
            } else {
                $user = User::query()->create([
                    'name' => Str::limit((string) ($payload['name'] ?? 'Google user'), 255, ''),
                    'email' => $email,
                    'google_id' => $googleId,
                    'email_verified_at' => now(),
                    'password' => Str::random(40),
                ]);
                $isNewUser = true;
            }
        }

        $user->profile()->firstOrCreate();
        $user->load('profile');

        $deviceName = $request->string('device_name')->toString() ?: ($request->userAgent() ?: 'mobile-app');
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => $isNewUser ? 'Google account registered successfully.' : 'Google sign-in successful.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => new UserResource($user),
            ],
        ], $isNewUser ? 201 : 200);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = Str::lower($validated['email']);
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $code = (string) random_int(100000, 999999);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $email],
                [
                    'token' => Hash::make($code),
                    'created_at' => now(),
                ],
            );

            Mail::raw(
                "Your Travel With You password reset code is {$code}. This code expires in ".self::PASSWORD_RESET_CODE_EXPIRY_MINUTES.' minutes.',
                function ($message) use ($email) {
                    $message->to($email)->subject('Your password reset code');
                }
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'If an account exists for this email, a password reset code has been sent.',
        ]);
    }

    public function verifyResetCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        if (! $this->validPasswordResetCode(Str::lower($validated['email']), $validated['code'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired password reset code.',
                'errors' => [
                    'code' => ['The provided password reset code is invalid or expired.'],
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset code verified successfully.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $email = Str::lower($validated['email']);

        if (! $this->validPasswordResetCode($email, $validated['code'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired password reset code.',
                'errors' => [
                    'code' => ['The provided password reset code is invalid or expired.'],
                ],
            ], 422);
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired password reset code.',
                'errors' => [
                    'code' => ['The provided password reset code is invalid or expired.'],
                ],
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.',
        ]);
    }

    public function me(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = request()->user();

        $user->load('profile');

        return response()->json([
            'success' => true,
            'message' => 'Authenticated user fetched successfully.',
            'data' => [
                'user' => new UserResource($user),
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = request()->user();

        $currentToken = $user->currentAccessToken();

        if ($currentToken) {
            $currentToken->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logout successful.',
            'data' => null,
        ]);
    }

    private function validPasswordResetCode(string $email, string $code): bool
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $record || ! $record->created_at) {
            return false;
        }

        if (Carbon::parse($record->created_at)->lessThan(now()->subMinutes(self::PASSWORD_RESET_CODE_EXPIRY_MINUTES))) {
            return false;
        }

        return Hash::check($code, $record->token);
    }
}
