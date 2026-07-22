<?php

namespace Modules\Gp\Support;

class ActionCatalog
{
    public static function modules(): array
    {
        return [
            ['module_code' => 'ap', 'name' => 'Application', 'sort_order' => 10],
            ['module_code' => 'gp', 'name' => 'System Settings', 'sort_order' => 20],
            ['module_code' => 'ad', 'name' => 'Administration', 'sort_order' => 30],
            ['module_code' => 'cr', 'name' => 'Customers', 'sort_order' => 40],
            ['module_code' => 'ca', 'name' => 'Cargo', 'sort_order' => 50],
            ['module_code' => 'pos', 'name' => 'POS', 'sort_order' => 60],
            ['module_code' => 're', 'name' => 'Reports', 'sort_order' => 70],
        ];
    }

    public static function actions(): array
    {
        return [
            ['action_code' => 'auth.login', 'name' => 'Backoffice login', 'controller' => 'Modules\Ap\Http\Controllers\ApplicationAuthController', 'function' => 'login', 'module_code' => 'ap', 'requires_auth' => false, 'requires_permission' => false, 'sort_order' => 10],
            ['action_code' => 'auth.bootstrap', 'name' => 'Backoffice bootstrap', 'controller' => 'Modules\Ap\Http\Controllers\ApplicationAuthController', 'function' => 'bootstrap', 'module_code' => 'ap', 'requires_permission' => false, 'sort_order' => 20],
            ['action_code' => 'auth.logout', 'name' => 'Backoffice logout', 'controller' => 'Modules\Ap\Http\Controllers\ApplicationAuthController', 'function' => 'logout', 'module_code' => 'ap', 'requires_permission' => false, 'sort_order' => 30],

            ['action_code' => 'system.actions', 'name' => 'Action registry', 'controller' => 'Modules\Gp\Http\Controllers\SystemActionController', 'function' => 'index', 'module_code' => 'gp', 'route' => '/settings/actions', 'is_menu' => true, 'sort_order' => 100],
            ['action_code' => 'system.menu', 'name' => 'Menu tree', 'controller' => 'Modules\Gp\Http\Controllers\NavigationController', 'function' => 'menu', 'module_code' => 'gp', 'requires_permission' => false, 'sort_order' => 110],
            ['action_code' => 'system.dictionaries', 'name' => 'Dictionary options', 'controller' => 'Modules\Gp\Http\Controllers\DictionaryController', 'function' => 'options', 'module_code' => 'gp', 'route' => '/settings/dictionaries', 'is_menu' => true, 'requires_permission' => false, 'sort_order' => 120],
            ['action_code' => 'system.organizations', 'name' => 'Organizations', 'controller' => 'Modules\Gp\Http\Controllers\OrganizationController', 'function' => 'index', 'module_code' => 'gp', 'route' => '/settings/organizations', 'is_menu' => true, 'sort_order' => 130],
            ['action_code' => 'system.branches', 'name' => 'Branches', 'controller' => 'Modules\Gp\Http\Controllers\BranchController', 'function' => 'index', 'module_code' => 'gp', 'route' => '/settings/branches', 'is_menu' => true, 'sort_order' => 140],

            ['action_code' => 'admin.roles', 'name' => 'Role list', 'controller' => 'Modules\Ad\Http\Controllers\RoleAdminController', 'function' => 'index', 'module_code' => 'ad', 'route' => '/admin/roles', 'is_menu' => true, 'sort_order' => 200],
            ['action_code' => 'admin.users', 'name' => 'User list', 'controller' => 'Modules\Ad\Http\Controllers\UserAdminController', 'function' => 'index', 'module_code' => 'ad', 'route' => '/admin/users', 'is_menu' => true, 'sort_order' => 210],
            ['action_code' => 'admin.automation', 'name' => 'Automation rules', 'controller' => 'Modules\Ad\Http\Controllers\AutomationController', 'function' => 'index', 'module_code' => 'ad', 'route' => '/admin/automation', 'is_menu' => true, 'sort_order' => 220],
            ['action_code' => 'admin.notifications', 'name' => 'Notifications', 'controller' => 'Modules\Ad\Http\Controllers\NotificationController', 'function' => 'index', 'module_code' => 'ad', 'route' => '/admin/notifications', 'is_menu' => true, 'sort_order' => 230],

            ['action_code' => 'customer.list', 'name' => 'Customer list', 'controller' => 'Modules\Cr\Http\Controllers\CustomerRegistryController', 'function' => 'index', 'module_code' => 'cr', 'route' => '/customers', 'is_menu' => true, 'sort_order' => 300],
            ['action_code' => 'cargo.dashboard', 'name' => 'Cargo dashboard', 'controller' => 'Modules\Ca\Http\Controllers\CaShipmentController', 'function' => 'dashboard', 'module_code' => 'ca', 'route' => '/cargo/dashboard', 'is_menu' => true, 'sort_order' => 400],
            ['action_code' => 'cargo.shipments', 'name' => 'Shipment list', 'controller' => 'Modules\Ca\Http\Controllers\CaShipmentController', 'function' => 'index', 'module_code' => 'ca', 'route' => '/cargo/shipments', 'is_menu' => true, 'sort_order' => 410],
            ['action_code' => 'pos.dashboard', 'name' => 'POS dashboard', 'controller' => 'Modules\Pos\Http\Controllers\PosSaleController', 'function' => 'dashboard', 'module_code' => 'pos', 'route' => '/pos/dashboard', 'is_menu' => true, 'sort_order' => 500],
            ['action_code' => 'report.templates', 'name' => 'Report templates', 'controller' => 'Modules\Re\Http\Controllers\ReportTemplateController', 'function' => 'index', 'module_code' => 're', 'route' => '/reports/templates', 'is_menu' => true, 'sort_order' => 600],
        ];
    }
}
