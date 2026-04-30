<?php

namespace Tests\Feature;

use Tests\TestCase;

class UitvShortcutTest extends TestCase
{
    public function test_uitv_redirects_to_herdenkingsportaal(): void
    {
        $response = $this->get('/uitv');

        $response->assertStatus(302);
        $this->assertSame(
            'https://herdenkingsportaal.nl/uitv',
            $response->headers->get('Location'),
            'havun.nl/uitv moet 302-redirecten naar herdenkingsportaal.nl/uitv (afstandsbediening-vriendelijke korte URL).'
        );
    }
}
