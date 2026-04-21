# Agents.md — Guía de trabajo con los controles de Hipotea

Este documento describe la arquitectura, los controladores, las entidades, los formularios y las rutas del proyecto **Hipotea** (Symfony PHP), con especial énfasis en `GrupoNegociadorController.php`.

---

## 1. Estructura del proyecto

```
AppBundle/
├── Controller/           ← Controladores de la aplicación
├── Entity/               ← Entidades Doctrine (ORM)
├── Form/                 ← Tipos de formulario Symfony
├── Repository/           ← Repositorios Doctrine personalizados
├── Resources/
│   ├── config/
│   │   ├── routing.yml   ← Definición de todas las rutas
│   │   └── doctrine/     ← Mappings ORM
│   └── views/
│       ├── Backoffice/   ← Plantillas Twig del backoffice
│       └── Frontoffice/  ← Plantillas Twig del frontoffice
├── Security/             ← Comprobaciones de seguridad/autenticación
├── Services/             ← Servicios reutilizables (Sheets, Helpers, Notificaciones)
├── Utils/                ← Utilidades (p.ej. UsuariosNombreCompleto)
└── Validator/            ← Validadores personalizados
```

---

## 2. Controladores

| Archivo | Descripción |
|---|---|
| `GrupoNegociadorController.php` | **Controlador principal**. Gestiona usuarios, expedientes, documentos, facturas, notificaciones, calendario, estadísticas y más. |
| `APIController.php` | Endpoints REST para la app móvil/API externa. |
| `BelenderController.php` | Integración con Belender (gestión documental). |
| `CalculadorasController.php` | Calculadoras hipotecarias (sencilla, avanzada, comparativa). |
| `ChatController.php` | Funcionalidad de chat interno. |
| `IArtificalController.php` / `IaConfigController.php` | Integración con IA. |
| `KommoController.php` | Integración con Kommo CRM. |
| `LectorDocumentosController.php` | Lectura y análisis de documentos. |
| `ParametrosController.php` | Gestión de parámetros de la aplicación. |
| `SheetsController.php` | Integración con Google Sheets. |
| `SimuladorViabilidadController.php` | Simulador de viabilidad hipotecaria. |
| `WebController.php` | Páginas públicas del sitio web. |
| `WhatsappController.php` | Envío de mensajes vía WhatsApp. |

---

## 3. Roles de usuario

La aplicación usa los siguientes roles de Symfony (campo `role` en la entidad `Usuario`):

| Rol | Descripción |
|---|---|
| `ROLE_ADMIN` | Administrador. Acceso total. |
| `ROLE_COMERCIAL` | Comercial interno de Hipotea. |
| `ROLE_TECNICO` | Técnico interno de Hipotea. |
| `ROLE_COLABORADOR` | Colaborador externo (inmobiliaria/agente). |
| `ROLE_JEFE_INMOBILIARIA` | Jefe de una red de inmobiliarias. |
| `ROLE_JEFE_OFICINA` | Jefe de una oficina concreta. |
| `ROLE_RESPONSABLE_ZONA` | Responsable de zona geográfica. |
| `ROLE_CLIENTE` | Cliente final del servicio hipotecario. |

---

## 4. Entidades principales

| Entidad | Descripción |
|---|---|
| `Usuario` | Usuario del sistema (todos los roles). Implementa `UserInterface`. |
| `Expediente` | Expediente hipotecario (caso). Núcleo del negocio. |
| `Fase` | Fase del proceso hipotecario (p.ej. "Estudio", "Firma"). |
| `Hito` | Hito dentro de una fase. |
| `GrupoCamposHito` | Agrupación de campos de un hito. |
| `CampoHito` | Campo de formulario dentro de un grupo. |
| `CampoHitoExpediente` | Valor de un campo para un expediente concreto. |
| `OpcionesCampo` | Opciones de un campo tipo desplegable. |
| `Documento` | Plantilla de documento. |
| `FicheroCampo` | Fichero adjunto a un campo de expediente. |
| `Notificacion` | Notificación interna para un usuario. |
| `Noticia` | Noticia/aviso publicado en el sistema. |
| `Factura` / `LineaFactura` | Facturación. |
| `ClienteFactura` / `EmisorFactura` | Partes de una factura. |
| `Inmobiliaria` | Red de colaboradores/inmobiliaria. |
| `Oficina` | Oficina perteneciente a una inmobiliaria. |
| `AgenteColaborador` | Agente colaborador individual. |
| `EntidadColaboradora` | Entidad colaboradora (banco, tasadora, etc.). |
| `SeguimientoExpediente` | Seguimiento y comentarios de un expediente. |
| `SeguimientoHorario` | Control horario de usuarios. |
| `Log` | Registro de actividad del sistema. |
| `Parametros` | Parámetros globales de configuración. |

---

## 5. GrupoNegociadorController — Referencia de acciones

### 5.1 Autenticación y cuenta

| Método | Ruta | Descripción |
|---|---|---|
| `indexAction` | `GET /` | Redirección al listado de expedientes según rol. |
| `iniciarSesionAction` | `GET/POST /Login` | Pantalla de inicio de sesión. |
| `recuperarCuentaAction` | `POST (AJAX) /AJAX/RecuperarCuenta` | Envía email de recuperación de contraseña. |
| `reestablecerCuentaAction` | `GET/POST /ReestablecerCuenta/{token}` | Formulario para restablecer contraseña. |
| `registrarCuentaAction` | `GET/POST /Registrarse` | Registro de nuevo cliente. |
| `activarCuentaAction` | `GET /ActivarCuenta/{token}` | Activa la cuenta tras registro. |
| `activarCuentaCreadaColaboradorAction` | `GET/POST /ActivarCuentaCreadaColaborador/{token}` | Activa cuenta creada por colaborador. |
| `miPerfilAction` | `GET/POST /MiPerfil` | Edición del perfil del usuario autenticado. |
| `obtenerFotoPerfilBase64Action` | `GET (AJAX) /AJAX/ObtenerFotoPerfilBase64` | Devuelve la foto de perfil en base64. |
| `pageNotFoundAction` | — | Página 404 personalizada. |

### 5.2 Gestión de usuarios internos

| Método | Ruta | Descripción |
|---|---|---|
| `listaUsuariosAction` | `GET /Admin/Lista/Usuarios` | Lista de usuarios internos (admin/comercial/técnico). |
| `agregarModificarUsuarioAction` | `GET/POST /Admin/Formulario/Usuario/{id}` | Crear/editar usuario interno. |
| `listaJefesInmobiliariaAction` | `GET /Admin/Lista/JefesInmobiliarias` | Lista de jefes de inmobiliaria. |
| `agregarModificarJefeInmobiliariaAction` | `GET/POST /Admin/Formulario/JefeInmobiliaria/{id}` | Crear/editar jefe de inmobiliaria. |
| `listaJefesOficinaAction` | `GET /Admin/Lista/JefesOficinas` | Lista de jefes de oficina. |
| `agregarModificarJefeOficinaAction` | `GET/POST /Admin/Formulario/JefeOficina/{id}` | Crear/editar jefe de oficina. |
| `listaResponsablesZonaAction` | `GET /Admin/Lista/ResponsablesZona` | Lista de responsables de zona. |
| `agregarModificarResponsableZonaAction` | `GET/POST /Admin/Formulario/ResponsableZona/{id}` | Crear/editar responsable de zona. |

### 5.3 Gestión de colaboradores externos

| Método | Ruta | Descripción |
|---|---|---|
| `listaUsuariosInmobiliariaAction` | `GET /Admin/Lista/UsuariosColaboradores` | Lista de usuarios colaboradores. |
| `agregarModificarUsuarioInmobiliariaAction` | `GET/POST /Admin/Formulario/UsuarioColaborador/{id}` | Crear/editar usuario colaborador. |
| `listaAgenteColaboradorAction` | `GET /Admin/Lista/AgentesColaboradores` | Lista de agentes colaboradores. |
| `agregarModificarAgenteColaboradorAction` | `GET/POST /Admin/Formulario/AgenteColaborador/{id}` | Crear/editar agente colaborador. |
| `listaEntidadColaboradoraAction` | `GET /Admin/Lista/EntidadesColaboradoras` | Lista de entidades colaboradoras. |
| `agregarModificarEntidadColaboradoraAction` | `GET/POST /Admin/Formulario/EntidadColaboradora/{id}` | Crear/editar entidad colaboradora. |
| `refrescarUsuariosColaboradorAction` | `POST (AJAX) /AJAX/RefrescarUsuariosColaborador` | Recarga usuarios de un colaborador. |
| `obtenerAgentesColaboradoresAction` | `POST (AJAX) /AJAX/ObtenerAgentesColaboradores` | Devuelve agentes de un colaborador. |
| `colaboradorAgregarClienteAction` | `GET/POST /Colaborador/AgregarCliente` | Permite al colaborador añadir un cliente. |

### 5.4 Inmobiliarias y oficinas

| Método | Ruta | Descripción |
|---|---|---|
| `listaInmobiliariaAction` | `GET /Admin/Lista/Colaboradores` | Lista de inmobiliarias/colaboradores. |
| `agregarModificarInmobiliariaAction` | `GET/POST /Admin/Formulario/Colaborador/{id}` | Crear/editar inmobiliaria. |
| `listaOficinasAction` | `GET /Admin/Lista/Oficinas` | Lista de oficinas. |
| `agregarModificarOficinaAction` | `GET/POST /Admin/Formulario/Oficina/{id}` | Crear/editar oficina. |
| `getOficinasByInmobiliariaAction` | `GET/POST (AJAX) /AJAX/ObtenerOficinas` | Devuelve oficinas de una inmobiliaria (JSON). |
| `obtenerUsuariosInmobiliariaOficinaAction` | `GET/POST (AJAX) /AJAX/ObtenerUsuariosInmobiliariaOficina` | Devuelve usuarios de una inmobiliaria/oficina. |

### 5.5 Clientes

| Método | Ruta | Descripción |
|---|---|---|
| `listaClientesAction` | `GET /ColaboradorComercialTecnico/Lista/Clientes` | Lista de clientes (filtrada por rol). |
| `agregarModificarClienteAction` | `GET/POST /ColaboradorComercialTecnico/Formulario/Usuario/{id}` | Crear/editar cliente. |
| `obtenerClienteAction` | `POST (AJAX) /AJAX/ObtenerCliente` (implícito) | Devuelve datos de un cliente. |

### 5.6 Fases, Hitos, Grupos, Campos y Opciones

| Método | Ruta | Descripción |
|---|---|---|
| `listaFasesHitosCamposOpcionesAction` | `GET /Admin/Lista/FasesHitosCamposOpciones` | Vista de toda la estructura de fases/hitos/campos. |
| `agregarModificarFaseAction` | `GET/POST /Admin/Formulario/Fase/{id}` | Crear/editar fase. |
| `agregarModificarHitoAction` | `GET/POST /Admin/Formulario/Fase/{idFase}/Hito/{idHito}` | Crear/editar hito dentro de una fase. |
| `agregarModificarGrupoCamposHitoAction` | `GET/POST /Admin/Formulario/Hito/{idHito}/GrupoCamposHito/{idGrupoCamposHito}` | Crear/editar grupo de campos de un hito. |
| `agregarModificarCampoHitoAction` | `GET/POST /Admin/Formulario/GrupoCamposHito/{idGrupoCamposHito}/CampoHito/{idCampoHito}` | Crear/editar campo de un hito. |
| `agregarModificarOpcionesCampoAction` | `GET/POST /Admin/Formulario/CampoHito/{idCampoHito}/OpcionesCampo/{idOpcionesCampo}` | Crear/editar opciones de un campo desplegable. |
| `ordenarFasesHitosCamposOpcionesAction` | `POST (AJAX) /AJAX/OrdenarFasesHitosCamposOpciones` | Guarda el nuevo orden de fases/hitos/campos. |

### 5.7 Expedientes

| Método | Ruta | Descripción |
|---|---|---|
| `listaExpedientes1Action` | `GET /Lista/Expedientes` | Listado paginado de expedientes (principal). |
| `listaExpedientesAjaxAction` | `GET/POST (AJAX) /AJAX/lista-expedientes` | Listado de expedientes vía AJAX. |
| `listaExpedientesSinAsignarAction` | `GET /Lista/ExpedientesSinAsignar` | Expedientes sin comercial/técnico asignado. |
| `agregarModificarExpedienteAction` | `GET/POST /ColaboradorComercialTecnico/Formulario/Expediente/{id}` | Crear/editar expediente completo con hitos y campos. |
| `modificarExpedienteAction` | `GET/POST /ClienteColaborador/Formulario/Expediente/{id}` | Edición limitada para cliente/colaborador. |
| `duplicarExpedienteAction` | `GET /ColaboradorComercialTecnico/Expediente/{id}/Duplicar` | Duplica un expediente existente. |
| `cancelarExpedienteAction` | `POST (AJAX) /AJAX/CancelarExpediente` | Cancela un expediente con motivo. |
| `notificacionExpedienteAction` | `POST (AJAX) /AJAX/NotificacionExpediente` | Envía una notificación relacionada con un expediente. |
| `seguimientoExpedienteAction` | `GET/POST /Seguimiento/Expediente/{id}` | Vista de seguimiento de un expediente. |
| `actualizarSeguimientoExpedienteAction` | `POST (AJAX) /AJAX/ActualizarSeguimientoExpediente` | Añade/edita seguimiento de un expediente. |
| `expedienteEmailAction` | `GET/POST /ColaboradorComercialTecnico/Expediente/{id}/Email` | Envía emails relacionados con el expediente. |
| `obtenerRegistroExpedienteAction` | `GET /ColaboradorComercialTecnico/Expediente/{id}/Registro` | Obtiene el historial/registro de un expediente. |

### 5.8 Documentos y ficheros de expediente

| Método | Ruta | Descripción |
|---|---|---|
| `listaDocumentosExpedienteAction` | `GET /Lista/DocumentosExpediente/{id}/{paraFirmar}` | Lista de documentos de un expediente. |
| `borrarFicheroExpedienteAction` | `POST (AJAX) /AJAX/BorrarFicheroExpediente` | Elimina un fichero de un campo de expediente. |
| `obtenerFicheroExpedienteAction` | `GET /Descargar/FicheroExpediente/{id}` | Descarga un fichero de expediente. |
| `listaDocumentosAction` | `GET /Lista/Documentos` | Listado de plantillas de documentos. |
| `agregarModificarDocumentoAction` | `GET/POST /ComercialTecnico/Formulario/Documento/{id}` | Crear/editar plantilla de documento. |
| `borrarFicheroDocumentoAction` | `POST (AJAX) /AJAX/BorrarFicheroDocumento` | Elimina fichero de plantilla de documento. |
| `descargarFicheroDocumentoAction` | `GET /Descargar/Documento/{id}` | Descarga una plantilla de documento. |

### 5.9 Informes de expediente

| Método | Ruta | Descripción |
|---|---|---|
| `informeExpedienteAction` | `GET /ColaboradorComercialTecnico/Expediente/{id}/Informe` | Genera PDF con el informe completo del expediente. |
| `informePendienteExpedienteAction` | `GET /ColaboradorComercialTecnico/Expediente/{id}/Pendiente` | Genera PDF con campos pendientes del expediente. |

### 5.10 Facturación

| Método | Ruta | Descripción |
|---|---|---|
| `listaClientesFacturaAction` | `GET /Admin/Lista/ClientesFactura` | Lista de clientes de factura. |
| `agregarModificarClienteFacturaAction` | `GET/POST /Admin/Formulario/ClienteFactura/{id}` | Crear/editar cliente de factura. |
| `listaEmisoresFacturaAction` | `GET /Admin/Lista/EmisoresFactura` | Lista de emisores de factura. |
| `agregarModificarEmisorFacturaAction` | `GET/POST /Admin/Formulario/EmisorFactura/{id}` | Crear/editar emisor de factura. |
| `listaFacturasAction` | `GET /Admin/Lista/Facturas` | Listado de facturas. |
| `agregarModificarFacturaAction` | `GET/POST /Admin/Formulario/Factura/{id}` | Crear/editar factura con líneas. |
| `ObtenerNumeroSerieFacturaAction` | `POST (AJAX) /AJAX/ObtenerNumeroSerieFactura` | Devuelve el siguiente número de serie. |
| `verFacturaAction` | `GET /Factura/{id}` | Vista/PDF de una factura. |
| `enviarFacturaEmailAction` | `POST (AJAX) /AJAX/EnviarFacturaEmail` | Envía la factura por email. |

### 5.11 Notificaciones y noticias

| Método | Ruta | Descripción |
|---|---|---|
| `crearNotificacionesAction` | `GET/POST /Admin/CrearNotificaciones` | Crea notificaciones masivas para usuarios. |
| `notificacionConfigurarEstadoLeidaAction` | `POST (AJAX) /AJAX/NotificacionConfigurarEstadoLeida` | Marca notificación como leída/no leída. |
| `listaNotificacionesAction` | `GET /ListaNotificaciones` | Lista de notificaciones del usuario. |
| `listaNoticiasAction` | `GET /Admin/Lista/Noticias` | Lista de noticias del sistema. |
| `agregarModificarNoticiaAction` | `GET/POST /Admin/Formulario/Noticia/{id}` | Crear/editar noticia. |
| `borrarFicheroNoticiaAction` | `POST (AJAX) /AJAX/BorrarFicheroNoticia` | Elimina fichero adjunto de una noticia. |

### 5.12 Calendario

| Método | Ruta | Descripción |
|---|---|---|
| `calendarioAction` | `GET /ColaboradorComercialTecnico/Calendario` | Vista del calendario de seguimientos. |
| `actualizarCalendarioAction` | `POST (AJAX) /AJAX/ActualizarCalendario` | Actualiza eventos en el calendario. |
| `seleccionarUsuarioCalendarioAction` | `POST (AJAX) /AJAX/SeleccionarUsuarioCalendario` | Filtra el calendario por usuario. |

### 5.13 Estadísticas

| Método | Ruta | Descripción |
|---|---|---|
| `estadisticasAction` | `GET /Admin/Estadisticas` | Panel de estadísticas generales. |
| `obtenerInformeEstadisticasAction` | `POST (AJAX) /AJAX/ObtenerInformeEstadisticas` | Datos de estadísticas filtrados en JSON. |

---

## 6. Formularios (Form/)

Los formularios Symfony están en `AppBundle/Form/`. Cada entidad dispone de su propio tipo:

| Formulario | Entidad asociada |
|---|---|
| `Expediente` | `ExpedienteEntidad` |
| `Usuario`, `UsuarioModificar`, `UsuarioCliente`, `UsuarioClienteModificar` | `UsuarioEntidad` |
| `UsuarioInmobiliaria`, `UsuarioInmobiliariaModificar` | `UsuarioEntidad` (rol colaborador) |
| `JefeInmobiliaria`, `JefeInmobiliariaModificar` | `UsuarioEntidad` (rol jefe inmobiliaria) |
| `JefeOficina`, `JefeOficinaModificar` | `UsuarioEntidad` (rol jefe oficina) |
| `ResponsableZona`, `ResponsableZonaModificar` | `UsuarioEntidad` (rol responsable zona) |
| `Fase`, `FaseModificar` | `FaseEntidad` |
| `Hito`, `HitoModificar`, `HitoExpediente` | `HitoEntidad` / `HitoExpedienteEntidad` |
| `GrupoCamposHito`, `GrupoCamposHitoModificar` | `GrupoCamposHitoEntidad` |
| `CampoHito`, `CampoHitoModificar` | `CampoHitoEntidad` |
| `CampoHitoExpediente*` (múltiples tipos) | `CampoHitoExpedienteEntidad` |
| `OpcionesCampo`, `OpcionesCampoModificar` | `OpcionesCampoEntidad` |
| `Factura`, `LineaFactura`, `ClienteFactura`, `EmisorFactura` | Entidades de facturación |
| `Documento` | `DocumentoEntidad` |
| `Noticia`, `NoticiaUsuario` | `NoticiaEntidad` |
| `Notificacion`, `NotificacionExpediente` | `NotificacionEntidad` |
| `Inmobiliaria`, `Oficina` | `InmobiliariaEntidad`, `OficinaEntidad` |
| `AgenteColaborador` | `AgenteColaboradorEntidad` |
| `EntidadColaboradora` | `EntidadColaboradoraEntidad` |
| `SeguimientoExpediente`, `SeguimientoHorario` | Entidades de seguimiento |
| `CrearCliente`, `CompletarDatosCliente` | `UsuarioEntidad` (cliente) |
| `CancelarExpediente` | — (solo campo motivoCancelacion) |
| `RegistrarUsuario`, `RecuperarUsuario` | `UsuarioEntidad` |
| `ExpedienteEmail`, `ExpedienteEmailCheckboxes` | `ExpedienteEmailEntidad` |

---

## 7. Servicios (Services/)

| Servicio | Descripción |
|---|---|
| `GoogleSheetsService` | Lectura/escritura en Google Sheets. |
| `Helpers` | Funciones auxiliares genéricas (fechas, strings, etc.). |
| `Notificaciones` | Lógica centralizada de creación de notificaciones. |

---

## 8. Seguridad (Security/)

- `ComprobarUsuario.php`: Listener/guard que verifica el estado del usuario al autenticarse (activo, caducado, etc.).

---

## 9. Convenciones del proyecto

- **Nomenclatura de acciones**: Cada método del controlador termina en `Action` (convención Symfony 3).
- **Rutas AJAX**: Prefijo `/AJAX/`. Siempre comprueban `$request->isXmlHttpRequest()`.
- **Rutas Admin**: Prefijo `/Admin/`. Solo accesibles para `ROLE_ADMIN`.
- **Rutas ColaboradorComercialTecnico**: Accesibles para colaboradores, comerciales y técnicos.
- **Rutas ClienteColaborador**: Accesibles para clientes y colaboradores.
- **Respuestas AJAX**: Devuelven `JsonResponse` con `['error' => bool, 'mensaje' => string]`.
- **Plantillas Twig**: En `Resources/views/Backoffice/`. Las listas en `Lista/`, formularios en `AgregarModificar/`.
- **Persist y flush**: Siempre se usa `$managerEntidad->persist($entidad)` seguido de `$managerEntidad->flush()`.
- **Flash messages**: `addFlash('info'|'warning'|'danger'|'success', 'mensaje')`.
- **Control de acceso por rol**: Se verifica con `$this->getUser()->getRoles()[0]` en los propios métodos del controlador (no solo en `security.yml`).

---

## 10. Guía rápida para añadir una nueva funcionalidad

1. **Crear la entidad** en `Entity/` con sus getters/setters.
2. **Crear el mapping Doctrine** en `Resources/config/doctrine/`.
3. **Crear el formulario** en `Form/`.
4. **Crear el método Action** en `GrupoNegociadorController.php` siguiendo el patrón existente:
   - Obtener doctrine y manager.
   - Crear/recuperar entidad.
   - Crear formulario con `$this->createForm(...)`.
   - Procesar con `handleRequest`, validar con `isSubmitted() && isValid()`.
   - Persistir y hacer flush.
   - Redirigir o renderizar la vista.
5. **Registrar la ruta** en `Resources/config/routing.yml`.
6. **Crear la plantilla Twig** en `Resources/views/Backoffice/`.

---

## 11. Dependencias externas relevantes

- **Symfony 3.x** (Framework base)
- **Doctrine ORM** (Persistencia de datos)
- **KnpSnappyBundle** (Generación de PDFs vía wkhtmltopdf)
- **SwiftMailer** (Envío de emails)
- **PDFMerger** (Combinación de PDFs)
- **Google Sheets API** (Integración con hojas de cálculo)
