<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $usuarios = User::query()
            ->when($request->filled('buscar'), function ($query) use ($request) {
                $term = $request->string('buscar')->toString();

                $query->where(function ($builder) use ($term) {
                    $builder->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->when($request->filled('rol'), fn ($query) => $query->where('rol', $request->string('rol')))
            ->latest()
            ->get();

        return view('admin.usuarios', [
            'usuarios' => $usuarios,
            'filtros' => $request->only(['buscar', 'rol']),
        ]);
    }

    public function store(Request $request)
    {
        $validado = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'rol' => ['required', Rule::in(User::ROLES)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        User::create([
            'name' => $validado['name'],
            'email' => $validado['email'],
            'rol' => $validado['rol'],
            'password' => Hash::make($validado['password']),
        ]);

        return redirect()->route('usuarios')->with('success', 'Usuario creado correctamente.');
    }

    public function update(Request $request, User $usuario)
    {
        $validado = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'rol' => ['required', Rule::in(User::ROLES)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $usuario->name = $validado['name'];
        $usuario->email = $validado['email'];
        $usuario->rol = $validado['rol'];

        if (! empty($validado['password'])) {
            $usuario->password = Hash::make($validado['password']);
        }

        $usuario->save();

        return redirect()->route('usuarios')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        if ($usuario->is(auth()->user())) {
            return redirect()->route('usuarios')->with('error', 'No puedes eliminar tu propia cuenta mientras estás conectado.');
        }

        $usuario->delete();

        return redirect()->route('usuarios')->with('success', 'Usuario eliminado correctamente.');
    }
}
