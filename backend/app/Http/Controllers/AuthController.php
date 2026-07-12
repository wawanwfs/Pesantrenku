<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function loginPage(Request $request)
    {
        if ($request->user()) {
            $role = $request->user()->loadMissing('roles')->role_names[0] ?? 'super_admin';

            return redirect('/dashboard/'.$this->homeRole($role));
        }

        return Inertia::render('Login');
    }

    public function login(LoginRequest $request)
    {
        $login = $request->string('login')->toString();
        $user = User::with('roles')
            ->where('email', $login)
            ->orWhere('username', $login)
            ->orWhere('phone', $login)
            ->first();

        if (! $user || $user->status !== 'active' || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages(['login' => 'Email/username/telepon atau password salah.']);
        }

        if ($request->is('api/*')) {
            $memberships = $this->membershipData($user);
            $user->tokens()->where('name', 'mobile')->delete();

            return response()->json([
                'token' => $user->createToken('mobile')->plainTextToken,
                'user' => $user,
                'roles' => $user->role_names,
                'memberships' => $memberships,
                'active_pesantren' => $memberships->first(),
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $activePesantren = $this->membershipData($user)->first();
        if ($activePesantren) {
            $request->session()->put('active_pesantren_id', $activePesantren->id);
        }

        $request->session()->forget('url.intended');

        return redirect('/dashboard/'.$this->homeRole($user->role_names[0] ?? 'super_admin'));
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('roles'),
            'roles' => $request->user()->role_names,
            'memberships' => $this->tenantContext->memberships($request),
            'active_pesantren' => $this->tenantContext->activePesantren($request),
        ]);
    }

    public function sessionStatus(Request $request)
    {
        $user = $request->user()?->loadMissing('roles');

        if (! $user) {
            return response()->json(['authenticated' => false]);
        }

        return response()->json([
            'authenticated' => true,
            'home' => '/dashboard/'.$this->homeRole($user->role_names[0] ?? 'super_admin'),
        ]);
    }

    public function switchTenant(Request $request)
    {
        $validated = $request->validate([
            'pesantren_id' => ['required', 'integer'],
        ]);

        $active = $this->tenantContext->setActive($request, (int) $validated['pesantren_id']);

        if ($request->is('api/*')) {
            return response()->json(['active_pesantren' => $active]);
        }

        return back();
    }

    public function logout(Request $request)
    {
        if ($request->is('api/*')) {
            $request->user()?->currentAccessToken()?->delete();

            return response()->json(['message' => 'Logout berhasil.']);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    private function homeRole(string $role): string
    {
        return str_replace('_', '-', match ($role) {
            'owner_pesantren' => 'owner',
            'admin_keuangan' => 'keuangan',
            'admin_akademik' => 'akademik',
            'admin_tu' => 'tu',
            default => $role,
        });
    }

    private function membershipData(User $user)
    {
        return DB::table('pesantren_user')
            ->join('pesantrens', 'pesantren_user.pesantren_id', '=', 'pesantrens.id')
            ->join('roles', 'pesantren_user.role_id', '=', 'roles.id')
            ->where('pesantren_user.user_id', $user->id)
            ->where('pesantren_user.status', 'active')
            ->where('pesantrens.status', 'active')
            ->whereNull('pesantrens.deleted_at')
            ->select([
                'pesantrens.id',
                'pesantrens.name',
                'pesantrens.city',
                'roles.name as role',
                'roles.label as role_label',
            ])
            ->orderBy('pesantren_user.id')
            ->get();
    }
}
