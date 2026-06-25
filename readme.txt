=== WooCommerce Multi-Store HPOS ===
Contributors:      carlosromero
Tags:              woocommerce, multistore, stock, prices, hpos, warehouses
Requires at least: 6.0
Tested up to:      6.7
Requires PHP:      8.0
Stable tag:        1.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Gestión de múltiples tiendas (stores/bodegas) con stock y precios independientes para WooCommerce, compatible con HPOS.

== Description ==

WooCommerce Multi-Store HPOS permite operar varias tiendas físicas o bodegas desde una única instalación de WooCommerce. Cada tienda gestiona su propio stock y precio por producto, con validación en todos los puntos del proceso de compra.

**Características principales:**

* **Selector de tienda** en el frontend (modal automático y shortcode `[mswc_selector]`).
* **Stock y precio independientes** por tienda/bodega, almacenados en tablas personalizadas.
* **Botón "Añadir al carrito" deshabilitado** automáticamente cuando la tienda activa no tiene stock del producto.
* **Gestión de tiendas desde el admin** de WooCommerce: crear, editar, eliminar y habilitar/deshabilitar tiendas con una interfaz completa (búsqueda, ordenamiento, paginación y acciones masivas).
* **Validación de tienda en múltiples capas:** selector AJAX, página de carrito, checkout clásico, checkout en bloques (Store API) y en el momento de crear el pedido.
* **Compatible con High-Performance Order Storage (HPOS)** de WooCommerce.
* **Descuento automático de stock** por tienda al procesar un pedido, con protección contra dobles descuentos.
* **Columnas de stock y precio promedio** en el listado de productos del administrador.
* **Pestaña "Stores"** en el editor de producto para gestionar stock y precio por bodega.
* **Aviso en el checkout** con la tienda activa y enlace para cambiarla.

== Installation ==

1. Sube la carpeta `woocommerce-multi-store` al directorio `/wp-content/plugins/`.
2. Activa el plugin desde el menú **Plugins** de WordPress.
3. Asegúrate de tener WooCommerce 8.0 o superior instalado y activo.
4. Al activar, el plugin crea automáticamente las tablas `wp_mswc_stores` y `wp_mswc_stores_stock` e inserta dos tiendas de ejemplo ("Store Norte" y "Store Sur").
5. Ve a **WooCommerce → Multi-Store** para gestionar tus tiendas.
6. En el editor de cada producto, usa la pestaña **Stores** para asignar stock y precio por bodega.

== Frequently Asked Questions ==

= ¿Cómo agrego o edito tiendas? =

Ve a **WooCommerce → Multi-Store** en el panel de administración. Desde ahí puedes crear nuevas tiendas, editar el nombre y el estado (habilitada/deshabilitada) de las existentes, y eliminarlas. También puedes habilitar, deshabilitar o eliminar varias tiendas a la vez con las acciones masivas.

= ¿Puedo deshabilitar una tienda temporalmente? =

Sí. Usa el enlace "Deshabilitar" en el listado de tiendas o el checkbox "Estado" en el formulario de edición. Las tiendas deshabilitadas no aparecen en el selector del frontend y los clientes que las tenían en sesión verán un aviso de error en el carrito y en el checkout.

= ¿Es compatible con variaciones de producto? =

Sí. El filtro de stock se aplica tanto a productos simples como a variaciones de producto.

= ¿Qué ocurre con el botón "Añadir al carrito" si no hay stock? =

Si la tienda activa en sesión tiene 0 unidades del producto (o no tiene ningún registro de stock), el botón "Añadir al carrito" se deshabilita automáticamente y el producto se muestra como agotado en esa bodega.

= ¿El stock se descuenta automáticamente al hacer un pedido? =

Sí. Cuando un pedido pasa al estado "Procesando" (ya sea por pago con tarjeta o por cambio manual), el stock se descuenta de la tienda asignada al pedido. El plugin usa un flag de idempotencia para evitar dobles descuentos si los dos hooks de procesamiento se disparan sobre el mismo pedido.

= ¿Qué pasa si una tienda se deshabilita mientras un cliente tiene el carrito abierto? =

El plugin valida el estado de la tienda en tiempo real en cada punto del proceso de compra. Si la tienda es deshabilitada mientras el cliente navega, recibirá un aviso de error al intentar pasar por caja y se le pedirá que seleccione otra tienda.

= ¿Qué pasa si desinstalo el plugin? =

El plugin elimina sus tablas (`wp_mswc_stores` y `wp_mswc_stores_stock`) y la opción `mswc_db_version` de la base de datos al desinstalarse. Los meta de pedido (`_mswc_store_dispatch_id`) permanecen en la tabla de pedidos para mantener el historial.

= ¿Es compatible con el checkout en bloques de WooCommerce? =

Sí. La validación de tienda se aplica al checkout clásico (shortcode) y al checkout en bloques mediante el filtro `woocommerce_store_api_cart_errors`.

== Screenshots ==

1. Modal de selección de tienda en el frontend.
2. Listado de tiendas en WooCommerce → Multi-Store (con búsqueda y acciones masivas).
3. Formulario de creación / edición de tienda.
4. Pestaña "Stores" en el editor de producto con stock y precio por bodega.
5. Columnas de stock y precio promedio en el listado de productos del administrador.
6. Aviso de tienda activa en el checkout con enlace para cambiarla.

== Changelog ==

= 1.0.0 =
* Versión inicial.
* Selector de tienda con modal automático y shortcode `[mswc_selector]`.
* Stock y precio independientes por bodega en la ficha de producto.
* Gestión completa de tiendas desde el admin de WooCommerce (CRUD, búsqueda, paginación, acciones masivas).
* Botón "Añadir al carrito" deshabilitado automáticamente sin stock en la tienda activa.
* Validación de tienda en múltiples capas: AJAX, carrito, checkout clásico, checkout en bloques y creación de pedido.
* Aviso de tienda activa en el checkout con enlace para cambiarla.
* Descuento automático de stock al procesar pedidos, con protección contra dobles descuentos.
* Compatible con High-Performance Order Storage (HPOS) de WooCommerce.
* Eliminación limpia de datos al desinstalar (uninstall.php).

== Upgrade Notice ==

= 1.0.0 =
Primera Beta.
