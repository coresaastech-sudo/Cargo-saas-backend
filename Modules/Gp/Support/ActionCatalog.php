<?php

namespace Modules\Gp\Support;

class ActionCatalog
{
    public static function modules(): array
    {
        return self::withCrud([
            ['module_code' => 'ap', 'name' => 'Application', 'sort_order' => 10],
            ['module_code' => 'gp', 'name' => 'System Settings', 'sort_order' => 20],
            ['module_code' => 'ad', 'name' => 'Administration', 'sort_order' => 30],
            ['module_code' => 'cr', 'name' => 'Customers', 'sort_order' => 40],
            ['module_code' => 'ca', 'name' => 'Cargo', 'sort_order' => 50],
            ['module_code' => 'pos', 'name' => 'POS', 'sort_order' => 60],
            ['module_code' => 're', 'name' => 'Reports', 'sort_order' => 70],
            ['module_code' => 'gl', 'name' => 'Ledger', 'sort_order' => 80],
        ]);
    }

    public static function actions(): array
    {
        return [
            self::action('auth.login', 'Backoffice login', 'ap', 'Modules\Ap\Http\Controllers\ApplicationAuthController', 'login', false, false, false, null, null, null, 10),
            self::action('auth.bootstrap', 'Backoffice bootstrap', 'ap', 'Modules\Ap\Http\Controllers\ApplicationAuthController', 'bootstrap', true, false, false, null, null, null, 20),
            self::action('auth.logout', 'Backoffice logout', 'ap', 'Modules\Ap\Http\Controllers\ApplicationAuthController', 'logout', true, false, false, null, null, null, 30),

            self::menu('system.menu', 'Menu tree', 'gp', 'Modules\Gp\Http\Controllers\NavigationController', 'menu', null, 'System', null, 90, false),
            self::menu('system.organizations', 'Organizations', 'gp', 'Modules\Gp\Http\Controllers\OrganizationController', 'index', '/settings/organizations', 'Organization registry', 'Тохиргоо', 100),
            self::menu('system.branches', 'Branches', 'gp', 'Modules\Gp\Http\Controllers\BranchController', 'index', '/settings/branches', 'Organization registry', 'Тохиргоо', 110),
            self::menu('system.actions', 'Action registry', 'gp', 'Modules\Gp\Http\Controllers\SystemActionController', 'index', '/settings/actions', 'System', 'Систем', 120),
            self::menu('system.modules', 'Module list', 'gp', 'Modules\Gp\Http\Controllers\SystemModuleController', 'index', '/settings/modules', 'System', 'Систем', 130),
            self::menu('system.dictionaries', 'Dictionaries', 'gp', 'Modules\Gp\Http\Controllers\DictionaryController', 'index', '/settings/dictionaries', 'System', 'Систем', 140),
            self::action('system.dictionaries.options', 'Dictionary options', 'gp', 'Modules\Gp\Http\Controllers\DictionaryController', 'getDictionary', true, false, false, null, 'System', 'Систем', 141),
            self::menu('system.sequences', 'Sequences', 'gp', 'Modules\Gp\Http\Controllers\SequenceController', 'index', '/settings/sequences', 'System', 'Систем', 150),
            self::menu('system.suspensions', 'Suspensions', 'gp', 'Modules\Gp\Http\Controllers\SuspensionController', 'index', '/settings/suspensions', 'System', 'Систем', 160),
            self::menu('system.providers', 'Provider configs', 'gp', 'Modules\Gp\Http\Controllers\ProviderConfigController', 'index', '/settings/providers', 'System', 'Систем', 170),
            self::menu('system.response-codes', 'Response codes', 'gp', 'Modules\Gp\Http\Controllers\ResponseCodeController', 'index', '/settings/response-codes', 'System', 'Систем', 180),
            self::menu('system.whitelabel', 'Whitelabel', 'gp', 'Modules\Gp\Http\Controllers\WhiteLabelController', 'index', '/settings/whitelabel', 'System', 'Систем', 190),
            self::menu('system.mail-configs', 'Mail configs', 'gp', 'Modules\Gp\Http\Controllers\MailConfigController', 'index', '/settings/mail-configs', 'System', 'Систем', 200),
            self::menu('system.service-types', 'Cargo service types', 'gp', 'Modules\Gp\Http\Controllers\ServiceTypeController', 'index', '/settings/service-types', 'Cargo setup', 'Тариф', 210),
            self::menu('system.tariffs', 'Cargo tariffs', 'gp', 'Modules\Gp\Http\Controllers\CargoTariffController', 'index', '/settings/tariffs', 'Cargo setup', 'Тариф', 220),
            self::menu('system.service-fees', 'Cargo service fees', 'gp', 'Modules\Gp\Http\Controllers\ServiceFeeController', 'index', '/settings/service-fees', 'Cargo setup', 'Тариф', 230),
            self::menu('system.jobs', 'System jobs', 'gp', 'Modules\Gp\Http\Controllers\SystemJobController', 'index', '/settings/jobs', 'System', 'Систем', 240),
            self::menu('system.permission-matrix', 'Permission matrix', 'gp', 'Modules\Gp\Http\Controllers\PermissionMatrixController', 'index', '/settings/permissions', 'System', 'Систем', 250),
            self::menu('system.files', 'File assets', 'gp', 'Modules\Gp\Http\Controllers\FileAssetController', 'index', '/settings/files', 'System', 'Систем', 260),
            self::menu('system.photos', 'Photo assets', 'gp', 'Modules\Gp\Http\Controllers\PhotoAssetController', 'index', '/settings/photos', 'System', 'Систем', 270),
            self::menu('system.user-delegates', 'User delegates', 'gp', 'Modules\Gp\Http\Controllers\UserDelegateController', 'index', '/settings/user-delegates', 'Organization registry', 'Тохиргоо', 280),
            self::menu('system.audit-logs', 'Audit logs', 'gp', 'Modules\Gp\Http\Controllers\AuditLogController', 'index', '/settings/logs/audit', 'System logs', 'Системийн лог', 300),
            self::menu('system.request-logs', 'Request logs', 'gp', 'Modules\Gp\Http\Controllers\RequestLogController', 'index', '/settings/logs/requests', 'System logs', 'Системийн лог', 310),
            self::menu('system.change-logs', 'Change logs', 'gp', 'Modules\Gp\Http\Controllers\ChangeLogController', 'index', '/settings/logs/changes', 'System logs', 'Системийн лог', 320),
            self::menu('system.error-logs', 'Error logs', 'gp', 'Modules\Gp\Http\Controllers\ErrorLogController', 'index', '/settings/logs/errors', 'System logs', 'Системийн лог', 330),
            self::menu('system.failed-jobs', 'Failed jobs', 'gp', 'Modules\Gp\Http\Controllers\FailedJobLogController', 'index', '/settings/logs/failed-jobs', 'System logs', 'Системийн лог', 340),
            self::menu('system.email-logs', 'Email logs', 'gp', 'Modules\Gp\Http\Controllers\EmailLogController', 'index', '/settings/logs/emails', 'System logs', 'Системийн лог', 350),

            self::menu('admin.users', 'User list', 'ad', 'Modules\Ad\Http\Controllers\UserAdminController', 'index', '/admin/users', 'Users', 'Хэрэглэгч', 400),
            self::menu('admin.roles', 'Role list', 'ad', 'Modules\Ad\Http\Controllers\RoleAdminController', 'index', '/admin/roles', 'Users', 'Хэрэглэгч', 410),
            self::menu('admin.user-roles', 'User roles', 'ad', 'Modules\Ad\Http\Controllers\UserRoleAdminController', 'index', '/admin/user-roles', 'Users', 'Хэрэглэгч', 420),
            self::menu('admin.secrets', 'Secret policies', 'ad', 'Modules\Ad\Http\Controllers\SecretPolicyController', 'index', '/admin/secrets', 'Users', 'Хэрэглэгч', 430),
            self::menu('admin.report-permissions', 'Report permissions', 'ad', 'Modules\Ad\Http\Controllers\ReportPermissionController', 'index', '/admin/report-permissions', 'Users', 'Хэрэглэгч', 440),
            self::menu('admin.operators', 'Operators', 'ad', 'Modules\Ad\Http\Controllers\OperatorController', 'index', '/admin/operators', 'Operators', 'Оператор', 450),
            self::menu('admin.notifications', 'Notifications', 'ad', 'Modules\Ad\Http\Controllers\NotificationController', 'index', '/admin/notifications', 'Notifications', 'Мэдэгдэл', 460),
            self::menu('admin.notification-templates', 'Notification templates', 'ad', 'Modules\Ad\Http\Controllers\NotificationTemplateController', 'index', '/admin/notification-templates', 'Notifications', 'Мэдэгдэл', 470),
            self::menu('admin.notification-outbox', 'Notification outbox', 'ad', 'Modules\Ad\Http\Controllers\NotificationOutboxController', 'index', '/admin/notification-outbox', 'Notifications', 'Мэдэгдэл', 480),
            self::menu('admin.automation', 'Automation rules', 'ad', 'Modules\Ad\Http\Controllers\AutomationController', 'index', '/admin/automation', 'Automation', 'Автоматжуулалт', 490),
            self::menu('admin.automation-runs', 'Automation runs', 'ad', 'Modules\Ad\Http\Controllers\AutomationRunController', 'index', '/admin/automation-runs', 'Automation', 'Автоматжуулалт', 500),
            self::menu('admin.receipt-configs', 'Receipt configs', 'ad', 'Modules\Ad\Http\Controllers\ReceiptConfigController', 'index', '/admin/receipt-configs', 'Receipts', 'И-Баримт', 510),
            self::menu('admin.receipt-logs', 'Receipt logs', 'ad', 'Modules\Ad\Http\Controllers\ReceiptLogController', 'index', '/admin/receipt-logs', 'Receipts', 'И-Баримт', 520),
            self::menu('admin.settlement-accounts', 'Settlement accounts', 'ad', 'Modules\Ad\Http\Controllers\SettlementAccountController', 'index', '/admin/settlement-accounts', 'Settlement', 'Данс', 530),
            self::menu('admin.email-blacklists', 'Email blacklist', 'ad', 'Modules\Ad\Http\Controllers\EmailBlacklistController', 'index', '/admin/email-blacklists', 'Notifications', 'Мэдэгдэл', 540),

            self::menu('application.profiles', 'Portal profiles', 'ap', 'Modules\Ap\Http\Controllers\PortalProfileController', 'index', '/application/profiles', 'Profile', 'Профайл', 600),
            self::menu('application.customer-users', 'Customer portal users', 'ap', 'Modules\Ap\Http\Controllers\PortalCustomerUserController', 'index', '/application/customer-users', 'Customer users', 'Харилцагч хэрэглэгч', 610),
            self::menu('application.services', 'Portal services', 'ap', 'Modules\Ap\Http\Controllers\PortalServiceController', 'index', '/application/services', 'Services', 'Үйлчилгээ', 620),
            self::menu('application.notifications', 'Portal notifications', 'ap', 'Modules\Ap\Http\Controllers\PortalNotificationController', 'index', '/application/notifications', 'Notifications', 'Мэдэгдэл', 630),
            self::menu('application.faqs', 'FAQ', 'ap', 'Modules\Ap\Http\Controllers\PortalFaqController', 'index', '/application/faqs', 'Content', 'Контент', 640),
            self::menu('application.private-resources', 'Private resources', 'ap', 'Modules\Ap\Http\Controllers\PortalPrivateResourceController', 'index', '/application/private-resources', 'Content', 'Контент', 650),
            self::menu('application.contracts', 'Customer contracts', 'ap', 'Modules\Ap\Http\Controllers\PortalContractController', 'index', '/application/contracts', 'Contracts', 'Гэрээ', 660),
            self::menu('application.stop-services', 'Stopped services', 'ap', 'Modules\Ap\Http\Controllers\PortalStopServiceController', 'index', '/application/stop-services', 'Services', 'Үйлчилгээ', 670),
            self::menu('application.access-tokens', 'Portal access tokens', 'ap', 'Modules\Ap\Http\Controllers\PortalAccessTokenController', 'index', '/application/access-tokens', 'Customer users', 'Харилцагч хэрэглэгч', 680),

            self::menu('customer.list', 'Customer list', 'cr', 'Modules\Cr\Http\Controllers\CustomerRegistryController', 'index', '/customers', 'Customer registry', 'Харилцагч', 700),
            self::menu('customer.addresses', 'Customer addresses', 'cr', 'Modules\Cr\Http\Controllers\CustomerAddressController', 'index', '/customers/addresses', 'Customer registry', 'Харилцагч', 710),
            self::menu('customer.contacts', 'Customer contacts', 'cr', 'Modules\Cr\Http\Controllers\CustomerContactController', 'index', '/customers/contacts', 'Customer registry', 'Харилцагч', 720),
            self::menu('customer.documents', 'Customer documents', 'cr', 'Modules\Cr\Http\Controllers\CustomerDocumentController', 'index', '/customers/documents', 'Documents', 'Баримт', 730),
            self::menu('customer.messages', 'Customer messages', 'cr', 'Modules\Cr\Http\Controllers\CustomerMessageController', 'index', '/customers/messages', 'Messaging', 'Мессеж', 740),
            self::menu('customer.relationships', 'Customer relationships', 'cr', 'Modules\Cr\Http\Controllers\CustomerRelationshipController', 'index', '/customers/relationships', 'Relations', 'Холбоо', 750),
            self::menu('customer.credentials', 'Customer credentials', 'cr', 'Modules\Cr\Http\Controllers\CustomerCredentialController', 'index', '/customers/credentials', 'Security', 'Нууцлал', 760),
            self::menu('customer.stakeholders', 'Customer stakeholders', 'cr', 'Modules\Cr\Http\Controllers\CustomerStakeholderController', 'index', '/customers/stakeholders', 'Relations', 'Холбоо', 770),
            self::menu('customer.billing-accounts', 'Customer billing accounts', 'cr', 'Modules\Cr\Http\Controllers\CustomerBillingAccountController', 'index', '/customers/billing-accounts', 'Billing', 'Төлбөр', 780),
            self::menu('customer.delivery-preferences', 'Delivery preferences', 'cr', 'Modules\Cr\Http\Controllers\CustomerDeliveryPreferenceController', 'index', '/customers/delivery-preferences', 'Cargo setup', 'Cargo', 790),
            self::menu('customer.batches', 'Customer batch imports', 'cr', 'Modules\Cr\Http\Controllers\CustomerBatchController', 'index', '/customers/batches', 'Batch', 'Багц', 800),

            self::menu('cargo.dashboard', 'Cargo dashboard', 'ca', 'Modules\Ca\Http\Controllers\CaShipmentController', 'dashboard', '/cargo/dashboard', 'Cargo', 'Cargo', 900),
            self::menu('cargo.shipments', 'Shipment list', 'ca', 'Modules\Ca\Http\Controllers\CaShipmentController', 'index', '/cargo/shipments', 'Cargo', 'Cargo', 910),
            self::menu('pos.dashboard', 'POS dashboard', 'pos', 'Modules\Pos\Http\Controllers\PosSaleController', 'dashboard', '/pos/dashboard', 'POS', 'POS', 1000),

            self::menu('report.templates', 'Report templates', 're', 'Modules\Re\Http\Controllers\ReportTemplateController', 'index', '/reports/templates', 'Report builder', 'Тайлан', 1100),
            self::menu('report.datasets', 'Report datasets', 're', 'Modules\Re\Http\Controllers\ReportDatasetController', 'index', '/reports/datasets', 'Report builder', 'Тайлан', 1110),
            self::menu('report.fields', 'Report fields', 're', 'Modules\Re\Http\Controllers\ReportFieldController', 'index', '/reports/fields', 'Report builder', 'Тайлан', 1120),
            self::menu('report.contents', 'Report contents', 're', 'Modules\Re\Http\Controllers\ReportContentController', 'index', '/reports/contents', 'Report builder', 'Тайлан', 1130),
            self::menu('report.dimensions', 'Report dimensions', 're', 'Modules\Re\Http\Controllers\ReportDimensionController', 'index', '/reports/dimensions', 'Report builder', 'Тайлан', 1140),
            self::menu('report.parameters', 'Report parameters', 're', 'Modules\Re\Http\Controllers\ReportParameterController', 'index', '/reports/parameters', 'Report builder', 'Тайлан', 1150),
            self::menu('report.parameter-options', 'Report parameter options', 're', 'Modules\Re\Http\Controllers\ReportParameterOptionController', 'index', '/reports/parameter-options', 'Report builder', 'Тайлан', 1160),
            self::menu('report.exports', 'Report exports', 're', 'Modules\Re\Http\Controllers\ReportExportController', 'index', '/reports/exports', 'Exports', 'Экспорт', 1170),
            self::menu('report.run-logs', 'Report run logs', 're', 'Modules\Re\Http\Controllers\ReportRunLogController', 'index', '/reports/run-logs', 'Logs', 'Лог', 1180),

            self::menu('ledger.account-groups', 'Ledger account groups', 'gl', 'Modules\Gl\Http\Controllers\LedgerAccountGroupController', 'index', '/ledger/account-groups', 'Ledger setup', 'Ledger', 1200),
            self::menu('ledger.accounts', 'Ledger accounts', 'gl', 'Modules\Gl\Http\Controllers\LedgerAccountController', 'index', '/ledger/accounts', 'Ledger setup', 'Ledger', 1210),
            self::menu('ledger.charts', 'Ledger charts', 'gl', 'Modules\Gl\Http\Controllers\LedgerChartController', 'index', '/ledger/charts', 'Ledger setup', 'Ledger', 1220),
            self::menu('ledger.inquiry', 'Ledger inquiry', 'gl', 'Modules\Gl\Http\Controllers\LedgerInquiryController', 'index', '/ledger/inquiry', 'Inquiry', 'Лавлагаа', 1230),
            self::menu('ledger.transactions', 'Ledger transactions', 'gl', 'Modules\Gl\Http\Controllers\LedgerTransactionController', 'index', '/ledger/transactions', 'Transactions', 'Гүйлгээ', 1240),
            self::menu('ledger.posting-rules', 'Posting rules', 'gl', 'Modules\Gl\Http\Controllers\LedgerPostingRuleController', 'index', '/ledger/posting-rules', 'Ledger setup', 'Ledger', 1250),
            self::menu('ledger.report-configs', 'Ledger report configs', 'gl', 'Modules\Gl\Http\Controllers\LedgerReportConfigController', 'index', '/ledger/report-configs', 'Reports', 'Тайлан', 1260),
            self::menu('ledger.report-columns', 'Ledger report columns', 'gl', 'Modules\Gl\Http\Controllers\LedgerReportColumnController', 'index', '/ledger/report-columns', 'Reports', 'Тайлан', 1270),
            self::menu('ledger.report-runs', 'Ledger report runs', 'gl', 'Modules\Gl\Http\Controllers\LedgerReportRunController', 'index', '/ledger/report-runs', 'Reports', 'Тайлан', 1280),
        ];
    }

    private static function menu(
        string $code,
        string $name,
        string $module,
        string $controller,
        string $function,
        ?string $route,
        ?string $groupCode,
        ?string $groupName,
        int $sortOrder,
        bool $requiresPermission = true,
    ): array {
        return self::action($code, $name, $module, $controller, $function, true, $requiresPermission, true, $route, $groupCode, $groupName, $sortOrder);
    }

    private static function action(
        string $code,
        string $name,
        string $module,
        string $controller,
        string $function,
        bool $requiresAuth = true,
        bool $requiresPermission = true,
        bool $isMenu = false,
        ?string $route = null,
        ?string $groupCode = null,
        ?string $groupName = null,
        int $sortOrder = 0,
    ): array {
        return [
            'action_code' => $code,
            'name' => $name,
            'controller' => $controller,
            'function' => $function,
            'module_code' => $module,
            'route' => $route,
            'group_code' => $groupCode,
            'group_name' => $groupName,
            'is_menu' => $isMenu,
            'requires_auth' => $requiresAuth,
            'requires_permission' => $requiresPermission,
            'sort_order' => $sortOrder,
        ];
    }

    private static function withCrud(array $actions): array
    {
        $expanded = [];

        foreach ($actions as $action) {
            $expanded[] = $action;

            if (($action['function'] ?? null) !== 'index') {
                continue;
            }

            foreach (['show', 'store', 'update', 'destroy'] as $offset => $function) {
                if (! method_exists($action['controller'], $function)) {
                    continue;
                }

                $expanded[] = array_merge($action, [
                    'action_code' => $action['action_code'].'.'.$function,
                    'name' => $action['name'].' '.ucfirst($function),
                    'function' => $function,
                    'route' => null,
                    'is_menu' => false,
                    'sort_order' => ($action['sort_order'] ?? 0) + $offset + 1,
                ]);
            }
        }

        return $expanded;
    }
}
