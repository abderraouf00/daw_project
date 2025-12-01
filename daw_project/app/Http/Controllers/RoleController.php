<?php
namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    // Get all roles
    public function index()
    {
        $roles = Role::withCount('users')->get();
        return response()->json($roles);
    }

    // Get single role
    public function show($id)
    {
        $role = Role::with('users')->findOrFail($id);
        return response()->json($role);
    }

    // Create role (Super Admin only)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name|max:100'
        ]);

        $role = Role::create($validated);

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role
        ], 201);
    }

    // Update role
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name,' . $id . '|max:100'
        ]);

        $role->update($validated);

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => $role
        ]);
    }

    // Delete role
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        // Check if role is assigned to users
        if ($role->users()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete role with assigned users'
            ], 400);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted successfully']);
    }
}
