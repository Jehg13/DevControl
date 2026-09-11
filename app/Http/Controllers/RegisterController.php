<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function create()
    {
        return view('register');
    }

    public function store(Request $request)
    {
        $datosValidados = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $usuario = User::create([
            'name' => $datosValidados['name'],
            'email' => $datosValidados['email'],
            'password' => $datosValidados['password'],
            'rol' => 'admin',
        ]);

        Auth::login($usuario);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
