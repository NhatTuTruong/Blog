@foreach (\App\Models\SiteContent::headerMetaTags() as $tag)
    <meta name="{{ $tag['name'] }}" content="{{ $tag['content'] }}">
@endforeach
