@php
    $brandName = $brandName ?? \App\Support\MailSettings::fromName();
    $appUrl = $appUrl ?? config('app.url');
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $brandName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#eef2f7;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
    @include('emails.partials.card', [
        'htmlBody' => $htmlBody,
        'brandName' => $brandName,
        'appUrl' => $appUrl,
    ])
</body>
</html>
