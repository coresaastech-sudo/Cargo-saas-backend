<?php

namespace Tests\Unit;

use Modules\Gp\Support\ActionCatalog;
use PHPUnit\Framework\TestCase;

class ActionCatalogTest extends TestCase
{
    public function test_catalog_uses_action_codes(): void
    {
        $codes = collect(ActionCatalog::actions())->pluck('action_code')->all();

        $this->assertContains('auth.login', $codes);
        $this->assertContains('system.menu', $codes);
        $this->assertContains('system.dictionaries', $codes);
        $this->assertContains('admin.automation', $codes);
        $this->assertContains('admin.notifications', $codes);
        $this->assertContains('customer.list', $codes);
        $this->assertContains('cargo.shipments', $codes);
        $this->assertContains('report.templates', $codes);
    }
}
