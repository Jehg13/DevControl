<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\User;

class LoginCcontroller extends Controller
{
    public function Login(Request $request){
        $datosValidados = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        $remember = $request->boolean('remember');

        if(!Auth::attempt($datosValidados, $remember)){
            return redirect()->route('login')->with('error', 'El correo o la contraseña son incorrectos');
        }

        $request->session()->regenerate();

        $usuario = Auth::User();

      return match($usuario->rol){
        'admin' => redirect()->route('dashboard'),
        default => abort(403,'Rol de usuario invalido')
    };
}
}
   