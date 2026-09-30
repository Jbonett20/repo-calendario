# 🎂 monchomania

Plataforma web **responsive** para gestionar los cumpleaños y noticias de una comunidad.
Construida con **PHP nativo (PDO + POO)**, **MySQL/MariaDB**, **Bootstrap 5**, **JavaScript (Fetch API)** y **Composer**.

---

## 🧰 Tecnologías

| Capa       | Tecnología                                        |
|------------|---------------------------------------------------|
| Backend    | PHP 7.4+ (PDO, prepared statements, POO)          |
| BD         | MySQL / MariaDB                                   |
| Frontend   | HTML5, CSS3 (variables), Bootstrap 5, JS ES6+     |
| Entorno    | Composer + `vlucas/phpdotenv` (archivo `.env`)    |

---

## 🚀 Instalación local (XAMPP)

1. Copia el proyecto dentro de `htdocs` (por ejemplo `C:\xampp\htdocs\misproyectos\moncho`).
2. Instala las dependencias de Composer:
   ```bash
   composer install
   ```
   > Si no tienes Composer, la app funciona igualmente gracias al cargador `.env` de respaldo.
3. Crea la base de datos importando `schema.sql` en phpMyAdmin
   (`http://localhost/phpmyadmin` → pestaña **Importar** → elegir `schema.sql`).
4. Configura el archivo `.env` con tus credenciales (ver sección siguiente).
5. Abre en el navegador:
   ```
   http://localhost/misproyectos/moncho
   ```

### Credenciales por defecto

| Rol              | Email                 | Contraseña |
|------------------|-----------------------|------------|
| Superadministrador | admin@monchomania.com | `admin123` |

> **Importante:** cambia esta contraseña después del primer inicio de sesión.

---

## ⚙️ Configuración del `.env`

Copia `.env.example` a `.env` (o edita el `.env` existente) y ajusta:

```env
APP_NAME="monchomania"
APP_ENV=production        # local | production
APP_DEBUG=false           # true solo en desarrollo

APP_URL=https://tudominio.com          # SIN barra final

DB_HOST=localhost
DB_PORT=3306
DB_NAME=monchomania
DB_USER=tu_usuario_mysql
DB_PASS=tu_contraseña_mysql

APP_TIMEZONE=America/Bogota
```

- `APP_URL` debe coincidir con la URL real desde la que se accede al sitio
  (ej. `http://localhost/misproyectos/moncho` en local, `https://tudominio.com` en producción).
- Nunca subas el `.env` con credenciales reales a un repositorio público.

---

## 🌐 Despliegue en Hostinger

1. **Subir archivos:** copia el contenido del proyecto a `public_html`
   (o a una subcarpeta como `public_html/monchomania` si es un sitio secundario).
   En ese caso, actualiza `APP_URL` en `.env`.
2. **Instalar dependencias:** si el plan tiene acceso SSH:
   ```bash
   composer install --no-dev
   ```
   Si no hay SSH, ejecuta `composer install` en local y sube la carpeta `vendor/` completa.
3. **Crear la BD:** en *hPanel → Bases de datos MySQL* crea una base de datos y un usuario,
   anota host/nombre/usuario/contraseña y ponlos en `.env`.
4. **Importar `schema.sql`:** *hPanel → phpMyAdmin → Importar*.
5. **Permisos de escritura:** la carpeta `uploads/` (y `uploads/profiles`, `uploads/news`)
   debe tener permisos de escritura (755/775 normalmente es suficiente).
6. **Accede** a tu dominio y entra con `admin@monchomania.com` / `admin123`.

---

## 📁 Estructura de archivos

```
moncho/                        (proyecto "monchomania")
├── config/
│   ├── app.php                # Bootstrap, .env, sesión, helpers, uploads
│   └── database.php           # Conexión PDO (Singleton)
├── uploads/
│   ├── profiles/              # Fotos de perfil subidas
│   └── news/                  # Imágenes de noticias
├── assets/
│   ├── css/style.css          # Estilos (rojo #C0392B, Bungee/Montserrat/Poppins)
│   ├── js/main.js             # Calendario, comentarios, noticias, admin
│   └── img/avatar-default.svg # Avatar por defecto
├── includes/
│   ├── header.php             # Cabecera HTML
│   ├── footer.php             # Pie de página
│   ├── navbar.php             # Navegación (respeta roles y visibilidad)
│   └── auth_middleware.php    # Protección de páginas internas
├── modules/
│   ├── auth/                  # login, register, logout
│   ├── profile/               # Perfil (actualizar datos y foto)
│   ├── calendar/              # Calendario + API de cumpleaños y saludos
│   ├── news/                  # Noticias (crear y comentar)
│   └── admin/                 # Gestión de usuarios y configuración de módulos
├── .env / .env.example
├── .htaccess
├── composer.json
├── schema.sql
└── index.php                  # Landing / redirección
```

---

## 🔐 Seguridad incluida

- **PDO con prepared statements** en todas las consultas (anti SQL-injection).
- **`password_hash()` / `password_verify()`** para contraseñas.
- **`session_regenerate_id()`** tras el login.
- **Middleware** que bloquea toda página interna sin sesión y a usuarios inactivos.
- **CSRF** en formularios y peticiones `fetch` (cabecera `X-CSRF-Token`).
- Escape de salida con `htmlspecialchars` (anti XSS).
- Validación real de MIME de las imágenes subidas (máx. 2 MB).
- Borrado automático (`unlink`) de la foto anterior al actualizar el perfil.
- Protección del `.env` y desactivación de listado de directorios vía `.htaccess`.
