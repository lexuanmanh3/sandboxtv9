<?php

namespace Tests\Unit;

use App\Services\Authorization\MenuBuilder;
use PHPUnit\Framework\TestCase;

class MenuBuilderTest extends TestCase
{
    private array $menu = [
        ['label' => 'Quản trị', 'order' => 20, 'children' => [
            ['label' => 'Vai trò', 'permission' => 'role.view', 'order' => 20],
            ['label' => 'Tài khoản', 'permission' => 'account.view', 'order' => 10],
        ]],
        ['label' => 'Báo cáo', 'permission' => 'report.view', 'order' => 10],
    ];

    public function test_authorized_leaf_and_its_parent_are_visible(): void
    {
        $menu = (new MenuBuilder())->build($this->menu, ['role.view']);

        $this->assertSame('Quản trị', $menu[0]['label']);
        $this->assertSame(['Vai trò'], array_column($menu[0]['children'], 'label'));
    }

    public function test_unauthorized_leaf_and_empty_parent_are_hidden(): void
    {
        $this->assertSame([], (new MenuBuilder())->build($this->menu, []));
    }

    public function test_items_are_sorted_by_order(): void
    {
        $menu = (new MenuBuilder())->build($this->menu, ['role.view', 'account.view', 'report.view']);

        $this->assertSame(['Báo cáo', 'Quản trị'], array_column($menu, 'label'));
        $this->assertSame(['Tài khoản', 'Vai trò'], array_column($menu[1]['children'], 'label'));
    }
}
