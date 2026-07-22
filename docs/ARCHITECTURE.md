# Cargo SaaS Backend Architecture

This backend keeps Mecore's useful modular shape without carrying bank or finance logic.

The project uses root `Modules/*`, but names and files are Cargo SaaS oriented:

```text
Modules/
  Gp/   System settings, organizations, branches, dictionaries, action registry
  Ad/   Administration, automation, notifications
  Ap/   Application/backoffice authentication
  Cr/   Customer registry
  Ca/   Cargo operations
  Pos/  Point of sale
  Re/   Reports
```

## Action Gateway

Cargo SaaS routes backend operations through `action_code` values.

```text
POST /api/v1/back/action
Header: action: cargo.shipments
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

New actions are inserted through module migrations, not through a generic config file:

```text
Modules/Gp/Database/Migrations/2026_07_22_000003_insert_gp_action_registry.php
```

The source catalog is:

```text
Modules/Gp/Support/ActionCatalog.php
```

## First Actions

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

## Module Ownership

`Gp` owns system setup and the action gateway.

`Ad` owns admin operations plus automation and notifications.

`Ap` owns authentication and app/bootstrap entry points.

`Cr` owns customer data.

`Re` owns report templates and report execution foundation.

`Ca` owns cargo operational records.

`Pos` owns point-of-sale records.
