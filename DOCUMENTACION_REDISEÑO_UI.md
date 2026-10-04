# Documentación de Arquitectura & Ajustes UI/UX - Carnicería Cano

Este documento detalla la especificación técnica de la arquitectura de renderizado dinámico, tipografía homogénea y componentes visuales del sitio web de **Carnicería Cano**.

---

## 🎨 1. Unificación de Encabezado y Fondo Global

### 📌 Diagnóstico & Solución Implementada:
- **Problema Anterior:** Existía una franja de fondo color naranja/ladrillo heredada (`#9f4922` a 220px) en la etiqueta `body`, lo que provocaba una disparidad visual y cortes abruptos entre la portada del inicio y las vistas secundarias como el Carrito o Checkout.
- **Solución Aplicada:** 
  - Se eliminó por completo el gradiente naranja heredado del `body`.
  - Se homogeneizó el fondo global en un tono crema suave (`#F7F3EE`).
  - La cabecera superior y el menú de navegación (`.store-header`) adoptaron un tono uniforme caoba/oscuro (`#1A0909`), haciendo que **todas las páginas compartan exactamente el mismo estilo de encabezado**.

---

## 🖼️ 2. Contenedor Banner Autónomo de Encabezados (`.page-header-banner`)

- **Diseño Autónomo:** Los títulos principales están contenidos en una tarjeta oscura con borde dorado (`.page-header-banner`), garantizando legibilidad en texto blanco puro y adaptabilidad de alto automática.

---

## ✒️ 3. Sistema de Tipografía Homogénea (Fonts)

- **Fuentes:**
  - `Georgia` (Serif): Encabezados principales, marcas y títulos de productos.
  - `-apple-system` / `Segoe UI` (Sans-Serif): Botones, menú, inputs y paginación.

---

## ⚡ 4. Arquitectura Dinámica: SPA-Lite / AJAX Fetch Engine (Sin Recarga de Página)

- **Peticiones Parciales:** Intercepción transparente mediante JavaScript **Fetch API**.
- **Actualización de DOM:** Reemplazo parcial del contenedor de catálogo `#catalogo`.

---

## 📋 5. Resumen de Archivos Modificados

| Archivo | Función |
| :--- | :--- |
| `css/storefront-shop.css` | Rediseño de paleta de marketing en Hero Banner, unificación de encabezados y eliminación del fondo naranja heredado |
| `storefront_layout.php` | Implementación de carrusel de categorías y buscador marketing de 52px de altura uniforme |
| `storefront_cart_view.php` | Encabezado en contenedor `.page-header-banner` sobre fondo crema unificado |
| `storefront_checkout_view.php` | Encabezado en contenedor `.page-header-banner` sobre fondo crema unificado |
| `DOCUMENTACION_REDISEÑO_UI.md` | Documentación técnica |

---

## 🥩 6. Rediseño de Marketing & Paleta del Hero Banner (`.hero-mobile-marketing`)

- **Estrategia Visual:** Para evitar el tono monocromático oscuro (`#1A0909`) que se confundía con la barra superior de navegación, se transformó el Hero en un degradado carmesí/borgoña artesanal con iluminación radial (`radial-gradient(circle at 75% 25%, rgba(200, 35, 35, 0.4) 0%, rgba(126, 20, 20, 0.95) 55%, #2B0707 100%)`).
- **Impacto de Conversión (E-Commerce):**
  - **Estimulación del apetito:** Tonos rojo carmesí cálido (`#7E1414` / `#AB1E1E`) característicos de cortes premium de carnicería.
  - **Jerarquía visual & Contraste:** Resalta el cuadro de búsqueda blanco (`52px`) y los botones amarillos (`var(--cano-yellow)`), guiando la mirada del usuario de forma inmediata hacia el catálogo de productos.
  - **Borde de acabado:** Borde inferior dorado (`border-bottom: 3px solid var(--cano-gold)`) con sombra interna (`box-shadow: inset 0 -8px 16px rgba(0, 0, 0, 0.25)`) para una separación elegante con la franja de categorías.

---

## 🧼 7. Eliminación de Badge Flotante "Fresco del Día" y Alineación Responsiva Topbar

- **Causa del Error:** La clase `.product-badge-marketing` utilizaba posicionamiento absoluto (`position: absolute; top: 6px; left: 6px;`) sin contención relativa (`position: relative`) en la tarjeta de producto, lo que provocaba que se flotara hacia la esquina superior izquierda de toda la página y se empalmara sobre la barra de "HORARIO ATENCIÓN".
- **Solución:**
  - Se removió la insignia flotante `.product-badge-marketing` de las tarjetas de producto en [storefront_catalog_view.php](file:///c:/xampp/htdocs/carniceriacano/storefront_catalog_view.php#L143-L150) para mantener la fotografía del producto 100% limpia y visible.
  - Se agregó `.product-card-header { position: relative; }` en [css/storefront-shop.css](file:///c:/xampp/htdocs/carniceriacano/css/storefront-shop.css#L372-L375).
  - Se ajustó la barra superior `.marketing-urgency-bar` con `display: flex; flex-wrap: wrap; justify-content: center;` garantizando alineación y legibilidad perfecta en pantallas móviles.

---

## 📱 8. Despeje de Fondo y Visibilidad Completa del Logo Inferior Móvil

- **Problema:** En pantallas móviles, la barra de navegación fija inferior (`.mobile-bottom-nav`, de ~70px de alto) cubría parcialmente la parte inferior del logo del footer (`img/logo_1.jpeg`) al hacer scroll al final de la página.
- **Solución:**
  - Se reordenó la jerarquía DOM en [storefront_layout.php](file:///c:/xampp/htdocs/carniceriacano/storefront_layout.php#L140-L165), ubicando el elemento `<footer>` de forma semántica antes de `<nav class="mobile-bottom-nav">`.
  - Se configuró `.store-footer-centered` en [css/storefront-shop.css](file:///c:/xampp/htdocs/carniceriacano/css/storefront-shop.css#L528-L535) con un `padding-bottom: 100px !important;` exclusivo para dispositivos móviles, garantizando que al llegar al final del desplazamiento, el logo se posicione con un margen superior libre de 30px por encima de la barra de navegación fija.

---

## 🔢 9. Paginación Inteligente Recortada (Mobile Windowing con `...`)

- **Problema:** Anteriormente, se iteraban todas las páginas existentes (ej. 1, 2, 3, 4, 5, 6, 7, 8, ... 16) en una sola línea. En dispositivos móviles, esto sobrepasaba el ancho de la pantalla generando desbordamiento horizontal.
- **Solución:**
  - **Algoritmo de Ventana (PHP):** En [storefront_catalog_view.php](file:///c:/xampp/htdocs/carniceriacano/storefront_catalog_view.php#L184-L215), se implementó una ventana de paginación acotada alrededor de la página activa `$currentPage` con separadores de puntos suspensivos `...` para páginas lejanas (ej. `[<] 1 ... 4 [5] 6 ... 16 [>]`).
  - **CSS Responsivo Flex:** En [css/storefront-shop.css](file:///c:/xampp/htdocs/carniceriacano/css/storefront-shop.css#L478-L515), se configuró `max-width: 100%`, `overflow-x: auto` y `flex-shrink: 0` para asegurar que el control de paginación jamás desborde ni deforme el layout en ninguna resolución móvil.

---

## 👜 10. Feedback Interactivo al Agregar (Botón Verde, Animación Bolsa & Modal Comentarios)

- **Cambio de Color del Botón:** Al presionar "Agregar", el botón de la tarjeta conmuta instantáneamente a verde éxito (`#198754`) mostrando `<i class="bi bi-check-circle-fill"></i> ¡Agregado!` durante 2.5s antes de regresar al color corporativo.
- **Animación & Crecimiento de la Bolsa 👜:** Todos los contadores (`.cart-items-counter`, `.header-cart-badge`, `.nav-cart-badge`, `.mobile-bottom-cart-badge`) se actualizan en tiempo real sin recargar la página. Los íconos de carrito rebotan con una animación `@keyframes cartBagBounce` simulando el inflado/crecimiento de la bolsa.
  - Las notas quedan almacenadas en `$_SESSION['store_cart'][$code]['comentario']` y se visualizan en la vista del Carrito ([storefront_cart_view.php](file:///c:/xampp/htdocs/carniceriacano/storefront_cart_view.php#L46-L53)).

---

## 📍 11. Sucursales 1 y 2, Geolocalización GPS (Máx. 5 km), Referencias & Métodos de Pago

- **Ajustes de Portada & Horarios:**
  - Se eliminó "Pollo" del titular principal en [storefront_layout.php](file:///c:/xampp/htdocs/carniceriacano/storefront_layout.php#L90-L95) (*"Carnes frescas de Res, Cerdo y Parrilla a tu mesa"*).
  - Se actualizaron los horarios diferenciados en la barra superior: **Sucursal 1:** 7:30 AM - 3:30 PM | **Sucursal 2:** 8:00 AM - 4:00 PM.
- **Geolocalización GPS & Radio Máximo de Reparto (5 km):**
  - Botón interactivo `📍 Usar mi ubicación actual (GPS)` mediante HTML5 Geolocation API en [storefront_checkout_view.php](file:///c:/xampp/htdocs/carniceriacano/storefront_checkout_view.php#L50-L75).
  - Cálculo de distancia por la fórmula de Haversine hacia la Sucursal seleccionada. Si la ubicación supera los 5 km para entrega a domicilio, se despliega una alerta preventiva y se bloquea el envío hasta seleccionar Recolección o cambiar de Sucursal.
- **Detalle de Dirección & Referencias Obligatorias:**
  - Campo de referencias de ubicación (`referencias_ubicacion`) obligatorio para detallar color de fachada, entre calles o puntos clave para el repartidor.
- **Métodos de Pago:**
  - **Efectivo:** Muestra opción para ingresar con cuánto billete pagará el cliente (para llevar cambio exacto).
  - **Transferencia SPEI:** Despliega datos bancarios oficiales (Banco, CLABE, Beneficiario).
- **Limpieza de Formulario:** Se retiró el cuadro general de indicaciones para el carnicero del checkout, ya que las notas se capturan individualmente por producto.

