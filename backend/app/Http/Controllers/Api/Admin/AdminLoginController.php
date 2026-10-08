<?php
// backend/app/Http/Controllers/Api/Admin/AdminLoginController.php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthUserResource;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminLoginController extends Controller
{
    public function store(Request $request, AuditLogger $audit): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $user = Auth::user();
        if (! $user->isAdmin() || $user->isDisabled()) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
            }

            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if ($user->first_login_at === null) {
            $user->forceFill(['first_login_at' => now()])->saveQuietly();
        }

        $audit->record(AuditLogger::ADMIN_LOGIN, $user);

        return response()->json(['user' => (new AuthUserResource($user))->resolve()]);
    }
}
