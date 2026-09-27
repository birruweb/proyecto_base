# Proyecto Base PHP

Base MVC en PHP puro para sistemas administrativos: login, usuarios, roles con permisos por módulo y un CRUD de ejemplo. Sin Composer ni dependencias externas: las librerías del frontend vienen incluidas, así que funciona también sin internet (intranet).

**Incluye:** PHP 8.1+ · MySQL/MariaDB · Bootstrap 5 · jQuery · DataTables 2 · SweetAlert2 · Bootstrap Icons

---

## Instalación en XAMPP

1. **Copia la carpeta** `proyecto-base` a `C:\xampp\htdocs\`. Le puedes cambiar el nombre, la app lo detecta sola.
2. En el **XAMPP Control Panel**, enciende **Apache** y **MySQL**.
3. Revisa el archivo **`.env`**. Ya viene con los valores de XAMPP (usuario `root` sin contraseña). En `DB_NAME` va el nombre de la base del proyecto.
4. **Instala la base de datos** desde la carpeta del proyecto:
   ```
   C:\xampp\php\php consola\instalar.php
   ```
   Crea la base `DB_NAME` si no existe y ejecuta `database/instalar.sql` en ella. Se puede repetir sin borrar nada.

   <details><summary>O a mano, con phpMyAdmin o DBeaver</summary>

   `instalar.sql` no crea la base: se instala en la que tengas seleccionada.
   - phpMyAdmin → crea la base (cotejamiento `utf8mb4_unicode_ci`) → selecciónala → **Importar** → elige el archivo.
   - DBeaver → crea la base → abre el archivo en un editor SQL **de esa base** → **Ejecutar script** (Alt+X).
   - Terminal: `C:\xampp\mysql\bin\mysql -u root NOMBRE_BASE < database\instalar.sql`
   </details>
5. Abre **http://localhost/proyecto-base**

**Usuario inicial:** `admin` / `Admin123!` (rol Superadmin) → cámbiala en *Mi perfil*.

**Roles incluidos:**

| Rol | Permisos |
|---|---|
| **Superadmin** | Acceso total; ignora la matriz de permisos |
| **Administrador** | Ver, crear y editar en todos los módulos; no puede eliminar |
| **Consulta** | Ejemplo mínimo: solo ver productos |

**Eliminar es exclusivo del Superadmin:** ningún otro rol puede tener ese permiso (la matriz de *Roles y permisos* solo ofrece Ver, Crear y Editar, y el servidor lo niega aunque la BD diga otra cosa). En la matriz, el interruptor **Todos** marca ver, crear y editar de un módulo de una vez; el del encabezado de un grupo lo hace con todos sus submódulos.

Crea un usuario con Administrador o Consulta para ver cómo funcionan los permisos. Cuando agregues módulos nuevos, marca sus permisos al Administrador en *Roles y permisos* (el Superadmin ya los ve).

---

## Estructura

```
proyecto-base/
├── .env                  ← configuración y contraseñas (NO se sube a Git)
├── .htaccess             ← manda todo a public/
├── app/
│   ├── Core/             ← el "framework": Router, Model, Controller, Auth...
│   ├── Controllers/      ← un controlador por módulo
│   ├── Models/           ← un modelo por tabla
│   ├── Views/            ← HTML de cada módulo + layouts
│   ├── routes.php        ← TODAS las rutas y sus permisos
│   └── bootstrap.php     ← arranque
├── consola/              ← scripts de terminal (generador de módulos)
├── database/instalar.sql
├── public/               ← lo ÚNICO accesible desde el navegador
│   ├── index.php         ← punto de entrada
│   └── assets/           ← css, js, vendor (librerías)
└── storage/logs/         ← bitácora de errores
```

## Cómo viaja una petición

```
Navegador ── /productos/crear ──► .htaccess ──► public/index.php
                                                    │
                     app/routes.php  ◄──────────────┘
                     (ruta + permiso 'productos.crear')
                                                    │
     Router: ¿existe la ruta? ¿token CSRF? ¿sesión? ¿tiene permiso?
                                                    │
     ProductosController::crear()  →  validar()  →  Producto::crear()
                                                    │
     JSON { ok: true, mensaje: "Producto creado" }  ◄┘
```

---

## Empezar un proyecto nuevo a partir de esta base

Ejemplo con un proyecto `cliente-a`:

1. Copia la carpeta `proyecto-base` como `C:\xampp\htdocs\cliente-a`.
2. En su `.env` cambia `APP_NAME`, `DB_NAME=cliente_a` y `SESSION_NAME=cliente_a_session`.
3. Desde `cliente-a`: `C:\xampp\php\php consola\instalar.php`. **No tienes que crear la base:** el comando la crea con el nombre de `DB_NAME`. Solo MySQL tiene que estar encendido.
4. Desde `cliente-a`: `C:\xampp\php\php consola\quitar-demo.php`
5. Abre `http://localhost/cliente-a`, entra con `admin` / `Admin123!` y cambia la contraseña.

Cada copia tiene su propia base y su propia sesión: no se mezclan aunque estén en el mismo XAMPP.

**Sobre el paso 4:** Productos es un módulo de ejemplo. Sirve para ver que todo funciona y como plantilla, pero en un proyecto real sobra. `quitar-demo.php` borra sus archivos, sus rutas, su tarjeta en Inicio, su registro en el menú (con sus permisos), la tabla `productos` y su parte de `database/instalar.sql`, para que las instalaciones nuevas del proyecto ya no lo traigan. Antes de borrar pregunta; con `--si` no pregunta, y con `--solo-codigo` no toca la base de datos. **No lo corras en la base**, solo en las copias.

---

## Menú: grupos, módulos raíz y submódulos

El menú se administra en **Administración → Módulos**, sin tocar la base de datos a mano:

| Tipo | Qué es | Ruta | Permisos |
|---|---|---|---|
| **Grupo** | Encabezado desplegable del menú (p. ej. *Catálogos*) | No | No |
| **Módulo raíz** | Enlace directo en el menú, fuera de grupos | Sí | Sí |
| **Submódulo** | Enlace dentro de un grupo | Sí | Sí |

- Un grupo sin submódulos visibles para el usuario no aparece en su menú.
- Si desactivas un grupo o un módulo, se oculta del menú y deja de dar acceso. Los permisos se conservan para cuando lo reactives.
- Usuarios, Roles y Módulos son del sistema: no se pueden eliminar ni cambiar su clave.

Registrar un módulo crea la **opción del menú y sus permisos**. El código (controlador, vista, JS y rutas) se crea aparte, como se explica abajo. Si registras un módulo sin código, al entrar dará 404.

> **¿Ya tenías el proyecto instalado antes de esta versión?** Ejecuta una sola vez `database/actualizaciones/001_modulos_jerarquia.sql`.

---

## Auditoría: quién creó y quién modificó

Cada registro guarda quién lo creó y quién lo modificó por última vez, con fecha y hora. En la columna **Acciones** de todas las tablas aparece el botón **ⓘ** para consultarlo.

Es automático: si la tabla tiene las columnas `creado_en`, `creado_por`, `actualizado_en` y `actualizado_por`, el modelo base las llena solo en `crear()` y `actualizar()`. No hay que tocar el controlador ni el JS.

- Para una tabla que ya existe, agrega las columnas con la plantilla del final de `database/actualizaciones/002_auditoria.sql`.
- En un `listado()` con SQL propio, agrega `{$this->camposAuditoria('alias')}` en el SELECT y `{$this->joinsAuditoria('alias')}` después del FROM (mira `Usuario::listado()`). Con `todos()` ya viene incluido.
- Los cambios automáticos del sistema (último acceso, intentos de login) usan `actualizarSilencioso()` y no cuentan como modificación.

> **¿Ya tenías el proyecto instalado?** Ejecuta una sola vez `database/actualizaciones/002_auditoria.sql`.

---

## Crear un módulo nuevo (ejemplo: Clientes)

### Rápido: con el generador

Crea la tabla (paso 1 de abajo) y luego, desde la carpeta del proyecto:

```
C:\xampp\php\php consola\crear-modulo.php clientes --grupo=catalogos --icono=bi-people
```

Lee las columnas de la tabla y genera el modelo, el controlador, la vista y el JS con el mismo patrón de Productos. También agrega las rutas en `routes.php` y registra el módulo en el menú. Solo falta dar permisos a los roles que no son administrador.

| Opción | Para qué |
|---|---|
| `--grupo=clave` | Grupo del menú (sin ella queda como módulo raíz) |
| `--nombre="Mis clientes"` | Nombre en el menú y título |
| `--singular=pais` | Si no adivina bien el singular (`paises` → `pais`) |
| `--icono=bi-people` | Icono de https://icons.getbootstrap.com |
| `--femenino` / `--masculino` | Para los textos: "Nueva marca" / "Nuevo cliente" |
| `--forzar` | Sobrescribe los archivos si ya existen |

Reconoce textos, textos largos, enteros, decimales, `activo` y otros `TINYINT(1)` (Sí/No), fechas, fecha y hora, horas, `ENUM`, columnas `UNIQUE` y **llaves foráneas** (se vuelven un select con los registros de la otra tabla). El resultado es un punto de partida: revisa los textos y quita de la tabla las columnas que no quieras ver.

### A mano

El módulo **Productos** es la plantilla. Copia sus 4 archivos y cambia los nombres.

**1. Tabla.** Ejecuta en tu BD. Las 4 últimas columnas son la auditoría (quién creó y modificó); inclúyelas siempre:

```sql
CREATE TABLE clientes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(150) NOT NULL,
    rfc             VARCHAR(13)  NULL,
    telefono        VARCHAR(20)  NULL,
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    creado_por      INT UNSIGNED NULL,
    actualizado_en  TIMESTAMP    NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    actualizado_por INT UNSIGNED NULL,
    CONSTRAINT fk_clientes_creado_por      FOREIGN KEY (creado_por)      REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_clientes_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Luego, en **Administración → Módulos → Nuevo módulo**, regístralo como *Submódulo* de un grupo (o *Módulo raíz*), con clave `clientes` y ruta `clientes`. Iconos: https://icons.getbootstrap.com

**2. Modelo** `app/Models/Cliente.php`:

```php
<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Cliente extends Model
{
    protected string $tabla = 'clientes';
    protected array $campos = ['nombre', 'rfc', 'telefono', 'activo'];
}
```

**3. Controlador:** copia `ProductosController.php` como `ClientesController.php`. Cambia `Producto` por `Cliente`, los textos, la vista y el JS, y ajusta las reglas:

```php
private function reglas(): array
{
    return [
        'nombre'   => 'required|max:150',
        'rfc'      => 'nullable|max:13',
        'telefono' => 'nullable|max:20',
        'activo'   => 'required|in:0,1',
    ];
}
```

**4. Rutas** en `app/routes.php`:

```php
use App\Controllers\ClientesController;

$router->get('/clientes',             [ClientesController::class, 'index'],      'clientes.ver');
$router->get('/clientes/listar',      [ClientesController::class, 'listar'],     'clientes.ver');
$router->post('/clientes/crear',      [ClientesController::class, 'crear'],      'clientes.crear');
$router->post('/clientes/actualizar', [ClientesController::class, 'actualizar'], 'clientes.editar');
$router->post('/clientes/eliminar',   [ClientesController::class, 'eliminar'],   'clientes.eliminar');
```

**5. Vista:** copia `app/Views/productos/index.php` como `app/Views/clientes/index.php`. Cambia los ids (`tablaClientes`, `modalCliente`, `formCliente`), las columnas y los campos del formulario. El `name` de cada campo debe ser igual al nombre de la columna.

**6. JavaScript:** copia `public/assets/js/modulos/productos.js` como `clientes.js`, cambia los ids, las rutas y el arreglo `columns`.

**7. Permisos:** el Administrador ya lo ve. Para otros roles, ve a **Roles y permisos** → editar → marca Clientes (aparece dentro de su grupo).

Si al revisar las rutas ves un **404**, casi siempre es un nombre que no coincide entre `routes.php`, la clase del controlador y el nombre del archivo. En Linux importan las mayúsculas: `ClientesController.php` ≠ `clientesController.php`.

---

## Referencia rápida

### PHP (controladores)
| Código | Qué hace |
|---|---|
| `$this->view('modulo/index', [...])` | Muestra una vista con el layout |
| `$this->validar([...reglas])` | Valida; si falla responde 422 con los errores por campo |
| `$this->ok('Mensaje', ['id' => 5])` | JSON de éxito |
| `$this->error('Mensaje', 409)` | JSON de error (reglas de negocio) |
| `$this->id()` | `id` recibido por GET/POST como entero |
| `$this->autorizar('clientes.editar')` | Corta con 403 si no tiene permiso |

**Reglas de validación:** `required`, `nullable`, `email`, `numeric`, `integer`, `min:n`, `max:n`, `in:a,b`, `regex:/.../`, `confirmed`, `unique:tabla,columna[,idIgnorar]`, `exists:tabla,columna`

### PHP (modelos)
`todos()`, `buscar($id)`, `buscarPor('columna', $valor)`, `crear($datos)`, `actualizar($id, $datos)`, `eliminar($id)`, `contar('activo = 1')`. Para consultas propias usa `$this->consulta($sql, $params)` y `$this->fila($sql, $params)`, siempre con `?` (nunca concatenes valores).

**Migas de pan:** salen solas de la tabla `modulos` (Inicio › Grupo › Módulo). Para agregar niveles, pasa `'migas' => [['texto' => 'Detalle', 'ruta' => 'productos/detalle']]` en `$this->view()` (`ruta` es opcional).

### PHP (vistas)
`e($texto)` escapa HTML (úsalo **siempre**), `url('ruta')`, `asset('css/app.css')`, `puede('modulo.accion')`, `csrf_field()`, `usuario_actual()`

### JavaScript
| Código | Qué hace |
|---|---|
| `App.url('clientes/listar')` | URL correcta aunque cambie la carpeta |
| `App.get(ruta, datos)` / `App.post(...)` | AJAX con CSRF; los errores se muestran solos |
| `App.tabla('#tabla', {...})` | DataTable en español |
| `App.formulario.enviar(form, ruta)` | Envía el formulario y marca los campos con error |
| `App.formulario.cargar(form, fila)` | Rellena el formulario para editar |
| `App.confirmar('texto')` | Confirmación (Promise con true/false) |
| `App.alerta.ok/error/aviso(msg)` | Notificaciones |
| `App.render.texto/fecha/soloFecha/moneda/estado/siNo` | Formatos de columna para DataTables |

---

## Seguridad incluida

- **Contraseñas** con `password_hash` (bcrypt), nunca en texto plano.
- **Bloqueo de cuenta** por 15 min tras 5 intentos fallidos. Para desbloquearla, edita y guarda al usuario.
- **CSRF** en todos los POST; el Router lo revisa automáticamente.
- **Inyección SQL:** consultas preparadas y lista blanca de columnas (`$campos`).
- **XSS:** `e()` en vistas y `App.render.texto` en tablas.
- **Sesión:** cookie `HttpOnly` + `SameSite`, ID nuevo al iniciar sesión y cierre por inactividad (`SESSION_LIFETIME`). La cookie es solo de la carpeta de la app y cada sesión queda marcada con su app, así que varias copias en el mismo servidor no comparten el inicio de sesión.
- **Permisos** revisados en el servidor en cada petición; ocultar un botón solo es estético.
- **Código privado:** solo `public/` es accesible, y `.env`, `app/` y `database/` devuelven 403.
- **Errores:** se registran en `storage/logs/` y el detalle solo se muestra con `APP_DEBUG=true`.
- **Reglas de negocio:** no puedes desactivarte ni borrarte a ti mismo, y siempre queda al menos un administrador activo.

---

## Producción (VPS con nginx)

En producción el `root` apunta a **`public/`**:

```nginx
server {
    listen 80;
    server_name tudominio.com;
    root /var/www/proyecto-base/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php-fpm.sock;
    }

    location ~ /\. {
        deny all;
    }
}
```

En el `.env` del servidor pon `APP_ENV=production` y `APP_DEBUG=false`, y usa un usuario de BD propio (no root):

```sql
CREATE USER 'proyecto_user'@'localhost' IDENTIFIED BY 'UnaClaveFuerte';
GRANT SELECT, INSERT, UPDATE, DELETE ON proyecto_base.* TO 'proyecto_user'@'localhost';
```

Ese usuario no puede crear bases ni tablas, así que **instala primero con un usuario administrador** de MySQL: pon ese usuario en el `.env`, corre `php consola/instalar.php` y después cambia el `.env` al usuario limitado. Usa también el usuario administrador para todo lo que cambie la estructura: crear tablas nuevas, los scripts de `database/actualizaciones/` y `quitar-demo.php`, que borra una tabla. `crear-modulo.php` funciona con el usuario limitado.

Permisos de archivos, con PHP como `www-data`:

```bash
sudo chown -R $USER:www-data /var/www/proyecto-base
sudo find /var/www/proyecto-base -type d -exec chmod 2750 {} \;
sudo find /var/www/proyecto-base -type f -exec chmod 640 {} \;
sudo chmod -R 2770 /var/www/proyecto-base/storage   # PHP escribe los logs aquí
```

---

## Problemas comunes

| Síntoma | Causa y solución |
|---|---|
| **404 de Apache** en todas las páginas menos la primera | `mod_rewrite` apagado o `AllowOverride None`. En `C:\xampp\apache\conf\httpd.conf`, revisa que `LoadModule rewrite_module` no tenga `#` y que el bloque de `htdocs` diga `AllowOverride All`. Reinicia Apache. |
| **500 de Apache** (página fea, no la de la app) | Tu Apache no permite `Options` en `.htaccess`. Pon `AllowOverride All` o borra las líneas `Options -Indexes`. |
| "No se pudo conectar a la base de datos" | MySQL apagado en XAMPP, o datos `DB_*` incorrectos en `.env`. |
| Error sin detalle | Pon `APP_DEBUG=true` en `.env` o revisa `storage/logs/`. |
| Sin estilos o links rotos | Fuerza la carpeta en `.env`: `APP_BASE_PATH=/proyecto-base` |
| "La sesión expiró" al guardar | La página llevaba mucho tiempo abierta; recarga. |
| Acentos raros (`Ã¡`) | Importa el SQL como UTF-8. El archivo ya trae `SET NAMES utf8mb4`. |
