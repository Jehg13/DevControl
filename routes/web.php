<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\LoginCcontroller;
use App\Http\Controllers\ProyectoController;
use App\Http\Controllers\TareasController;
use App\Http\Controllers\BugController;
use App\Models\User;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::post('/',[LoginCcontroller::class, 'Login'])->name('login.process');

Route::get('/', function () {
    return view('login');
})->name('login');

Route::post('/logout', function (Request $request) {

    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');

})->name('logout');


Route::middleware(['auth', 'role:admin'])->group(function () {
Route::get('/dashboard', function(){ return view('admin.index'); })->name('dashboard');
Route::get('/dashboard/proyectos', [ProyectoController::class, 'index'])->name('proyectos.index');
Route::post('/dashboard/proyectos', [ProyectoController::class, 'store'])->name('proyectos.store');
Route::put('/dashboard/proyectos/{proyecto}', [ProyectoController::class, 'update'])->name('proyectos.update');
Route::delete('/dashboard/proyectos/{proyecto}', [ProyectoController::class, 'destroy'])->name('proyectos.destroy');
Route::get('/dashboard/proyectos/{proyecto}', [ProyectoController::class, 'show'])->name('proyectos.show');
Route::resource('/dashboard/tareas', TareasController::class);
Route::get('/dashboard/actualizaciones', function(){ return view('admin.actualizaciones');})->name('actualizaciones');
Route::get('/dashboard/bugs', [BugController::class, 'index'])->name('bugs.index');
Route::get('/dashboard/bugs/crear', [BugController::class, 'create']) ->name('bugs.create');
Route::post('/dashboard/bugs', [BugController::class, 'store'])->name('bugs.store');
Route::get('/dashboard/bugs/{bug}', [BugController::class, 'show'])->name('bugs.show');
Route::get('/dashboard/bugs/{bug}/editar', [BugController::class, 'edit'])->name('bugs.edit');
Route::put('/dashboard/bugs/{bug}', [BugController::class, 'update'])->name('bugs.update');
Route::delete('/bugs/{bug}', [BugController::class, 'destroy'])->name('bugs.destroy');Route::get('/dashboard/archivos', function(){ return view('admin.archivos');})->name('archivos');
Route::get('/dashboard/seguimiento', function(){ return view('admin.seguimiento');})->name('seguimiento');
Route::get('/dashboard/notas', function(){ return view('admin.notas');})->name('notas');
});