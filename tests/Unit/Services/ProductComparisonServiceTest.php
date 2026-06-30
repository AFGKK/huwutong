<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\ProductSpecGroup;
use App\Models\ProductSpec;
use App\Models\ProductSpecValue;
use App\Services\ProductComparisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductComparisonServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductComparisonService $service;
    private Product $product1;
    private Product $product2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProductComparisonService::class);

        $this->product1 = Product::create(['name' => 'Product A', 'slug' => 'product-a']);
        $this->product2 = Product::create(['name' => 'Product B', 'slug' => 'product-b']);
    }

    public function test_can_create_spec_group()
    {
        $group = $this->service->createSpecGroup($this->product1->id, '基本参数', 0);

        $this->assertNotNull($group->id);
        $this->assertEquals('基本参数', $group->name);
        $this->assertEquals($this->product1->id, $group->product_id);
    }

    public function test_can_update_spec_group()
    {
        $group = $this->service->createSpecGroup($this->product1->id, 'Old Name', 0);

        $updated = $this->service->updateSpecGroup($group->id, ['name' => 'New Name']);

        $this->assertEquals('New Name', $updated->name);
    }

    public function test_can_delete_spec_group()
    {
        $group = $this->service->createSpecGroup($this->product1->id, 'To Delete', 0);

        $this->service->deleteSpecGroup($group->id);

        $this->assertNull(ProductSpecGroup::find($group->id));
    }

    public function test_can_create_spec()
    {
        $group = $this->service->createSpecGroup($this->product1->id, '性能', 0);

        $spec = $this->service->createSpec($group->id, [
            'label' => 'CPU',
            'type' => 'text',
            'unit' => 'GHz',
            'sort_order' => 0,
        ]);

        $this->assertNotNull($spec->id);
        $this->assertEquals('CPU', $spec->label);
        $this->assertEquals('GHz', $spec->unit);
    }

    public function test_can_update_spec()
    {
        $group = $this->service->createSpecGroup($this->product1->id, '性能', 0);
        $spec = $this->service->createSpec($group->id, ['label' => 'CPU', 'type' => 'text']);

        $updated = $this->service->updateSpec($spec->id, ['label' => '处理器', 'unit' => 'GHz']);

        $this->assertEquals('处理器', $updated->label);
        $this->assertEquals('GHz', $updated->unit);
    }

    public function test_can_delete_spec()
    {
        $group = $this->service->createSpecGroup($this->product1->id, '性能', 0);
        $spec = $this->service->createSpec($group->id, ['label' => 'RAM', 'type' => 'text']);

        $this->service->deleteSpec($spec->id);

        $this->assertNull(ProductSpec::find($spec->id));
    }

    public function test_can_set_spec_value()
    {
        $group = $this->service->createSpecGroup($this->product1->id, '性能', 0);
        $spec = $this->service->createSpec($group->id, ['label' => 'CPU', 'type' => 'text', 'unit' => 'GHz']);

        $value = $this->service->setSpecValue($this->product1->id, $spec->id, '3.2');

        $this->assertEquals('3.2', $value->value);
        $this->assertEquals('3.2 GHz', $value->formattedValue());
    }

    public function test_boolean_spec_formatted_value()
    {
        $group = $this->service->createSpecGroup($this->product1->id, '功能', 0);
        $spec = $this->service->createSpec($group->id, ['label' => '支持IPv6', 'type' => 'boolean']);

        $value = $this->service->setSpecValue($this->product1->id, $spec->id, '1');
        $this->assertEquals('✓', $value->formattedValue());

        $value2 = $this->service->setSpecValue($this->product1->id, $spec->id, '');
        $this->assertEquals('✗', $value2->formattedValue());
    }

    public function test_get_product_specs_returns_structured_data()
    {
        $group = $this->service->createSpecGroup($this->product1->id, '基本参数', 0);
        $spec = $this->service->createSpec($group->id, ['label' => 'CPU', 'type' => 'text', 'unit' => 'GHz']);
        $this->service->setSpecValue($this->product1->id, $spec->id, '2.8');

        $specs = $this->service->getProductSpecs($this->product1->id);

        $this->assertCount(1, $specs);
        $this->assertEquals('基本参数', $specs[0]['name']);
        $this->assertCount(1, $specs[0]['specs']);
        $this->assertEquals('CPU', $specs[0]['specs'][0]['label']);
        $this->assertEquals('2.8 GHz', $specs[0]['specs'][0]['formatted_value']);
    }

    public function test_compare_products()
    {
        // Set up specs for both products
        $group1 = $this->service->createSpecGroup($this->product1->id, '性能', 0);
        $spec1 = $this->service->createSpec($group1->id, ['label' => 'CPU', 'type' => 'text', 'unit' => 'GHz']);
        $this->service->setSpecValue($this->product1->id, $spec1->id, '2.8');

        // Product2 also needs the same spec via its own group
        $group1b = $this->service->createSpecGroup($this->product2->id, '性能', 0);
        $spec1b = $this->service->createSpec($group1b->id, ['label' => 'CPU', 'type' => 'text', 'unit' => 'GHz']);
        $this->service->setSpecValue($this->product2->id, $spec1b->id, '3.2');

        $group2a = $this->service->createSpecGroup($this->product1->id, '存储', 1);
        $spec2 = $this->service->createSpec($group2a->id, ['label' => 'RAM', 'type' => 'text', 'unit' => 'GB']);
        $this->service->setSpecValue($this->product1->id, $spec2->id, '8');

        $group2b = $this->service->createSpecGroup($this->product2->id, '存储', 1);
        $spec2b = $this->service->createSpec($group2b->id, ['label' => 'RAM', 'type' => 'text', 'unit' => 'GB']);
        $this->service->setSpecValue($this->product2->id, $spec2b->id, '16');

        $result = $this->service->compareProducts([$this->product1->id, $this->product2->id]);

        $this->assertCount(2, $result['products']);
        $this->assertCount(2, $result['groups']); // 性能 + 存储

        // 获取两个分组
        $groupNames = collect($result['groups'])->pluck('name')->toArray();
        $this->assertContains('性能', $groupNames);
        $this->assertContains('存储', $groupNames);

        // 找到对应的分组验证值
        $foundXingneng = false;
        $foundCunchu = false;
        foreach ($result['groups'] as $group) {
            if ($group['name'] === '性能') {
                $this->assertCount(1, $group['rows'], '性能 should have 1 row');
                $this->assertEquals('2.8 GHz', $group['rows'][0]['values'][$this->product1->id]);
                $this->assertEquals('3.2 GHz', $group['rows'][0]['values'][$this->product2->id]);
                $foundXingneng = true;
            }
            if ($group['name'] === '存储') {
                $this->assertCount(1, $group['rows'], '存储 should have 1 row');
                $this->assertEquals('8 GB', $group['rows'][0]['values'][$this->product1->id]);
                $this->assertEquals('16 GB', $group['rows'][0]['values'][$this->product2->id]);
                $foundCunchu = true;
            }
        }
        $this->assertTrue($foundXingneng, '性能 group not found');
        $this->assertTrue($foundCunchu, '存储 group not found');
    }

    public function test_compare_with_specs_from_different_products()
    {
        // product1 has spec group A with spec X
        $groupA = $this->service->createSpecGroup($this->product1->id, '网络', 0);
        $specX = $this->service->createSpec($groupA->id, ['label' => '带宽', 'type' => 'text', 'unit' => 'Mbps']);
        $this->service->setSpecValue($this->product1->id, $specX->id, '100');

        // product2 has spec group B with spec Y (different group name)
        $groupB = $this->service->createSpecGroup($this->product2->id, '存储', 0);
        $specY = $this->service->createSpec($groupB->id, ['label' => '容量', 'type' => 'text', 'unit' => 'GB']);
        $this->service->setSpecValue($this->product2->id, $specY->id, '500');

        $result = $this->service->compareProducts([$this->product1->id, $this->product2->id]);

        // Should have 2 groups (网络, 存储)
        $this->assertCount(2, $result['groups']);
    }

    public function test_can_create_comparison()
    {
        $comparison = $this->service->createComparison(
            null, 'session-123',
            [$this->product1->id, $this->product2->id],
            '我的对比',
        );

        $this->assertNotNull($comparison->id);
        $this->assertCount(2, $comparison->products);
        $this->assertEquals('session-123', $comparison->session_id);
    }

    public function test_can_get_comparison()
    {
        $comparison = $this->service->createComparison(
            null, 'session-456',
            [$this->product1->id],
        );

        $loaded = $this->service->getComparison($comparison->id);
        $this->assertNotNull($loaded);
        $this->assertCount(1, $loaded->products);
    }

    public function test_can_delete_spec_value_on_update()
    {
        $group = $this->service->createSpecGroup($this->product1->id, 'Test', 0);
        $spec = $this->service->createSpec($group->id, ['label' => 'Test', 'type' => 'text']);

        $this->service->setSpecValue($this->product1->id, $spec->id, 'value1');
        $this->service->setSpecValue($this->product1->id, $spec->id, 'value2');

        // Should be an update (same product_id + spec_id unique constraint)
        $values = ProductSpecValue::where('product_id', $this->product1->id)
            ->where('spec_id', $spec->id)
            ->get();

        $this->assertCount(1, $values);
        $this->assertEquals('value2', $values[0]->value);
    }
}
