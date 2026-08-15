<?php

namespace Tests\Feature;

use App\Http\Middleware\ForceAdminLocale;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminLocalizationTest extends TestCase
{
    public function test_admin_middleware_forces_arabic_and_rtl_only_during_the_request(): void
    {
        app()->setLocale('en');

        $localeDuringRequest = null;
        $directionDuringRequest = null;

        (new ForceAdminLocale)->handle(
            Request::create('/admin'),
            function () use (&$localeDuringRequest, &$directionDuringRequest) {
                $localeDuringRequest = app()->getLocale();
                $directionDuringRequest = __('filament-panels::layout.direction');

                return response('ok');
            },
        );

        $this->assertSame('ar', $localeDuringRequest);
        $this->assertSame('rtl', $directionDuringRequest);
        $this->assertSame('en', app()->getLocale());
    }

    public function test_shield_permission_labels_are_localized(): void
    {
        $this->assertTrue(config('filament-shield.localization.enabled'));
    }
}
