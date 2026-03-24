<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function edit(User $user)
    {
        $userPermissions = $user->permissions()->pluck('permission')->toArray();
        $available = Permission::AVAILABLE;

        return view('admin.permissions.edit', compact('user', 'userPermissions', 'available'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string|in:' . implode(',', array_keys(Permission::AVAILABLE)),
        ]);

        $user->permissions()->delete();

        foreach ($validated['permissions'] ?? [] as $perm) {
            Permission::create(['user_id' => $user->id, 'permission' => $perm]);
        }

        return redirect()->route('admin.users.index')
            ->with('success', "Permisos de {$user->name} actualizados.");
    }
}
