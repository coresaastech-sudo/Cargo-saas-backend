<?php

namespace Tests\Unit;

use Modules\Gp\Support\ActionCatalog;
use PHPUnit\Framework\TestCase;

class ActionCatalogTest extends TestCase
{
    public function test_catalog_uses_action_codes(): void
    {
        $codes = collect(ActionCatalog::actions())->pluck('action_code')->all();

        $this->assertSameSize($codes, array_unique($codes));
        $this->assertContains('auth.login', $codes);
        $this->assertContains('system.menu', $codes);
        $this->assertContains('system.dictionaries', $codes);
        $this->assertContains('admin.automation', $codes);
        $this->assertContains('admin.notifications', $codes);
        $this->assertContains('customer.list', $codes);
        $this->assertContains('cargo.shipments', $codes);
        $this->assertContains('report.templates', $codes);
        $this->assertContains('ledger.posting-rules', $codes);
    }

    public function test_catalog_targets_existing_controllers(): void
    {
        foreach (ActionCatalog::actions() as $action) {
            $this->assertArrayHasKey('action_code', $action);
            $this->assertArrayHasKey('controller', $action);
            $this->assertArrayHasKey('function', $action);
            $this->assertFalse(str_starts_with($action['action_code'], implode('', ['p', 'c', '.'])));
            $this->assertTrue(class_exists($action['controller']), $action['controller']);
            $this->assertTrue(method_exists($action['controller'], $action['function']), $action['action_code']);
        }
    }
}
