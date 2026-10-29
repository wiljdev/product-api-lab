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

## Documentación de la API

-   Especificación OpenAPI 3.1 escrita a mano en `docs/openapi.yaml`, sin
    dependencias extra. Es la única fuente: el repositorio la versiona y la
    aplicación la sirve en `/docs/openapi.yaml`.
-   Swagger UI en `/docs` (la raíz `/` redirige ahí), cargado desde
    jsDelivr con versión fija y hash SRI.
-   `DocumentationTest` falla si una ruta de `/api/v1` no está documentada,
    si se documenta una operación que no existe o si una referencia `$ref`
    está rota.
-   Colección de Postman v2.1 en `docs/postman_collection.json`. Ejecutada
    en orden (Collection Runner) inicia sesión como editor y viewer, crea y
    actualiza un producto y su precio, muestra los errores `401`, `403`,
    `404` y `422`, y al final borra lo creado y revoca los tokens. Cada
    petición comprueba su código de estado, y los ejemplos de respuesta son
    respuestas reales con el token enmascarado.
-   `PostmanCollectionTest` falla si una ruta no tiene petición en la
    colección o si se cuela un token real.

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

-   Rate limiting en toda la API: 60 peticiones por minuto por usuario
    autenticado o por IP. El login tiene un límite más estricto: 5 intentos
    por minuto por email e IP, y 20 por IP, para frenar ataques de fuerza
    bruta. Al superarlo se responde `429` con la cabecera `Retry-After`.
    **Implementada**
-   Escrituras protegidas con tokens Bearer de Laravel Sanctum
    (`auth:sanctum`) y la ability `products:write`. **Implementada**
-   Dos roles (`UserRole`): `editor` recibe tokens con `products:write`;
    `viewer` recibe tokens sin abilities. Sin token la API responde `401`;
    con un token sin permiso, `403`. Los usuarios nuevos son `viewer` por
    defecto (mínimo privilegio). **Implementada**
-   Emisión y revocación de tokens mediante `POST /api/v1/auth/login` y
    `POST /api/v1/auth/logout`; `GET /api/v1/auth/me` devuelve el dueño
    del token. Logout revoca solo el token usado. **Implementada**
-   Los tokens caducan a los `SANCTUM_EXPIRATION` minutos (24 horas por
    defecto). **Implementada**
-   El login responde el mismo error para email desconocido y contraseña
    incorrecta, y los intentos fallidos duran siempre lo mismo (`Timebox` de
    400 ms), para no revelar qué emails están registrados ni por el mensaje ni
    por el tiempo de respuesta. **Implementada**
-   El rol del usuario no es asignable de forma masiva; solo se cambia de
    forma explícita (`forceFill`). **Implementada**
-   Dos usuarios demo configurables en `config/demo.php`: el editor
    (`DEMO_USER_*`) y el viewer (`DEMO_VIEWER_*`), para probar el `403`.
    Fuera de local se deben usar contraseñas robustas. **Implementada**
-   CORS limitado a los orígenes del frontend definidos en
    `CORS_ALLOWED_ORIGINS`, sin comodines ni credenciales (la API usa tokens
    Bearer, no cookies). Solo se permiten los métodos y cabeceras que usa la
    API, y se exponen las cabeceras de rate limiting. **Implementada**
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
| Sanctum con abilities              | Confirmada |
| Convención de `exchange_rate`      | Confirmada |
| Representación de importes         | Confirmada |
