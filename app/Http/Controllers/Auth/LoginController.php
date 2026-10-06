<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /**
     * Seeds the session with the user's home branch on login — null (all-branch access,
     * e.g. Owner) surfaces a branch switcher in the navbar instead. See
     * App\Http\Middleware\EnsureBranchAccess and resources/views/vendor/adminlte for the
     * consumers of this session key.
     */
    protected function authenticated(Request $request, $user)
    {
        if (! $user->is_active) {
            \Illuminate\Support\Facades\Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw \Illuminate\Validation\ValidationException::withMessages([
                $this->username() => ['Your account has been deactivated. Please contact your system administrator.'],
            ]);
        }

        if (! $user->isWithinShift()) {
            $shiftDetails = substr($user->time_in, 0, 5) . ' - ' . substr($user->time_out, 0, 5);
            \Illuminate\Support\Facades\Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw \Illuminate\Validation\ValidationException::withMessages([
                $this->username() => ["Login is restricted outside scheduled shift hours (Allowed Shift: {$shiftDetails})."],
            ]);
        }

        $request->session()->put('active_branch_id', $user->branch_id);
    }
}
