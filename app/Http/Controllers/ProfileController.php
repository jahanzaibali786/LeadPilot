<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show()
    {
        return view('profile.show', ['user' => auth()->user()]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_profile_photo' => 'nullable|boolean',
            'current_password' => 'nullable|required_with:password|string',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if (filled($data['password'] ?? null)) {
            if (! Hash::check($data['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => 'The current password is incorrect.'])->withInput();
            }
            $user->password = $data['password'];
        }

        if ($request->boolean('remove_profile_photo')) {
            if ($user->profile_photo_path) Storage::disk('public')->delete($user->profile_photo_path);
            $user->profile_photo_path = null;
        } elseif ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) Storage::disk('public')->delete($user->profile_photo_path);
            $user->profile_photo_path = $request->file('profile_photo')->store('profile-photos', 'public');
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->save();

        return back()->with('success', 'Profile updated.');
    }
}