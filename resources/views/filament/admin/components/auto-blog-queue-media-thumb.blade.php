@php
    use App\Models\AutoBlogQueueItem;
    use App\Models\Blog;
    use App\Support\PublicStorage;
    use Illuminate\Support\Str;

    /** @var AutoBlogQueueItem $record */
    $record = $getRecord();

    $previewUrl = null;

    // Priority 1: Featured image from the generated blog
    if ($record->blog_id) {
        $blog = $record->blog;
        if (! $blog && is_int($record->blog_id)) {
            $blog = Blog::query()->find($record->blog_id);
        }
        $featuredImage = $blog?->featured_image ?? null;
        if (! empty($featuredImage)) {
            $normalized = PublicStorage::normalizePath($featuredImage);
            if (PublicStorage::exists($normalized)) {
                $previewUrl = PublicStorage::url($normalized);
            }
        }
    }

    // Priority 2: Image from queue item
    $queueImagePath = $record->image_path ?? null;
    if (! $previewUrl && ! empty($queueImagePath)) {
        $normalized = PublicStorage::normalizePath((string) $queueImagePath);
        if (PublicStorage::exists($normalized)) {
            $previewUrl = PublicStorage::url($normalized);
        }
    }

    // Priority 3: Category default image (from BlogCategory model or public folder)
    $categoryName = $record->category_name ?? null;
    if (! $previewUrl) {
        // First try: from BlogCategory relationship
        $category = $record->blogCategory;
        if (! $category && $record->blog_category_id) {
            $category = \App\Models\BlogCategory::query()->find($record->blog_category_id);
        }
        if ($category) {
            $imageUrl = $category->imageUrl ?? null;
            if (! empty($imageUrl)) {
                $previewUrl = $imageUrl;
            }
        }
        // Second try: from public/categories/{slug}.{ext}
        if (! $previewUrl && ! empty($categoryName)) {
            $categorySlug = ! empty($category?->slug) ? $category->slug : Str::slug($categoryName);
            foreach (['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'] as $ext) {
                foreach (['categories', 'images/categories'] as $dir) {
                    $categoryImagePath = "{$dir}/{$categorySlug}.{$ext}";
                    if (is_file(public_path($categoryImagePath))) {
                        $previewUrl = asset($categoryImagePath);
                        break 2;
                    }
                }
            }
        }
    }

    // Priority 4: Generic default image
    if (! $previewUrl) {
        foreach (['images/default.jpg', 'categories/default.jpg', 'images/categories/default.jpg', 'images/default-brand.svg', 'images/instagram/default1.svg'] as $defaultPath) {
            if (is_file(public_path($defaultPath))) {
                $previewUrl = asset($defaultPath);
                break;
            }
        }
    }
@endphp

<div style="width: 40px; height: 40px" class="relative inline-flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-100 dark:border-white/10 dark:bg-gray-800">
    @if ($previewUrl)
        <img
            src="{{ $previewUrl }}"
            alt=""
            class="block h-full w-full object-cover"
            loading="lazy"
        />
    @else
        <div class="flex h-full w-full items-center justify-center">
            <span class="text-[8px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Blog</span>
        </div>
    @endif
</div>
