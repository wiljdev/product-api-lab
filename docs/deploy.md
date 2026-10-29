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

El servidor solo acepta claves SSH, no contraseñas. Como root (por ejemplo desde la terminal web del proveedor del VPS), autoriza tu clave pública para el usuario del sitio:

```bash
U=ingeniapp-api-productos
mkdir -p /home/$U/.ssh
echo '<tu clave pública ssh-ed25519 ...>' >> /home/$U/.ssh/authorized_keys
chown -R $U:$U /home/$U/.ssh
chmod 700 /home/$U/.ssh
chmod 600 /home/$U/.ssh/authorized_keys
```

Usa el propio usuario del sitio y no uno añadido en la pestaña SSH/FTP: así los archivos tienen el mismo dueño que PHP-FPM y Laravel puede escribir en `storage/`. Después conéctate desde tu equipo:

```bash
ssh -i ~/.ssh/<tu clave privada> ingeniapp-api-productos@<IP del VPS>
```

El repositorio es público, así que se clona por HTTPS sin claves de despliegue.

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

Los tokens caducados no se borran solos. En el sitio, **Cron Jobs**, añade una tarea diaria (por ejemplo a las 03:00):

```bash
php /home/ingeniapp-api-productos/htdocs/api-productos.ingeniapp.cloud/artisan sanctum:prune-expired --hours=24
```

Comprueba la API:

```bash
curl -s https://api-productos.ingeniapp.cloud/up -o /dev/null -w "%{http_code}\n"
curl -s https://api-productos.ingeniapp.cloud/api/v1/currencies
```

## 3. Sitio del frontend

### 3.1 Crear el sitio

En CloudPanel: **Add Site → Create a Static HTML Site**, con dominio `productos.ingeniapp.cloud` y su propio usuario (por ejemplo `ingeniapp-productos`). Emite también su certificado en **SSL/TLS** y autoriza tu clave SSH para ese usuario igual que en el paso 2.4.

### 3.2 Rutas de Vue

El frontend usa rutas como `/products/1` o `/playground`, que no existen como archivos. La plantilla estática de CloudPanel no trae un bloque `location /`; en el sitio, **Vhost**, añádelo justo debajo de `index index.html;`:

```nginx
  index index.html;

  location / {
    try_files $uri $uri/ /index.html;
  }
```

Nginx sirve el archivo si existe y, si no, `index.html`, para que Vue Router muestre la página. Los assets llevan un hash en el nombre, así que la caché larga que ya define la plantilla para `css` y `js` es correcta.

Guarda los cambios; CloudPanel valida y recarga Nginx.

### 3.3 Compilar y subir

En tu equipo, desde la carpeta `frontend` (Git Bash):

```bash
npm ci
VITE_API_URL=https://api-productos.ingeniapp.cloud/api/v1 VITE_REPOSITORY_URL=https://github.com/wiljdev/product-api-lab npm run build
```

En PowerShell las variables se definen antes del build:

```powershell
$env:VITE_API_URL = "https://api-productos.ingeniapp.cloud/api/v1"
$env:VITE_REPOSITORY_URL = "https://github.com/wiljdev/product-api-lab"
npm run build
```

Sube el resultado. Primero se borran los assets anteriores, que tienen otros nombres y quedarían huérfanos:

```bash
cd dist
ssh -i ~/.ssh/<tu clave privada> ingeniapp-productos@<IP del VPS> "rm -rf htdocs/productos.ingeniapp.cloud/assets"
scp -i ~/.ssh/<tu clave privada> -r index.html assets ingeniapp-productos@<IP del VPS>:htdocs/productos.ingeniapp.cloud/
```

En CMD de Windows la ruta de la clave se escribe `%USERPROFILE%\.ssh\<clave>` y en PowerShell `$env:USERPROFILE\.ssh\<clave>`.

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
-   HTTPS en los dos subdominios, con HSTS: en el **Vhost** de cada sitio, junto a las demás directivas, añade
    `add_header Strict-Transport-Security "max-age=31536000" always;`.
-   `.env` fuera del repositorio (está en `.gitignore`) y con contraseñas propias de producción.
-   Usuario de base de datos exclusivo de la aplicación; MySQL no expuesto a Internet.
-   `CORS_ALLOWED_ORIGINS` limitado al dominio del frontend.
-   Contraseñas demo robustas, que se cambian al terminar la evaluación; los tokens caducan a las 24 horas y la API limita las peticiones.
-   Acceso SSH solo con clave y panel de CloudPanel restringido por IP si es posible.
