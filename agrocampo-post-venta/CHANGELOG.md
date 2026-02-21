# Changelog

## [1.4.13] - 2026-02-21

### Changed
- Se mejora el layout del PDF con render adaptativo en campos de detalle: valores cortos se muestran en fila compacta y textos extensos en bloque con borde.
- Se preservan saltos de línea en campos narrativos (`lubricantes`, `filtros`, `componentes`, `trabajos realizados`, `observaciones`) para evitar párrafos aplastados.
- Se evita generar cajas grandes innecesarias para placeholders como `No informado`, mejorando equilibrio visual y paginación.

## [1.4.12] - 2026-02-21

### Changed
- Se normaliza la salida del PDF para evitar valores crudos como `NULL`, placeholders y estructuras serializadas en campos de texto.
- Se aplica fallback `No informado` en campos clave del PDF cuando no existe dato útil, mejorando legibilidad del reporte final.
- Se agrega extracción legible de datos serializados (por ejemplo `street_address`) y aplanado de arreglos para impresión en PDF.

## [1.4.11] - 2026-02-17

### Added
- Se agrega columna `legacy_id` en la tabla de informes para guardar el ID histórico del formulario anterior.
- El importador CSV ahora reconoce y mapea ID legado desde cabeceras como `legacy_id`, `entry_id` o variantes de `id formulario`.

### Changed
- En el gestor, la columna ID muestra prioritariamente `legacy_id` cuando existe y mantiene el ID interno para acciones funcionales.

## [1.4.10] - 2026-02-17

### Added
- Se agrega en Ajustes una sección "Zona peligrosa" con acción para eliminar todos los informes del plugin.
- Se incorpora el handler `admin_post_agp_pv_delete_all_submissions` con validación de permisos, nonce y confirmación en cliente.

### Changed
- El borrado masivo total ahora elimina adjuntos PDF asociados y luego limpia la tabla de informes.
- Se agregan avisos de resultado para eliminación total (éxito/error) con conteo de registros eliminados.

## [1.4.9] - 2026-02-17

### Fixed
- Se corrige el importador CSV para no abortar todo el proceso al fallar una fila: ahora continúa, cuenta filas fallidas y completa la importación parcial.
- Se reemplaza el mensaje genérico de error por validaciones más claras (cabeceras mínimas) y detalle cuando ninguna fila pudo importarse (incluye línea y error DB).

### Changed
- El resumen de importación en Ajustes ahora muestra `Importados`, `Omitidos` y `Fallidos`, con aviso tipo warning cuando hay filas fallidas.

## [1.4.8] - 2026-02-17

### Added
- Se agrega en Ajustes un nuevo bloque "Importador de informes" para subir CSV históricos del plugin anterior (Forminator).
- Se incorpora un nuevo handler `admin_post_agp_pv_import_legacy_csv` con nonce y validación de permisos para ejecutar la importación desde administración.

### Changed
- Se implementa mapeo de columnas del CSV legado (incluyendo "Hora de envío", campos técnicos y categorías) hacia la estructura interna de informes actuales.
- Se agregan avisos de resultado de importación en Ajustes con resumen de filas importadas y omitidas.

## [1.4.7] - 2026-02-17

### Added
- Se incorpora ruta segura para "Ver PDF" en el gestor usando URL firmada con nonce (`?view=<id>&_wpnonce=<nonce>`).
- Se agrega handler en admin para validar permisos y nonce, cargar el informe por ID y responder el PDF en línea con headers `Content-Type`, `Content-Disposition`, `Content-Length` y no-cache.

### Changed
- El enlace "Ver PDF" del listado/detalle ahora abre una URL firmada en pestaña nueva, evitando exposición de PDF sin autorización válida.

## [1.4.6] - 2026-02-17

### Changed
- Se reorganiza la pantalla de Ajustes con mejor layout, jerarquía de botones y mensajes de estado más visibles.
- Se mejora la legibilidad del gestor de informes con estilos para tabla y estados visuales de correo/PDF.
- Se incorpora barra de filtros en el gestor con búsqueda, filtros por estado (correo/PDF), correo y rango de fechas.
- Se agrega ordenamiento por columnas y se mantiene paginación en el listado de informes.

### Added
- Se agregan acciones masivas en el gestor: reintentar envío de correo, regenerar PDF, exportar CSV y eliminar.
- Se agrega acción por fila para regenerar PDF en cada informe.
- Se incorpora funcionalidad de regeneración de PDF desde `AGP_PV_PDF::regenerate_pdf_attachment()`.

## [1.4.5] - 2026-02-17

### Fixed
- Se evita el fatal error por redeclaración de `FPDF` cargando la librería solo cuando la clase no existe.

## [1.4.4] - 2026-02-17

### Changed
- Se renombra la interfaz a "Informe Técnico" en panel de administración y plantilla frontend.
- Se agrega un botón de acceso rápido en Ajustes para abrir el formulario público en `/post-venta/`.
- Se actualizan textos de prueba de correo para reflejar el nuevo nombre.

## [1.4.3] - 2026-02-17

### Changed
- Bump de versión del plugin de 1.4.2 a 1.4.3 en el archivo principal.
- Se agrega este archivo CHANGELOG para registrar cambios de versiones.
