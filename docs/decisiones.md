# Decisiones técnicas

Este documento recoge las decisiones de diseño de la API de productos y
divisas. Las marcadas como **Propuesta** aún no están implementadas; se
actualizan a **Implementada** cuando el código y sus tests las respaldan.

## Arquitectura

-   API REST en Laravel 13, con la aplicación en la raíz del repositorio.
-   Eloquent para todo el acceso a datos.
-   Respuestas JSON generadas con API Resources, para no exponer campos
    internos.
-   Validación con Form Requests.
-   Rutas versionadas bajo `/api/v1`. La prueba describe los endpoints
    como `/products`; el prefijo permite evolucionar la API sin romper
    clientes existentes.

## Modelo de datos

### `currencies`

| Campo           | Tipo           | Notas                                         |
| --------------- | -------------- | --------------------------------------------- |
| `id`            | bigint, PK     |                                               |
| `name`          | varchar(3)     | Código ISO 4217 (p. ej. `USD`), único         |
| `symbol`        | varchar(10)    | Símbolo (p. ej. `$`)                          |
| `exchange_rate` | decimal(18,6)  | Ver [convención](#convención-de-exchange_rate) |

### `products`

| Campo                | Tipo          | Notas                       |
| -------------------- | ------------- | --------------------------- |
| `id`                 | bigint, PK    |                             |
| `name`               | varchar       | Obligatorio                 |
| `description`        | text          | Obligatoria                 |
| `price`              | decimal(12,2) | Precio en la divisa base    |
| `currency_id`        | FK            | Divisa base                 |
| `tax_cost`           | decimal(12,2) | Costo de impuestos          |
| `manufacturing_cost` | decimal(12,2) | Costo de fabricación        |

### `product_prices`

| Campo         | Tipo          | Notas                              |
| ------------- | ------------- | ---------------------------------- |
| `id`          | bigint, PK    |                                    |
| `product_id`  | FK            | Producto                           |
| `currency_id` | FK            | Divisa del precio                  |
| `price`       | decimal(12,2) | Precio explícito en esa divisa     |

### Reglas

-   Importes con `DECIMAL`, nunca `FLOAT`. No se aceptan valores negativos.
-   Un único precio por producto y divisa: índice único
    `(product_id, currency_id)`, validado antes de insertar para responder
    `422` en vez de un error de base de datos. **Implementada**
-   No se registra un precio en la divisa base del producto: ese precio
    es el campo `price` del propio producto. **Implementada**
-   Al eliminar un producto se eliminan sus precios (`cascadeOnDelete`).
    **Implementada**
-   No se puede eliminar una divisa usada como base de un producto o en
    un precio (`restrictOnDelete`). **Implementada**

## Convención de `exchange_rate`

**Confirmada:** `exchange_rate` indica cuántas unidades de la divisa
equivalen a 1 USD (USD = 1). El precio en `product_prices` siempre se
registra de forma explícita; la API no convierte precios
automáticamente.

## Datos de demostración

`php artisan migrate:fresh --seed` crea 6 divisas (USD, EUR, GBP, MXN, COP
y PEN) con tasas aproximadas, 8 productos y un precio por cada divisa
distinta de la base de cada producto (40 precios). Los precios demo se
calculan con las tasas en el seeder; la API los trata como valores
explícitos. Los seeders se pueden ejecutar varias veces sin duplicar
registros.

## Representación de importes

**Confirmada:** los importes se devuelven como string con decimales fijos
(`"19.99"`, `"4150.000000"`). Así no se pierde precisión por la coma
flotante de JSON. En las peticiones se aceptan como número o string, con
un máximo de 2 decimales y sin valores negativos.

## Seguridad

-   Lecturas públicas con rate limiting.
-   Escrituras protegidas con tokens Bearer de Laravel Sanctum
    (`auth:sanctum`). **Implementada**. Falta restringirlas a la ability
    `products:write`. **Propuesta**
-   Emisión y revocación de tokens mediante `POST /api/v1/auth/login` y
    `POST /api/v1/auth/logout`; `GET /api/v1/auth/me` devuelve el dueño
    del token. Logout revoca solo el token usado. **Implementada**
-   Los tokens caducan a los `SANCTUM_EXPIRATION` minutos (24 horas por
    defecto). **Implementada**
-   El login responde el mismo error para email desconocido y contraseña
    incorrecta, para no revelar qué emails están registrados.
    **Implementada**
-   El usuario demo se configura con `DEMO_USER_EMAIL` y
    `DEMO_USER_PASSWORD` (`config/demo.php`); fuera de local se debe usar
    una contraseña robusta. **Implementada**
-   Todas las rutas `/api` responden JSON aunque el cliente no envíe
    `Accept: application/json` (middleware `ForceJsonResponse`).
    **Implementada**
-   Errores en JSON con la forma `{"message": "..."}` (y `errors` en los
    `422`). Los `404` no exponen nombres de clases internas. Las trazas
    solo aparecen en local con `APP_DEBUG=true`. **Implementada**

## Registro de decisiones

| Decisión                           | Estado     |
| ---------------------------------- | ---------- |
| Laravel 13 en la raíz del repo     | Confirmada |
| Prefijo `/api/v1`                  | Confirmada |
| PHPUnit para los tests             | Confirmada |
| MySQL en desarrollo, SQLite en tests | Confirmada |
| Sanctum con abilities              | Propuesta  |
| Convención de `exchange_rate`      | Confirmada |
| Representación de importes         | Confirmada |
