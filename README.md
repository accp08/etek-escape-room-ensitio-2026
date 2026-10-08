# Ciberamenaza Inminente — Escape Room de Ciberseguridad

PWA mobile-first construida sobre Laravel 13 + Tailwind v4.

> **Escape room en sitio.** Cada misión es una estación física de la sala. El celular
> solo sirve para **escanear el QR** de la estación (eso arranca el cronómetro) y
> **escribir el código** que entrega el reto. Cronómetro, intentos, ranking y
> certificado los lleva el servidor, y todo se configura desde el dashboard, que
> además tiene una **pantalla de proyección** por estación para el televisor.

---

## Puesta en marcha

Requiere **PHP 8.4** (el `php` por defecto del equipo es 8.2):

```bash
/opt/homebrew/opt/php@8.4/bin/php artisan serve
npm run dev        # o npm run build para producción
```

Base de datos MySQL `escape-room` (configurada en `.env`).

---

## Pantallas

| Ruta | Vista | Descripción |
|---|---|---|
| `/` | `auth/login` | Acceso del participante |
| `/instrucciones` | `game/instrucciones` | Briefing en 5 pasos |
| `/centro-de-mando` | `game/hub` | Hub: estado del ataque, fragmentos y misiones |
| `/mision/{slug}` | `missions/escanear` | Antesala: instrucciones y lector del QR de la estación |
| `/mision/{slug}/activar/{token}` | — | Destino del QR: activa la misión y arranca el cronómetro |
| `/mision/{slug}/jugar` | `missions/codigo` | Ventana del código, con el cronómetro corriendo |
| `/mision/{slug}/resultado` | `missions/resultado` | Resultado: superada, reintento, fallada o tiempo agotado |
| `/codigo-final` | `game/final` | Consola de recuperación |
| `/amenaza-contenida` | `game/victoria` | Cierre de la experiencia |
| `/ranking` | `game/ranking` | Ranking general |
| `/certificado` | `game/certificado` | Certificado de participación, exportable a PDF, y video de cierre |
| `/video-final` | — | Sirve el video con soporte de rangos |
| `/dashboard/login` | `dashboard/login` | Acceso de administración |
| `/dashboard` | `dashboard/panel` | Panel de administración |
| `/dashboard/misiones/{id}/proyectar` | `dashboard/misiones/proyectar` | Pantalla de la estación para el televisor: QR e instrucciones |
| `/dashboard/misiones/qr` | `dashboard/misiones/qr` | Hoja imprimible con el QR de cada estación |

---

## Cómo cambiar el contenido

Todo se edita en el **dashboard**. `config/escape_room.php` solo trae los valores
iniciales que `ContenidoSeeder` copia a la base de datos en una instalación nueva.

### Una misión = una estación

En **Misiones → (misión)** se configura:

- **Título, etiqueta, icono, introducción** (la historia) e **instrucciones del reto**.
  Las instrucciones se ven en la pantalla de proyección y en el celular.
- **Duración** y **oportunidades** (`0` = ilimitadas).
- **Código de la estación**: el que el participante obtiene al resolver el reto físico.
  No distingue mayúsculas, espacios ni guiones. Sin código, nadie puede superarla
  (el listado la marca como «Sin código»).
- **QR de la estación**: proyectar, descargar en PNG o regenerar. Regenerar invalida
  el QR impreso o proyectado; las pantallas de proyección abiertas se actualizan solas
  en menos de 30 s.

Agregar una misión es añadir un bloque al arreglo `misiones` del config y volver a
correr el seeder. El hub, el contador de fragmentos y la consola final se ajustan solos.

---

## Diseño

- Paleta tomada del escape room 2025 de appdtl.com (tema *Neon Tech*): fondo
  `#0b1220`→`#0e1930`, acentos `#00e5ff` / `#6a5cff` / `#ff2da1`.
- Tokens en `resources/css/app.css` bajo `@theme`; componentes (`.panel`, `.btn`,
  `.pick`, `.opt`, `.timer`, `.code-slot`) en `@layer components`.
- **Mobile-first con marco de dispositivo:** en móvil la app ocupa toda la pantalla;
  desde 900 px se dibuja un celular de 412 px centrado (`.device` en `app.css`).
- **Animaciones:** entradas escalonadas (`.anim-in` / `.anim-pop` con la variable
  `--i` para el retardo), transiciones entre pasos (`.step-enter`), hojas a pantalla
  completa (`.sheet-in`), línea de escaneo (`.scan`), aros expansivos (`.anim-ring`) y
  latido del botón principal. Todo se desactiva con `prefers-reduced-motion`.

### Regla del marco: todo vive dentro del celular

La app se comporta como una pantalla de dispositivo, también en escritorio. Para que
eso se sostenga:

- `.stage` mide exactamente `100dvh` y **no desplaza**. El único elemento que hace
  scroll es `.device-scroll`. Nunca hacer que el `body` o la página desplacen.
- `.device` lleva `transform: translateZ(0)`, lo que lo convierte en el bloque
  contenedor de sus descendientes `position: fixed`. Sin eso, cualquier capa fija se
  anclaría a la ventana del navegador y se saldría del celular en escritorio.
- Toda capa a pantalla completa (lector, visor, modal, menú, notificación) debe:
  1. llevar la clase **`.hoja`** junto a `fixed inset-0 z-50`, y
  2. declararse en la sección Blade **`@section('hojas')`**, no dentro de
     `@section('contenido')`.

  La sección `hojas` se pinta como hijo directo de `.device`, por encima de la
  cabecera y del pie de la misión. Si la capa se deja dentro de `contenido`, el pie
  la tapa por abajo.
- `.device` tiene `overflow: hidden`, así que el contenido que excede el alto se
  recorta y se desplaza dentro de `.device-scroll`. Eso es lo esperado.

Hay dos guiones de verificación en el historial del proyecto que comprueban esto
midiendo, para cada ruta, que ningún elemento pinte por fuera del marco y que no
exista ningún `position: fixed` fuera de `.hoja`.

## Cómo funciona el juego

### Acceso

El administrador crea los participantes desde el dashboard; el sistema genera un
**código de acceso** de 6 caracteres (sin O/0 ni I/1, para poder dictarlo). El
participante entra con **documento + código**.

> El código no es una credencial sensible —es de un solo evento— por eso se guarda en
> claro y el administrador puede consultarlo y regenerarlo.
>
> **Pendiente:** el sistema final debe autenticar por **OAuth 2.0 con Microsoft**.
> Cuando eso llegue, reemplaza `AccesoController` y el middleware
> `ParticipanteAutenticado`; el resto del juego no cambia.

### Reglas

- Cada misión tiene **tiempo límite** y **oportunidades** propias (por defecto 3 min y 2;
  `0` = ilimitadas).
- Una misión se supera escribiendo el **código correcto de la estación** antes de que
  el cronómetro llegue a cero. Cada misión vale un acierto.
- Cada oportunidad tiene **su propio cronómetro**, que arranca al **escanear el QR**, y el
  tiempo de todas **se suma** al total de la misión (y al ranking). Un código incorrecto o
  agotar el cronómetro consume la oportunidad; la siguiente arranca al volver a escanear.
- Al agotar las oportunidades, la misión queda **fallada** y el participante sigue con las otras.
- Cuando ya no le quedan misiones por jugar, las falladas **se reabren una sola vez**
  (segunda vuelta). Después de eso quedan cerradas definitivamente.
- Cada misión superada entrega un **fragmento del código**. Con todos se completa la
  recuperación y se llega a la pantalla de victoria.
- El **ranking** ordena por aciertos y, a igualdad, por menor tiempo.

### Flujo de una misión

1. En el centro de mando el participante toca una misión y ve sus instrucciones y el
   **lector de QR** (`missions/escanear`). El cronómetro aún no corre.
2. Escanea el QR de la estación (proyectado en el televisor o impreso), que apunta a
   `/mision/{slug}/activar/{token}`. Con el lector de la app o con la cámara del
   celular: la misión se activa y **arranca el cronómetro**. Escanear otra vez no lo
   reinicia.
3. Queda abierta la **ventana del código** (`missions/codigo`). Resuelve el reto físico
   y escribe el código. Un envío vacío con tiempo disponible no gasta la oportunidad.
4. Quien escanea sin sesión pasa por el login y vuelve a la misión.

> El lector de la app usa la cámara, que el navegador solo permite en **HTTPS** (o
> `localhost`). Sin HTTPS sigue funcionando el escaneo con la cámara del celular.
> En iPhone (sin `BarcodeDetector`) la lectura la hace `jsqr`, que se carga solo en esa pantalla.

### Pantalla de proyección

**Misiones → Proyectar** abre, en una pestaña, la pantalla de la estación pensada para
un televisor: el QR en grande, el título, la historia, las instrucciones del reto, los
pasos (escanear → resolver → ingresar el código) y el tiempo y las oportunidades.

- `F` o el botón **Pantalla completa**; `←` / `→` pasan a la misión anterior / siguiente.
- Los controles y el cursor se ocultan solos a los 3 s sin mover el mouse.
- Se adapta a cualquier resolución (todo se mide en una unidad relativa a un lienzo
  16:9) y a pantallas verticales.
- Requiere la sesión del dashboard: déjala abierta en el equipo conectado al televisor.

### Integridad

Lo importante lo decide el servidor, no el navegador:

- **El código de la estación nunca viaja al cliente.** Está en la base de datos y
  `Juego::responder()` lo compara allí (`Mision::codigoPresencialCoincide`).
- **El QR lleva un token secreto por misión.** Adivinar la URL no activa la misión, y
  regenerarlo invalida los QR anteriores.
- **El cronómetro es del servidor.** `iniciada_en` se guarda al escanear y el tiempo
  restante se calcula contra el reloj del servidor, así que recargar la página no
  regala tiempo. Si vence, la misión se cierra sola como fallada.
- **Sin escanear no hay código.** Enviar un código sin haber activado la misión no se
  evalúa ni gasta oportunidades.

### Piezas

| Archivo | Rol |
|---|---|
| `app/Services/Juego.php` | Ciclo de la partida: estados, reactivación, código, ranking |
| `app/Models/Mision.php` | Misión: QR (token, URL de activación) y código de la estación |
| `app/Support/Contenido.php` | Textos y ajustes (una lectura por petición) |
| `resources/js/qr.js` | Lector de QR, QR del dashboard y pantalla de proyección |
| `app/Support/Marca.php` | Empresa activa, con el config como respaldo |
| `app/Http/Middleware/ParticipanteAutenticado.php` | Sesión del participante |

## Dashboard de administración

Ruta **`/dashboard`**, con su propio acceso en `/dashboard/login`. Es independiente del
login de participantes: el juego entra por `/`, la administración por `/dashboard`.

### Puesta en marcha

```bash
php artisan migrate
php artisan storage:link                        # sirve los logos subidos
php artisan db:seed --class=AdministradorSeeder # usuario admin + empresa
php artisan db:seed --class=ContenidoSeeder     # misiones, textos, parámetros
```

`ContenidoSeeder` copia a la base de datos el contenido que vive en
`config/escape_room.php`. **A partir de ahí la fuente de verdad es la base de datos**:
el config queda solo como semilla para una instalación nueva y como respaldo de la
marca. Ambos seeders son idempotentes.

El seeder crea el usuario administrador y la empresa inicial. Es idempotente. Las
credenciales por defecto son `admin@escaperoom.local` / `escaperoom2026`, y se pueden
fijar con `ADMIN_EMAIL` y `ADMIN_PASSWORD` en el `.env`.

> **Cambia esa contraseña antes de publicar.**

### Estructura

Barra lateral con las secciones agrupadas:

| Grupo | Sección | Qué hace |
|---|---|---|
| General | **Dashboard** | Cifras de cabecera, partidas por día, precisión por misión, mejores resultados y actividad reciente |
| Contenido | **Misiones** | Parámetros de cada misión, sus instrucciones, el **código de la estación**, su **QR** y la **pantalla de proyección** |
| Contenido | **Instrucciones** | Los pasos del briefing inicial |
| Contenido | **Textos** | Cada etiqueta, título y mensaje que ve el participante |
| Usuarios | **Participantes** | Alta, edición, código de acceso, reiniciar partida, eliminar |
| Reportes | **Resultados** | Ranking, seguimiento del certificado, detalle por misión y exportación a CSV |
| Reportes | **Bitácora** | Toda la actividad, con filtros por canal, nivel y texto |
| Configuración | **Empresa** | Nombre y logo, con vista previa en vivo |
| Configuración | **Parámetros** | Duración e intentos por defecto, nombre de la experiencia y video. |

Los cambios se aplican de inmediato: el juego lee siempre de la base de datos.

### Editor de contenido enriquecido

Las instrucciones de cada misión se editan con un editor visual (negrita, cursiva, subrayado, listas, enlaces)
que incluye una vista de código para tocar el HTML a mano.

Ese HTML se imprime en el juego con `{!! !!}`, así que **siempre pasa por
`App\Support\HtmlSeguro`** antes de guardarse: se conserva una lista blanca de
etiquetas (`p`, `strong`, `em`, `u`, `s`, `br`, `code`, `ul`, `ol`, `li`, `a[href]`) y
se descarta todo lo demás. Las etiquetas peligrosas (`script`, `style`, `iframe`,
`object`, `embed`, `form`…) se eliminan **con su contenido**, los atributos de evento
(`onclick`, `onerror`…) se borran y los enlaces solo admiten `http`, `https`, `mailto`
y `tel`. Verificado enviando cargas maliciosas directamente al endpoint, saltándose
el editor.

### Seguimiento del certificado

El reporte de Resultados muestra, por participante, si descargó su certificado:

| Estado | Qué significa |
|---|---|
| **Descargado** | Pulsó el botón de descarga. Se guarda la fecha y cuántas veces |
| **Solo lo vio** | Abrió la pantalla del certificado pero no pulsó descargar |
| **No lo abrió** | Nunca llegó a esa pantalla |

Las tres tarjetas de arriba filtran la tabla al tocarlas, y el CSV incluye el estado,
la fecha de descarga y el número de descargas.

> **Límite real:** la exportación a PDF la hace el navegador (`window.print` →
> «Guardar como PDF»). El servidor registra el clic mediante un `fetch` con
> `keepalive` justo antes de abrir el diálogo, pero **si el participante cancela ese
> diálogo, eso ya no es observable**. Por eso el reporte lo advierte en pantalla.

### Bitácora

Todo queda registrado en la tabla `registros`, a través de `App\Support\Bitacora`:

- **Canal `juego`** — accesos, intentos fallidos, QR escaneados (y QR no válidos), resultado de cada misión, código
  incorrecto, partida completada.
- **Canal `dashboard`** — accesos, y cada edición de misiones, contenido,
  QR regenerados, briefing, textos, parámetros, participantes y marca.
- **Canal `sistema`** — intentos de acceso fallidos al panel (nivel `alerta`).

Cada registro guarda quién, qué, cuándo, la IP y un contexto en JSON. La bitácora
nunca interrumpe la operación que la invoca: si falla, se descarta en silencio.

### Gráficos

Los colores de los gráficos **no son los mismos de la interfaz**: los tonos neón de la
marca no pasan las comprobaciones de un palette categórico sobre fondo oscuro (quedan
fuera de la banda de luminosidad y por debajo del piso de croma). Se usa una variante
ajustada —`#009fb8`, `#6a5cff`, `#ff2da1`, `#17a55c`— validada en banda de luminosidad,
croma, separación para daltonismo y contraste contra la superficie. Los gráficos son
SVG en línea, sin librerías.

El logo se guarda en el disco `public` (`storage/app/public/marca/`) y se sirve por
`/storage/...`. Al reemplazarlo se borra el archivo anterior. Se aceptan PNG, JPG, WEBP
y SVG de hasta 2 MB.

### Cómo llega la marca a las vistas

`App\Support\Marca::actual()` resuelve la marca con este orden de prioridad:

1. La empresa activa en la tabla `empresas` (lo que se guarda desde el dashboard).
2. Si no hay ninguna —o la tabla todavía no existe— los valores de
   `config/escape_room.php`.

Ese arreglo se comparte con **todas** las vistas como `$marca` desde
`AppServiceProvider`, y se resuelve una sola vez por petición. Por eso el juego nunca
queda sin marca, ni siquiera antes de migrar.

### Detalles de implementación

- El dashboard **no usa el marco de celular**: es una herramienta de escritorio, con
  layout propio (`layouts/dashboard`) y ancho completo, compartiendo paleta y
  componentes con el juego.
- La ruta llamada `login` es la del participante, así que `bootstrap/app.php` redefine
  `redirectGuestsTo` hacia `dashboard.login`; sin eso, Laravel mandaría al juego a quien
  entre al dashboard sin sesión.
- El formulario de acceso tiene `throttle:6,1` contra fuerza bruta.

## Video de cierre

En la pantalla del certificado hay un botón **Ver el video de la experiencia** que abre
el reproductor en una hoja a pantalla completa, dentro del marco del celular.

- El archivo se configura en `config/escape_room.php` → `'video_final' => 'escape.mp4'`
  (ruta dentro de `public/`; `null` oculta el botón).
- Pesa ~21 MB, así que **no se descarga al cargar la página**: el `src` se asigna al
  abrir el reproductor (`preload="none"`).
- Al cerrar, el video se pausa. `Esc` también cierra y pausa.
- La hoja lleva `.solo-pantalla`, así que no aparece al exportar el certificado a PDF.

### Por qué el video se sirve por una ruta y no como archivo estático

El servidor embebido de PHP (`artisan serve`) **no responde peticiones por rangos**
(`Range`), y sin eso Safari en iOS no reproduce video y no se puede adelantar. La ruta
`/video-final` devuelve un `BinaryFileResponse`, que sí las maneja: responde `206
Partial Content` con `Accept-Ranges: bytes`.

En producción, detrás de nginx o Apache, se puede apuntar el `<video>` directamente al
archivo estático (`asset($video)`): es más eficiente y esos servidores ya soportan
rangos de forma nativa.

## Marca del cliente (multiempresa)

El logo aparece en **login, ranking y certificado**. La forma normal de cambiarlo es
desde el **dashboard** (ver arriba): nombre y logo se guardan en base de datos y se
aplican solos.

Lo que sigue describe el **respaldo**: los valores que usa el juego cuando no hay
ninguna empresa configurada todavía.

1. Dejar el logo en `public/marca/` (SVG o PNG con fondo transparente; sirve
   cualquier proporción).
2. Apuntar a él en `config/escape_room.php`:

```php
'marca' => [
    'titulo'    => 'Ciberamenaza Inminente',
    'subtitulo' => 'Protocolo de contención · 2026',
    'cliente'   => 'SOE360',
    'logo'      => 'marca/logo-soe.png',  // null lo oculta
    'logo_alt'  => 'SOE360',
],
```

No hay que tocar vistas ni CSS. El logo se pinta con el parcial
`partials/logo.blade.php`, que lo coloca **siempre sobre una placa blanca**
(`.placa-logo` en `app.css`). Esa decisión es lo que hace el cambio de empresa
indoloro: un logo azul, negro o de colores planos se lee igual de bien sobre la
paleta oscura del juego, y no hay que preparar una versión en blanco de cada marca.
El borde y el halo cian de la placa son los que la integran con el resto.

La placa fija el **alto** y deja el ancho libre, con `max-width: 100%` como tope,
así que la proporción del archivo no importa. Tamaños disponibles: `sm`, `md`, `lg`
y `cert` (este último en unidades `cqw`, para que escale junto con el certificado
tanto en la vista previa como en el PDF).

Probado con logos en los dos extremos —uno de 7:1 en color oscuro y uno cuadrado de
color claro— en las tres pantallas y en el PDF, sin desbordes.

## Certificado de participación

Se llega desde la pantalla de victoria (`/certificado`). El botón **Descargar
certificado en PDF** llama a `window.print()`: el navegador abre su diálogo y el
participante elige *Guardar como PDF*. No hay dependencias de PDF del lado del
servidor.

Cómo está armado:

- **Una sola marcación** sirve para la vista previa dentro del celular y para la hoja
  impresa. Todas las medidas del certificado están en unidades de contenedor (`cqw`)
  sobre `.cert-marco { container-type: inline-size }`, así el diseño escala sin
  duplicar estilos.
- Las reglas `@media print` de `app.css` desarman el marco del celular, ocultan todo
  lo marcado con `.solo-pantalla` y dejan el certificado a sangre completa en
  **A4 horizontal** (`@page { size: A4 landscape; margin: 0 }`).
- `print-color-adjust: exact` conserva los fondos oscuros al exportar. El usuario debe
  tener activada la opción **Gráficos de fondo** del diálogo de impresión; el aviso
  bajo el botón se lo recuerda.
- El texto con degradado recortado (`background-clip: text`) se exporta como un bloque
  de color en PDF, así que al imprimir se sustituye por color plano.

Los datos del participante son de maquetación y están al inicio de la vista
`game/certificado`; se reemplazan por los de la sesión cuando exista backend.

## PWA

`public/manifest.webmanifest`, `public/sw.js` e iconos en `public/icons/`.
El service worker usa *network-first* con respaldo en caché: suficiente para
maquetación, conviene definir precache y versionado antes de producción.

> El service worker solo se registra en **HTTPS** (o en `localhost`).

---

## Pendiente

- Autenticación de participantes por **OAuth 2.0 con Microsoft** (ver *Acceso*).
