<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    // Get all users (Super Admin only)
    public function index()
    {
        $users = User::with('roles')->paginate(20);
        return response()->json($users);
    }

    // Get single user profile
    public function show($id)
    {
        $user = User::with(['roles', 'createdEvents', 'submissions', 'committees'])->findOrFail($id);
        return response()->json($user);
    }

    // Update user profile
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Check authorization
        if ($request->user()->id != $id && !$request->user()->hasRole('super_admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'institution' => 'nullable|string|max:255',
            'bio' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
            'research_domain' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:100',
        ]);

        // Handle photo upload
        if ($request->hasFile('photo')) {
            // Delete old photo
            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }
            $validated['photo'] = $request->file('photo')->store('photos', 'public');
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }

    // Delete user (Super Admin only)
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        
        // Delete photo if exists
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    // Assign role to user (Super Admin only)
    public function assignRole(Request $request, $id)
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id'
        ]);

        $user = User::findOrFail($id);
        $user->roles()->syncWithoutDetaching([$validated['role_id']]);

        return response()->json([
            'message' => 'Role assigned successfully',
            'user' => $user->load('roles')
        ]);
    }

    // Remove role from user (Super Admin only)
    public function removeRole(Request $request, $id)
    {
        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id'
        ]);

        $user = User::findOrFail($id);
        $user->roles()->detach($validated['role_id']);

        return response()->json([
            'message' => 'Role removed successfully',
            'user' => $user->load('roles')
        ]);
    }
}