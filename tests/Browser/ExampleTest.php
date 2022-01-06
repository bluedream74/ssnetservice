<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ExampleTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * A basic browser test example.
     *
     * @return void
     */
    public function testBasicExample()
    {

        $this->browse(function ($browser) {
            $browser->visit('https://www.mimatsu-group.co.jp/contact/')
                    ->click('input.submitBtn')
                    ->assertSee('ありがとうございま');
        });
    }
}
