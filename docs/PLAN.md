# MageOS_Company — Plan de desarrollo

Estado: **aprobado** · Última revisión: 2026-10-20

## 0. Decisiones tomadas

| # | Tema | Decisión |
|---|---|---|
| D1 | Vendor / módulos | `MageOS_Company` (`app/code/MageOS/Company`, namespace `MageOS\Company`) y `MageOS_CompanyGraphQl` (`app/code/MageOS/CompanyGraphQl`) |
| D2 | Plataforma | Mage-OS ≥ 3. Código con sintaxis PHP 8.1 (compatible con versiones superiores). Rango real de PHP acotado por Mage-OS 3 (verificar en `composer.json` raíz) |
| D3 | Frontend | Luma. Preparado para un futuro `MageOS_CompanyHyva` (lógica en ViewModels, templates finos) |
| D4 | Customer ↔ compañía | Un customer pertenece como máximo a **una** compañía |
| D5 | Direcciones | Entidad EAV propia `company_address` (N direcciones). **No** se integra con el checkout en esta etapa, pero los códigos de atributo replican `customer_address` para facilitarlo en el futuro |
| D6 | Login | Customers de compañías no aprobadas (`pending`, `rejected`, `blocked`) **no** pueden loguear |
| D7 | Roles | `admin` (acceso total) / `user`. Permisos granulares en un módulo futuro, vía `CompanyAuthorizationInterface` |
| D8 | Atributos | Lista de la sección 3 aprobada |
| D9 | Alcance | Compañías por website |
| D10 | Tablas | Sin prefijo (`company_*`). **Riesgo aceptado:** incompatible con Adobe Commerce B2B (`Magento_Company`) en la misma instancia |
| D11 | Código ERP | `company_entity.company_id`: nullable, **único global** (un código por compañía, no repetible entre compañías ni websites) |
| D12 | Postulante `pending` | Los mensajes del admin se envían solo por email; el hilo in-app se habilita tras la aprobación. Link firmado a página pública de estado + hilo: mejora en fase 8 |

## 1. Estándares

- `declare(strict_types=1);` en todos los archivos PHP.
- Tipado estricto de parámetros, retornos y propiedades; constructor property promotion; `readonly` cuando aplique.
- Estados y roles como constantes de clase (no `enum` nativos, por compatibilidad con DI/serialización de Magento).
- `magento/magento-coding-standard` (PHPCS estándar `Magento2`) + PSR-12. PHPStan nivel ≥ 6.
- Solo service contracts hacia afuera. Prohibido `ObjectManager` directo, `save()`/`load()` del modelo fuera de repositorios.
- Toda salida en templates escapada con `$escaper`. Todo POST con form key.
- Traducciones en `i18n/en_US.csv` y `i18n/es_ES.csv`.

## 2. Estructura de módulos

```
app/code/MageOS/Company/
├── Api/                      # interfaces de servicio
│   └── Data/                 # interfaces de datos
├── Controller/
│   ├── Adminhtml/            # Index, Edit, Save, Delete, MassAction, Approve, Reject, Block, Message/*
│   ├── Account/              # área de usuario
│   ├── Address/
│   ├── Users/
│   ├── Message/
│   └── Register/             # formulario de registro público
├── Model/
│   ├── ResourceModel/        # entidades EAV, colecciones, attribute, relaciones
│   ├── Validator/
│   ├── Authorization/        # CompanyContext, CompanyAuthorization
│   └── Notification/         # emails + admin inbox
├── Observer/  Plugin/
├── Setup/Patch/Data/         # entity types, attribute sets, atributos, form attributes
├── Ui/Component/             # listings y forms admin, DataProviders, modifiers
├── ViewModel/                # frontend
├── etc/
│   ├── module.xml  db_schema.xml  db_schema_whitelist.json  di.xml
│   ├── acl.xml  webapi.xml  extension_attributes.xml  config.xml
│   ├── email_templates.xml  events.xml  communication.xml  queue*.xml
│   ├── adminhtml/{menu.xml, routes.xml, system.xml, di.xml}
│   ├── frontend/{routes.xml, di.xml}
│   └── webapi_rest/di.xml
├── view/
│   ├── adminhtml/{layout, templates, ui_component, web}
│   ├── frontend/{layout, templates, web}
│   └── email/  (en view/frontend/email y view/adminhtml/email)
├── i18n/
├── docs/
└── Test/{Unit, Integration, Api}

app/code/MageOS/CompanyGraphQl/
├── etc/{module.xml, schema.graphqls, di.xml}
├── Model/Resolver/
└── Test/Api/
```

## 3. Modelo de datos

### 3.1 Entity types EAV

| Entity type | Tabla base | Additional attribute table | Imita a |
|---|---|---|---|
| `company` | `company_entity` | `company_eav_attribute` | `customer` |
| `company_address` | `company_address_entity` | `company_eav_attribute` (compartida) | `customer_address` |

Ambos se registran en `eav_entity_type` mediante Data Patch, con attribute set y grupo por defecto, `entity_model`, `attribute_model`, `entity_attribute_collection` e `increment_model` nulo. Igual que customer/customer_address, ambas entidades **comparten** la tabla de metadatos (`company_eav_attribute`), la de formularios (`company_form_attribute`) y la de scope por website (`company_eav_attribute_website`).

### 3.2 Tablas

| Tabla | Contenido |
|---|---|
| `company_entity` | atributos static de compañía |
| `company_entity_{varchar,int,text,decimal,datetime}` | valores EAV (`value_id`, `attribute_id`, `entity_id`, `value`; sin `store_id`, igual que customer) |
| `company_eav_attribute` | `attribute_id` PK/FK, `is_visible`, `input_filter`, `multiline_count`, `validate_rules`, `is_system`, `sort_order`, `is_used_in_grid`, `is_visible_in_grid`, `is_filterable_in_grid`, `is_searchable_in_grid` |
| `company_form_attribute` | `form_code`, `attribute_id` (compañía y dirección) |
| `company_eav_attribute_website` | overrides por website de `is_visible`, `is_required`, `default_value`, `multiline_count` (requerida por la capa EAV base) |
| `company_address_entity` | static de dirección |
| `company_address_entity_{varchar,int,text,decimal,datetime}` | valores EAV de dirección |
| `company_customer` | relación customer ↔ compañía |
| `company_status_history` | auditoría de estados |
| `company_message` | mensajes |

### 3.3 `company_entity` (static)

| Columna | Tipo | Null | Índices / FK | Nota |
|---|---|---|---|---|
| `entity_id` | int unsigned | no | PK | |
| `name` | varchar(255) | no | fulltext | nombre comercial |
| `company_id` | varchar(64) | sí | **UNIQUE** | código ERP; vacío al crear; editable solo por admin/integración |
| `status` | smallint | no | index | 0 pending, 1 approved, 2 rejected, 3 blocked |
| `email` | varchar(255) | no | UNIQUE (`email`, `website_id`) | email corporativo |
| `website_id` | smallint unsigned | no | FK `store_website` (restrict) | |
| `super_user_id` | int unsigned | sí | FK `customer_entity` (set null) | administrador principal |
| `default_billing` | int unsigned | sí | FK `company_address_entity` (set null) | |
| `default_shipping` | int unsigned | sí | FK `company_address_entity` (set null) | |
| `legal_address` | int unsigned | sí | FK `company_address_entity` (set null) | domicilio fiscal |
| `created_at` | timestamp | no | | |
| `updated_at` | timestamp | no | | on update |

### 3.4 `company` — atributos EAV

| Código | Backend | Input | Req. | Nota |
|---|---|---|---|---|
| `legal_name` | varchar | text | sí | razón social |
| `vat_id` | varchar | text | sí | validación configurable (formato / VIES) |
| `phone` | varchar | text | sí | |
| `fax` | varchar | text | no | |
| `website_url` | varchar | text | no | validación URL |
| `industry` | int | select | no | source con opciones administrables (`eav_attribute_option`) |
| `employees_range` | int | select | no | 1‑10, 11‑50, 51‑200, 200+ |
| `description` | text | textarea | no | |
| `registration_comment` | text | textarea | no | comentario del postulante |
| `preferred_language` | varchar | select | no | locale para emails/ERP |

### 3.5 `company_address_entity` (static) y atributos

Static: `entity_id`, `parent_id` (FK `company_entity` cascade), `is_active`, `created_at`, `updated_at`, y los mismos campos static que `customer_address_entity` (`firstname`, `lastname`, `company`, `street`, `city`, `country_id`, `region`, `region_id`, `postcode`, `telephone`, `vat_id`).

| Código | Input | Req. |
|---|---|---|
| `firstname` / `lastname` | text | sí (contacto) |
| `company` | text | no |
| `street` | multiline (2) | sí |
| `city` | text | sí |
| `country_id` | select | sí |
| `region_id` / `region` | select / text | según país (`Magento\Directory`) |
| `postcode` | text | según país |
| `telephone` | text | sí |
| `vat_id` | text | no |
| `label` | text | no — "Sede central", "Almacén 2"… |

### 3.6 `company_customer`

| Columna | Tipo | Nota |
|---|---|---|
| `customer_id` | int unsigned PK | FK `customer_entity` cascade → garantiza 1 compañía por customer |
| `company_entity_id` | int unsigned | FK `company_entity` cascade, index |
| `role` | varchar(32) | `admin` / `user` |
| `job_title` | varchar(255) null | |
| `status` | smallint | 1 active / 0 inactive |
| `created_at` | timestamp | |

### 3.7 `company_status_history`

`history_id`, `company_entity_id` FK cascade, `from_status`, `to_status`, `comment` text, `actor_type` (`admin`/`customer`/`system`/`integration`), `actor_id`, `created_at`.

### 3.8 `company_message`

`message_id`, `company_entity_id` FK cascade, `author_type` (`admin`/`customer`/`system`), `author_id` null, `body` text, `is_internal` bool (solo admins), `read_at_admin` null, `read_at_customer` null, `created_at`. Índice (`company_entity_id`, `created_at`).

## 4. Capa de dominio

### 4.1 Interfaces de datos (`Api/Data`)

- `CompanyInterface` (extiende `CustomAttributesDataInterface`) — getters/setters de static + constantes de estado.
- `CompanyExtensionInterface` (generada).
- `CompanySearchResultsInterface`.
- `CompanyAddressInterface` (+ `SearchResults`).
- `CompanyCustomerInterface` — `customer_id`, `company_id`, `role`, `job_title`, `status`.
- `MessageInterface` (+ `SearchResults`).
- `StatusHistoryInterface`.

### 4.2 Servicios (`Api`)

| Interfaz | Métodos |
|---|---|
| `CompanyRepositoryInterface` | `save`, `getById`, `getByErpCode`, `getList`, `delete`, `deleteById` |
| `CompanyAddressRepositoryInterface` | `save`, `getById`, `getList`, `delete`, `deleteById` |
| `CompanyManagementInterface` | `register(CompanyInterface, CustomerInterface, ?string $password)`, `getByCustomerId`, `assignCustomer`, `unassignCustomer`, `getCustomers`, `setSuperUser` |
| `CompanyAddressManagementInterface` | `setDefaultBilling`, `setDefaultShipping`, `setLegalAddress` |
| `CompanyApprovalInterface` | `approve(id, ?comment)`, `reject(id, reason)`, `block(id, reason)`, `unblock(id, ?comment)`, `resubmit(id)` |
| `MessageRepositoryInterface` | `save`, `getById`, `getList` |
| `MessageManagementInterface` | `send(companyId, body, authorType, authorId, isInternal)`, `getThread`, `markRead` |
| `CompanyMetadataInterface` / `CompanyAddressMetadataInterface` | metadatos de atributos por formulario (como `CustomerMetadataInterface`) |
| `CompanyAuthorizationInterface` | `isAllowed(int $customerId, string $resource): bool` |

### 4.3 Modelos

- `Model\Company` extends `AbstractExtensibleModel` implements `CompanyInterface`.
- `Model\ResourceModel\Company` extends `Magento\Eav\Model\Entity\VersionControl\AbstractEntity` (como `Customer\Model\ResourceModel\Customer`).
- `Model\ResourceModel\Company\Collection` extends `Magento\Eav\Model\Entity\Collection\VersionControl\AbstractCollection`.
- `Model\Attribute` extends `Magento\Eav\Model\Attribute` (+ `ResourceModel\Attribute\Collection`).
- Equivalentes para `CompanyAddress`.
- Modelos planos (no EAV) para `CompanyCustomer`, `Message`, `StatusHistory`.
- Hidratación vía `DataObjectHelper` / `ExtensibleDataObjectConverter`.

### 4.4 Extension attribute en Customer

`Magento\Customer\Api\Data\CustomerInterface` → `company_attributes` (`CompanyCustomerInterface`). Plugin en `CustomerRepositoryInterface::get/getById/getList/save` para cargar/persistir.

### 4.5 Validación

`Model\Validator\*` aplicados en repositorios/servicios: requeridos según metadata, `vat_id`, URL, email, unicidad (`company_id` global, `email` por website), país/región/postcode, coherencia de website customer ↔ compañía.

### 4.6 Eventos propios

`company_register_after`, `company_save_after`, `company_status_change_after`, `company_customer_assign_after`, `company_customer_unassign_after`, `company_message_send_after`. Base para notificaciones e integración ERP.

## 5. Reglas de negocio

### 5.1 Máquina de estados

```
pending  → approved | rejected
approved → blocked
blocked  → approved
rejected → pending   (reenvío de solicitud)
```

Centralizada en `CompanyApproval`. Transición inválida → `LocalizedException`. Cada transición:
1. Registra `company_status_history` (actor, motivo).
2. Crea mensaje `system` en el hilo.
3. Dispara `company_status_change_after`.
4. Envía email (template por transición).

`reject` y `block` exigen motivo.

### 5.2 Bloqueo de login (D6)

- Plugin `afterAuthenticate` en `Magento\Customer\Api\AccountManagementInterface`: si la compañía del customer no está `approved` → `UserLockedException` con mensaje según estado. Cubre storefront, token REST y `generateCustomerToken` GraphQL.
- Al pasar a `blocked`/`rejected`: revocar tokens (`Magento\Integration\Api\TokenRevokerInterface` / `UserTokenRevokerInterface`) y sesiones de sus usuarios.
- "Login as Customer" de admin permitido (soporte).

### 5.3 Roles (D7)

- `CompanyAuthorizationInterface`, implementación por defecto `Model\Authorization\RoleBasedAuthorization`:
  - `admin` → `true` siempre.
  - `user` → solo `company.view`, `company.address.view`, `company.messages.view`, `company.messages.send`.
- Recursos como constantes: `company.view`, `company.edit`, `company.users.manage`, `company.address.view`, `company.address.manage`, `company.messages.view`, `company.messages.send`.
- Controllers, resolvers y servicios `mine` consultan **siempre** la interfaz, nunca el rol directo. El módulo de permisos futuro reemplaza la `preference`.
- Invariante: siempre ≥ 1 admin activo; no se puede degradar/desasignar al último admin ni al `super_user_id`.

### 5.4 Website (D9)

- Asignación de customer solo si `customer.website_id == company.website_id`, salvo `customer/account_share/scope = global` (permitido, con aviso en admin).
- Registro frontend toma website actual.
- APIs de cliente: el store de la request debe pertenecer al website de la compañía.
- Config con scope website.

## 6. Backend (adminhtml)

- **Menú**: `Customers > Companies`, `Customers > Company Messages`.
- **ACL** (`acl.xml`):
  ```
  MageOS_Company::company
    ├── MageOS_Company::company_view
    ├── MageOS_Company::company_edit
    ├── MageOS_Company::company_delete
    ├── MageOS_Company::company_approve
    ├── MageOS_Company::company_erp
    └── MageOS_Company::messages
  MageOS_Company::config
  ```
- **Grid** `company_listing`: columnas configurables por `is_used_in_grid`, filtros (estado, website, fechas), búsqueda fulltext, badge de estado, contador de mensajes no leídos. Mass actions: approve, reject, block, delete. (Grid flat/indexer `company_grid_flat` en fase 8 si el volumen lo pide.)
- **Formulario** `company_form` (DataProvider + modifiers desde `CompanyMetadataInterface`). Pestañas:
  1. General (static + EAV; `company_id` ERP solo con `company_erp`).
  2. Direcciones (listado + modal edición, defaults, sede legal).
  3. Usuarios (asignar/desasignar customers, rol, job title, super user).
  4. Historial de estado.
  5. Mensajes (hilo, responder, nota interna).
- Botones Approve / Reject / Block / Unblock con modal de motivo.
- **Pestaña "Company" en Customer edit** (compañía, rol, link).
- **Configuración** (`system.xml` → `Customers > Company`):
  - General: enable, auto-approve, validación VAT.
  - Registro: habilitar formulario público, captcha, términos.
  - Emails: sender, destinatarios admin, templates (solicitud recibida, aprobada, rechazada, bloqueada, nuevo mensaje → cliente, nuevo mensaje → admin, invitación de usuario).
- Notificación en inbox admin (`Magento\AdminNotification`) por nueva solicitud y nuevo mensaje.
- Controllers: `Magento\Backend\App\Action`, `ADMIN_RESOURCE`, `HttpGetActionInterface`/`HttpPostActionInterface`.

## 7. API REST (`etc/webapi.xml`)

| Método | Ruta | Recurso |
|---|---|---|
| GET | `/V1/companies` | `MageOS_Company::company_view` |
| GET | `/V1/companies/:companyId` | `::company_view` |
| POST | `/V1/companies` | `::company_edit` |
| PUT | `/V1/companies/:companyId` | `::company_edit` |
| DELETE | `/V1/companies/:companyId` | `::company_delete` |
| GET | `/V1/companies/erp/:erpCode` | `::company_erp` |
| PUT | `/V1/companies/:companyId/erp` | `::company_erp` |
| POST | `/V1/companies/:companyId/approve` · `/reject` · `/block` · `/unblock` | `::company_approve` |
| GET/POST/DELETE | `/V1/companies/:companyId/customers[/:customerId]` | `::company_edit` |
| GET/POST/PUT/DELETE | `/V1/companies/:companyId/addresses[/:addressId]` | `::company_view` / `::company_edit` |
| GET/POST | `/V1/companies/:companyId/messages` | `::messages` |
| GET | `/V1/companies/mine` | `self` |
| PUT | `/V1/companies/mine` | `self` + `company.edit` |
| GET/POST/PUT/DELETE | `/V1/companies/mine/addresses[/:addressId]` | `self` + `company.address.*` |
| GET/POST | `/V1/companies/mine/customers` | `self` + `company.users.manage` |
| GET/POST | `/V1/companies/mine/messages` | `self` + `company.messages.*` |
| POST | `/V1/companies/register` | `anonymous` |

**Seguridad**
- Rutas `mine`: `<parameter name="customerId" force="true">%customer_id%</parameter>`; la compañía se deduce del customer, nunca del input.
- `Model\Authorization\CompanyContext` + `CompanyAuthorizationInterface` en cada operación de cliente.
- Campos protegidos (`status`, `company_id`, `super_user_id`, `website_id`) ignorados/rechazados si el caller no es admin/integración.
- Direcciones/mensajes: verificar que el recurso pertenece a la compañía del caller (evita IDOR).
- `is_internal` nunca expuesto a cliente.
- `register`: reCAPTCHA (`Magento_ReCaptchaWebapiRest`), límites de tamaño, respuestas que no revelan existencia de email.
- ERP vía integración OAuth con ACL mínima (`company_view` + `company_erp`).

## 8. API GraphQL (`MageOS_CompanyGraphQl`)

**Schema**
- `type Company`, `CompanyAddress`, `CompanyUser`, `CompanyMessage`, `CompanyStatusEnum`, `CompanyRoleEnum`.
- `Customer.company: Company` (extiende tipo).
- Queries: `company`, `companyMessages(pageSize, currentPage)`, `companyAttributesMetadata(formCode)`.
- Mutations: `createCompanyApplication(input)`, `updateCompany(input)`, `createCompanyAddress` / `updateCompanyAddress` / `deleteCompanyAddress`, `inviteCompanyUser`, `updateCompanyUser`, `removeCompanyUser`, `sendCompanyMessage`, `markCompanyMessagesRead`.

**Seguridad**
- Resolver base que valida `$context->getExtensionAttributes()->getIsCustomer()` → `GraphQlAuthorizationException`.
- Compañía siempre desde el token; IDs de dirección/usuario validados contra ella.
- `CompanyAuthorizationInterface` por operación.
- `GraphQlInputException` para validación; reCAPTCHA en `createCompanyApplication`.
- Límites de query depth/complexity.
- Sin operaciones admin (admin/ERP → REST).
- Identity classes para cache tags `company_<id>`.

## 9. Frontend (Luma) — área de usuario

Navegación `customer_account`: "Mi compañía".

| Ruta | Función | Permiso |
|---|---|---|
| `company/account/index` | perfil y estado | `company.view` |
| `company/account/edit` | editar datos (form `company_account_edit`) | `company.edit` |
| `company/address/*` | listado, alta, edición, defaults | `company.address.*` |
| `company/users/*` | listar, invitar por email, rol, activar/desactivar | `company.users.manage` |
| `company/message/*` | hilo y respuesta | `company.messages.*` |

- Controllers: sesión obligatoria (redirect a login), `CompanyContext`, form key en POST.
- ViewModels para lógica; templates escapados; formularios con `mage/validation`.
- Invitación de usuario: si el email existe y no tiene compañía → asignación + email; si no existe → email con link de alta (token con expiración, tabla `company_invitation` — evaluar si entra en fase 5).

## 10. Formulario de registro

- Ruta pública `company/register` (link en header y en login).
- Modos:
  1. **Invitado**: crea customer + compañía + dirección legal en una transacción (`CompanyManagement::register`).
  2. **Customer logueado sin compañía**: crea compañía y lo asigna como `admin` + `super_user_id`. Tras enviar, se cierra su sesión si la compañía queda `pending` (D6).
- Campos dinámicos según `company_register` y `company_address_register`.
- Validación cliente y servidor, reCAPTCHA, términos opcionales.
- Resultado: estado `pending` (o `approved` si auto-approve), historial, email "solicitud recibida" al postulante, email + inbox al admin.
- Página de éxito explicando que la cuenta se activará tras la aprobación.

## 11. Mensajería

- Un hilo por compañía (`company_message`).
- Admin escribe desde el formulario de compañía; cliente desde `company/message` / REST `mine` / GraphQL.
- Notificación al otro extremo por email, encolada (`MessageQueue`, topic `company.message.notify`) para no bloquear la request.
- Contadores de no leídos en grid admin y en cuenta de cliente.
- Body en texto plano (escapado al renderizar), longitud máxima configurable, rate limit por customer.
- Notas internas (`is_internal`) solo visibles en admin.
- Postulante `pending` (D12): los mensajes del admin se envían por email; el hilo in-app queda disponible tras la aprobación.

## 12. Tests

- **Unit**: validadores, máquina de estados, `RoleBasedAuthorization`, `CompanyContext`.
- **Integration**: patches EAV, repositorios con atributos EAV, registro transaccional, bloqueo de login, revocación de tokens.
- **API functional**: REST (ACL admin, `mine`, IDOR entre compañías, registro anónimo) y GraphQL (autorización, mutations).
- CI: PHPCS `Magento2`, PHPStan, unit + integration.
- **E2E** (`Test/E2E/run.sh`, script CLI fuera de PHPUnit): HTTP real contra storefront, REST y GraphQL, mails leídos de Mailpit y sondas de inyección SQL in-process sobre los grids del admin. Cubre CSRF (form key), mass assignment, XSS en páginas y emails, link firmado (D12), rechazo/reenvío/aprobación, invitaciones e IDOR entre compañías. `run.sh` desvía el SMTP a Mailpit con `config:set` (nunca `--lock-env`: desincroniza el hash de config y el storefront da 500) y restaura los valores originales; los fixtures se borran al final.

## 13. Fases

| Fase | Contenido | Entregable |
|---|---|---|
| 1 | Esqueleto, `db_schema`, entity types `company` y `company_address`, atributos, form attributes, modelos, repositorios, validadores, extension attribute en customer | Módulo instalable, tests unit/integration de persistencia |
| 2 | Backend: menú, ACL, grid, formulario (general, direcciones, usuarios), pestaña en customer, configuración | CRUD admin completo |
| 3 | Registro frontend, máquina de estados, aprobación en admin, bloqueo de login, emails, historial | Flujo de alta y aprobación end-to-end |
| 4 | Mensajería (admin + cliente), cola de notificaciones, inbox admin | Intercambio de mensajes |
| 5 | Área de usuario: perfil, edición, direcciones, usuarios/invitaciones, `CompanyAuthorizationInterface` | Autogestión por admin de compañía |
| 6 | REST completa + tests API | API admin/ERP y `mine` |
| 7 | `MageOS_CompanyGraphQl` + tests | API GraphQL |
| 8 | Gestión de atributos custom en admin (`Stores > Attributes > Company`), grid flat/indexer, link firmado para postulantes pending (D12) | Extensibilidad y rendimiento |

### Estado

| Fase | Estado | Notas |
|---|---|---|
| 1 | ✅ Completa (2026-10-20) | Ver 13.1 |
| 2 | ✅ Completa (2026-10-20) | Ver 13.2 |
| 3 | ✅ Completa (2026-10-20) | Ver 13.3 |
| 4 | ✅ Completa (2026-10-20) | Ver 13.4 |
| 5 | ✅ Completa (2026-10-20) | Ver 13.5 |
| 6 | ✅ Completa (2026-10-20) | Ver 13.6 |
| 7 | ✅ Completa (2026-10-20) | Ver 13.7 |
| 8 | ✅ Completa (2026-10-20) | Ver 13.8 |

### 13.1 Fase 1: notas de implementación

- Tablas: las 18 de la sección 3.2 (incluye `company_status_history` y `company_message`, sin modelos todavía).
- `default_billing`, `default_shipping` y `legal_address` **sin FK** (como `customer_entity`) para evitar FKs circulares; al borrar una dirección, `ResourceModel\CompanyAddress::_afterDelete()` limpia las referencias.
- `Model\EavCustomAttributesTrait`: los atributos EAV no static se exponen como custom attributes y se mantienen sincronizados con los valores planos que usa el resource model.
- Repositorios: un payload "desacoplado" (con ID pero no cargado por el repositorio, p. ej. REST) se **fusiona** sobre lo guardado; los campos `null` no se tocan. Vaciar un campo vía API: ver 13.6.
- `Model\CompanyRegistry`: caché por request compartida; borrar una dirección invalida la compañía cacheada.
- `CompanyRepository::save()` guarda la compañía y asigna al super user como `admin` en la misma transacción.
- Extension attribute `company_attributes` en Customer: **solo lectura** (no se persiste desde `CustomerRepository::save`), para que un cliente no pueda unirse a una compañía editando su cuenta.
- Invariantes de roles en `Model\CompanyCustomer\Assigner`: el super user siempre es admin; siempre queda ≥ 1 admin activo.
- Validación de VAT: formato básico (4–20 alfanuméricos). La validación configurable/VIES queda para la fase 2/3.
- `CompanyAddressManagementInterface` no se creó: los defaults se asignan con los setters de la compañía y se validan (la dirección debe pertenecer a la compañía).
- Tests: unitarios de validadores. Los tests de integración quedan pendientes: el entorno no tiene base de datos de tests (`REWARD_TEST_DB=false`). La persistencia se verificó con un smoke test de 35 comprobaciones contra la base de desarrollo.

### 13.2 Fase 2: notas de implementación

- Rutas admin `mageos_company/*` (frontName `mageos_company`). Menú `Customers > Companies`; "Company Messages" se agrega en la fase 4.
- ACL según la sección 6. Además del `ADMIN_RESOURCE` de cada controller:
  - `company_id` (ERP) solo editable con `company_erp`.
  - `status` solo editable con `company_approve`.
  - `website_id` solo al crear.
  - `super_user_id` solo al editar (se elige entre los usuarios de la compañía).
  - Se aplica en el formulario (campo deshabilitado) **y** en `Controller\Adminhtml\Company\Save` (el valor se ignora).
- `status` se edita directamente desde el formulario. En la fase 3 se reemplaza por los botones Approve/Reject/Block con historial.
- Campos del formulario dinámicos: `Ui\DataProvider\Form\AttributeMetaProvider` arma la metadata UI desde `company_form_attribute`, es decir, desde los atributos del form code (`adminhtml_company`, `adminhtml_company_address`). Las reglas de validación se generan con `EavValidationRules`. El mismo servicio servirá para los formularios de frontend (fases 3 y 5).
- Grid `company_listing`:
  - Data provider propio sobre la colección EAV. La búsqueda fulltext usa OR sobre name/email/ERP/legal_name/vat_id.
  - Join de nombre/email del super user y subconsulta de cantidad de usuarios; esas columnas no son filtrables ni ordenables.
  - Columnas fijas en XML; las columnas dinámicas por `is_used_in_grid` quedan para la fase 8 junto con el grid flat.
  - Mass action: solo delete. Approve/reject/block vienen en la fase 3.
- Direcciones y usuarios: listados embebidos (`insertListing`) y formularios en modal (`insertForm`), con el mismo patrón que las direcciones del customer.
  - Direcciones: toggles default billing/shipping/legal en el formulario, acciones "Set as default …" y delete.
  - Usuarios: asignar por email (en el website de la compañía), cambiar rol/cargo, quitar. Las invariantes de roles se validan en el servicio (fase 1).
  - El estado activo/inactivo de un usuario se edita desde la fase 5.
- `CompanyAddress::beforeSave()` completa `region` con el nombre de la región cuando hay `region_id`, como customer.
- Pestaña "Company" en la edición de customer (`htmlContent` en `customer_form`, ACL `company_view`). Muestra compañía, estado, ERP, rol, super user y cargo.
- Configuración `Stores > Configuration > Customers > Companies`, scope website:
  - General: enable, auto-approve, validación VAT (`none`/`format`, ya aplicada en `CompanyValidator`).
  - Registro: habilitar, términos (texto).
  - Emails: remitente, destinatarios admin.
  - Los templates de email se agregan en la fase 3 con los emails; el captcha, en la fase 3 con el registro.
- Verificación:
  - Smoke test in-process de 38 comprobaciones contra la base de desarrollo: render de los 5 componentes UI, filtros/orden/fulltext del grid, metadata, árbol de componentes JS, controllers de dirección/usuario/compañía, invariantes, botones y pestaña de customer. Los datos de prueba se borraron al terminar.
  - No hubo prueba en navegador: la interacción JS (modales, recarga de listados) quedó sin verificar.

### 13.3 Fase 3: notas de implementación

- **Máquina de estados**: `Api\CompanyApprovalInterface` (`approve`, `reject`, `block`, `unblock`, `resubmit`, `getHistory`), implementada en `Model\CompanyApproval`. Las transiciones permitidas y los motivos obligatorios están en `Model\Status\TransitionPolicy`.
  - `approve` solo vale desde `pending` y `unblock` solo desde `blocked`, aunque las dos lleven a `approved`.
  - Cada cambio guarda el estado y el historial en una transacción. Después dispara `company_status_change_after`, cierra sesiones si corresponde y envía el email.
  - El mensaje `system` en el hilo (paso 2 de 5.1) se agregó en la fase 4.
- **Historial** (`company_status_history`): modelo `StatusHistory`; `Model\Status\HistoryRecorder` resuelve el actor desde `UserContextInterface` (admin, integración, customer o system).
  - El repositorio **no** registra historial. Lo registran quienes crean o cambian el estado: el registro frontend, el alta desde admin y `CompanyApproval`. El alta por REST (fase 6) tendrá que registrarlo también.
  - En admin, el estado solo se elige al crear la compañía. En la edición, `Save` ignora `status` y el campo queda deshabilitado; se cambia con los botones.
- **Admin**:
  - Botones Approve / Reject / Block / Unblock / Reopen Application. Son virtual types de `Block\Adminhtml\Company\Edit\StatusButton` y solo aparecen si el estado lo permite y hay ACL `company_approve`.
  - Abren un prompt (`js/company/status-action.js`). El motivo es obligatorio para reject y block, y el comentario es opcional para el resto. Postean a `company/changeStatus`.
  - Mass actions approve, reject, block y unblock (`company/massStatus`), con prompt (`js/grid/massactions.js`). Las compañías cuyo estado no permite la acción se saltean y se informan.
  - `Ui\Component\MassAction` oculta las acciones sin permiso (`aclResource` en la config de cada acción).
  - Fieldset "Status History" en el formulario de compañía: fecha, desde, hasta, actor y comentario.
- **Login (D6)**: plugin `afterAuthenticate` en `AccountManagementInterface`. Si la compañía no está aprobada, lanza una `LocalizedException` (no `UserLockedException`):
  - en el storefront muestra el motivo según el estado, porque `LoginPost` muestra el mensaje de las `LocalizedException` y oculta el de las `AuthenticationException`;
  - el token REST y GraphQL devuelven su error genérico de autenticación;
  - el mensaje solo aparece con la contraseña correcta, así que no revela nada a quien no tiene las credenciales;
  - "Login as Customer" no pasa por `authenticate`.
- **Bloqueo y rechazo**: `Model\Status\SessionTerminator` revoca los tokens de API (`CustomerTokenServiceInterface`) y las sesiones de storefront (`SessionCleanerInterface`) de todos los usuarios de la compañía. Cuando una sesión cerrada vuelve a usarse, el core lanza `SessionException`: en modo developer se ve la página de error, y en producción redirige a la home. Es el mismo comportamiento que cuando un admin cambia la contraseña de un customer.
- **Registro frontend** (`company/register`):
  - `CompanyManagementInterface::register(company, customer, ?password, ?legalAddress)`, implementado en `Model\Registration\Registrar`.
    - En una transacción: guarda la compañía, guarda la dirección legal (que queda también como facturación y envío por defecto) y, si es invitado, crea el customer (`createAccount`). Después asigna el super user y registra el historial.
    - Estado, código ERP, website y defaults no se toman del input.
    - Las validaciones de compañía y dirección corren antes de crear el customer: un error (p. ej. email de compañía duplicado) no deja customer ni emails enviados.
  - Campos dinámicos según `company_register` y `company_address_register` (`ViewModel\Registration` + `register/field.phtml`). País y región usan `regionUpdater`.
  - Validación en servidor con `Model\Form` (`Magento\Eav\Model\Form`): requeridos y reglas de cada atributo.
    - Los valores se aplican con `setData`, no con `compactData()`, que pasa strings a los setters tipados (`setRegionId("")`, `setStreet(string)`).
  - Customer logueado sin compañía: no ve los campos de cuenta, la dirección se precarga con su nombre y, si la compañía queda `pending`, se cierra su sesión.
  - Términos opcionales (texto con links permitidos) y reCAPTCHA (`recaptcha_frontend/type_for/company_register`, observer en predispatch). El reCAPTCHA no se probó con claves reales.
  - La página de éxito recibe el resultado por la URL (`?approved=0|1`): solo muestra texto, y la sesión puede haberse cerrado.
  - Link "Register Your Company" en el header (`Block\RegisterLink`, oculto si el registro está deshabilitado) y bloque en la página de login.
- **Source model de país**: el source de país de customer extiende `Table`, y su `getOptionText()` busca IDs de opción EAV en lugar de códigos ISO, así que la validación EAV fallaba. El patch `UseCompanyCountrySource` lo reemplaza por `Model\CompanyAddress\Attribute\Source\Country`.
- **Un solo email al postulante**: para los customers creados en el registro, `Model\Registration\WelcomeEmailDeferral` y el plugin `aroundNewAccount` en `EmailNotificationInterface` controlan el email de cuenta de Magento.
  - La bienvenida ("ya puedes iniciar sesión") no se envía nunca; la reemplaza el email "compañía aprobada" (también con auto-aprobación).
  - El email de confirmación de cuenta (si la tienda exige confirmación) lleva el link de activación, así que se guarda en `company_customer.pending_welcome_email` y se envía al aprobar la compañía (de inmediato con auto-aprobación). En ese caso el postulante recibe dos emails al aprobarse.
  - Mientras está pendiente, el postulante recibe solo "solicitud recibida". Los avisos van solo al super user, no al email corporativo.
- **Emails** (`etc/email_templates.xml`, configurables en `Customers > Companies > Email Notifications`): solicitud recibida, aprobada, rechazada, bloqueada, desbloqueada y nueva solicitud (admin, a `admin_recipients`, con link a la compañía).
  - Se envían en el store por defecto del website de la compañía, al super user (o al email de la compañía si no tiene).
  - Los fallos se loguean y no cortan la operación.
  - `resubmit` reenvía "solicitud recibida".
  - No se usa `preferred_language`: el idioma es el del store.
- **Pendiente / decidido para otras fases**:
  - Reapertura de una solicitud rechazada: solo desde admin ("Reopen Application"). Por el cliente, con el link firmado de la fase 8 (D12).
- **Dependencias nuevas**: `Magento_Authorization`, `Magento_Integration`, `Magento_Email`, `Magento_User`, `Magento_ReCaptchaUi`, `Magento_ReCaptchaAdminUi` y `Magento_ReCaptchaFrontendUi`.
- **Despliegue**: Mage-OS genera los interceptors solo con los métodos que tienen plugins. Tras instalar esta fase hay que regenerar `generated/` (en producción, `setup:di:compile`; en developer, borrar `generated/code`). Si no, el plugin de login no se ejecuta y cambia el constructor de `Controller\Adminhtml\Company\Save`.
- **Verificación** (contra la base de desarrollo; datos y emails de prueba borrados al terminar):
  - Unit: 39 tests (`TransitionPolicy`, `CompanyApproval`, plugin de login y los validadores de fases anteriores). PHPCS y PHPStan nivel 6 sin errores.
  - E2E por HTTP (curl contra `https://mageos300.test`):
    - registro: errores de validación, registro como invitado, como customer logueado (con logout), términos obligatorios, email de compañía duplicado (rollback sin customer) y auto-aprobación;
    - login: denegado con el mensaje de cada estado y error genérico con contraseña incorrecta;
    - REST: token denegado; un token emitido se revoca al bloquear (401);
    - emails recibidos en el buzón de desarrollo.
  - In-process admin:
    - botones según estado y ACL; campo de estado deshabilitado; historial renderizado;
    - transiciones válidas e inválidas, y motivo obligatorio;
    - mass actions con salteados; acciones ocultas sin ACL;
    - alta desde admin con historial; `Save` ignora `status` en la edición.
  - Sin prueba en navegador: prompts JS de botones y mass actions.

### 13.4 Fase 4: notas de implementación

- **Dominio**: `MessageInterface` (+ `MessageSearchResultsInterface`), `MessageRepositoryInterface` (`save`, `getById`, `getList`) y `MessageManagementInterface`, implementado en `Model\MessageManagement`:
  - `send(companyId, body, authorType, authorId, isInternal)`: texto plano (se normalizan los saltos de línea y se hace trim); dispara `company_message_send_after`.
  - `getThread(companyId, includeInternal)`: del más antiguo al más nuevo.
  - `markRead(companyId, readerType)` y `getUnreadCount(companyId, readerType)`.
  - Un mensaje se crea leído por su propio lado. Las notas internas cuentan como leídas por la compañía y los mensajes `system` como leídos por el admin, así que "no leído" es siempre "columna de lectura en null".
- **Reglas**:
  - Solo el admin escribe notas internas, y nunca se notifican ni se muestran a la compañía.
  - Un customer solo escribe en su propia compañía, si está aprobada y él es usuario activo.
  - Límite de mensajes por customer y hora (`company/messages/rate_limit`, 20 por defecto; 0 = sin límite).
  - Largo máximo del cuerpo (`company/messages/max_length`, 2000 por defecto; no aplica a mensajes `system`).
- **Mensajes `system`**: `CompanyApproval` deja un mensaje con el nuevo estado y el motivo en cada transición. No se notifica, porque la transición ya envía su email.
- **Notificaciones encoladas**: topic y consumer `company.message.notify`, conexión por defecto (MySQL si no hay AMQP), consumidor `Model\Message\NotifyConsumer`.
  - Admin → compañía: email a todos los usuarios activos (o al email de la compañía si no hay usuarios), con el texto del mensaje. Mientras la compañía está pendiente (D12), el email no incluye el link al hilo.
  - Compañía → admin: email a `admin_recipients` y aviso en el inbox del admin (`Model\Notification\AdminInbox`).
  - Las notas internas y los mensajes `system` no se encolan.
  - Los consumers corren con el cron (`consumers_runner`) o con `bin/magento queue:consumers:start company.message.notify`. Sin cron, los emails quedan en cola.
  - `Model\Notification\AdminUrl` arma el link a la compañía en el admin desde cualquier área, CLI incluido. Ojo: `FrontNameResolver::getFrontName(true)` devuelve vacío en CLI porque compara el host del request.
- **Inbox del admin**: aviso por nueva solicitud (`Registrar`) y por nuevo mensaje de una compañía (consumidor).
- **Admin**:
  - Menú `Customers > Company Messages` (ACL `messages`): grid de conversaciones (`company_message_listing`), una fila por compañía con mensajes. Muestra último mensaje, fecha, cantidad y no leídos, con filtro "Unread" sí/no, búsqueda por nombre o email y acción "Open Conversation".
  - En el grid de compañías se agrega la columna "Unread Messages".
  - Fieldset "Messages" en el formulario de compañía (ACL `messages`): hilo con notas internas resaltadas y marca "New". Abajo, respuesta con la opción "nota interna", enviada por AJAX a `message/send`, que devuelve el hilo actualizado.
  - Ver el hilo lo marca como leído.
- **Storefront**:
  - `company/message` en Mi cuenta: link "Company Messages (N)" con los no leídos, visible solo para usuarios activos de una compañía aprobada.
  - Muestra el hilo sin notas internas. El admin aparece como "Customer Service", los mensajes `system` como "Notification" y los propios como "You".
  - Formulario de respuesta. Si el envío falla, el borrador se conserva.
  - Los controllers implementan `AccountInterface` (login obligatorio). La compañía sale de `Model\Authorization\CompanyContext`, nunca del input; la fase 5 lo completa con `CompanyAuthorizationInterface`.
- **Verificación** (base de desarrollo; datos, emails, avisos del inbox y cola de prueba borrados al terminar):
  - Unit: 50 tests (11 nuevos de `MessageManagement`: estados de lectura, cola, validaciones, rate limit, autor ajeno o inactivo, compañía pendiente). PHPCS y PHPStan nivel 6 sin errores.
  - E2E:
    - registro → aviso en el inbox;
    - admin escribe a una compañía pendiente → el consumidor envía el email con el texto completo y sin link; la nota interna no se encola;
    - al aprobar → mensaje `system`;
    - login del cliente → "Company Messages (2)", hilo sin la nota interna, contador en 0 después de leer;
    - respuesta del cliente → email al admin con link correcto y aviso en el inbox;
    - grids con no leídos, filtro "Unread" y lectura desde el admin;
    - mensaje vacío rechazado y rate limit;
    - invitado redirigido al login; customer sin compañía redirigido con aviso;
    - POST sin form key rechazado;
    - fieldset y controller ocultos o denegados sin ACL.
  - Sin prueba en navegador: el envío AJAX del admin solo se probó llamando al controller.

### 13.5 Fase 5: notas de implementación

- **`CompanyAuthorizationInterface`** (`isAllowed(customerId, resource)`), con los recursos de la sección 5.3 como constantes. La implementación por defecto es `Model\Authorization\RoleBasedAuthorization`:
  - `admin` puede todo;
  - `user` solo `company.view`, `company.address.view`, `company.messages.view` y `company.messages.send`;
  - sin compañía aprobada o con el usuario inactivo, no se permite nada.
  - Un módulo de permisos la reemplaza con una `preference`.
- **`Model\Authorization\CompanyContext`**: compañía, rol y estado del customer. La compañía siempre sale del customer, nunca del input.
- **Usuario activo/inactivo**: `CompanyManagementInterface::setCustomerStatus()`, con las invariantes en `Assigner::setStatus()`: el super user y el último admin activo no se pueden desactivar.
  - Desactivar cierra las sesiones y revoca los tokens de ese usuario (`SessionTerminator::terminateCustomer()`).
  - El plugin de login también rechaza a los usuarios inactivos.
  - El estado se edita también en el formulario de usuario del admin.
- **Área de compañía en Mi cuenta** (Luma; controllers sobre `Controller\AbstractCompanyAction`, que exige login, toma la compañía de `CompanyContext` y valida el `RESOURCE` de cada acción):
  - `company/account`: perfil (valores del form `company_account_edit` y código de cliente ERP de solo lectura), estado y rol propio.
  - `company/account/edit`: formulario dinámico `company_account_edit`. Solo se aplican los atributos de ese form: `status`, `company_id`, `website_id` y `super_user_id` posteados se ignoran.
  - `company/address`: libreta de direcciones; alta y edición con el form `company_address_edit` (país/región con `regionUpdater`); borrar; marcar como facturación, envío o legal por defecto.
  - `company/users`: usuarios (rol, cargo, estado, contacto principal), invitaciones abiertas (reenviar, cancelar, vencidas marcadas) y formulario de invitación. Desde la edición de un usuario se cambian rol, cargo y estado; también se puede quitar al usuario.
  - Navegación en Mi cuenta: "My Company", "Company Addresses", "Company Users" y "Company Messages", cada link visible solo con su recurso (`Block\Account\CompanyLink`).
  - Los mensajes (fase 4) usan los recursos `company.messages.view` y `company.messages.send`.
  - IDs ajenos o inexistentes (direcciones, usuarios, invitaciones) dan el mismo mensaje ("not found in your company"), para no revelar datos de otras compañías.
- **Formularios EAV reutilizables**: `Model\Form\RequestApplier` (extraer, validar y aplicar; un valor vacío posteado limpia el campo) y `ViewModel\AttributeFields` (definición de campos y widget país/región en `form/country-region.phtml`). El registro de la fase 3 se refactorizó para usarlos.
- **Invitaciones** (tabla `company_invitation`, `Model\Invitation\InvitationManager`). **Desvío del plan (sección 9)**: no se asigna directamente un customer existente. Toda invitación requiere aceptar el link, porque un admin de compañía no debe poder sumar la cuenta de otra persona sin su consentimiento (quedaría sujeta al bloqueo de su compañía, entre otras cosas).
  - Token aleatorio de 48 caracteres; en la base se guarda solo su SHA-256. Validez configurable (`company/users/invitation_lifetime`, 7 días); vencimiento evaluado con el reloj de la base.
  - Reinvitar el mismo email reemplaza la invitación abierta.
  - No se puede invitar a usuarios de la propia compañía. A quien pertenece a otra compañía se lo rechaza con un mensaje genérico, sin decir de qué compañía es.
  - `company/invitation/accept?token=…` (público). El link solo vale en el website de la compañía, y la compañía debe estar aprobada.
    - Invitado sin cuenta: crea la cuenta con el email invitado (no editable), se une y queda logueado si no hace falta confirmar el email.
    - Con cuenta existente: debe iniciar sesión (login con referer de vuelta).
    - Logueado con otra cuenta: se le pide cerrar sesión.
    - Solo el dueño del email invitado puede aceptar.
  - Email "invitation" configurable en `Customers > Companies > Email Notifications`.
- **Verificación** (base de desarrollo; datos, emails y avisos de prueba borrados al terminar):
  - Unit: 73 tests (nuevos: `RoleBasedAuthorization`, `Assigner::setStatus`, `InvitationManager`, login de usuario inactivo). PHPCS y PHPStan nivel 6 sin errores.
  - E2E por HTTP (curl):
    - perfil y navegación por rol;
    - edición con error de validación, y campos protegidos ignorados;
    - direcciones: alta con default, set default, edición, borrado de ID inexistente;
    - invitaciones:
      - email inválido o ya usuario;
      - aceptación como invitado nuevo (crea cuenta, queda logueado, rol `user`);
      - sin acceso de `user` a usuarios, edición y gestión de direcciones (GET y POST);
      - reutilizar el token falla;
      - cuenta existente → modo "sign in"; otra cuenta logueada → aviso; aceptación tras login;
      - invitación vencida y cancelada;
      - usuario de otra compañía rechazado con mensaje genérico;
    - desactivar: sesión cortada y login rechazado; reactivar;
    - protecciones del super user (rol, estado, quitar);
    - IDOR de otra compañía sobre usuarios y direcciones;
    - regresión del registro.
  - In-process admin: estado en el formulario de usuario (carga, desactivar, reactivar, super user protegido).
  - Sin prueba en navegador: confirmación JS de borrar/quitar (`js/confirm-submit.js`).

### 13.6 Fase 6: notas de implementación

- **`etc/webapi.xml`**: 38 rutas.

  | Recurso ACL | Rutas |
  |---|---|
  | `company_view` | 6 |
  | `company_edit` | 9 |
  | `company_delete` | 1 |
  | `company_erp` | 2 |
  | `company_approve` | 5 |
  | `messages` | 2 |
  | `self` | 12 |
  | `anonymous` | 1 |

  Además de la tabla de la sección 7 se agregaron:
  - `POST /:companyId/resubmit`
  - `GET /:companyId/history`
  - `PUT /:companyId/customers/:customerId/status`
  - `PUT /:companyId/super-user/:customerId`
  - `POST /mine/messages/read`
- **Escritura admin/integración**: `CompanyManagementInterface::create()`, `update()` y `setErpCode()`, implementados en `Model\Management\CompanyWriter`. El repositorio sigue siendo la persistencia interna.
  - `create`: la compañía arranca `pending`, con historial y sin código ERP. El website, si no viene, es el del store por defecto.
  - `update`: los campos omitidos o `null` no se tocan y `""` limpia el valor. Ignora `status` (va por las rutas de aprobación), `company_id` (va por `PUT /erp`, con ACL `company_erp`) y `website_id`.
  - `PUT /V1/companies/:companyId` también copia el ID de la ruta en el `company_id` del payload (comportamiento de `ParamsOverrider` del core); `update` lo ignora.
- **Direcciones admin**: `CompanyAddressManagementInterface`, que valida que la dirección pertenezca a la compañía de la ruta. Los `PUT` usan `:id` para que el web API copie el ID en el payload.
- **Mensajes admin**: `MessageManagementInterface::sendAsAdmin()`. El autor sale del contexto: un admin queda con su ID; una integración, como admin sin ID (se muestra como "Integration"). `getThread` admite `includeInternal`.
- **"mine"**: `MyCompanyManagementInterface`, con `customerId` forzado desde el token (`%customer_id%`).
  - Cada operación valida su recurso con `CompanyAuthorizationInterface`; si falta permiso o compañía activa, responde `AuthorizationException` (401).
  - Los payloads se limitan a los atributos de los forms `company_account_edit` y `company_address_edit`, y se validan con sus reglas (`Model\Form\FormData`).
  - Una dirección de otra compañía responde 404.
  - `POST /mine/customers` invita a un usuario (no lo asigna) y los mensajes nunca incluyen notas internas.
- **Registro anónimo** `POST /V1/companies/register`: `CompanyRegistrationInterface::apply(company, customer, password, legalAddress)`.
  - Se copian solo los atributos de `company_register` y `company_address_register`.
  - El customer siempre es nuevo: se ignoran su ID, grupo y website.
  - Mismo reCAPTCHA que el formulario (`Model\Registration\WebapiCaptchaConfigProvider`, clave `company_register`).
  - Si el email de customer ya existe responde un error genérico. El de compañía duplicada sí lo informa: es un dato corporativo, no personal.
- **Verificación** (base de desarrollo; token admin de prueba emitido in-process para el admin 1 y revocado al terminar; datos y emails borrados):
  - Smoke E2E de 50 comprobaciones sobre la API real (HTTP), con tokens de admin y de customer:
    - anónimo sin acceso;
    - create/update con campos protegidos ignorados; código ERP y búsqueda por ERP; email duplicado;
    - transiciones válidas e inválidas; historial;
    - CRUD de direcciones admin con el ID de la ruta;
    - registro: validación, campos protegidos ignorados, ID de customer ignorado, error genérico por email existente; token denegado mientras está pendiente;
    - "mine": lectura, update restringido y validado, CRUD de direcciones, IDOR (404 en lectura, escritura y borrado);
    - usuarios e invitación; mensajes sin notas internas; marcar leídos;
    - rol `user` sin permisos de escritura (401); usuario desactivado con el token revocado; customer sin compañía (401); super user no desasignable.
  - Mapeo de rutas a ACL verificado contra la configuración webapi compilada; proveedor reCAPTCHA registrado.
  - Unit: 76 tests (nuevo `CompanyWriterTest`). PHPCS y PHPStan nivel 6 sin errores.
  - **Pendiente**: tests de `dev/tests/api-functional`. El entorno no tiene la instalación de tests API, y el smoke E2E no quedó en el repositorio.
  - **No probado con roles admin restringidos** (p. ej. sin `company_erp`): requeriría crear usuarios admin de prueba. El ACL de cada ruta se verificó en la configuración.

### 13.7 Fase 7: notas de implementación

- **Módulo `MageOS_CompanyGraphQl`** (`app/code/MageOS/CompanyGraphQl`, habilitado). Depende de `MageOS_Company`, `Magento_GraphQl` y `Magento_CustomerGraphQl`. Sin lógica de negocio propia: todos los resolvers usan `MyCompanyManagementInterface`, `CompanyRegistrationInterface` y `MessageManagementInterface`, igual que REST y la tienda.
- **Schema**:
  - Queries: `company`, `Customer.company`, `companyMessages(pageSize, currentPage)` (del más nuevo al más viejo, `pageSize` ≤ 100, con `unread_count`) y `companyAttributesMetadata(formCode)` (pública, enum con los 4 forms de la tienda).
  - Mutations:
    - `createCompanyApplication` (anónima);
    - `updateCompany`;
    - `create`, `update` y `deleteCompanyAddress` (las flags `default_billing`, `default_shipping` y `legal_address` en el input);
    - `inviteCompanyUser`, `updateCompanyUser` (rol, cargo, estado) y `removeCompanyUser`;
    - `sendCompanyMessage` y `markCompanyMessagesRead`.
  - Tipos `Company`, `CompanyAddress`, `CompanyUser`, `CompanyMessage` y enums de estado, rol, estado de usuario, autor y form.
  - IDs como `uid` (base64, `Magento\Framework\GraphQl\Query\Uid`).
  - Los atributos EAV van como `custom_attributes` (`attribute_code`, `value`, `label`). Así un atributo nuevo (fase 8) aparece sin cambiar el schema.
  - `Company.allowed_resources` permite que el frontend muestre u oculte acciones.
- **Seguridad**:
  - `AbstractCustomerResolver` exige customer logueado y mapea las excepciones del servicio:
    - `AuthorizationException` → `graphql-authorization`;
    - `NoSuchEntityException` → `graphql-no-such-entity`;
    - validación → `graphql-input` (todos los mensajes juntos).
  - La compañía siempre sale del token.
  - Los campos protegidos no existen en los inputs, y los `custom_attributes` se limitan a los forms (`FormData`).
  - `Company.users` requiere `company.users.manage`; si falta, el campo vuelve `null` con error y el resto de la query responde igual.
  - Las direcciones por defecto solo se exponen con `company.address.view`.
  - Una dirección ajena o inexistente da el mismo error: no revela los IDs de otras compañías. Se aplica también a REST.
  - reCAPTCHA `company_register` también para `createCompanyApplication`: el proveedor de webapi reconoce la clase del resolver.
  - Profundidad y complejidad: límites del core (`graphql/validation`).
  - Sin operaciones admin (admin/ERP → REST).
  - Las queries de compañía son privadas del customer, así que no se cachean en FPC. No se agregaron identity classes de caché: no aplican a respuestas autenticadas (cambio respecto de la sección 8).
- **Cambios en `MageOS_Company`**:
  - `MyCompanyManagementInterface` suma `updateUser`, `removeUser` y `setDefaultAddress`, con rutas REST `PUT|DELETE /V1/companies/mine/customers/:userId` y `PUT /V1/companies/mine/addresses/:addressId/default/:type`.
  - Los controllers de la tienda (guardar y quitar usuario, dirección por defecto) ahora usan esos métodos.
  - Nuevo `Model\Form\AttributeMetadataProvider`.
- **Verificación** (base de desarrollo; datos y emails borrados al terminar):
  - Smoke E2E GraphQL de 33 comprobaciones contra `https://mageos300.test/graphql`:
    - metadatos anónimos;
    - solicitud: validación, `PENDING`, error genérico por email existente, sin token mientras está pendiente;
    - query completa de compañía y `Customer.company`; invitado rechazado;
    - update restringido y validado;
    - CRUD de direcciones con flags; uid inválido; IDOR al actualizar y borrar;
    - invitar; super user no degradable; usuario inexistente;
    - rol `user` (lee y ve direcciones; `users` y `updateCompany` denegados);
    - desactivar (token revocado); quitar usuario;
    - mensajes con paginación, `unread_count`, marcar leídos y límite de `pageSize`.
  - Regresión de los controllers refactorizados de la tienda.
  - Unit: 78 tests (2 nuevos de mapeo de inputs GraphQL). PHPCS (incluye `.graphqls`) y PHPStan nivel 6 sin errores en ambos módulos.
  - Pendiente: tests de `dev/tests/api-functional` para GraphQL (mismo motivo que en 13.6).

### 13.8 Fase 8: notas de implementación

- **Atributos propios** (`Stores > Attributes > Company`, ACL `MageOS_Company::attributes`, rutas `mageos_company/attribute/*`):
  - Grid `company_attribute_listing`: atributos visibles de `company` y `company_address`, y todos los propios. Filtros por entidad, tipo, obligatorio, origen (sistema/propio) y "en el grid". Botones "Add Company Attribute" y "Add Company Address Attribute".
  - Formulario `company_attribute_form`: etiqueta por defecto y por store view, código, tipo, valor por defecto, orden, obligatorio, activado, formularios, validación (tipo de validación y largo mínimo/máximo), opciones (filas reordenables, por defecto, borrar) y flags del grid.
  - Tipos: texto, área de texto, fecha, sí/no, desplegable y selección múltiple (`Model\Attribute\InputTypes`). La selección múltiple usa backend `text`, para que el core limpie las opciones borradas en los valores guardados.
  - Lógica en `Model\Attribute\AttributeEditor` y persistencia en `Model\Attribute\AttributeRepository` (resource del atributo de compañía, que guarda `company_form_attribute`).
  - Código: 2 a 30 caracteres `[a-z][a-z0-9_]`; no puede repetir un atributo, una columna de la tabla de la entidad ni nombres reservados (columnas del grid, campos de la API). El código y el tipo no se cambian después.
  - Los formularios ofrecidos dependen de la entidad; un form code de otra entidad se descarta.
  - **Atributos del sistema** (`AttributeEditor::getPermissions()`): se cambian la etiqueta, el orden, las opciones si el source es `Table` (`industry`, `employees_range`) y los flags del grid de los EAV de compañía. Los formularios solo en los EAV opcionales (`fax`, `website_url`, `industry`…). No se cambian el código, el tipo, "obligatorio", la validación ni se borran.
  - Las opciones solo se reemplazan si el formulario las posteó (`options_submitted`): un guardado sin el fieldset no las borra.
  - Los atributos nuevos aparecen sin cambios de código en el registro, Mi compañía, la libreta de direcciones, el admin, REST (`FormData`) y GraphQL (`custom_attributes`, `companyAttributesMetadata`, que ahora también devuelve Sí/No de los atributos booleanos).
  - Valor por defecto de desplegables: se guarda, pero los formularios de la tienda no lo preseleccionan (igual que antes).
- **Grid flat** (indexer `company_grid`, tabla `company_grid_flat`):
  - Usa el framework del grid de customers: `Magento\Framework\Indexer\Action\Entity`, `SaveHandler\Grid` y `GridStructure`. Campos static en `etc/indexer.xml`; los EAV con `is_used_in_grid` los agrega `Model\Indexer\AttributeProvider` (searchable → índice fulltext; filterable → índice B-tree; las columnas `text` no llevan índice B-tree).
  - Mview `company_grid` sobre `company_entity` y sus tablas de valores (soporta "Update by Schedule"). En "Update on Save", `Observer\Grid\ReindexCompany` reindexa la fila al confirmar el guardado o el borrado (`company_save_commit_after` / `company_delete_commit_after`).
  - Cambiar el uso en el grid, el filtro o la búsqueda de un atributo invalida el índice (`Observer\Grid\InvalidateOnAttributeChange`); el cron reindexa los índices inválidos. Un full reindex recrea la tabla con las columnas actuales.
  - El patch `ReindexCompanyGrid` construye la tabla al instalar.
  - `company_listing` lee `company_grid_flat` (`Model\ResourceModel\Company\Grid\Collection`). Super user, cantidad de usuarios y no leídos se calculan al consultar (cambian con otras entidades) y no se filtran ni ordenan.
  - Columnas de atributos generadas por `Ui\Component\Listing\Columns`: etiqueta, visibilidad por defecto, filtro (texto, select o rango de fechas) y opciones según el atributo. Se quitaron del XML `legal_name`, `vat_id`, `phone` e `industry`, que ahora salen de sus flags.
  - Búsqueda por palabra: `LIKE` sobre las columnas del índice fulltext (coincidencias parciales). Filtro de selección múltiple con `FIND_IN_SET`.
  - En este entorno el indexer quedó en "Update by Schedule": los cambios aparecen en el grid cuando corre el cron (`indexer_update_all_views`).
- **Link firmado para postulantes (D12)**:
  - `Model\Registration\StatusLink`: token `<id>.<vencimiento>.<HMAC-SHA256>` con la clave de cifrado del deployment. La firma cubre ID, vencimiento, website, email de la compañía y super user: cambiar cualquiera anula los links enviados. No guarda estado.
  - Validez configurable en `Customers > Companies > Registration > Application Status Link Lifetime` (30 días; 0 desactiva los links y anula los ya enviados).
  - El link va en los emails "solicitud recibida", "rechazada" y "nuevo mensaje" mientras la compañía está `pending` o `rejected`. Se arma siempre con la URL de la tienda, también cuando el email sale del admin.
  - Página pública `company/application/status?token=…` (`noindex`, `Referrer-Policy: no-referrer`, sin FPC): estado, motivo del rechazo, hilo sin notas internas (lo marca leído), respuesta y, si está rechazada, "Reenviar solicitud" con comentario opcional.
  - Con la compañía aprobada, el link lleva al login; bloqueada o con token inválido/vencido, muestra un error genérico sin datos de la compañía. Solo vale en el website de la compañía.
  - Respuestas: `MessageManagementInterface::sendAsApplicant()` (no expuesto en web API). Escribe como el super user, con el mismo largo máximo y rate limit; solo si la compañía está `pending` o `rejected`.
  - Reenvío: `CompanyApproval::resubmit()` dentro de `ActorResolver::actAs()`, para que el historial registre al super user como actor `customer`. Envía de nuevo "solicitud recibida" con un link nuevo.
  - Los POST requieren form key y token válido.
- **Verificación** (base de desarrollo; datos de prueba, emails, avisos del inbox y cola borrados al terminar):
  - In-process admin, 46 comprobaciones de atributos: grid y formularios (nuevo, custom, sistema), switcher por tipo, errores de validación (código inválido/reservado/existente, etiqueta vacía, tipo, entidad, min > max, opción duplicada), alta de los 6 tipos y de una dirección, opciones (orden, por defecto, renombrar, borrar), store labels, protecciones de sistema, formularios de la tienda e invalidación del índice.
  - Grid, 22 comprobaciones: modo schedule (mview) y realtime (alta, edición, cambio de estado, borrado), columnas dinámicas y su configuración, render, búsqueda por palabra en un atributo propio, filtros select y multiselect, orden por atributo propio y columnas calculadas.
  - E2E HTTP del postulante: registro con atributos propios (valor inválido rechazado), link en el email, página pendiente, tokens alterados/de otra compañía/sin token, respuesta (sin form key y con token alterado: rechazadas), mensaje del admin con link y nota interna oculta, rechazo con motivo, reenvío (historial `customer:<super user>`), aprobado → login, bloqueado → no disponible y respuesta rechazada. Se corrigió que el email enviado desde el admin generaba una URL del admin.
  - GraphQL: `companyAttributesMetadata` devuelve los atributos nuevos con su etiqueta de store view.
  - Unit: 97 tests (nuevos: `StatusLinkTest`, `AttributeEditorTest`, `sendAsApplicant`). PHPCS y PHPStan nivel 6 sin errores.
  - Sin prueba en navegador: dynamic rows de opciones, switcher del tipo de campo y columnas dinámicas en el grid (verificados por la configuración y el render del componente).

## 14. Fuera de alcance (por ahora)

- Uso de direcciones de compañía en checkout (D5).
- Permisos granulares (módulo separado).
- Frontend Hyvä.
- Integración ERP concreta (solo se expone `company_id` + REST + eventos).
- Compatibilidad con Adobe Commerce B2B.
