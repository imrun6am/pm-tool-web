<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root path redirects to the dashboard (which in turn requires auth).
     */
    public function test_the_root_redirects_to_the_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('dashboard'));
    }
}
