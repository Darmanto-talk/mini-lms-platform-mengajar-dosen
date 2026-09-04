<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Tamu yang membuka "/" diarahkan ke dashboard, lalu ke halaman masuk.
     */
    public function test_the_application_redirects_guests_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('dashboard'));
        $this->followingRedirects()->get('/')->assertOk()->assertSee('Masuk');
    }
}
