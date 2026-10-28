# Product API Lab

[![Tests](https://github.com/wiljdev/product-api-lab/actions/workflows/tests.yml/badge.svg)](https://github.com/wiljdev/product-api-lab/actions/workflows/tests.yml)

API REST en Laravel para gestionar productos, divisas y precios de productos en distintas divisas.

-   CRUD de productos con su divisa base, costo de impuestos y costo de fabricación.
-   Registro y consulta de precios de un producto en otras divisas.
-   Lecturas públicas y escrituras protegidas con tokens Bearer de Laravel Sanctum y roles.
-   Respuestas JSON consistentes, validación con Form Requests, API Resources y rate limiting.
-   Documentación OpenAPI con Swagger UI y colección de Postman.
-   Suite de tests automatizados (PHPUnit) y CI con GitHub Actions.
-   Frontend Vue 3 independiente con catálogo, detalle de precios y un playground para probar la API.

## Stack

| Componente | Versión |
| --- | --- |
| PHP | 8.3 o superior |
| Laravel | 13 |
| Base de datos | MySQL 8 (desarrollo) y SQLite en memoria (tests) |
| Autenticación | Laravel Sanctum 4 |
| Tests | PHPUnit 12 y Vitest |
| Frontend | Vue 3, Vue Router y Vite |

## Instalación local

Requisitos: PHP 8.3+ con `pdo_mysql` y `pdo_sqlite`, Composer y MySQL 8. Con [Laragon](https://laragon.org/) ya está todo incluido.

```bash
git clone https://github.com/wiljdev/product-api-lab.git
cd product-api-lab
composer install
cp .env.example .env
php artisan key:generate
```

Crea la base de datos y ajusta las variables `DB_*` de `.env` si tus credenciales son distintas:

```sql
CREATE DATABASE product_api_lab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Ejecuta las migraciones con los datos de demostración y levanta el servidor:

```bash
php artisan migrate --seed
php artisan serve
```

La API queda en `http://127.0.0.1:8000/api/v1` y la documentación en `http://127.0.0.1:8000/docs`. Con Laragon también está disponible en `http://product-api-lab.test`.

Para volver a los datos iniciales en cualquier momento:

```bash
php artisan migrate:fresh --seed
```

### Datos de demostración

El seeder crea 6 divisas (USD, EUR, GBP, MXN, COP y PEN), 8 productos con sus precios en las demás divisas y dos usuarios:

| Usuario | Rol | Puede escribir |
| --- | --- | --- |
| `demo@example.com` | `editor` | Sí |
| `viewer@example.com` | `viewer` | No, sus escrituras devuelven `403` |

En local la contraseña de ambos es el valor por defecto de `config/demo.php`. Se cambia con `DEMO_USER_PASSWORD` y `DEMO_VIEWER_PASSWORD` en `.env`; fuera de local deben ser contraseñas robustas.

## Uso

### Obtener un token

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "demo@example.com", "password": "<contraseña>", "device_name": "curl"}'
```

La respuesta incluye `data.token`. Envíalo en la cabecera `Authorization: Bearer <token>` en las peticiones de escritura. Los tokens caducan a las 24 horas (`SANCTUM_EXPIRATION`) y `POST /auth/logout` revoca el token actual.

### Endpoints

Todas las rutas tienen el prefijo `/api/v1`.

| Método | Ruta | Acceso | Descripción |
| --- | --- | --- | --- |
| `POST` | `/auth/login` | Público | Obtener un token |
| `POST` | `/auth/logout` | Token | Revocar el token actual |
| `GET` | `/auth/me` | Token | Usuario dueño del token |
| `GET` | `/products` | Público | Listar productos (paginado, `per_page` de 1 a 100) |
| `POST` | `/products` | Editor | Crear un producto |
| `GET` | `/products/{id}` | Público | Consultar un producto |
| `PUT` / `PATCH` | `/products/{id}` | Editor | Reemplazar o actualizar un producto |
| `DELETE` | `/products/{id}` | Editor | Eliminar un producto y sus precios |
| `GET` | `/products/{id}/prices` | Público | Listar los precios de un producto |
| `POST` | `/products/{id}/prices` | Editor | Registrar un precio en otra divisa |
| `GET` | `/currencies` | Público | Listar divisas |

Ejemplo de producto:

```json
{
    "data": {
        "id": 1,
        "name": "Teclado mecánico TKL",
        "description": "Teclado mecánico sin pad numérico con switches táctiles y retroiluminación RGB.",
        "price": "89.90",
        "currency_id": 1,
        "currency": { "id": 1, "name": "USD", "symbol": "$", "exchange_rate": "1.000000" },
        "tax_cost": "17.08",
        "manufacturing_cost": "32.50",
        "created_at": "2026-10-28T21:07:39.000000Z",
        "updated_at": "2026-10-28T21:07:39.000000Z"
    }
}
```

### Códigos de respuesta

| Código | Cuándo |
| --- | --- |
| `200` / `201` / `204` | Consulta o actualización, creación y eliminación o logout |
| `401` | Falta el token, o es inválido, ha caducado o fue revocado |
| `403` | El token no tiene permiso de escritura (rol `viewer`) |
| `404` | El recurso no existe |
| `422` | Validación fallida, con los errores agrupados por campo |
| `429` | Límite de peticiones superado (incluye `Retry-After`) |

Los errores siempre son JSON con la forma `{"message": "..."}`, aunque el cliente no envíe `Accept: application/json`.

## Frontend

La carpeta [`frontend/`](frontend) contiene una aplicación Vue 3 + Vite independiente que consume la API solo por HTTP. Con la API levantada:

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

Queda en `http://localhost:5173`. `VITE_API_URL` en `frontend/.env` indica la URL de la API (por defecto `http://127.0.0.1:8000/api/v1`). La API solo acepta peticiones de navegador desde los orígenes de `CORS_ALLOWED_ORIGINS` en el `.env` de Laravel; por defecto, `http://localhost:5173` y `http://127.0.0.1:5173`. Los tests del frontend se ejecutan con `npm test` y el build de producción con `npm run build`.

## Documentación

-   **Swagger UI:** `/docs`. Permite autorizar con el token y probar cada endpoint desde el navegador.
-   **OpenAPI 3.1:** [`docs/openapi.yaml`](docs/openapi.yaml), también servido en `/docs/openapi.yaml`.
-   **Postman:** [`docs/postman_collection.json`](docs/postman_collection.json). Importa la colección, ajusta la variable `baseUrl` y las contraseñas, y ejecútala completa con el Collection Runner: inicia sesión, crea y actualiza un producto y su precio, muestra los errores `401`, `403`, `404` y `422`, y al final borra lo creado y revoca los tokens.
-   **Decisiones técnicas:** [`docs/decisiones.md`](docs/decisiones.md).

## Tests

```bash
php artisan test
```

Los tests usan SQLite en memoria, así que no tocan la base de datos de desarrollo. Cubren:

-   Endpoints de productos, precios, divisas y autenticación, incluidas las validaciones.
-   Seguridad: `401` sin token o con token inválido, caducado o revocado; `403` con el rol `viewer`; rate limiting.
-   Modelos, relaciones, restricciones de base de datos y seeders.
-   Que la especificación OpenAPI y la colección de Postman cubren todas las rutas de la API.

Cada push y pull request a `main` ejecuta en GitHub Actions ([`.github/workflows/tests.yml`](.github/workflows/tests.yml)) la comprobación de estilo con Laravel Pint y la suite completa en PHP 8.3 y 8.4, además de los tests y el build del frontend.

## Decisiones técnicas

El detalle está en [`docs/decisiones.md`](docs/decisiones.md). En resumen:

-   **Importes:** columnas `DECIMAL` (nunca `FLOAT`) y valores devueltos como string (`"89.90"`) para no perder precisión.
-   **Divisas:** `name` es el código ISO 4217 y `exchange_rate` es la cantidad de esa divisa equivalente a 1 USD.
-   **Precios:** un único precio por producto y divisa, y nunca en la divisa base, porque ese precio es el campo `price` del producto. La API no convierte precios automáticamente.
-   **Integridad:** al borrar un producto se borran sus precios; no se puede borrar una divisa en uso.
-   **Autorización:** el rol del usuario define las abilities del token; los usuarios nuevos son `viewer` por defecto.
-   **Versionado:** rutas bajo `/api/v1` para poder evolucionar la API sin romper clientes.

## Estructura

```text
app/
├── Enums/                      # UserRole y TokenAbility
├── Http/
│   ├── Controllers/Api/V1/     # Auth, Product, ProductPrice y Currency
│   ├── Middleware/             # ForceJsonResponse
│   ├── Requests/               # Validación
│   └── Resources/              # Representación JSON
└── Models/                     # Currency, Product, ProductPrice y User
database/
├── factories/
├── migrations/
└── seeders/                    # Divisas, productos, precios y usuarios demo
docs/                           # OpenAPI, Postman y decisiones técnicas
routes/api.php                  # Rutas de la API
tests/Feature/                  # Tests de la API, modelos, seeders y documentación
```

## Limitaciones conocidas

-   Sin historial de precios: cada producto guarda un único precio vigente por divisa.
-   Sin conversión automática de divisas; `exchange_rate` es informativo.
-   Las divisas solo se consultan por la API; se gestionan con los seeders.
-   No hay endpoints de registro ni de gestión de usuarios; los usuarios demo se crean con el seeder.
