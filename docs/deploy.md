# Despliegue en VPS con CloudPanel

Guía para publicar la API y el frontend en un VPS gestionado con [CloudPanel](https://www.cloudpanel.io/), cada uno en su subdominio:

| Subdominio | Contenido | Tipo de sitio |
| --- | --- | --- |
| `api-productos.ingeniapp.cloud` | API Laravel y Swagger UI (`/docs`) | PHP (Laravel) |
| `productos.ingeniapp.cloud` | Frontend Vue compilado | Static HTML |

En los comandos, `<IP del VPS>` es la IP pública del servidor.

## 1. DNS

En el panel DNS del dominio, crea dos registros `A` apuntando a la IP del VPS:

| Tipo | Nombre | Contenido | TTL |
| --- | --- | --- | --- |
| A | `api-productos` | `<IP del VPS>` | 300 |
| A | `productos` | `<IP del VPS>` | 300 |

No hace falta tocar el resto de registros (`@`, `www`, correo). Comprueba que resuelven antes de pedir los certificados SSL:

```bash
nslookup api-productos.ingeniapp.cloud
nslookup productos.ingeniapp.cloud
```

## 2. Sitio de la API

### 2.1 Crear el sitio

En CloudPanel: **Add Site → Create a PHP Site**.

-   **Application:** Laravel (la versión más reciente de la lista; la configuración de Nginx es la misma).
-   **Domain Name:** `api-productos.ingeniapp.cloud`
-   **PHP Version:** 8.3 o superior.
-   **Site User / Password:** por ejemplo `ingeniapp-api-productos`, con una contraseña robusta.

La plantilla de Laravel ya sirve la carpeta `public/` como raíz web.

### 2.2 Base de datos

En el sitio: **Databases → Add Database**, con nombre `product_api_lab`, un usuario propio y una contraseña robusta. Solo es accesible desde el propio servidor (`127.0.0.1`).

### 2.3 Certificado SSL

En el sitio: **SSL/TLS → Actions → New Let's Encrypt Certificate**. Requiere que el DNS ya resuelva a la IP del VPS.

### 2.4 Código

Conéctate por SSH con el usuario del sitio:

```bash
ssh ingeniapp-api-productos@<IP del VPS>
```

Si el repositorio es privado, crea una clave de despliegue y añade la clave pública en GitHub, en **Settings → Deploy keys** (solo lectura):

```bash
ssh-keygen -t ed25519 -C "deploy@api-productos" -f ~/.ssh/id_ed25519 -N ""
cat ~/.ssh/id_ed25519.pub
```

Clona el repositorio en la carpeta del sitio. CloudPanel la crea al dar de alta el sitio; revisa su contenido antes de vaciarla:

```bash
cd ~/htdocs
ls -la api-productos.ingeniapp.cloud
rm -rf api-productos.ingeniapp.cloud
git clone https://github.com/wiljdev/product-api-lab.git api-productos.ingeniapp.cloud
cd api-productos.ingeniapp.cloud
```

Instala las dependencias de producción. Si `php -v` no muestra la versión elegida para el sitio, usa el binario explícito (por ejemplo `php8.3`):

```bash
php -v
composer install --no-dev --optimize-autoloader --no-interaction
```

### 2.5 Variables de entorno

```bash
cp .env.example .env
nano .env
```

Valores de producción (el resto puede quedar como en `.env.example`):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api-productos.ingeniapp.cloud
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=product_api_lab
DB_USERNAME=<usuario de la base de datos>
DB_PASSWORD=<contraseña de la base de datos>

CORS_ALLOWED_ORIGINS=https://productos.ingeniapp.cloud
SANCTUM_EXPIRATION=1440

DEMO_USER_PASSWORD=<contraseña robusta del editor demo>
DEMO_VIEWER_PASSWORD=<contraseña robusta del viewer demo>
```

`APP_DEBUG=false` es obligatorio: evita que los errores muestren trazas o configuración. Si `DEMO_*_PASSWORD` se dejan vacías, los usuarios demo usarían la contraseña local por defecto.

### 2.6 Instalación

```bash
php artisan key:generate --force
php artisan migrate --seed --force
php artisan optimize
```

`optimize` cachea configuración, rutas, eventos y vistas. Después de cambiar el `.env` hay que volver a ejecutarlo.

Comprueba la API:

```bash
curl -s https://api-productos.ingeniapp.cloud/up -o /dev/null -w "%{http_code}\n"
curl -s https://api-productos.ingeniapp.cloud/api/v1/currencies
```

## 3. Sitio del frontend

### 3.1 Crear el sitio

En CloudPanel: **Add Site → Create a Static HTML Site**, con dominio `productos.ingeniapp.cloud` y su propio usuario (por ejemplo `ingeniapp-productos`). Emite también su certificado en **SSL/TLS**.

### 3.2 Rutas de Vue

El frontend usa rutas como `/products/1` o `/playground`, que no existen como archivos. En el sitio, **Vhost**, dentro del bloque `server` que escucha en el puerto 443, deja así el `location /`:

```nginx
location / {
    try_files $uri $uri/ /index.html;
}
```

Guarda los cambios; CloudPanel valida y recarga Nginx.

### 3.3 Compilar y subir

En tu equipo, desde la raíz del proyecto (Git Bash):

```bash
cd frontend
npm ci
VITE_API_URL=https://api-productos.ingeniapp.cloud/api/v1 VITE_REPOSITORY_URL=https://github.com/wiljdev/product-api-lab npm run build
scp -r dist/* ingeniapp-productos@<IP del VPS>:htdocs/productos.ingeniapp.cloud/
```

En PowerShell las variables se definen antes del build:

```powershell
$env:VITE_API_URL = "https://api-productos.ingeniapp.cloud/api/v1"
$env:VITE_REPOSITORY_URL = "https://github.com/wiljdev/product-api-lab"
npm run build
```

## 4. Verificación

-   `https://productos.ingeniapp.cloud` muestra "API en línea" con 8 productos y 6 divisas.
-   Recargar `https://productos.ingeniapp.cloud/products/1` no da 404.
-   `https://api-productos.ingeniapp.cloud/docs` muestra Swagger UI y "Try it out" funciona.
-   En el playground, el login del editor permite escribir y el del viewer recibe `403`.
-   Un error devuelve JSON sin trazas, por ejemplo `https://api-productos.ingeniapp.cloud/api/v1/products/999999`.
-   La colección de Postman funciona con `baseUrl` = `https://api-productos.ingeniapp.cloud/api/v1`.

## 5. Actualizaciones

API, desde la carpeta del sitio:

```bash
git pull
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize
```

Frontend: repetir el paso 3.3.

Para restaurar los datos de demostración (borra todos los datos):

```bash
php artisan migrate:fresh --seed --force
```

## Lista de seguridad

-   `APP_DEBUG=false` y `APP_ENV=production`.
-   HTTPS en los dos subdominios.
-   `.env` fuera del repositorio (está en `.gitignore`) y con contraseñas propias de producción.
-   Usuario de base de datos exclusivo de la aplicación; MySQL no expuesto a Internet.
-   `CORS_ALLOWED_ORIGINS` limitado al dominio del frontend.
-   Contraseñas demo robustas; los tokens caducan a las 24 horas y la API limita las peticiones.
