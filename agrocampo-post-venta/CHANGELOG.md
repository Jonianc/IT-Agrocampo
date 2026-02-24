# Changelog

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
