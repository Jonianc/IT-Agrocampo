# Changelog

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
