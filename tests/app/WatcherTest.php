<?php

namespace Tests\App;

use App\Watcher;
use App\WatcherLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WatcherTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function itWillGetLastLog(): void
    {
        $watcher = factory(Watcher::class)->create();

        factory(WatcherLog::class)->create([
            'watcher_id' => $watcher->id,
            'created_at' => Carbon::now()->subDay(),
        ]);
        $lastLog = factory(WatcherLog::class)->create([
            'watcher_id' => $watcher->id,
        ]);
        factory(WatcherLog::class)->create([
            'watcher_id' => $watcher->id,
            'created_at' => Carbon::now()->subMinute(),
        ]);
        factory(WatcherLog::class)->create([
            'watcher_id' => $watcher->id,
            'created_at' => Carbon::now()->subHour(),
        ]);

        $this->assertEquals($lastLog->fresh(), $watcher->lastLog());
    }

    /** @test */
    public function itWillReturnNullIfNotLog(): void
    {
        $watcher = factory(Watcher::class)->create();

        $this->assertNull($watcher->lastLog());
    }

    /** @test */
    public function itCanParseDomainFromUrl(): void
    {
        $watcher = factory(Watcher::class)->create([
            'url' => 'https://www.foobar.com/something/else/index.html',
        ]);

        $this->assertEquals('foobar.com', $watcher->urlDomain());
    }

    /** @test */
    public function itReturnsStatusErrorIfLastLogHasError(): void
    {
        $log = factory(WatcherLog::class)->create([
            'error' => 'Some error'
        ]);

        $this->assertEquals('error', $log->watcher->status);
    }

    /** @test */
    public function itReturnsStatusDisabledIfNoIntervalSet(): void
    {
        $watcher = factory(Watcher::class)->state('disabled')->create();

        $this->assertEquals('disabled', $watcher->status);
    }

    /** @test */
    public function itReturnsStatusOkayIfNoLastLogErrorAndIntervalHasMinutesSet(): void
    {
        $watcher = factory(Watcher::class)->create();

        $this->assertEquals('ok', $watcher->status);
    }
}
