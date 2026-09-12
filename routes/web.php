<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\LoginCcontroller;
use App\Http\Controllers\ProyectoController;
use App\Http\Controllers\TareasController;
use App\Http\Controllers\BugController;
use App\Http\Controllers\AsistenteController;
use App\Http\Controllers\IncidenteController;
use App\Http\Controllers\ActualizacionController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\RegisterController;
use App\Models\User;
use App\Http\Controllers\NexusController;
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

Route::get('/register', [RegisterController::class, 'create'])->name('register');
Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

Route::post('/logout', function (Request $request) {

    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');

})->name('logout');


Route::middleware(['auth', 'role:admin'])->group(function () {
Route::get('/dashboard/asistente', [AsistenteController::class, 'index'])->name('asistente.index');
Route::post('/dashboard/asistente/mensaje', [AsistenteController::class, 'message'])->name('asistente.message');
Route::get('/dashboard/asistente/hallazgos', [NexusController::class, 'findings'])->name('nexus.findings');
Route::get('/dashboard/asistente/salud', [NexusController::class, 'health'])->name('nexus.health');
Route::get('/dashboard/asistente/propuestas', [NexusController::class, 'proposals'])->name('nexus.proposals');
Route::delete('/dashboard/asistente/historial', [AsistenteController::class, 'clear'])->name('asistente.clear');
Route::get('/dashboard', function(){ return view('admin.index'); })->name('dashboard');
Route::get('/dashboard/proyectos', [ProyectoController::class, 'index'])->name('proyectos.index');
Route::post('/dashboard/proyectos', [ProyectoController::class, 'store'])->name('proyectos.store');
Route::put('/dashboard/proyectos/{proyecto}', [ProyectoController::class, 'update'])->name('proyectos.update');
Route::delete('/dashboard/proyectos/{proyecto}', [ProyectoController::class, 'destroy'])->name('proyectos.destroy');
Route::get('/dashboard/proyectos/{proyecto}', [ProyectoController::class, 'show'])->name('proyectos.show');
Route::post('/dashboard/proyectos/{proyecto}/github/sincronizar', [ProyectoController::class, 'sincronizarGithub'])->name('proyectos.github.sync');
Route::post('/dashboard/proyectos/{proyecto}/github/manual', [ProyectoController::class, 'configurarGithubManual'])->name('proyectos.github.manual');
Route::post('/dashboard/proyectos/{proyecto}/github/analizar', [ProyectoController::class, 'analizarGithub'])->name('proyectos.github.analyze');
Route::post('/dashboard/proyectos/{proyecto}/github/commit', [ProyectoController::class, 'crearCommitGithub'])->name('proyectos.github.commit');
Route::resource('/dashboard/tareas', TareasController::class);
Route::get('/dashboard/actualizaciones', [ActualizacionController::class, 'index'])->name('actualizaciones');
Route::post('/dashboard/actualizaciones', [ActualizacionController::class, 'store'])->name('actualizaciones.store');
Route::get('/dashboard/bugs', [BugController::class, 'index'])->name('bugs.index');
Route::get('/dashboard/bugs/crear', [BugController::class, 'create']) ->name('bugs.create');
Route::post('/dashboard/bugs', [BugController::class, 'store'])->name('bugs.store');
Route::get('/dashboard/bugs/{bug}', [BugController::class, 'show'])->name('bugs.show');
Route::get('/dashboard/bugs/{bug}/editar', [BugController::class, 'edit'])->name('bugs.edit');
Route::put('/dashboard/bugs/{bug}', [BugController::class, 'update'])->name('bugs.update');
Route::delete('/bugs/{bug}', [BugController::class, 'destroy'])->name('bugs.destroy');Route::get('/dashboard/archivos', function(){ return view('admin.archivos');})->name('archivos');
Route::get('/dashboard/seguimiento', function(){ return view('admin.seguimiento');})->name('seguimiento');
Route::get('/dashboard/notas', function(){ return view('admin.notas');})->name('notas');
Route::get('/dashboard/monitoreo', function(){ return view('admin.monitoreo'); })->name('monitoreo');
Route::get('/dashboard/incidentes', [IncidenteController::class, 'index'])->name('incidentes');
Route::get('/dashboard/notificaciones', function(){ return view('admin.modulo-en-construccion', ['titulo' => 'Notificaciones', 'descripcion' => 'Alertas importantes de bugs, caídas, despliegues y tareas.']);})->name('notificaciones');
Route::get('/dashboard/configuracion', function(){ return view('admin.configuracion'); })->name('configuracion');
Route::get('/dashboard/usuarios', [UsuarioController::class, 'index'])->name('usuarios');
Route::post('/dashboard/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
Route::put('/dashboard/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
Route::delete('/dashboard/usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');
Route::get('/dashboard/actividad', function(){ return view('admin.actividad'); })->name('actividad');
});