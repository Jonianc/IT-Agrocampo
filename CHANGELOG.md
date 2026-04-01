## [1.13.0] - 2026-04-01
- `/post-venta-informes/`: ajuste UI responsive del toolbar de filtros para mejorar distribución en desktop/intermedio/móvil y evitar desbordes de acciones.
- se mantiene alcance visual: sin cambios en permisos, filtros, consultas SQL, nonces, persistencia ni flujo funcional.

## [1.12.0] - 2026-04-01
- `/post-venta-informes/`: las columnas `Correo` y `PDF` ahora muestran badges con texto humano (`Pendiente`, `Enviado/Listo`, `Falló`) para lectura operativa más rápida.
- el ajuste es solo visual en frontend standalone de informes; no cambia permisos, filtros, consultas SQL ni persistencia.

## [1.11.0] - 2026-04-01
- se agrega gestor general de informes en frontend standalone bajo `/post-venta-informes/` para operación diaria fuera de wp-admin, manteniendo permisos de administrador.
- el admin de informes conserva su función de respaldo e incorpora acceso directo al nuevo gestor frontend general.

## [1.10.21] - 2026-04-01
- PDF: en sección Firmas para Garantía ahora se renderizan `Firma Cliente`, `Firma Técnico` y `Firma Jefe de Taller` en una sola fila de 3 columnas para optimizar espacio sin agregar celdas vacías.

## [1.10.20] - 2026-04-01
- PDF: se agrega render de `Firma Jefe de Taller` en la sección Firmas (segunda fila) para casos de Garantía y cuando exista adjunto.

## [1.10.19] - 2026-04-01
- fix en serialización de firmas en frontend: para `Garantía`, la firma de jefe de taller ya no se limpia por estar en un paso oculto al enviar desde paso 4.

## [1.10.18] - 2026-03-31
- para tipo de servicio Garantía ahora son obligatorios `Faena Lugar`, `Nombre Jefe de Taller` y `Firma Jefe de Taller`.
- se agrega persistencia de `jefe_taller_nombre` y `firma_jefe_taller_id` en base de datos, junto con guardado de firma en frontend/backend.
- el correo del informe incluye enlace a `Firma Jefe de Taller` cuando existe.

## [1.10.17] - 2026-03-31
- en activación del plugin ahora se registran explícitamente las reglas `post-venta` y `post-venta-observaciones` antes de `flush_rewrite_rules()`.
- evita tener que guardar manualmente los enlaces permanentes tras instalación para habilitar las rutas standalone.

## [1.10.16] - 2026-03-31
- se corrige bloqueo de submit en desktop por validación de campos no enfocables en pasos ocultos (`trabajos_realizados`/`observaciones`).
- `Trabajos realizados` ahora permite enviar correctamente con el límite exacto (`455/455`) sin invalidación artificial del contador.

## [1.10.15] - 2026-03-31
- fix preventivo en frontend para desktop: se robustece la actualización visual del bloque "Estado de la máquina" para no bloquear el flujo de submit si faltan nodos UI esperados.
- el botón Enviar mantiene comportamiento operativo aunque el bloque de badge/resumen no esté presente o llegue incompleto por caché.

## [1.10.14] - 2026-03-31
- cuando falla el correo por rate limit (429), el mensaje al usuario ahora indica explícitamente que el informe quedó guardado pero el correo está pendiente de envío.
- se normaliza el error técnico del proveedor para mostrar un mensaje legible y, cuando existe, la hora sugerida de reintento.

## [1.10.13] - 2026-03-31
- mejora de accesibilidad ARIA en el bloque "Estado de la máquina": el selector informa `aria-controls` y actualiza `aria-expanded` según visibilidad de "Fecha a contactar".
- el campo condicional y su ayuda contextual ahora sincronizan `aria-hidden` para entregar contexto más claro a navegación asistiva.

## [1.10.12] - 2026-03-31
- se agrega encabezado compacto en el bloque "Estado de la máquina" con badge del estado seleccionado para lectura rápida.
- el badge se actualiza dinámicamente en frontend según la selección (incluyendo estado vacío) sin alterar lógica de guardado.

## [1.10.11] - 2026-03-31
- se agrega feedback visual por estado en el bloque "Estado de la máquina" (operativo/detenido/operativo con pendiente) mediante acentos de color.
- el bloque usa clase por selección en frontend para mejorar lectura rápida sin alterar validación ni persistencia.

## [1.10.10] - 2026-03-31
- se agrega microcopy contextual en "Fecha a contactar", visible solo cuando el estado es "Operativo con pendiente".
- la ayuda contextual se oculta automáticamente en los demás estados, manteniendo lógica y validación existentes.

## [1.10.9] - 2026-03-31
- ajuste UI/UX del bloque "Estado de la máquina" en mobile: menor densidad vertical, labels más compactos y controles más proporcionados para mejorar lectura en pantallas chicas.
- ajuste visual de mensajes de error del bloque en mobile para mantener legibilidad sin aumentar altura innecesaria.

## [1.10.8] - 2026-03-31
- mejora UI mínima del bloque "Estado de la máquina" en mobile (espaciado interno y separación de campos) para lectura más limpia.
- se elimina el texto de ayuda "Visible y obligatoria solo para “Operativo con pendiente”." del campo "Fecha a contactar".

## [1.10.7] - 2026-03-31
- fix del campo condicional "Fecha a contactar": vuelve a mostrarse al seleccionar "Operativo con pendiente" y se oculta/deshabilita en los demás estados.
- se mantiene el comportamiento de limpieza del valor al ocultarse, sin cambios en backend ni persistencia.

## [1.10.6] - 2026-03-31
- fix visual del bloque "Estado de la máquina": el campo condicional "Fecha a contactar" vuelve a respetar `hidden` cuando no corresponde.
- ajuste de accesibilidad: se elimina la supresión de `outline` en foco y se mantiene foco visible compatible con `forced-colors/high-contrast`.

## [1.10.5] - 2026-03-31
- ajuste visual del bloque "Estado de la máquina" en standalone para mejorar jerarquía y legibilidad (borde de acento, contraste de etiqueta y espaciado).
- mejora de foco visual en selector de estado y campo de fecha a contactar, sin cambios de lógica ni persistencia.
- estado deshabilitado de "Fecha a contactar" con estilo más claro para reducir ambigüedad operativa.

## [1.10.4] - 2026-03-31
- agregado campo "Estado de la máquina" en el paso Detalle con opciones Operativo, Detenido y Operativo con pendiente.
- si el estado es "Operativo con pendiente", ahora exige "Fecha a contactar" y la muestra/oculta dinámicamente.
- integrado el nuevo dato en base de datos, admin, exportación CSV compatible, PDF y correo.
- agregado ajuste para definir la ubicación del bloque dentro del paso Detalle.

## [1.10.3] - 2026-03-26
- Standalone de observaciones: se corrige el layout de acciones del filtro para que `Descargar reporte` no quede recortado en desktop/intermedios y pueda envolver correctamente sin salirse del panel.
- La columna final del formulario ahora reparte mejor el espacio disponible y baja las acciones a fila completa antes, evitando overflow visual en resoluciones como la de validación.

## [1.10.2] - 2026-03-26
- Standalone de observaciones: se agrega botón `Descargar reporte` junto a `Filtrar` y `Limpiar`.
- La exportación XLSX desde standalone respeta la vista filtrada actual, incluyendo búsqueda, estado y período (`Últimos días` o rango `Desde/Hasta`).
- En caso de error al exportar desde standalone, el aviso vuelve a la misma vista con mensaje visible.

## [1.10.1] - 2026-03-26
- Gestor admin de observaciones: la cabecera se vuelve más sobria y explícita, posicionando la vista interna como **panel de respaldo** y recomendando el uso diario del gestor visual/standalone.
- Se agrega un bloque compacto de orientación (`Ruta recomendada` / `Este admin`) para separar mejor operación diaria versus soporte, sin quitar funciones ni rutas existentes.
- `Descargar reporte` baja a CTA secundario sobrio dentro del toolbar y el acceso al frontend se renombra a `Abrir gestor visual principal`, reduciendo ruido en admin sin perder fallback técnico.

## [1.10.0] - 2026-03-26
- Gestor admin de observaciones: se agrega botón **Descargar reporte** junto a `Aplicar filtros / Limpiar`, respetando la vista filtrada actual y el orden activo del listado.
- Nueva exportación **XLSX real** pensada para usuario final: encabezados legibles, observación completa, estados en texto humano, fechas `dd/mm/aaaa hh:mm` y metadatos de reporte (fecha, filtros aplicados, total exportado).
- El generador XLSX funciona con `ZipArchive` o fallback `PclZip`, evitando depender de una librería externa pesada para esta primera fase.

## [1.9.9] - 2026-03-26
- Standalone observaciones: se corrige el contador de **Vencidas** cuando hay filtro por `Desde/Hasta`, eliminando un descalce en el orden de parámetros SQL del resumen.
- Filtro de período: `Últimos días` queda visualmente atenuado cuando hay rango activo (`Desde/Hasta`) y muestra una nota explícita de que queda sin efecto mientras exista rango.
- Se mantiene `days` en la URL para conservar contexto, pero ya no induce confusión sobre cuál filtro manda realmente.

## [1.9.8] - 2026-03-26
- Standalone observaciones: se agrega filtro flexible de período por URL con `Últimos días` y `Desde / Hasta`, priorizando rango personalizado cuando se completa alguna fecha.
- La bandeja recalcula `Todas`, `Pendientes`, `Resueltas` y `Vencidas` con el período activo, usando fecha de creación para pendientes/vencidas y fecha de revisión para resueltas.
- Se suman presets rápidos (`Hoy`, `7 días`, `15 días`, `30 días`, `Este mes`, `Mes pasado`) y las acciones de fila ahora conservan el filtro activo al volver desde marcar revisado o agregar observación.

## [1.9.7] - 2026-03-26
- Standalone observaciones: la columna `Observación` vuelve a mostrar el texto completo directamente en la tabla, eliminando `Ver más` y el diálogo asociado para esa lectura.
- Standalone observaciones: se quita el fondo amarillo de `Pendientes` en tabs y filas, manteniendo el badge/acentos para conservar jerarquía sin teñir el bloque completo.

## [1.9.6] - 2026-03-26
- Standalone observaciones: `Gestor interno` pasa a CTA terciario compacto para reducir peso visual en el header.
- Standalone observaciones: se elimina `Ver PDF` del bloque de acciones y se mantiene solo en la columna `PDF` para evitar redundancia.
- Standalone observaciones: `Acción` usa botones visibles por fila (`Marcar revisado` / `Agregar observación`) sin menú desplegable inline.
- Standalone observaciones: `Ver más` ahora abre un diálogo modal y deja de expandir/desordenar la altura de la fila en la tabla.
- Standalone observaciones: las filas `Vencida` mantienen badge + borde rojo, pero se elimina el fondo rojo permanente para mejorar legibilidad.

# Changelog

## [1.9.5] - 2026-03-24
- Se refuerza la UI/UX del gestor de observaciones en standalone: header más trabajado, métricas en tarjetas, tabs tipo segment control y corrección visual para que **Vencidas** destaque en rojo también cuando está activa.
- La tabla frontend mejora lectura y priorización con filas tintadas por estado, badge adicional **Nueva observación**, observaciones truncadas a 2–3 líneas con `Ver más` y menú de acciones por fila.
- El gestor admin moderniza cabecera, filtros, detalle del informe y tabla interna, agregando preview de observación, menú de acciones, filas con jerarquía visual por estado y mejor separación para pendientes, revisadas y vencidas.

## [1.9.4] - 2026-03-24
- Se corrige la lógica de **Pendientes** para que la bandeja, contadores y filtros consideren como no revisado cualquier informe con observación real cuyo `review_status` no sea `reviewed`, manteniendo compatibilidad con históricos que quedaron sin estado útil.
- La vista standalone actualiza tabs y resumen: **Pendientes** pasa a alerta amarilla más visible, **Vencidas** refuerza el rojo y ambas muestran correctamente los informes pendientes vencidos con badge en la fila.
- El gestor admin agrega filtro **No revisado**, alinea el listado con la misma lógica efectiva de revisión y muestra mejor los estados heredados con observación real.

## [1.9.3] - 2026-03-24
- Se redefine la bandeja para que **Pendientes** muestre solo informes con observaciones reales y `review_status = pending_review`, mientras **Resueltas** mantiene los revisados y se agrega el nuevo tab/filtro **Vencidos** para pendientes fuera de plazo.
- La vista standalone incorpora conteo/tab de vencidos, badge visual para observaciones vencidas y una acción inline para anexar observaciones en informes revisados sin reabrirlos.
- El gestor admin agrega filtro de vencidos y acción para anexar observaciones a informes revisados desde el detalle, manteniendo trazabilidad con fecha, hora y usuario.

## [1.9.2] - 2026-03-24
- Se normalizan observaciones al guardar/importar: `NULL`, `N/A`, `NA`, `-` y `SIN OBSERVACIONES` pasan a vacío.
- `review_status` se calcula desde la observación normalizada para evitar pendientes falsos en nuevos registros e importaciones nuevas.
- El gestor admin, la vista standalone y las notificaciones reutilizan la misma lógica de placeholders para mantener filtros y conteos consistentes sin tocar históricos.

## [1.9.1] - 2026-03-16

### Added
- Se agregan acciones manuales en Ajustes para enviar de inmediato un resumen diario/semanal y para probar el correo de vencimiento sin esperar al cron.
- La prueba de vencimiento usa una observación real vencida cuando existe; si no, envía un ejemplo controlado para validar asunto, cuerpo y formato.

### Changed
- El envío manual de resúmenes puede ejecutarse aunque el resumen automático esté desactivado, manteniendo separado el uso manual del cron.

## [1.9.0] - 2026-03-16

### Added
- Se agrega módulo de notificaciones automáticas por correo solo para admin con aviso diario por observaciones pendientes vencidas y resúmenes automáticos diario/semanal.
- Se agrega configuración en Ajustes para correo admin, activación de avisos, plantillas editables, umbral de vencimiento y rango global de visualización de observaciones.
- Se añade trazabilidad `overdue_notified_at` para evitar reenvíos múltiples el mismo día por una misma observación vencida.

### Changed
- El gestor admin de informes con observaciones y la vista standalone ahora respetan un rango global de “últimos X días” basado en `created_at`.
- La exportación desde la vista filtrada de observaciones queda alineada con el rango global configurado, porque opera sobre la bandeja ya acotada.

## [1.8.5] - 2026-03-15

### Changed
- La vista standalone de observaciones adopta una UI tipo panel, alineada al mockup de referencia: cabecera con KPIs, CTA superior, bloque de filtros más limpio, tabs por estado y tabla con badges/acciones más claras.
- Se agrega paginación real en frontend para el gestor de observaciones, manteniendo filtros por búsqueda y estado entre páginas.
- Los contadores Total/Pendientes/Resueltas ahora se calculan sobre la búsqueda activa para reflejar mejor el estado real de la bandeja.

## [1.8.3] - 2026-03-13

### Changed
- Mejora visual del gestor frontend de observaciones: se agrega contenedor con scroll horizontal accesible para la tabla, cabecera sticky y anchos mínimos por columna para mantener legibilidad en pantallas pequeñas.
- Se ajusta el estilo de filtros y contenedor para una experiencia responsive más consistente en móvil y escritorio, sin cambios funcionales en acciones ni datos.

## [1.8.2] - 2026-03-13

### Fixed
- El gestor frontend de observaciones excluye registros con `observaciones` vacías y también valores legacy `'NULL'` (incluyendo variantes con espacios/case).
- En la tabla frontend, cuando una observación viene con marcador legacy `'NULL'`, se renderiza como vacío (`—`) en lugar de mostrar el texto literal.

## [1.8.1] - 2026-03-13

### Changed
- En el gestor frontend de observaciones se ajusta el orden para priorizar `legacy_id` de mayor a menor (con fallback por `id` descendente cuando no existe `legacy_id`).
- Se agrega acción `Ver PDF` por fila en el frontend, abriendo en nueva pestaña con URL nonceada usando el mismo mecanismo de previsualización ya existente.

## [1.8.0] - 2026-03-13

### Added
- Se agrega gestor de observaciones en frontend standalone sin theme bajo slug `/post-venta-observaciones/`.
- El gestor frontend permite filtrar por búsqueda/estado de revisión y marcar informes como revisados manteniendo trazabilidad (`review_status`, `reviewed_by`, `reviewed_at`).

### Changed
- Se añade atajo en administración para abrir el gestor frontend de observaciones directamente desde los gestores internos.

## [1.7.0] - 2026-03-13

### Added
- Se agrega trazabilidad de revisión en informes: `reviewed_by` (ID de usuario) y `reviewed_at` (fecha/hora).
- El gestor de observaciones muestra `Revisado por` y `Fecha revisión` para seguimiento administrativo.

### Changed
- La acción `Marcar revisado` ahora registra además del estado `reviewed` el usuario autenticado que ejecuta la revisión y el timestamp de la operación.

## [1.6.0] - 2026-03-13

### Added
- Se agrega un gestor independiente en admin para informes con observaciones (`Informe Técnico > Con observaciones`) con listado dedicado.
- En el gestor de observaciones se incorpora acción por fila `Marcar revisado` para actualizar `review_status` a `reviewed` con `manage_options` + nonce por informe.

### Changed
- El gestor independiente filtra exclusivamente informes con `Observaciones` no vacías (trim) y permite filtrar por estado de revisión (`pending_review` / `reviewed`).
- Las acciones por fila y redirecciones de notices preservan el contexto del gestor desde el que se ejecutan (`general` u `observaciones`).

## [1.5.0] - 2026-03-13

### Added
- Se agrega estado de revisión interna por informe (`review_status`) con soporte de valores `not_required`, `pending_review` y `reviewed`.

### Changed
- Al guardar envíos nuevos, el informe queda automáticamente en `pending_review` cuando `Observaciones` contiene texto no vacío (trim); si no contiene, se guarda como `not_required`.

## [1.4.89] - 2026-03-06

### Changed
- Se estandariza el formato de fechas en PDF a `DD/MM/AAAA` para cabecera y campos de servicio, incluyendo normalización de entradas legacy en `MM/DD/AAAA`.

## [1.4.88] - 2026-03-03

### Changed
- Se ajusta el formato del nombre de PDF para separar elementos con guion bajo (`_`) en lugar de `+` (`IT_ID_MODELO_SERIE_o_INTERNO_CLIENTE.pdf`).
- Se actualiza el saneamiento del nombre de archivo para el nuevo esquema con `_` y fallback `IT_ID.pdf`.

## [1.4.87] - 2026-03-03

### Fixed
- Se refuerza el guardado de técnicos en Ajustes usando una normalización centralizada para evitar diferencias entre lectura/escritura de la opción.
- Si la lista enviada queda vacía tras sanitización, se elimina la opción para volver automáticamente al listado por defecto.

### Changed
- Se agrega límite defensivo en la gestión de técnicos (máximo 100 entradas y 80 caracteres por nombre).

## [1.4.86] - 2026-03-03

### Added
- Se agrega gestión de técnicos en Admin > Ajustes, con guardado seguro (`manage_options` + `nonce`) y soporte de lista editable (uno por línea).
- El selector `Técnico` del formulario standalone ahora carga dinámicamente desde la configuración administrable.

### Changed
- Se centraliza la lista por defecto de técnicos en el plugin para usarla como fallback cuando no hay configuración guardada.

## [1.4.85] - 2026-03-03

### Fixed
- Se corrige el formato del nombre de PDF para emitir explícitamente `IT+ID+MODELO+SERIE_o_INTERNO+CLIENTE.pdf`.
- Se elimina la forzada de minúsculas en los segmentos del nombre y se conserva el prefijo `IT` en mayúsculas.

### Changed
- Se agrega saneamiento específico de nombre de archivo PDF que preserva `+` y elimina caracteres no válidos, manteniendo compatibilidad con sistemas de archivos.

## [1.4.84] - 2026-03-03

### Changed
- Se actualiza el nombre de archivo PDF para usar el patrón `it-IT-MODELO-SERIE_o_INTERNO-CLIENTE.pdf`, priorizando `Serie` y usando `N° Interno` como fallback cuando no hay serie.
- Se normalizan segmentos del nombre de archivo (sin acentos, minúsculas, solo alfanumérico con guiones) y se recortan longitudes para mantener compatibilidad en sistemas de archivos.

## [1.4.83] - 2026-03-02

### Fixed
- Se corrige layout del PDF para evitar superposición en filas de `Equipo` (incluyendo `Fecha servicio`) usando renderizado vertical seguro de filas label/valor.
- Se ajusta el flujo de paginación previo a `Firmas` para minimizar saltos donde la sección quedaba sola en página siguiente.

### Changed
- En PDF ya no se muestra el placeholder `No informado` en campos generales: los campos vacíos se ocultan.
- Excepción explícita: `Correo` mantiene `No informado` cuando no existe dato.
- `Servicio` y `Detalle` ahora renderizan solo campos con contenido visible, conservando orden y wrapping sin romper cajas.

## [1.4.82] - 2026-03-02

### Added
- Se agrega exportador CSV compatible con el importador legacy del plugin (`agp_pv_export_legacy_csv`) disponible en Ajustes.
- La tabla de informes mantiene exportación masiva, ahora emitiendo formato compatible con el importador (mismos encabezados y estructura esperada).

### Security
- El exportador protege acceso con `manage_options` + `nonce` y aplica mitigación de CSV Injection para celdas que comienzan con `=`, `+`, `-`, `@`.

### Changed
- Se normaliza el mapeo de `tipo_servicio`/`tipo_mantencion` al exportar para priorizar labels y usar fallback legible cuando falta el label persistido.

## [1.4.81] - 2026-03-01

### Fixed
- Se normaliza la plantilla PDF de Informe Técnico para evitar solapes en la sección Servicio usando layout estable de una sola columna para labels/valores.
- Se unifica el render de fechas a formato `dd-mm-YYYY` y se diferencia explícitamente `Fecha servicio` en Equipo y `Fecha reparación`/`Fecha cierre` en Servicio.
- Se estandariza `N° Interno` para usar placeholder único `No informado` cuando llega vacío o con rellenos inválidos (`0`, `0000`, etc.).

### Changed
- Se homogeneiza capitalización de campos de entidad (`Máquina`, `Cliente`, `Faena / Lugar`) para evitar salidas en minúsculas accidentales.
- La sección `Detalle` ahora siempre renderiza el set completo (`Lubricantes`, `Filtros Utilizados`, `Componentes Utilizados`, `Trabajos Realizados`, `Observaciones`) con regla consistente para vacíos (`No informado`).
- Se normaliza formato de insumos con cantidad al estilo `Nombre (xN)` cuando el contenido permite inferir cantidad.

### UX
- En firmas se mantiene render unificado: imagen centrada si existe y fallback `Firma no disponible` centrado con estilo consistente cuando no existe.

## [1.4.80] - 2026-02-27

### UX
- Se agrega aviso visual en frontend cuando el Service Worker detecta una versión nueva del formulario, con acción directa “Actualizar ahora”.

### Changed
- El registro del Service Worker ahora observa `updatefound`/`statechange` y recarga automáticamente tras `controllerchange` para aplicar la nueva versión de forma controlada.

### Security
- Se incorpora listener `postMessage` (`SKIP_WAITING`) en el Service Worker para actualizar de forma explícita sin relajar las reglas de cache existentes.

## [1.4.79] - 2026-02-27

### Changed
- Se endurece el Service Worker con parámetros dinámicos (`ver`, `shell`, `assetPrefix`) para mejorar invalidación por versión y compatibilidad en instalaciones WordPress fuera de rutas estándar.
- El frontend ahora registra el Service Worker con telemetría ligera de resultado (`service_worker_registered` / `service_worker_register_failed`) en el canal de observabilidad existente.

### Security
- El Service Worker excluye explícitamente solicitudes del manifest dinámico (`agp_pv_manifest`) del cache para evitar persistencia accidental de metadatos de instalación.

## [1.4.78] - 2026-02-27

### Added
- Se agrega Service Worker del formulario standalone para cachear el shell del frontend (`/post-venta/`, CSS/JS del plugin) y mejorar resiliencia de carga.

### Security
- El Service Worker excluye endpoints sensibles (`admin-ajax.php`, `wp-admin`, `wp-login.php`) y cualquier request no `GET`, evitando cache accidental de envíos o rutas administrativas.

### Changed
- El frontend standalone registra el Service Worker solo en contexto seguro (`https`/localhost) usando URL y scope localizados desde backend.

## [1.4.77] - 2026-02-27

### Changed
- El manifest PWA pasa a generarse dinámicamente desde WordPress (`?agp_pv_manifest=1`) para evitar depender de archivos estáticos en el repositorio.
- Se eliminan del plugin los archivos estáticos `manifest.webmanifest` e íconos SVG; ahora los iconos de app se configuran desde Ajustes usando la biblioteca de medios.
- La pantalla de Ajustes incorpora selectores para ícono 192x192 y 512x512, guardados como opciones y usados por el manifest dinámico.

## [1.4.76] - 2026-02-27

### Changed
- Se retira la etiqueta `apple-touch-icon` del standalone para evitar una referencia SVG no soportada de forma consistente por iOS en contexto de iconos de inicio.
- Se mantiene el flujo PWA sin binarios (manifest + iconos SVG) para navegadores compatibles con instalación web app.

## [1.4.75] - 2026-02-27

### Changed
- Se reemplazan los íconos binarios (`.png`) del flujo tipo app por íconos SVG de texto plano para compatibilidad con repositorios que no aceptan binarios.
- El manifest ahora referencia `image/svg+xml` para los iconos de instalación (`192x192` y `512x512`).
- El `apple-touch-icon` del standalone también apunta a SVG para mantener consistencia sin archivos binarios.

## [1.4.74] - 2026-02-27

### Added
- Se incorpora `manifest.webmanifest` para habilitar instalación tipo app del formulario standalone con `display: standalone`, `start_url` y `scope` en `/post-venta/`.
- Se agregan íconos de aplicación (`192x192` y `512x512`) para instalación en Android/desktop y soporte `apple-touch-icon`.

### Changed
- El template standalone ahora publica `<link rel="manifest">` y `theme-color` para completar el flujo de instalación iniciado en la tarea anterior.

## [1.4.73] - 2026-02-27

### UX
- Se agrega un bloque visible en el frontend standalone para crear un acceso directo del formulario tipo app, con CTA dedicado cuando el navegador expone instalación.
- Se muestran instrucciones contextuales para instalación manual en iOS/otros navegadores cuando no existe prompt nativo de instalación.

### Changed
- El frontend detecta modo app instalado (`display-mode: standalone`) y ajusta el mensaje para evitar mostrar acciones redundantes.

## [1.4.72] - 2026-02-26

### UX
- Se mejora la pantalla final de confirmación (paso 5) con microcopy contextual según resultado (`success` o `warning`) y acción directa para copiar el ID del informe.
- El botón `Iniciar nuevo envío` ahora limpia metadatos de confirmación (ID/meta) antes de regresar al paso 1.

### Accessibility
- La confirmación final mantiene feedback textual persistente y reutiliza el canal de estado global para informar éxito/error de copiado de ID.

## [1.4.71] - 2026-02-26

### Fixed
- Se unifica el estado post-envío en mobile/desktop: tras submit exitoso el formulario navega al nuevo paso 5 (`Enviado`) en vez de quedar en pasos editables (1 o 4 según dispositivo).
- Se evita que eventos `change` internos del reset post-submit limpien accidentalmente el estado terminal del stepper durante la transición.

### UX
- Se agrega vista dedicada de confirmación (`Envío completado`) con mensaje de resultado y acción explícita para iniciar un nuevo envío.

## [1.4.70] - 2026-02-26

### Fixed
- Se excluye del rate limit a usuarios autenticados en `agp_pv_submit` para evitar bloqueos falsos positivos en operación interna (opción A), manteniendo la protección para tráfico anónimo.

### Changed
- El evento `request_started` en debug agrega `rate_limit_exempt` para diagnosticar rápidamente si la solicitud quedó fuera del throttling.

## [1.4.69] - 2026-02-26

### Added
- Se agrega logging técnico server-side para `agp_pv_submit` en `debug.log` (cuando `WP_DEBUG`/`agp_pv_debug` y `WP_DEBUG_LOG` están activos) cubriendo inicio de request, rechazos, fallas de validación y resultados de envío (`success`, `partial_mail`, `partial_pdf`).

### Changed
- El payload de logs del submit se mantiene sin datos sensibles: solo metadatos técnicos (conteo/campos con error, tipo de evento e IDs de envío/informe).

## [1.4.68] - 2026-02-26

### Fixed
- Tras envío exitoso en frontend, el flujo ya no regresa al paso 1 del formulario; se mantiene en el contexto final para evitar confusión en móvil.

### UX
- Cuando existe estado terminal `Enviado`, los pasos 1–4 del stepper quedan marcados como completados y solo el paso final conserva `aria-current="step"`.

## [1.4.67] - 2026-02-26

### UX
- Se agrega un quinto indicador del stepper (`Enviado`) para representar el estado final tras un envío exitoso del formulario.
- El indicador final distingue éxito completo y éxito parcial (`correo/PDF`) con estilo visual específico para advertencias.

### Accessibility
- El estado final del stepper ahora actualiza `aria-label` con contexto (`enviado` / `enviado con advertencia`) cuando la respuesta AJAX confirma guardado.

## [1.4.66] - 2026-02-26

### UX
- El stepper ahora muestra estado de avance por paso basado en borrador/formulario (`vacío`, `en progreso`, `completo`) mediante clases y metadatos por indicador.

### Changed
- Se recalcula el estado de avance del stepper en interacción de campos, cambios de paso, cambios condicionales y eventos de persistencia de borrador (guardar, limpiar, restaurar).
- Se añaden etiquetas accesibles (`aria-label`) por indicador con el estado del paso para reforzar contexto en navegación asistida.

## [1.4.65] - 2026-02-26

### Changed
- Se incorpora validación contextual en `Detalle`: al menos uno entre `Trabajos realizados` u `Observaciones` debe incluir contenido mínimo útil (10 caracteres).
- La regla se aplica tanto en frontend como backend para evitar bypass y mantener consistencia del flujo.

### UX
- En errores server-side, el formulario vuelve automáticamente al paso del primer campo inválido antes de enfocar, mejorando corrección guiada en stepper.

## [1.4.64] - 2026-02-26

### Fixed
- Se corrige el cálculo de errores por paso en el stepper para que no dependa de `:hidden` del paso inactivo. Los campos requeridos de pasos no activos vuelven a contabilizarse y marcan `is-error`/`data-has-errors` cuando corresponde.

### Changed
- En la detección de errores por paso se excluyen solo controles realmente no aplicables (`disabled`, `type=hidden`, `[hidden]` o dentro de contenedores condicionales ocultos), manteniendo metadatos por paso consistentes en flujos como restauración de borrador.

## [1.4.63] - 2026-02-26

### UX
- Se agrega microcopy breve en cada textarea de la sección `Detalle` para guiar qué tipo de información ingresar en terreno.

### Changed
- Se reutiliza el sistema existente de contadores por `maxlength` en `Detalle`: `Trabajos realizados` ahora muestra contador en formato `usados/máximo` usando los mismos atributos `data-*` y clases del frontend actual.
- No se agregan límites nuevos: solo se muestra contador donde ya existe `maxlength`.

## [1.4.62] - 2026-02-26

### Accessibility
- El stepper del formulario ahora marca errores por paso con clase `is-error` y metadatos accesibles (`aria-invalid`/`data-has-errors`) cuando existen campos requeridos visibles inválidos.
- Se mantiene `aria-current="step"` en el indicador activo para mejorar contexto de navegación por tecnologías asistivas.

### Changed
- Se recalcula el estado de error del stepper en cambios de paso, eventos de interacción (`input`/`change`/`blur`) y al alternar condicionales visibles/ocultos, excluyendo controles ocultos o deshabilitados.

## [1.4.61] - 2026-02-26

### Security
- Se endurece el frontend de observabilidad y conectividad con payload mínimo versionado (`eventVersion`) en eventos `agpPv:*`, manteniendo datos no sensibles.

### Changed
- Se agrega fallback compatible para `CustomEvent` en navegadores legacy y guardas defensivas para acceso a `agpPvData`.
- Se evita doble envío concurrente (`in-flight guard`) deshabilitando submit/retry durante requests activos y bloqueando reintentos simultáneos.
- Se robustecen pendientes offline con validación de JSON + expiración automática (TTL 24h) para evitar estados pendientes corruptos o fantasma.

## [1.4.60] - 2026-02-26

### i18n
- Se completa el cierre de internacionalización del frontend standalone, eliminando textos visibles hardcodeados en opciones de `Tipo de Servicio` y `Tipo de Mantención`, además del label del botón de envío.
- Se agrega mensaje localizado `draftCleared` para evitar string fijo en JS al limpiar borrador local.

### Changed
- Se corrigen acentos en labels frontend traducibles (por ejemplo `Garantía`, `Diagnóstico Técnico`) manteniendo valores internos de opción para compatibilidad.

## [1.4.59] - 2026-02-26

### Added
- Se agrega observabilidad ligera en frontend mediante eventos `CustomEvent` (`agpPv:*`) para instrumentar estados clave: red, envío, validación, errores server y reintentos.
- Se habilita logging técnico opcional en consola bajo `WP_DEBUG` (`debugFrontend`) para soporte en terreno sin introducir telemetría externa.

### Changed
- El flujo de submit/reintento emite eventos estructurados sin incluir payload sensible (sin firmas/base64/adjuntos), facilitando diagnóstico no intrusivo.

## [1.4.58] - 2026-02-26

### Added
- Se agrega estado de conectividad en frontend (`online/offline`) para el formulario standalone, con mensaje persistente y feedback en tiempo real al recuperar red.
- Se incorpora botón `Reintentar envío pendiente` cuando hubo intento sin conexión o error de red sin respuesta.

### Changed
- El submit frontend ahora detecta modo offline antes de enviar y marca un estado local de envío pendiente para reintento manual.
- En fallos de red (`xhr.status=0`) se conserva estado pendiente y se muestra guía de reintento, evitando perder el trabajo capturado en terreno.

## [1.4.57] - 2026-02-26

### Changed
- Se endurecen atributos HTML del formulario standalone para reducir errores de captura en terreno: `autocomplete`, `autocapitalize`, `spellcheck` e `inputmode` en campos críticos.
- Se agrega un patrón suave en `N° Interno` para guiar formato válido (letras, números, guion, slash, guion bajo y espacios) sin sustituir la validación server-side.
- Se unifica tope de `max=999999` en `Cantidad de Horas` para consistencia con el campo `Horas`.

### UX
- Se mejora el teclado contextual en móvil para campos email/número y se reduce autocorrección no deseada en identificadores de máquina/modelo/serie.

## [1.4.56] - 2026-02-26

### Performance
- Se aplica inicialización diferida (lazy-init) en frontend para módulos pesados: `Firmas` (canvas) y `Adjuntos/Fotos`, cargándolos solo al entrar por primera vez a sus pasos.
- Se reduce el trabajo en la carga inicial del formulario standalone al evitar registrar listeners de firmas/fotos antes de que el usuario los necesite.

### Changed
- El bootstrap del formulario ahora inicializa `initStepper()` primero y delega la activación de `initSignatures()`/`initPhotos()` al evento `agpPvStepChanged`.

## [1.4.55] - 2026-02-26

### Changed
- Se agrega un resumen global de errores accesible en el formulario standalone, con enlaces por campo para navegación rápida al primer problema detectado.
- La validación por paso y la validación final de envío ahora muestran errores consolidados en un bloque superior para reducir fricción en móvil.

### Accessibility
- El resumen de errores incorpora `role="alert"` y `aria-live` para anunciar incidencias a tecnologías asistivas, manteniendo foco navegable hacia cada input.

## [1.4.54] - 2026-02-24

### Changed
- Se mejora legibilidad base del PDF con umbrales mínimos de tipografía/interlineado en modo compacto para evitar texto excesivamente pequeño.
- Se incrementa contraste de placeholders en filas (`row2`/`row4`) usando un gris más oscuro para facilitar lectura e impresión.

### Accessibility
- Se añaden tokens internos de legibilidad (fuente mínima, altura de línea mínima, gris de placeholders) para mantener consistencia visual en cabeceras y bloques de contenido.

### Testing
- Se amplía la regresión CLI con una aserción específica de altura mínima de fila en `compact_density`.

## [1.4.53] - 2026-02-24

### i18n
- Se internacionalizan los mapas de etiquetas de `tipo_servicio` y `tipo_mantencion` en backend (`AJAX` y render PDF), eliminando strings hardcodeados no traducibles.
- Se corrige consistencia ortográfica/accentuada en labels de servicio (por ejemplo: `Garantía`, `Diagnóstico Técnico`) para salida unificada.

## [1.4.52] - 2026-02-24

### Added
- Se agrega configuración de branding PDF en ajustes admin para título de cabecera, plantilla de etiqueta IT y texto de pie de página.

### Changed
- El render de cabecera/pie del PDF ahora usa branding configurable con fallback seguro a valores por defecto.
- Se aplican límites de longitud y saneamiento de campos de branding para evitar desbordes de layout y mantener compatibilidad.

### Testing
- Se extiende la regresión CLI con aserciones del branding por defecto para validar compatibilidad de configuración.

## [1.4.51] - 2026-02-24

### Security
- Se endurece la previsualización admin de PDF validando permisos, nonce, método HTTP permitido (`GET/HEAD`) y PDF realmente válido antes de servirlo.
- Se agregan cabeceras defensivas de respuesta para preview (`nosniff`, `no-store`, `no-cache`, `Accept-Ranges: none`).

### Changed
- La entrega de preview PDF ahora usa streaming directo (`readfile`) y soporta solicitudes `HEAD` para mejorar robustez y reducir uso de memoria.
- Se incorpora logging técnico de eventos de denegación/fallo en preview bajo modo debug del plugin.

## [1.4.50] - 2026-02-24

### Security
- Se endurece el manejo de adjuntos PDF: validación defensiva de ruta/lectura, estructura PDF y MIME antes de adjuntar en correo.

### Changed
- Se estandariza el naming de PDF generado con helper dedicado (`it-{report_id}-{yyyymmdd}.pdf`) para mejorar consistencia operativa y compatibilidad de adjuntos.
- Cuando el PDF existe pero falla validación de adjunto, el correo se envía sin archivo y retorna warning explícito.

### Testing
- Se amplía la regresión CLI para cubrir validación de adjunto PDF en casos de archivo inexistente y PDF válido local.

## [1.4.49] - 2026-02-24

### Changed
- Se centralizan los umbrales del preflight de layout PDF en `get_layout_thresholds()` y se habilita ajuste externo con el filtro `agp_pv_pdf_layout_thresholds`.
- El preflight de `Observaciones + Firmas` ahora consume umbrales configurables (`observaciones_short_max_chars`, `signatures_card_min_height`, `preflight_extra_padding_mm`) y registra los thresholds efectivos en logging estructurado.

### Testing
- Se amplía la regresión CLI para validar que los thresholds por defecto existen y que el filtro permite sobrescribirlos de forma determinística.

## [1.4.48] - 2026-02-24

### Changed
- Se amplía la regresión CLI de PDF con escenarios de estrés para estimadores de altura (`adaptive`, `row2`, `box`) y validaciones de compactación/firma, mejorando cobertura de overflow y layout final.
- El script ahora verifica más placeholders canónicos y agrega una prueba de render de observaciones extensas + bloque de firmas para detectar fallos no fatales en composición de página.

### Testing
- Se fortalece el script `scripts/pdf-layout-regression.php` con aserciones adicionales y mensajes de fallo más específicos para diagnóstico rápido.

## [1.4.47] - 2026-02-24

### Added
- Se agregan métricas persistentes de generación PDF por informe (`pdf_generated_ms`, `pdf_size_bytes`, `pdf_page_count`, `pdf_warnings`) para mejorar trazabilidad operativa y diagnóstico.

### Changed
- La generación/regeneración de PDF ahora persiste telemetría de ejecución (tiempo, tamaño, páginas y warnings no fatales) tanto en éxito como en escenarios de fallo.
- El listado y detalle admin incorporan una vista resumida de métricas PDF para soporte técnico sin revisar logs del servidor.

## [1.4.46] - 2026-02-24

### Changed
- Se optimiza la regeneración masiva de PDF por lotes con presupuesto temporal por request (time-slicing), evitando que una sola llamada AJAX procese demasiados informes y reduzca la estabilidad en hostings lentos.
- El endpoint batch ahora retorna telemetría de ejecución (`elapsed_ms`, `next_limit`) para ajustar dinámicamente el tamaño del siguiente lote.
- El cliente admin de regeneración masiva adapta automáticamente el tamaño de lote dentro de límites configurables (`batchMin`/`batchMax`) para mejorar throughput y reducir timeouts.

## [1.4.45] - 2026-02-24

### Changed
- Se mejora i18n/compatibilidad del frontend standalone moviendo mensajes hardcodeados JS (estado, borrador, límites de fotos y contadores de longitud) a `wp_localize_script` para permitir traducción consistente por dominio del plugin.
- Se agrega helper de formateo en JS para plantillas localizables con placeholders (`%s`, `%d`, `%1$d`, `%2$d`) sin perder fallback.

### i18n
- Se internacionaliza la etiqueta de cabecera PDF `IT: %d` usando funciones de traducción del plugin para mantener consistencia con el resto del documento.

## [1.4.44] - 2026-02-24

### Changed
- Se agregan límites de longitud y guías visuales en frontend para campos críticos de cabecera (`Cliente`, `Faena`, `Máquina`, `Modelo`, `Serie`, `N° Interno`) con contador de caracteres restantes para prevenir desbordes en PDF.
- Se incorpora feedback visual cuando un campo se acerca al límite de caracteres, mejorando la captura en móvil y escritorio.

### Security
- Se añade validación defensiva de longitudes en backend para los mismos campos, evitando que payloads fuera de rango salten las restricciones del frontend.

## [1.4.43] - 2026-02-24

### Security
- Se endurecen acciones admin sensibles con nonce atado al `submission_id` para `Reintentar correo` y `Regenerar PDF`, reduciendo riesgo de reutilización cruzada de enlaces.
- Se mantiene compatibilidad retroactiva aceptando nonce legacy durante transición de enlaces existentes.
- Se exige método HTTP `POST` en endpoints AJAX críticos (`agp_pv_submit` y regeneración batch de PDFs) devolviendo `405` en métodos inválidos.

### Changed
- Se agrega helper interno de validación de nonce por envío (`verify_submission_action_nonce`) y validación centralizada de método HTTP para rutas AJAX en admin.

## [1.4.42] - 2026-02-24

### Added
- Se agrega logging estructurado de decisiones de layout PDF (`intro_layout`, `service_visibility`, `observaciones_preflight`) con contexto técnico (`submission_id`, modo aplicado, espacio disponible y umbrales de preflight).
- Nuevo switch de observabilidad vía opción `agp_pv_pdf_layout_debug` (además de `WP_DEBUG` / `agp_pv_debug`) para habilitar trazas sin tocar lógica de render.

### Changed
- Se centraliza la puerta de logging en `is_layout_logging_enabled()` para mantener consistencia entre logs de layout y logs generales del generador PDF.

## [1.4.41] - 2026-02-24

### Added
- Se agrega script CLI de regresión `scripts/pdf-layout-regression.php` con fixtures para validar el comportamiento del layout 2-up (`Datos Generales` + `Equipo`) y su fallback a apilado cuando el contenido es extenso.
- El script incorpora aserciones de semántica para placeholders de dato ausente (`No informado`, `SIN INFORMACIÓN`, `n/a`) para detectar regresiones tempranas en la lógica de render condicional del PDF.

### Changed
- Se formaliza una comprobación mínima repetible de layout PDF para reducir riesgo de regresiones entre iteraciones de compactación/paginación.

## [1.4.40] - 2026-02-24

### Changed
- Se unifica la semántica de “dato ausente” en el motor PDF con un helper central (`is_missing_display_value`) y un set canónico de placeholders (por ejemplo: `No informado`, `sin información`, `N/A`, `null`, `-`).
- Se normaliza la detección de placeholders en `row2`, `row4`, layout 2-up y `adaptive_text`, evitando comparaciones ad-hoc y mejorando consistencia visual (gris/omisión) en todas las secciones.
- `normalize_pdf_value` ahora usa la misma semántica central para decidir fallback, reduciendo discrepancias entre render y normalización de valores.

## [1.4.39] - 2026-02-24

### Changed
- Se aplica render condicional en la tarjeta `Servicio`: campos vacíos o con valor `No informado` ya no reservan filas, y cuando todos están ausentes se resume en una sola línea compacta en gris.
- Se incorpora layout adaptativo 2-up para cabecera de contenido: `Datos Generales` y `Equipo` se muestran en dos cards en la misma fila cuando el contenido es corto; si crece (p. ej. nombres/series largas), se usa fallback automático al layout apilado anterior.
- Se agrega estimación de altura para el bloque 2-up con control de overflow para mantener estabilidad de paginación y evitar solapes en casos de contenido extenso.

## [1.4.38] - 2026-02-24

### Fixed
- Se corrige un fatal error en generación/regeneración de PDF cuando el preflight recibía valores `null` en `Observaciones` (o campos análogos), robusteciendo los estimadores para normalizar entradas a string antes de codificar/renderizar.
- Se añade hardening en funciones de estimación de altura del layout (`estimate_adaptive_text_height`, `estimate_row2_value_height`, `estimate_box_text_height`) para manejar datos vacíos o nulos sin romper la generación.

## [1.4.37] - 2026-02-24

### Fixed
- Se recalibra el preflight determinístico de `Observaciones + Firmas` para estimar de forma más fiel alturas en modo fila/caja y decidir compactación progresiva antes de cortar página.
- Se afina el modo global `compact_density` y los límites de compactación de `Firmas` (incluyendo caso sin imágenes) para reducir mejor el desperdicio vertical al cierre del informe.
- Se ajusta la sección `Servicio` para mantener grilla consistente y compacta sin redundancias visuales que inflen altura innecesaria.

## [1.4.36] - 2026-02-24

### Changed
- Se incorpora un modo global de densidad compacta del PDF (paddings, gaps, line-height y spacing de cierre) que se activa solo cuando el preflight detecta riesgo de salto innecesario antes del cierre `Observaciones + Firmas`.
- Se agrega preflight determinístico para estimar `Observaciones` (adaptativa) + cierre de card + `Firmas` (normal/compacta), activando compactación progresiva y dejando el salto de página como último recurso.
- La sección `Servicio` ahora se renderiza en grilla 2 columnas (`row4`) de forma consistente, manteniendo campos críticos visibles y mostrando placeholders de forma más compacta/atenuada.
- Se optimiza `Firmas` cuando no hay imágenes disponibles (solo `Firma no disponible`) reduciendo altura de cajas y `tail gap` para evitar desperdicio vertical.

### Fixed
- Se mejora el objetivo de 1 página agresiva sin incoherencias visuales en escenarios de contenido variable, especialmente en el tramo final del informe técnico.

## [1.4.35] - 2026-02-24

### Changed
- Se optimiza el bloque `Detalle` para renderizar `Lubricantes` y `Filtros Utilizados` en layout de 2 columnas (50/50) cuando ambas listas son cortas/medias; si alguna crece demasiado, el PDF vuelve automáticamente a columna única.
- Se agrega lógica de compactación coordinada entre `Observaciones` (cuando es corta) y `Firmas` para mejorar la probabilidad de mantener ambas secciones en la misma página antes de forzar salto.

### Fixed
- Se mejora el aprovechamiento vertical del informe en escenarios de contenido variable, reduciendo saltos innecesarios y blancos residuales hacia el cierre del PDF.

## [1.4.34] - 2026-02-24

### Fixed
- Se ajusta la paginación de `Firmas` con modo compacto dinámico cuando queda poco espacio al final de página, evitando más casos donde esta sección quedaba aislada en una página nueva.
- Se reduce la reserva mínima de la tarjeta `Firmas` y se adapta el espaciado de cierre para aprovechar mejor el espacio vertical disponible sin romper el layout tipo tarjeta.
- Se agrega cálculo explícito de espacio restante en página para decidir compactación y reducir saltos innecesarios en escenarios de contenido variable.

## [1.4.33] - 2026-02-24

### Fixed
- Se corrige la estabilidad de paginación del layout PDF tipo tarjeta reduciendo alturas mínimas conservadoras que provocaban saltos innecesarios y grandes áreas en blanco.
- Se ajusta la lógica de apertura/cierre de tarjetas para ocupar mejor el espacio vertical disponible y evitar que bloques cortos queden aislados en una página nueva.
- Se compacta el bloque de firmas (altura de cajas y espaciados) para disminuir casos donde `Firmas` quedaba sola en la página 2 con contenido normal.

## [1.4.32] - 2026-02-24

### Changed
- Se rediseña la UI del PDF al estilo tarjeta (dashboard) con contenedores redondeados por sección, separadores internos y mayor jerarquía visual en cabecera.
- El bloque de encabezado ahora muestra `IT` y fecha dentro de una caja redondeada, alineada con el título para un look más limpio y consistente.
- Se reorganiza el flujo de contenido en tarjetas para `Datos Generales`, `Equipo`, `Servicio`, `Detalle` y `Firmas`, mejorando lectura y escaneo del informe impreso.

## [1.4.31] - 2026-02-24

### Changed
- Se aplica un refresh visual del PDF con estilo más aireado/premium: mayor jerarquía tipográfica en encabezado, mejor separación entre secciones y ritmo vertical más cómodo para lectura impresa.
- Se ajustan filas compactas (`row2`/`row4`) y bloques narrativos con nuevos line-height, padding y espaciados para mejorar legibilidad sin perder contenido.
- Se mejora la presentación del bloque de firmas (tamaño de caja, etiquetas y alineación) para un cierre visual más limpio del informe técnico.

## [1.4.30] - 2026-02-24

### Changed
- Se elimina el truncado automático con sufijo `... (continúa)` en bloques de detalle del PDF para mostrar mensajes completos.
- Se mantiene la normalización de saltos y espacios en textos largos, pero sin recortar contenido de trabajos/observaciones/componentes/lubricantes/filtros.

## [1.4.29] - 2026-02-24

### Fixed
- Se corrige la captura de firmas en el formulario cuando el paso de Firmas aún no estaba visible al inicializar el canvas, evitando lienzos colapsados y pérdida de trazo.
- Se mejora el parseo server-side del `data:image/png;base64` de firmas para preservar correctamente el payload y evitar fallas al guardar adjuntos.

## [1.4.28] - 2026-02-23

### Fixed
- Se unifica el ID visible del informe en todos los puntos clave: listado, correo y PDF, priorizando `legacy_id` y usando `id` interno solo como fallback.
- El encabezado del PDF (`IT:`) y el nombre del archivo PDF ahora usan el ID visible (legacy/secuencia importada) para mantener consistencia operativa.

## [1.4.27] - 2026-02-23

### Fixed
- El informe ahora usa como ID visible la secuencia de `legacy_id` cuando existen formularios importados, manteniendo continuidad con el último número importado.
- Los nuevos envíos continúan desde el último ID importado (solo base importada), evitando mezclar la secuencia con IDs internos autoincrementales locales.

## [1.4.26] - 2026-02-23

### Fixed
- Se corrige visibilidad del mensaje posterior al envío exitoso: el contenedor de estado se mueve fuera del paso 4 para mantenerse visible cuando el formulario vuelve al paso 1.
- Se ajusta el estilo del estado global para mejorar lectura del resultado de envío en cualquier paso del formulario.

## [1.4.25] - 2026-02-23

### Changed
- Se mejora el feedback de envío exitoso en el formulario con mensajes contextuales por resultado (`success`, `partial_mail`, `partial_pdf`).
- El estado de éxito ahora muestra el ID del informe generado para facilitar seguimiento operativo en terreno.
- Se agregan estilos visuales de estado (`success`, `warning`, `error`) para la barra de estado del formulario.

## [1.4.24] - 2026-02-23

### Added
- Se agrega guardado automático de borrador local del formulario (campos de texto/select/fecha/email/número) con restauración al volver a `/post-venta/`.
- Se incorpora un botón "Limpiar borrador local" para eliminar manualmente el estado guardado desde interfaz.

### Changed
- El borrador ahora guarda también el paso activo del stepper y restaura la navegación al reingresar.
- Se aplica expiración del borrador local (48 horas) y limpieza automática tras envío exitoso.

## [1.4.23] - 2026-02-23

### Security
- Se incorpora rate limiting por IP en el endpoint público `agp_pv_submit` para mitigar abuso del formulario.
- El límite por defecto bloquea nuevos intentos al superar 5 envíos dentro de una ventana de 15 minutos.

### Changed
- Se agregan filtros `agp_pv_rate_limit_max_attempts`, `agp_pv_rate_limit_window_seconds` y `agp_pv_rate_limit_key` para ajustar umbrales y estrategia de clave sin modificar el core del plugin.

## [1.4.22] - 2026-02-23

### Fixed
- Se agrega el mensaje `success` en la configuración localizada del frontend para evitar estados vacíos tras envíos exitosos.
- El estado final del formulario ahora prioriza el mensaje devuelto por backend y usa fallback seguro cuando falta configuración de mensajes.

## [1.4.21] - 2026-02-22

### Changed
- Se ajusta la compresión de fotos a un modo de compresión alta más agresivo (doble perfil de dimensión/calidad) para acercar resultados de peso a versiones previas.
- La selección final ahora prioriza explícitamente el archivo más liviano y evita conservar variantes comprimidas cuando no existe ahorro real frente al original.
- Se mantiene y mejora la vista previa de peso antes de enviar, mostrando claramente ahorro por imagen y total comprimido.

## [1.4.20] - 2026-02-22

### Changed
- Se mejora la compresión de fotos en cliente con estrategia iterativa (calidad/dimensión) para reducir peso al máximo sin degradación perceptible.
- En la previsualización ahora se muestra el tamaño comprimido por imagen antes de enviar, incluyendo comparación contra el tamaño original y porcentaje de ahorro.
- El indicador de tamaño total de adjuntos ahora informa el total comprimido y el total original cuando hay ahorro.

## [1.4.19] - 2026-02-22

### Changed
- Se optimiza el flujo de fotos en móvil: compresión en cliente para imágenes pesadas antes del envío y control del peso total en interfaz.
- Se agrega feedback visual del peso total seleccionado y reglas claras de límite (5 MB por foto, 20 MB total).

### Security
- Se refuerza la validación server-side de adjuntos de fotos: límite de tamaño por archivo y validación de tipo real mediante `wp_check_filetype_and_ext`.

## [1.4.18] - 2026-02-22

### Changed
- Se implementa un stepper de 4 pasos en el formulario público (Datos, Detalle, Firmas y Adjuntos) para reducir scroll y carga cognitiva en móvil.
- Se agregan controles de navegación Anterior/Siguiente con validación por paso antes de avanzar.
- Al enviar exitosamente, el formulario vuelve al paso inicial y mantiene la lógica condicional existente para campos dinámicos.

## [1.4.17] - 2026-02-22

### Changed
- Se aplica un rediseño mobile-first del formulario público: mejor jerarquía visual, campos táctiles más cómodos y espaciado optimizado para pantallas pequeñas.
- Se agrega una barra de acción sticky en móvil para mantener visible el botón de envío durante todo el llenado del informe.
- Se ajusta el comportamiento responsivo de grillas, bloques de firma y previsualización de fotos para reducir fricción en uso desde terreno.

## [1.4.16] - 2026-02-22

### Changed
- Se agrega límite y truncado elegante en bloques de detalle del PDF (lubricantes, filtros, componentes, trabajos y observaciones) para evitar cajas desproporcionadas y mejorar paginación.
- Los textos largos ahora se recortan sin quebrar lectura y añaden sufijo `... (continúa)` cuando corresponde.

## [1.4.15] - 2026-02-22

### Fixed
- Se corrige la salida de `Faena / Lugar` en PDF para payloads legacy serializados (`street_address`), extrayendo texto legible sin usar deserialización PHP.
- Se mantiene la mitigación de seguridad: no se reintroduce `unserialize`/`maybe_unserialize` sobre entradas controladas por usuario.

## [1.4.14] - 2026-02-22

### Added
- Se agrega en la pantalla principal del gestor un botón "Regenerar todos los PDF" para reprocesar todos los registros históricos.
- Se incorpora procesamiento por lotes vía AJAX con barra de progreso y contador de resultados (OK/Fallidos), evitando bloqueos por timeout en operaciones masivas.

### Security
- La regeneración masiva exige permisos de administración (`manage_options`) y valida nonce en cada lote AJAX.

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
