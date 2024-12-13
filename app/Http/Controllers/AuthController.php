<?php

namespace App\Http\Controllers;

use App\Models\Administrator;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function signup(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|unique:users|min:4|max:60',
            'password' => 'required|min:5|max:20'
        ]);

        $user = User::create([
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token' => $token
        ], 201);
    }

    public function signin(Request $request)
{
    $validated = $request->validate([
        'username' => 'required|min:4|max:60',
        'password' => 'required|min:5|max:20'
    ]);

    $admin = Administrator::where('username', $validated['username'])->first();
    if ($admin && (Hash::check($validated['password'], $admin->password) || $validated['password'] === $admin->password)) {
        $admin->last_login_at = now();
        $admin->save();

        $token = $admin->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'role' => 'admin'
        ]);
    }

    $user = User::where('username', $validated['username'])->first();
    if ($user && (Hash::check($validated['password'], $user->password) || $validated['password'] === $user->password)) {
        $user->last_login_at = now();
        $user->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'role' => 'user'
        ]);

        // return response()->json([
        //     'status' => 'success',
        //     'token' => $token,
        //     'role' => $user->role
        // ]);
    }

    return response()->json([
        'status' => 'invalid',
        'message' => 'Wrong username or password'
    ], 401);
}

    public function signout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success'
        ]);
    }
}

