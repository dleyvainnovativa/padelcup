<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManagerRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ManagerController extends Controller
{
    public function index()
    {
        $managers = User::where('role', 'manager')
            ->latest()
            ->paginate(20);

        return view('admin.managers.index', compact('managers'));
    }

    public function create()
    {
        return view('admin.managers.create');
    }

    public function store(StoreManagerRequest $request)
    {
        User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'role' => 'manager',
            'terms_accepted_at' => now(),
            'terms_version' => config('app.terms_version', '1.0'),
            'email_verified_at' => now(), // admin-vetted
        ]);

        return redirect()
            ->route('admin.managers.index')
            ->with('status', 'Organizador creado correctamente.');
    }
}
