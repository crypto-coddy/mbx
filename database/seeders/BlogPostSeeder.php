<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Seeder;

class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::where('email', env('SUPER_ADMIN_EMAIL', 'admin@mbxzone.com'))->first()?->id
            ?? User::query()->value('id');

        $posts = [
            [
                'title' => 'Welcome to QuantX Space',
                'slug' => 'welcome-to-quantx-space',
                'excerpt' => 'Your hub for platform updates, market insights, and trading tips from the QuantX team.',
                'body' => <<<'HTML'
<p>Welcome to <strong>Space</strong> — the QuantX blog where we share product updates, market commentary, and practical guides for traders.</p>
<p>Check back here for announcements about new instruments, wallet features, and educational content to help you trade with confidence.</p>
HTML,
            ],
            [
                'title' => 'Gold (XAU) — what moves the price this week',
                'slug' => 'gold-xau-market-outlook',
                'excerpt' => 'A quick look at drivers behind gold prices and how to watch XAUUSD on the Trading tab.',
                'body' => <<<'HTML'
<p>Gold remains one of the most traded instruments on QuantX. Key factors this week include US dollar strength, central-bank commentary, and risk sentiment in global equities.</p>
<p>On the <strong>Trading</strong> tab, open <strong>XAUUSD</strong> to see live bid/ask quotes, spread, and the day’s high and low range before placing a trade.</p>
<p>Tip: use category filters or sort by trade price to compare gold against crypto and forex pairs quickly.</p>
HTML,
            ],
            [
                'title' => 'Reading bid, ask & spread on every row',
                'slug' => 'how-to-read-bid-ask-spread',
                'excerpt' => 'Understand the two price columns, spread (S:), and daily L/H values on the instrument list.',
                'body' => <<<'HTML'
<p>Each instrument row shows two trade prices: <strong>bid</strong> (left) and <strong>ask</strong> (right). The difference between them is the <strong>spread</strong>, shown as <em>S:</em> next to the daily change.</p>
<p>Below each price you will see <strong>L:</strong> (day low) and <strong>H:</strong> (day high) for that quote column.</p>
<p>When you buy, you typically pay near the ask; when you close a position, settlement follows admin review of profit or loss on the sell request.</p>
HTML,
            ],
            [
                'title' => 'Crypto watch: BTC and ETH volatility',
                'slug' => 'crypto-btc-eth-volatility',
                'excerpt' => 'Bitcoin and Ethereum often lead risk appetite. Here is how to follow them on QuantX.',
                'body' => <<<'HTML'
<p><strong>BTCUSD</strong> and <strong>ETHUSD</strong> are available under the Crypto category on the Trading screen. Prices refresh automatically while the app is open.</p>
<p>Crypto can move sharply in short sessions — check the percentage change on each row and compare day high/low before sizing your trade.</p>
<p>Remember: only trade amounts you can afford from your wallet balance shown at the top of the Trading tab.</p>
HTML,
            ],
            [
                'title' => 'Forex pairs EURUSD & USDJPY — session basics',
                'slug' => 'forex-eurusd-usdjpy-basics',
                'excerpt' => 'Major FX pairs on QuantX and what beginners should notice in the quote list.',
                'body' => <<<'HTML'
<p>Forex instruments such as <strong>EURUSD</strong>, <strong>GBPUSD</strong>, and <strong>USDJPY</strong> trade around the clock across global sessions.</p>
<p>JPY pairs often display three decimal places; other majors use two. Use the sort menu to order by trade price high or low when scanning opportunities.</p>
<p>Combine category tabs with search to jump straight to a pair you follow regularly.</p>
HTML,
            ],
            [
                'title' => 'Orders tab: open, pending & closed trades',
                'slug' => 'orders-tab-open-pending-closed',
                'excerpt' => 'Track your positions after you buy — and what happens when you request a close.',
                'body' => <<<'HTML'
<p>After you buy from the Trading screen, your position appears under <strong>Orders</strong> as an open trade.</p>
<p>When you request a close, the trade moves to pending settlement while admin confirms profit or loss. Settled trades appear in closed history and wallet credits follow approval.</p>
<p>Keep an eye on the Dashboard for wallet balance and quick actions between sessions.</p>
HTML,
            ],
        ];

        foreach ($posts as $index => $post) {
            BlogPost::updateOrCreate(
                ['slug' => $post['slug']],
                [
                    ...$post,
                    'cover_image_url' => null,
                    'is_published' => true,
                    'published_at' => now()->subDays(count($posts) - $index)->subHours($index * 3),
                    'sort_order' => 100 - $index,
                    'created_by' => $adminId,
                    'updated_by' => $adminId,
                ]
            );
        }

        $this->command?->info('Seeded '.count($posts).' blog posts for Space.');
    }
}
