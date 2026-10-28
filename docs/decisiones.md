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
| `name`          | varchar        | Código de la divisa (p. ej. `USD`), único     |
| `symbol`        | varchar        | Símbolo (p. ej. `$`)                          |
| `exchange_rate` | decimal        | Ver [convención](#convención-de-exchange_rate) |

### `products`

| Campo                | Tipo          | Notas                       |
| -------------------- | ------------- | --------------------------- |
| `id`                 | bigint, PK    |                             |
| `name`               | varchar       | Obligatorio                 |
| `description`        | text          | Opcional                    |
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
    `(product_id, currency_id)`. **Propuesta**
-   Al eliminar un producto se eliminan sus precios (`cascadeOnDelete`).
    **Propuesta**
-   No se puede eliminar una divisa en uso (`restrictOnDelete`).
    **Propuesta**

## Convención de `exchange_rate`

**Propuesta:** `exchange_rate` indica cuántas unidades de la divisa
equivalen a 1 USD (USD = 1). El precio en `product_prices` siempre se
registra de forma explícita; la API no convierte precios
automáticamente.

## Representación de importes

**Pendiente:** decidir si los importes se devuelven como string (`"19.99"`,
comportamiento por defecto del cast `decimal:2`) o como número.

## Seguridad

-   Lecturas públicas con rate limiting.
-   Escrituras protegidas con tokens Bearer de Laravel Sanctum y la
    ability `products:write`. **Propuesta**
-   Emisión y revocación de tokens mediante `POST /api/v1/auth/login` y
    `POST /api/v1/auth/logout`. **Propuesta**
-   Errores en JSON con códigos `401`, `403`, `404`, `422`, `429` y `500`,
    sin exponer trazas.

## Registro de decisiones

| Decisión                           | Estado     |
| ---------------------------------- | ---------- |
| Laravel 13 en la raíz del repo     | Confirmada |
| Prefijo `/api/v1`                  | Confirmada |
| PHPUnit para los tests             | Confirmada |
| MySQL en desarrollo, SQLite en tests | Confirmada |
| Sanctum con abilities              | Propuesta  |
| Convención de `exchange_rate`      | Propuesta  |
| Representación de importes         | Pendiente  |
