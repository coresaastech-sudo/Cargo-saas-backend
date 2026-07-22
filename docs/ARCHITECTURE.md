# Cargo SaaS Backend Architecture

This backend keeps Mecore's useful modular shape without carrying bank, loan, or core-finance logic.

The project uses root `Modules/*`, but names and files are Cargo SaaS oriented:

```text
Modules/
  Gp/   System settings, organizations, branches, dictionaries, action registry, logs
  Ad/   Administration, users, operators, automation, notifications, receipts
  Ap/   Application/backoffice authentication and customer portal foundation
  Cr/   Customer registry and customer submodules
  Ca/   Cargo operations
  Pos/  Point of sale
  Re/   Report builder
  Gl/   Cargo operational ledger
```

## Action Gateway

Cargo SaaS routes backend operations through `action_code` values.

```text
POST /api/v1/back/action
Header: posting_code: cargo.shipments
Body: JSON payload
```

The gateway is:

```text
Modules/Gp/Http/Controllers/ActionGatewayController.php
```

It resolves an action from:

```text
gp_action_registry.action_code
gp_action_registry.controller
gp_action_registry.function
gp_action_registry.group_code
gp_action_registry.group_name
```

Then it dispatches with Laravel container calls:

```php
App::call($action->controller . '@' . $action->function)
```

## GP Foundation

The `Gp` module owns:

- `gp_organizations`
- `gp_branches`
- `gp_modules`
- `gp_action_registry`
- `gp_org_actions`
- `gp_roles`
- `gp_role_actions`
- `gp_user_roles`
- `gp_dictionaries`
- `gp_dictionary_items`
- service types, tariffs, service fees
- provider, response, whitelabel, mail configs
- audit, request, change, error, failed-job and email logs
- file/photo assets and user delegates

New actions are inserted through module migrations, not through a generic config file:

```text
Modules/Gp/Database/Migrations/2026_07_22_000003_insert_gp_action_registry.php
```

The source catalog is:

```text
Modules/Gp/Support/ActionCatalog.php
```

Mecore-origin process mappings are imported into Cargo as `action_code` records, not as `process_code` records:

```text
Modules/Gp/Database/Migrations/2026_07_22_000006_import_mecore_action_registry.php
```

Only copied Cargo-available controllers/functions are registered. Mecore modules outside the Cargo scope are skipped.

## Submodule Shape

Backoffice APIs remain single-route POST calls:

```text
POST /api/v1/back/action
Header: posting_code: <action_code>
```

Each submodule has its own controller/service/job/migration foundation, while the gateway remains the only internal route. The main submodule groups are:

- `Gp`: organization registry, system setup, cargo tariff setup, system logs.
- `Ad`: users, roles, operators, secrets, report permissions, notifications, automation, receipts, settlement accounts.
- `Ap`: portal profiles, customer users, portal services, FAQ/content, contracts, service suspension, access tokens.
- `Cr`: customer registry, addresses, contacts, documents, messages, relationships, credentials, stakeholders, billing accounts, delivery preferences, batch imports.
- `Re`: datasets, fields, templates, contents, dimensions, parameters, exports, run logs.
- `Gl`: account groups, accounts, charts, transactions, posting rules, report configs.

## Entity and View Models

Cargo SaaS follows the Mecore-style split between write models and read/view models:

```text
Modules/{Module}/Entities/{Entity}.php
Modules/{Module}/Entities/Views/Vw{Entity}.php
```

Table entities extend `App\Models\Model`, use `HasFactory`, and define the writable table plus fillable fields. View entities also extend `App\Models\Model`, live under `Entities/Views`, and point to `vw_*` database views used by list/detail reads where available.

Controllers reference these classes directly:

```php
protected $model = AdOperator::class;
protected $view = VwAdOperator::class;
```

View migrations are registered per module under `Modules/{Module}/Database/Migrations/*_create_{module}_views.php`.

## Mecore-Origin Code Import

The system keeps Mecore's actual module implementation files for the reusable platform parts:

```text
Modules/{Ad,Gp,Ap,Cr,Re,Gl}/Http/Controllers
Modules/{Ad,Gp,Ap,Cr,Re,Gl}/Http/Services
Modules/{Ad,Gp,Ap,Cr,Re,Gl}/Http/Requests
Modules/{Ad,Gp,Ap,Cr,Re,Gl}/Jobs
Modules/{Ad,Gp,Ap,Cr,Re,Gl}/Entities
Modules/{Ad,Gp,Ap,Cr,Re,Gl}/Entities/Views
Modules/{Ad,Gp,Ap,Cr,Re,Gl}/Database/Migrations
```

Mecore names were adapted only where Cargo conventions require it: `Gpa` is mapped to `Gp`, `process_code` is mapped to `action_code`, and the request header value is carried by `posting_code`.

## Example Actions

- `auth.login`
- `auth.bootstrap`
- `auth.logout`
- `system.actions`
- `system.menu`
- `system.dictionaries`
- `system.organizations`
- `system.branches`
- `admin.roles`
- `admin.users`
- `admin.automation`
- `admin.notifications`
- `customer.list`
- `cargo.dashboard`
- `cargo.shipments`
- `pos.dashboard`
- `report.templates`
- `ledger.accounts`
- `ledger.posting-rules`

## Module Ownership

`Gp` owns system setup and the action gateway.

`Ad` owns admin operations plus automation and notifications.

`Ap` owns authentication and app/bootstrap entry points.

`Cr` owns customer data.

`Re` owns report templates, report builder configuration and report execution foundation.

`Ca` owns cargo operational records.

`Pos` owns point-of-sale records.

`Gl` owns cargo operational ledger setup only. It does not import banking or loan accounting logic.
