<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function loginForm() { return view('auth.login'); }
    public function registerForm() { return view('auth.register'); }
    public function login(Request $request)
    {
        $credentials = $request->validate(['email'=>'required|email','password'=>'required']);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) return back()->withErrors(['email'=>'Invalid email or password.'])->onlyInput('email');
        $request->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }
    public function register(Request $request)
    {
        $data = $request->validate(['name'=>'required|max:120','email'=>'required|email|unique:users','password'=>'required|min:8|confirmed']);
        $user = User::create(['name'=>$data['name'],'email'=>$data['email'],'password'=>Hash::make($data['password'])]);
        $user->assignRole('Admin/User');
        Auth::login($user);
        return redirect()->route('dashboard');
    }
    public function logout(Request $request)
    {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('login');
    }
    public function forgotForm() { return view('auth.forgot-password'); }
    public function forgot(Request $request)
    {
        $request->validate(['email'=>'required|email']);
        $status = Password::sendResetLink($request->only('email'));
        return $status === Password::RESET_LINK_SENT ? back()->with('status',__($status)) : back()->withErrors(['email'=>__($status)]);
    }
    public function resetForm(Request $request, string $token) { return view('auth.reset-password',['token'=>$token,'email'=>$request->email]); }
    public function reset(Request $request)
    {
        $request->validate(['token'=>'required','email'=>'required|email','password'=>'required|min:8|confirmed']);
        $status=Password::reset($request->only('email','password','password_confirmation','token'),function(User $user,string $password){$user->forceFill(['password'=>Hash::make($password),'remember_token'=>Str::random(60)])->save();event(new PasswordReset($user));});
        return $status===Password::PASSWORD_RESET ? redirect()->route('login')->with('status',__($status)) : back()->withErrors(['email'=>__($status)]);
    }
}
