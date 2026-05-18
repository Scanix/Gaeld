<?php

namespace Tests\Feature\Organizations;

use App\Domains\Organizations\Enums\OrganizationModule;
use Tests\TestCase;

class OrganizationModulePresetsTest extends TestCase
{
    public function test_freelancer_preset_enables_fiduciary_export(): void
    {
        $presets = OrganizationModule::presets();

        $this->assertTrue(
            $presets['freelancer']['fiduciary_export'],
            'Freelancers must have fiduciary_export enabled so they can hand off their accounts to an accountant.'
        );
    }

    public function test_fiduciary_preset_enables_fiduciary_export(): void
    {
        $presets = OrganizationModule::presets();

        $this->assertTrue($presets['fiduciary']['fiduciary_export']);
    }

    public function test_sme_preset_does_not_enable_fiduciary_export(): void
    {
        // Guard: changing SME must be a deliberate decision, not a side effect.
        $presets = OrganizationModule::presets();

        $this->assertFalse(
            $presets['sme']['fiduciary_export'],
            'SME fiduciary_export preset was changed unexpectedly. Update this test intentionally if you meant to change it.'
        );
    }

    public function test_all_presets_contain_the_same_module_keys(): void
    {
        $presets = OrganizationModule::presets();
        $keys = array_keys(reset($presets));

        foreach ($presets as $type => $modules) {
            $this->assertSame(
                $keys,
                array_keys($modules),
                "Preset '{$type}' is missing module keys or has extras compared to the first preset."
            );
        }
    }
}
