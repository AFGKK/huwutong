<?php

namespace Tests\Unit\Services;

use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\Customer;
use App\Models\License;
use App\Models\Tenant;
use App\Services\CustomFieldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomFieldServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CustomFieldService $service;
    protected Tenant $tenant;
    protected License $license;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(CustomFieldService::class);

        $this->tenant = Tenant::factory()->create();
        $this->license = License::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_it_creates_field_definition(): void
    {
        $def = $this->service->createDefinition([
            'name' => 'Industry',
            'field_type' => 'select',
            'options' => ['Education', 'Medical', 'Finance'],
            'applies_to' => ['customer', 'license'],
            'group' => 'Business',
            'sort_order' => 10,
        ], $this->tenant->id);

        $this->assertNotNull($def);
        $this->assertEquals('Industry', $def->name);
        $this->assertEquals('industry', $def->slug);
        $this->assertEquals(['customer', 'license'], $def->applies_to);
        $this->assertEquals(['Education', 'Medical', 'Finance'], $def->options);
    }

    public function test_it_updates_field_definition(): void
    {
        $def = $this->service->createDefinition([
            'name' => 'Department',
            'field_type' => 'text',
            'applies_to' => ['license'],
        ], $this->tenant->id);

        $updated = $this->service->updateDefinition($def, [
            'name' => 'Department Name',
            'is_required' => true,
            'applies_to' => ['license', 'customer'],
        ]);

        $this->assertEquals('Department Name', $updated->name);
        $this->assertTrue($updated->is_required);
        $this->assertEquals(['license', 'customer'], $updated->applies_to);
    }

    public function test_it_deletes_field_definition_and_values(): void
    {
        $def = $this->service->createDefinition([
            'name' => 'Test Field',
            'field_type' => 'text',
            'applies_to' => ['license'],
        ], $this->tenant->id);

        CustomFieldValue::create([
            'field_definition_id' => $def->id,
            'fieldable_id' => $this->license->id,
            'fieldable_type' => get_class($this->license),
            'value' => 'test',
        ]);

        $this->service->deleteDefinition($def);

        $this->assertNull(CustomFieldDefinition::find($def->id));
        $this->assertEquals(0, CustomFieldValue::where('field_definition_id', $def->id)->count());
    }

    public function test_it_gets_values_for_entity(): void
    {
        $def = $this->service->createDefinition([
            'name' => 'Deploy Type',
            'field_type' => 'select',
            'options' => ['SaaS', 'On-Prem', 'Hybrid'],
            'applies_to' => ['license'],
        ], $this->tenant->id);

        CustomFieldValue::create([
            'field_definition_id' => $def->id,
            'fieldable_id' => $this->license->id,
            'fieldable_type' => get_class($this->license),
            'value' => 'On-Prem',
        ]);

        $values = $this->service->getValues($this->license);

        $this->assertCount(1, $values);
        $this->assertEquals('Deploy Type', $values->first()->name);
        // value is set on the model as dynamic attribute via getForEntity
        $this->assertEquals('On-Prem', $values->first()->value);
    }

    public function test_it_filters_entity_types(): void
    {
        $this->service->createDefinition([
            'name' => 'License Field',
            'field_type' => 'text',
            'applies_to' => ['license'],
        ], $this->tenant->id);

        $this->service->createDefinition([
            'name' => 'Customer Field',
            'field_type' => 'text',
            'applies_to' => ['customer'],
        ], $this->tenant->id);

        $licenseDefs = CustomFieldDefinition::getForTenant($this->tenant->id, 'license');
        $customerDefs = CustomFieldDefinition::getForTenant($this->tenant->id, 'customer');

        $this->assertCount(1, $licenseDefs);
        $this->assertEquals('License Field', $licenseDefs->first()->name);

        $this->assertCount(1, $customerDefs);
        $this->assertEquals('Customer Field', $customerDefs->first()->name);
    }

    public function test_it_updates_field_values(): void
    {
        $def = $this->service->createDefinition([
            'name' => 'Seat Count',
            'field_type' => 'number',
            'is_required' => false,
            'applies_to' => ['license'],
        ], $this->tenant->id);

        $this->service->updateValues($this->license, [
            (string) $def->id => '50',
        ]);

        $values = $this->service->getValues($this->license);
        $this->assertCount(1, $values);
        $this->assertEquals('50', $values->first()->value);
    }

    public function test_it_clears_value_when_empty(): void
    {
        $def = $this->service->createDefinition([
            'name' => 'Note',
            'field_type' => 'textarea',
            'applies_to' => ['customer'],
        ], $this->tenant->id);

        $this->service->updateValues($this->customer, [
            (string) $def->id => 'some note',
        ]);

        $values = $this->service->getValues($this->customer);
        $this->assertCount(1, $values);
        $this->assertEquals('some note', $values->first()->value);

        $this->service->updateValues($this->customer, [
            (string) $def->id => '',
        ]);

        $values = $this->service->getValues($this->customer);
        $this->assertCount(1, $values);
        $this->assertNull($values->first()->value);
    }

    public function test_it_gets_values_for_customer(): void
    {
        $def = $this->service->createDefinition([
            'name' => 'Customer Level',
            'field_type' => 'text',
            'applies_to' => ['customer'],
        ], $this->tenant->id);

        CustomFieldValue::create([
            'field_definition_id' => $def->id,
            'fieldable_id' => $this->customer->id,
            'fieldable_type' => get_class($this->customer),
            'value' => 'VIP',
        ]);

        $customerValues = $this->service->getValues($this->customer);
        $this->assertCount(1, $customerValues);
        $this->assertEquals('VIP', $customerValues->first()->value);
    }

    public function test_it_validates_select_field(): void
    {
        $def = $this->service->createDefinition([
            'name' => 'Type',
            'field_type' => 'select',
            'options' => ['A', 'B', 'C'],
            'is_required' => true,
        ], $this->tenant->id);

        $this->assertTrue($this->service->validateFieldValue($def, 'A', false));
        $this->assertFalse($this->service->validateFieldValue($def, 'D', false));
        $this->assertFalse($this->service->validateFieldValue($def, null, false));
    }

    public function test_it_copies_values(): void
    {
        $def = $this->service->createDefinition([
            'name' => 'Copy Test',
            'field_type' => 'text',
            'applies_to' => ['license'],
        ], $this->tenant->id);

        CustomFieldValue::create([
            'field_definition_id' => $def->id,
            'fieldable_id' => $this->license->id,
            'fieldable_type' => get_class($this->license),
            'value' => 'original',
        ]);

        $targetLicense = License::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->service->copyValues($this->license, $targetLicense);

        $targetValues = $this->service->getValues($targetLicense);
        $this->assertCount(1, $targetValues);
        $this->assertEquals('original', $targetValues->first()->value);
    }

    public function test_it_filters_entities_by_field(): void
    {
        $def = $this->service->createDefinition([
            'name' => 'Channel',
            'field_type' => 'select',
            'options' => ['Direct', 'Partner', 'Affiliate'],
            'applies_to' => ['customer'],
        ], $this->tenant->id);

        CustomFieldValue::create([
            'field_definition_id' => $def->id,
            'fieldable_id' => $this->customer->id,
            'fieldable_type' => get_class($this->customer),
            'value' => 'Direct',
        ]);

        $result = $this->service->filterEntitiesByFields('customer', ['channel' => 'Direct']);

        $this->assertCount(1, $result);
        $this->assertEquals($this->customer->id, $result[0]);
    }
}
