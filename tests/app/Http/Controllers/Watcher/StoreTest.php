<?php

namespace Tests\App\Http\Controllers\Watcher;

use App\Events\WatcherCreatedOrUpdated;
use App\Region;
use App\User;
use App\Utils\Fetchers\HtmlFetcher;
use App\Watcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function itCanCreateWatcher(): void
    {
        Event::fake();
        $user = factory(User::class)->create();
        $region = factory(Region::class)->create();
        $data = [
            'name' => 'Foo',
            'url' => 'http://some-url.com/with/price',
            'query' => 'some-class',
            'client' => HtmlFetcher::CLIENT_BROWERSHOT,
            'xpath_stock' => '//div/span',
            'stock_text' => 'in stock.',
            'stock_condition' => Watcher::STOCK_CONDITION_CONTAINS_TEXT,
            'stock_alert' => true,
            'region_id' => $region->id,
        ];

        $this->actingAs($user)->postJson(route('watcher.store'), array_merge(
            $data,
            [
                'xpath_name' => 'some-class',
            ]
        ))->assertSuccessful();

        Event::assertDispatched(WatcherCreatedOrUpdated::class);

        $this->assertDatabaseHas('watchers', array_merge(
            $data,
            ['user_id' => $user->id]
        ));
    }

    /** @test */
    public function itWillAddTemplate(): void
    {
        $user = factory(User::class)->create();
        $data = [
            'name' => 'Foo',
            'url' => 'http://some-url.com/with/price',
            'query' => 'some-class',
            'client' => HtmlFetcher::CLIENT_BROWERSHOT,
            'xpath_stock' => '//div/span',
            'stock_text' => 'in stock.',
            'stock_condition' => Watcher::STOCK_CONDITION_CONTAINS_TEXT,
            'stock_alert' => true,
            'update_queries' => true,
        ];

        $this->actingAs($user)->postJson(route('watcher.store'), $data)->assertSuccessful();

        $this->assertDatabaseHas('templates', [
            'domain' => 'some-url.com',
            'xpath_value' => 'some-class',
            'user_id' => $user->id,
            'client' => HtmlFetcher::CLIENT_BROWERSHOT,
            'xpath_stock' => '//div/span',
            'stock_text' => 'in stock.',
            'stock_condition' => Watcher::STOCK_CONDITION_CONTAINS_TEXT,
        ]);
    }

    /** @test */
    public function itWillNotAddTemplate(): void
    {
        $user = factory(User::class)->create();
        $data = [
            'name' => 'Foo',
            'url' => 'http://some-url.com/with/price',
            'query' => 'some-class',
            'client' => HtmlFetcher::CLIENT_BROWERSHOT,
            'xpath_stock' => '//div/span',
            'stock_text' => 'in stock.',
            'stock_condition' => Watcher::STOCK_CONDITION_CONTAINS_TEXT,
            'stock_alert' => true,
            'update_queries' => false,
        ];

        $this->actingAs($user)->postJson(route('watcher.store'), $data)->assertSuccessful();

        $this->assertDatabaseMissing('templates', [
            'domain' => 'some-url.com',
        ]);
    }
}
