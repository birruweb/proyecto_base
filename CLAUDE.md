# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Plain-PHP (8.1+) MVC base for admin systems: login, users, roles with per-module permissions, a DB-driven menu, and an example CRUD (Productos). **No npm, no build step, no test suite.** Frontend libraries (Bootstrap 5, jQuery, DataTables 2, SweetAlert2, Bootstrap Icons) are committed in `public/assets/vendor/`. PHP libraries (currently only PhpSpreadsheet) come from Composer: `vendor/` is **gitignored**, so every clone/copy runs `composer install` (commit `composer.json` + `composer.lock` only). `composer.json` pins `config.platform.php` to 8.1 so the lock stays installable on the 8.1 minimum — keep it. `bootstrap.php` requires `vendor/autoload.php` and exits with a "corre composer install" message if it's missing (the `consola/` scripts don't need it). Required PHP extensions beyond defaults: `zip`, `gd` (off by default in XAMPP's `php.ini`). `App\Core\Excel` wraps PhpSpreadsheet (`descargar()`, `leer()`, `crear()`) and writes every cell with an explicit type so user text is never parsed as a formula. All code, identifiers, UI text, and comments are in **Spanish** — keep new code consistent (`crear`, `actualizar`, `listar`, `reglas`, etc.). `README.md` is the user-facing guide (Spanish) and has the full API cheat sheet.

## Running

- XAMPP: Apache + MySQL on, then `php consola/instalar.php` (creates `DB_NAME` from `.env` if missing and runs `database/instalar.sql` statement by statement; idempotent). Open `http://localhost/proyecto-base`, login `admin` / `Admin123!` (Superadmin). `instalar.sql` deliberately has no `CREATE DATABASE`/`USE` — it installs into whatever DB is selected, so each project copy gets its own DB; don't reintroduce a hardcoded name. The installer splits on `;` at end of line after stripping `--` comment lines, so keep statements ending in `;\n`.
- Alternative: `php -S localhost:8000 -t public public/index.php` (index.php handles static files for `cli-server`).
- Scaffold a CRUD module from an existing table: `php consola/crear-modulo.php <tabla> [--grupo=clave] [--icono=bi-x] [--nombre="..."] [--singular=x] [--femenino|--masculino] [--forzar]`. It reads `information_schema` (types, NULL, UNIQUE, FKs, ENUM), writes model/controller/view/JS following the Productos pattern, appends routes to `app/routes.php`, and inserts the `modulos` row. Keep its templates in sync if the Productos pattern changes.
- `php consola/quitar-demo.php [--si] [--solo-codigo]` removes the Productos demo from a *derived* project (files, routes, dashboard card, `instalar.sql` parts, `modulos` row, table). It edits files by regex, so if you change how Productos is wired (routes block, dashboard card, `instalar.sql` inserts), update its patterns too. Never run it in the base repo itself.
- Syntax check a file: `php -l app/Controllers/FooController.php` (XAMPP PHP: `C:\xampp\php\php.exe`).
- Config in `.env` (copied from `.env.example`). `APP_DEBUG=true` shows error details; errors are always logged to `storage/logs/` (`app-YYYY-MM-DD.log`, `php-error.log`) — check there when debugging.
- Sessions are isolated per app copy: cookie `path` is `Request::base()` and `Session::iniciar()` stamps `$_SESSION['_app']` (hash of `BASE_DIR`), discarding sessions from other copies sharing PHP's session dir.
- Schema changes for existing installs go in `database/actualizaciones/NNN_*.sql` (run once manually); `instalar.sql` must also reflect the final schema for fresh installs.

## Request flow

Root `.htaccess` rewrites everything to `public/`; `public/.htaccess` sends non-files to `public/index.php` → `app/bootstrap.php` (PSR-4-style autoloader `App\X\Y` → `app/X/Y.php`, `.env`, error handler, security headers, session) → `app/routes.php` → `Router::despachar()`.

The Router is an exact-match map (no route params — ids travel as `?id=` / POST `id`, read with `$this->id()`). For each request it: 404/405s unknown routes, verifies CSRF on **every POST** automatically (`_token` field or `X-CSRF-TOKEN` header), then checks the route's third argument:
- `null` → public; `'auth'` → logged in; `'modulo.accion'` → logged in + permission (`ver|crear|editar|eliminar`).

Unauthenticated AJAX gets 401 JSON; browser requests redirect to login. `Response::abort()` returns JSON for AJAX, an error page otherwise.

## Permissions and menu (DB-driven)

- Tables `roles`, `modulos`, `permisos`, `usuarios`. `modulos.clave` is the permission prefix (`productos` → `productos.ver`). Modules form a hierarchy via `padre_id`: groups (no `ruta`), root modules, submodules. The sidebar (`app/Views/partials/sidebar.php`) is built from `Modulo::menu()` filtered by `puede(clave.ver)`.
- `Auth` reloads user + permissions from DB on **every request** (deactivation/permission changes apply immediately). Roles with `es_superadmin=1` bypass all checks. **`*.eliminar` is Superadmin-only**: `Auth::puede()` returns false for any `.eliminar` on other roles regardless of DB data, `RolesController` forces `eliminar=0` when saving, and the roles matrix only shows Ver/Crear/Editar (plus "Todos" switches per module and per group, UI-only). Inactive modules/groups grant no permissions.
- Breadcrumbs (`partials/breadcrumb.php`, rendered in the topbar) are also DB-driven: `Modulo::rastro()` finds the module whose `ruta` best prefixes the current route and yields `Inicio › Grupo › Módulo`; non-module pages fall back to `$titulo`. Controllers can append levels with `'migas' => [['texto' => ..., 'ruta' => ?]]` in `view()` data.
- Adding a module requires both code (model, controller, view, JS, routes) **and** a `modulos` row (created via the Administración → Módulos UI, which also creates permission rows). Usuarios/Roles/Módulos are system modules and can't be deleted.
- Business rules: users can't deactivate/delete themselves; at least one active admin must remain.

## Models

`App\Core\Model` provides generic CRUD with prepared statements. Subclasses only declare `$tabla` and `$campos` (write whitelist — `crear`/`actualizar` silently drop other keys). Identifiers go through `q()`; for custom SQL use `$this->consulta()` / `$this->fila()` / `$this->ejecutar()` with `?` placeholders.

**Automatic audit:** if a table has `creado_por` and `actualizado_por` (plus `creado_en`/`actualizado_en`), `crear()`/`actualizar()` stamp the current user, and `todos()` joins in `creado_por_nombre`/`actualizado_por_nombre`. Custom `listado()` queries must add `{$this->camposAuditoria('t')}` to SELECT and `{$this->joinsAuditoria('t')}` after FROM (see `Usuario::listado()`). System-initiated updates (last login, failed attempts) must use `actualizarSilencioso()`. New tables should always include the four audit columns (template in README and `database/actualizaciones/002_auditoria.sql`).

## Controllers / views / JS conventions

- Controllers extend `App\Core\Controller`. Pattern (see `ProductosController`): `index()` renders `modulo/index` with `'titulo'` and `'scripts' => ['js/modulos/x.js']` (the main layout loads those); `listar()` returns `{data: [...]}` for DataTables; `crear/actualizar/eliminar` call `$this->validar($this->reglas())` (auto-422 with per-field errors) then respond with `$this->ok()` / `$this->error()` (JSON `{ok, mensaje, errores?}`).
- Validator rules: `required, nullable, email, numeric, integer, min:n, max:n, in:a,b, regex:/…/, confirmed, unique:tabla,columna[,idIgnorar], exists:tabla,columna`.
- Views are plain PHP rendered into `app/Views/layouts/{main,auth,blank}.php`. Always escape with `e()`; use `url()`, `asset()`, `puede()`, `csrf_field()`. Form field `name`s must match DB column names. Permission flags are passed to JS via `data-editar` / `data-eliminar` attributes on the table.
- `public/assets/js/app.js` exposes the global `App` (`App.url`, `App.get/post` with CSRF, `App.tabla`, `App.formulario.enviar/cargar/limpiar`, `App.confirmar`, `App.alerta`, `App.render.*` incl. `soloFecha`/`siNo`, `App.botonesAccion`). A global `ajaxError` handler already shows errors (401 → login, 419 → reload, 422 → warning), so module JS usually only handles `.done()`. Use `App.render.texto` for user text in DataTables (XSS). The ⓘ audit-info button is handled globally for all tables.
- New CRUD module = copy the 4 Productos files (`Models/Producto.php`, `Controllers/ProductosController.php`, `Views/productos/index.php`, `public/assets/js/modulos/productos.js`) + 5 routes in `routes.php` + register in the Módulos UI. File/class name case must match exactly (Linux production).

## Production

nginx `root` points at `public/`; set `APP_ENV=production`, `APP_DEBUG=false`, and a non-root DB user. `storage/` must be writable by PHP.
