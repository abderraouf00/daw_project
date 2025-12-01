<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Register a new user
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'institution' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
            'research_domain' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
            'role_id' => 'nullable|exists:roles,id'
        ]);

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('photos', 'public');
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'institution' => $validated['institution'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'photo' => $validated['photo'] ?? null,
            'research_domain' => $validated['research_domain'] ?? null,
            'country' => $validated['country'] ?? null,
        ]);

        // Assign role (default: participant)
        $roleId = $validated['role_id'] ?? Role::where('name', 'participant')->first()->id;
        $user->roles()->attach($roleId);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user->load('roles')
        ], 201);
    }

    // Login
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user->load('roles'),
            'token' => $token
        ]);
    }

    // Logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    // Get authenticated user
    public function me(Request $request)
    {
        return response()->json($request->user()->load('roles'));
    }

    // Password reset request
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // TODO: Implement password reset logic (send email with reset link)
        
        return response()->json([
            'message' => 'Password reset link sent to your email'
        ]);
    }
}
