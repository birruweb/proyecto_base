<?php
/**
 * Rutas de la aplicación.
 *
 * $router->get|post(ruta, [Controlador::class, 'metodo'], permiso)
 *
 * permiso:
 *   null              -> ruta pública
 *   'auth'            -> requiere sesión
 *   'modulo.accion'   -> requiere sesión + permiso (ver | crear | editar | eliminar)
 *
 * Los POST validan el token CSRF automáticamente.
 */
declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\ModulosController;
use App\Controllers\PerfilController;
use App\Controllers\ProductosController;
use App\Controllers\RolesController;
use App\Controllers\UsuariosController;

/** @var App\Core\Router $router */

// ---- Públicas ----
$router->get('/login',  [AuthController::class, 'formulario']);
$router->post('/login', [AuthController::class, 'login']);

// ---- Con sesión ----
$router->post('/logout', [AuthController::class, 'logout'], 'auth');
$router->get('/',        [DashboardController::class, 'index'], 'auth');

$router->get('/perfil',           [PerfilController::class, 'index'], 'auth');
$router->post('/perfil/password', [PerfilController::class, 'cambiarPassword'], 'auth');

// ---- Usuarios ----
$router->get('/usuarios',             [UsuariosController::class, 'index'],      'usuarios.ver');
$router->get('/usuarios/listar',      [UsuariosController::class, 'listar'],     'usuarios.ver');
$router->post('/usuarios/crear',      [UsuariosController::class, 'crear'],      'usuarios.crear');
$router->post('/usuarios/actualizar', [UsuariosController::class, 'actualizar'], 'usuarios.editar');
$router->post('/usuarios/eliminar',   [UsuariosController::class, 'eliminar'],   'usuarios.eliminar');

// ---- Roles y permisos ----
$router->get('/roles',             [RolesController::class, 'index'],      'roles.ver');
$router->get('/roles/listar',      [RolesController::class, 'listar'],     'roles.ver');
$router->get('/roles/permisos',    [RolesController::class, 'permisos'],   'roles.ver');
$router->post('/roles/crear',      [RolesController::class, 'crear'],      'roles.crear');
$router->post('/roles/actualizar', [RolesController::class, 'actualizar'], 'roles.editar');
$router->post('/roles/eliminar',   [RolesController::class, 'eliminar'],   'roles.eliminar');

// ---- Módulos (administración del menú) ----
$router->get('/modulos',             [ModulosController::class, 'index'],      'modulos.ver');
$router->get('/modulos/listar',      [ModulosController::class, 'listar'],     'modulos.ver');
$router->post('/modulos/crear',      [ModulosController::class, 'crear'],      'modulos.crear');
$router->post('/modulos/actualizar', [ModulosController::class, 'actualizar'], 'modulos.editar');
$router->post('/modulos/eliminar',   [ModulosController::class, 'eliminar'],   'modulos.eliminar');

// ---- Productos (módulo de ejemplo: cópialo para crear los tuyos) ----
$router->get('/productos',             [ProductosController::class, 'index'],      'productos.ver');
$router->get('/productos/listar',      [ProductosController::class, 'listar'],     'productos.ver');
$router->get('/productos/exportar',    [ProductosController::class, 'exportar'],   'productos.ver');
$router->post('/productos/crear',      [ProductosController::class, 'crear'],      'productos.crear');
$router->post('/productos/actualizar', [ProductosController::class, 'actualizar'], 'productos.editar');
$router->post('/productos/eliminar',   [ProductosController::class, 'eliminar'],   'productos.eliminar');