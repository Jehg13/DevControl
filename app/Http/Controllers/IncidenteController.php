<?php

namespace App\Http\Controllers;

use App\Models\Incidente;
use App\Models\Proyecto;

class IncidenteController extends Controller
{
    public function index()
    {
        return view('admin.incidentes', [
            'incidentes' => Incidente::with('proyecto')->latest()->get(),
            'proyectos' => Proyecto::orderBy('nombre')->get(),
        ]);
    }
}
