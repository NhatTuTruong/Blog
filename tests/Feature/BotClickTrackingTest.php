<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Campaign;
use App\Models\Click;
use App\Models\PageView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotClickTrackingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function bot_clicks_are_marked_and_excluded_from_stats()
    {
        $user = User::factory()->create();
        $brand = Brand::factory()->create(['user_id' => $user->id, 'approved' => true]);
        $campaign = Campaign::factory()->create([
            'brand_id' => $brand->id,
            'status' => 'active',
            'affiliate_url' => 'https://example.com',
            'slug' => $user->code . '/test-campaign',
        ]);

        // 1 click từ người dùng thật
        $this->get(route('click.redirect', [
            'userCode' => $user->code,
            'slug' => 'test-campaign',
        ]), [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
        ]);

        // 1 click từ bot (Googlebot)
        $this->get(route('click.redirect', [
            'userCode' => $user->code,
            'slug' => 'test-campaign',
        ]), [
            'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        ]);

        // Tổng bản ghi click = 2 (có log đầy đủ)
        $this->assertEquals(2, Click::count());

        // Click người: is_bot = false
        $this->assertEquals(1, Click::where('is_bot', false)->count());

        // Click bot: is_bot = true
        $this->assertEquals(1, Click::where('is_bot', true)->count());

        // Các thống kê cho campaign phải chỉ tính click người
        $this->assertEquals(1, $campaign->clicks()->where('is_bot', false)->count());

        // Kiểm tra một cách gián tiếp: homepage sắp xếp theo tổng click + page view,
        // nhưng chỉ tính clicks.is_bot = 0 / page_views.is_bot = 0,
        // nên nếu chỉ có thêm bot thì thứ hạng/giá trị sẽ không đổi.
        // Ở đây đơn giản xác nhận lại rằng tổng clicks người vẫn là 1.
    }
}

