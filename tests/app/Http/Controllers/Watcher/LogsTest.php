<?php

namespace Tests\App\Http\Controllers\Watcher;

use App\Watcher;
use App\WatcherLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function itReturnsLogs(): void
    {
        $watcher = factory(Watcher::class)->create();
        $logs = factory(WatcherLog::class, 3)->create([
            'watcher_id' => $watcher->id
        ]);

        $this->actingAs($watcher->user)->get(route('watcher.logs', $watcher))
            ->assertSuccessful()
            ->assertJsonCount(3)
            ->assertJson($logs->toArray());
    }

    /** @test */
    public function itReturnsLimitedLogs(): void
    {
        $watcher = factory(Watcher::class)->create();
        factory(WatcherLog::class, 10)->create([
            'watcher_id' => $watcher->id
        ]);

        $this->actingAs($watcher->user)->get(route('watcher.logs', [
            'watcher' => $watcher->id,
            'limit' => 5,
        ]))
            ->assertSuccessful()
            ->assertJsonCount(5);
    }
}
