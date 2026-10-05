<?php

namespace App\Filament\Admin\Support;

use Filament\Forms\Components\RichEditor;

class BlogRichEditor
{
    public static function configure(RichEditor $editor): RichEditor
    {
        return $editor
            ->toolbarButtons([
                'bold',
                'italic',
                'underline',
                'strike',
                'link',
                'h1',
                'h2',
                'h3',
                'blockquote',
                'codeBlock',
                'bulletList',
                'orderedList',
                'attachFiles',
                'undo',
                'redo',
            ])
            ->fileAttachmentsDirectory('blog-content')
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsVisibility('public')
            ->extraAttributes(['class' => 'blog-rich-editor-field'])
            ->extraInputAttributes([
                'style' => 'min-height: 420px;',
                'data-blog-content-editor' => 'true',
            ])
            ->helperText('H1–H3; P, Aa+pt (bôi đen → nhập cỡ/▾ chọn cỡ → Enter hoặc Áp), ▶ video, ảnh. Không bôi đen: áp dụng cỡ cho đoạn <p> tại con trỏ.');
    }
}
