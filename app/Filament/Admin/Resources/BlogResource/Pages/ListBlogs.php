<?php

namespace App\Filament\Admin\Resources\BlogResource\Pages;

use App\Filament\Admin\Resources\BlogResource;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\User;
use App\Services\GeminiBlogService;
use App\Support\AdminSettings;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListBlogs extends ListRecords
{
    protected static string $resource = BlogResource::class;

    protected function getHeaderActions(): array
    {
        $categoryOptions = BlogCategory::optionsForSelect();

        return [
            Actions\Action::make('back')
                ->label('Quay lại')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(url()->previous()),
            Actions\Action::make('createWithAi')
                ->label('Tạo bài viết bằng AI')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->modalHeading('Tạo bài quảng cáo brand bằng AI')
                ->modalDescription('Mỗi lần nhấn «Tạo ngay» = 1 request AI. AI viết bài quảng cáo brand bằng tiếng Anh theo thông tin bạn nhập (có thể mất 1–3 phút).')
                ->modalSubmitActionLabel('Tạo ngay')
                ->form([
                    Forms\Components\TextInput::make('brand_domain')
                        ->label('Domain brand')
                        ->placeholder('vd: nike.com, www.amazon.com')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Chỉ cần domain — có hoặc không có https://www.'),
                    Forms\Components\Select::make('blog_category_id')
                        ->label('Danh mục bài viết')
                        ->options($categoryOptions)
                        ->searchable()
                        ->placeholder('General (mặc định)')
                        ->helperText('Chỉ dùng để phân loại bài sau khi tạo — không ảnh hưởng nội dung AI.'),
                    Forms\Components\Textarea::make('content_idea')
                        ->label('Nội dung / ý tưởng')
                        ->rows(4)
                        ->maxLength(2000)
                        ->placeholder('Mô tả góc bài, sản phẩm cần nhắc, điểm nhấn muốn AI viết…')
                        ->helperText('AI sẽ viết bài theo ý tưởng này. Không bắt buộc.'),
                    Forms\Components\TextInput::make('aff_link')
                        ->label('Link AFF')
                        ->url()
                        ->maxLength(2048)
                        ->placeholder('https://…')
                        ->helperText('Link affiliate dùng cho CTA trong bài. Không bắt buộc — nếu trống sẽ dùng website chính thức của brand.'),
                    Forms\Components\TagsInput::make('coupon_codes')
                        ->label('Coupon code')
                        ->placeholder('Nhập mã rồi nhấn Enter')
                        ->helperText('Có thể thêm nhiều mã. Không bắt buộc.'),
                ])
                ->action(function (array $data): void {
                    @set_time_limit(600);

                    $gemini = app(GeminiBlogService::class);

                    if (! AdminSettings::hasGeminiApiKey()) {
                        Notification::make()
                            ->title('Lỗi cấu hình')
                            ->body('Gemini API key chưa được cấu hình trong Cài đặt hệ thống.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $domain = (string) ($data['brand_domain'] ?? '');
                    $blogCategoryId = ! empty($data['blog_category_id']) ? (int) $data['blog_category_id'] : null;
                    $categoryLabel = $blogCategoryId
                        ? (BlogCategory::query()->find($blogCategoryId)?->name ?? 'General')
                        : 'General';

                    $contentIdea = filled($data['content_idea'] ?? null)
                        ? trim((string) $data['content_idea'])
                        : null;
                    $affLink = filled($data['aff_link'] ?? null)
                        ? trim((string) $data['aff_link'])
                        : null;
                    $couponCodes = collect($data['coupon_codes'] ?? [])
                        ->map(fn ($code) => trim((string) $code))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    $result = $gemini->generateBrandPromoBlog(
                        $domain,
                        $contentIdea,
                        $affLink,
                        $couponCodes,
                    );

                    if (! $result) {
                        Notification::make()
                            ->title('Lỗi AI')
                            ->body($gemini->lastError ?? 'Không thể tạo nội dung từ AI.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $author = User::where('is_admin', true)->first() ?? User::first();

                    $blog = Blog::create([
                        'user_id' => $author?->id,
                        'blog_category_id' => $blogCategoryId,
                        'title' => $result['title'],
                        'category' => $categoryLabel,
                        'content' => $result['content'],
                        'featured_image' => $result['featured_image'] ?? null,
                        'is_published' => true,
                        'views_count' => 0,
                    ]);

                    $host = $result['domain'] ?? GeminiBlogService::normalizeDomain($domain);

                    Notification::make()
                        ->title('Đã tạo bài quảng cáo brand')
                        ->body("Bài \"{$blog->title}\" cho {$host} đã được tạo.")
                        ->success()
                        ->send();

                    $this->redirect(BlogResource::getUrl('edit', ['record' => $blog]));
                }),
            Actions\CreateAction::make()
                ->label('Thêm blog'),
        ];
    }
}
