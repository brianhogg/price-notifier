<?php

namespace App\Jobs;

use App\Events\WatcherCreatedOrUpdated;
use App\Http\Resources\WatcherResource;
use App\Utils\HtmlFetcher;
use App\Utils\HtmlParser;
use App\Utils\PriceHelper;
use App\Watcher;
use App\WatcherLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateWatcher implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Watcher
     */
    private $watcher;
    private $error = null;
    private $price = null;
    private $rawPrice = null;
    private $rawStock = null;
    private $hasStock = null;

    public function __construct(Watcher $watcher)
    {
        $this->watcher = $watcher;
    }

    public function handle(): void
    {
        $startTime = Carbon::now();

        /** @var HtmlFetcher $fetcher */
        $fetcher = resolve(HtmlFetcher::class);

        try {
            $html = $fetcher->getHtmlFromUrl($this->watcher->url, $this->watcher->client);
            $parser = new HtmlParser($html);

            $this->rawPrice = $parser->nodeValueByXPathQuery($this->watcher->query);
            $this->price = PriceHelper::numbersFromText($this->rawPrice);

            $this->hasStock = $this->calculateStock($parser);

            if ($this->price) {
                $this->setLowestPrice($this->price);

                if (!$this->watcher->initial_value) {
                    $this->watcher->update([
                        'initial_value' => $this->price,
                    ]);
                }
            }

            $this->updatePriceChanges();

            $this->sendAlerts();
        } catch (Exception $e) {
            $this->error = $e->getMessage();
        }

        $this->watcher->update([
            'has_stock' => $this->hasStock,
            'last_sync' => Carbon::now(),
            'value' => $this->price,
        ]);

        event(new WatcherCreatedOrUpdated(WatcherResource::make($this->watcher)));

        $this->rawPrice = strlen($this->rawPrice) > 191
            ? substr($this->rawPrice, 0, 190)
            : $this->rawPrice;

        WatcherLog::create([
            'watcher_id' => $this->watcher->id,
            'formatted_value' => $this->price ?? null,
            'raw_value' => $this->rawPrice ?? null,
            'duration' => Carbon::now()->diffInMilliseconds($startTime),
            'region' => config('pcn.region'),
            'error' => strlen($this->error) > config('pcn.fetcher.error_max_length')
                ? substr($this->error, 0, config('pcn.fetcher.error_max_length') - 1)
                : $this->error,
            'has_stock' => $this->hasStock,
            'raw_stock' => $this->rawStock,
        ]);
    }

    private function sendAlerts()
    {
        if ($this->price && $this->watcher->value) {
            $alertPrice = number_format($this->watcher->alert_value, 2);
            $oldPrice = number_format($this->watcher->value, 2);
            $newPrice = number_format($this->price, 2);

            if ($alertPrice && $newPrice && $oldPrice !== $newPrice && $newPrice < $alertPrice) {
                SendPushoverMessage::dispatch(
                    $this->watcher->user,
                    'Price Alert!',
                    "{$this->watcher->name}\n\${$newPrice}",
                    $this->watcher->url
                );
            }
        }

        if ($this->hasStock && $this->watcher->stock_alert && $this->watcher->has_stock === false) {
            SendPushoverMessage::dispatch(
                $this->watcher->user,
                'Stock Alert!',
                "{$this->watcher->name}\n\${$this->price}",
                $this->watcher->url
            );
            if (env('SLACK_CHANNEL', false) && env('SLACK_TOKEN', false)) {
                SendSlackMessage::dispatch("Stock Alert! @here\n{$this->watcher->name}\n\${$this->price}\n{$this->watcher->url}");
            }
        }
    }

    private function setLowestPrice(string $formattedValue)
    {
        $price = number_format($formattedValue, 2);
        $lowestPrice = number_format($this->watcher->lowest_price, 2);

        if (!$this->watcher->lowest_price || $price <= $lowestPrice) {
            $this->watcher->update([
                'lowest_price' => $formattedValue,
                'lowest_at' => Carbon::now()
            ]);
        }
    }

    private function updatePriceChanges()
    {
        $lastPriceChange = $this->watcher->priceChanges()->latest()->first();
        if (!$lastPriceChange
            || $lastPriceChange->price !== $this->price
            || $lastPriceChange->stock !== $this->hasStock
        ) {
            $this->watcher->priceChanges()->create([
                'price' => $this->price,
                'stock' => $this->hasStock,
            ]);
        }
    }

    private function calculateStock(HtmlParser $parser)
    {
        if ($this->watcher->xpath_stock && $this->watcher->stock_text) {
            $this->rawStock = $parser->nodeValueByXPathQuery($this->watcher->xpath_stock);
            
            $hasStock = ($this->watcher->stock_contains && stripos($this->rawStock, $this->watcher->stock_text) !== false) ||
                (!$this->watcher->stock_contains && stripos($this->rawStock, $this->watcher->stock_text) === false);

            $this->rawStock = strlen($this->rawStock) > 191
                ? substr($this->rawStock, 0, 190)
                : $this->rawStock;

            return $hasStock;
        }

        return null;
    }
}
