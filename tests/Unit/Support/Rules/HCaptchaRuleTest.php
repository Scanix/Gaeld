<?php

namespace Tests\Unit\Support\Rules;

use App\Support\Rules\HCaptchaRule;
use Tests\TestCase;

class HCaptchaRuleTest extends TestCase
{
    public function test_rules_are_nullable_when_no_site_key_is_configured(): void
    {
        config()->set('services.hcaptcha.site_key', '');

        $this->assertSame(['nullable', 'string'], HCaptchaRule::rules());
    }

    public function test_rules_are_required_when_site_key_is_configured(): void
    {
        config()->set('services.hcaptcha.site_key', 'site-key-abc');

        $rules = HCaptchaRule::rules();

        $this->assertSame('required', $rules[0]);
        $this->assertSame('string', $rules[1]);
        $this->assertInstanceOf(HCaptchaRule::class, $rules[2]);
    }
}
