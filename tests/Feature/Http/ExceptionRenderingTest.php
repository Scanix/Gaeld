<?php

namespace Tests\Feature\Http;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ExceptionRenderingTest extends TestCase
{
    public function test_generic_web_exceptions_render_the_inertia_error_page(): void
    {
        config(['app.debug' => false]);

        Route::get('/testing/generic-error', fn () => throw new RuntimeException('test failure'));

        $response = $this->get('/testing/generic-error');

        $response->assertStatus(500)
            ->assertInertia(fn ($page) => $page
                ->component('Error')
                ->where('status', 500));
    }
}
