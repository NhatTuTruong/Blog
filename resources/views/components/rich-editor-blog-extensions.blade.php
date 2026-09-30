@php
    $mediaUploadUrl = route('blog.upload-media');
@endphp
<meta name="blog-upload-media-url" content="{{ $mediaUploadUrl }}">
<style>
    .blog-rich-editor-field trix-toolbar .trix-button-group--blog-tools {
        display: inline-flex;
        align-items: center;
        flex-shrink: 0;
    }
    .blog-rich-editor-field .blog-trix-btn-label {
        font-size: 0.72rem;
        font-weight: 700;
        min-width: 1.5rem;
    }
    .blog-rich-editor-field figure.blog-inline-video video {
        display: block;
        width: 100%;
        max-height: 280px;
        height: auto;
        border-radius: 0.5rem;
        margin: 0.35rem 0 0;
        background: #0f172a;
    }
    .blog-rich-editor-field figure.blog-inline-video {
        margin: 0.75rem 0;
    }
</style>
<script>
(function () {
    const mediaUploadUrl = document.querySelector('meta[name="blog-upload-media-url"]')?.getAttribute('content') || '';

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function isBlogEditor(editorElement) {
        return editorElement.hasAttribute('data-blog-content-editor')
            || !!editorElement.closest('.blog-rich-editor-field');
    }

    function uploadMedia(file) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('_token', csrfToken());

        return fetch(mediaUploadUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Upload failed');
            }
            return response.json();
        });
    }

    function escapeAttr(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function insertVideoBlock(editorElement, url) {
        const safeUrl = escapeAttr(url);
        const html = '<figure class="blog-inline-video" data-video-src="' + safeUrl + '">'
            + '<video controls playsinline preload="metadata" src="' + safeUrl + '"></video>'
            + '</figure>';
        editorElement.editor.insertHTML(html);
    }

    function decorateVideoFigures(root) {
        root.querySelectorAll('figure.blog-inline-video[data-video-src]').forEach(function (figure) {
            const url = figure.getAttribute('data-video-src');
            if (!url) {
                return;
            }
            let video = figure.querySelector('video');
            if (!video) {
                video = document.createElement('video');
                video.controls = true;
                video.playsInline = true;
                video.preload = 'metadata';
                figure.insertBefore(video, figure.firstChild);
            }
            if (!video.getAttribute('src')) {
                video.src = url;
            }
        });
    }

    function clearFormatting(editorElement) {
        const editor = editorElement.editor;
        ['heading1', 'heading', 'subHeading', 'quote', 'code', 'bullet', 'number', 'bold', 'italic', 'underline', 'strike', 'href'].forEach(function (attr) {
            editor.deactivateAttribute(attr);
        });
    }

    function setParagraphBlock(editorElement) {
        const editor = editorElement.editor;
        ['heading1', 'heading', 'subHeading', 'quote', 'code'].forEach(function (attr) {
            editor.deactivateAttribute(attr);
        });
    }

    function addToolbarTools(toolbar, editorElement) {
        if (toolbar.querySelector('[data-blog-trix-tools]')) {
            return;
        }

        const group = document.createElement('div');
        group.className = 'trix-button-group trix-button-group--blog-tools';
        group.setAttribute('data-trix-button-group', 'blog-tools');
        group.setAttribute('data-blog-trix-tools', '1');

        const paraBtn = document.createElement('button');
        paraBtn.type = 'button';
        paraBtn.className = 'trix-button trix-button--icon';
        paraBtn.title = 'Đoạn văn (P)';
        paraBtn.innerHTML = '<span class="blog-trix-btn-label">P</span>';
        paraBtn.addEventListener('click', function (e) {
            e.preventDefault();
            setParagraphBlock(editorElement);
        });

        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'trix-button trix-button--icon';
        clearBtn.title = 'Xóa định dạng';
        clearBtn.innerHTML = '<span class="blog-trix-btn-label">Tx</span>';
        clearBtn.addEventListener('click', function (e) {
            e.preventDefault();
            clearFormatting(editorElement);
        });

        const videoBtn = document.createElement('button');
        videoBtn.type = 'button';
        videoBtn.className = 'trix-button trix-button--icon';
        videoBtn.title = 'Chèn video (hiển thị trong bài)';
        videoBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M8 5v14l11-7L8 5z"/></svg>';

        const videoInput = document.createElement('input');
        videoInput.type = 'file';
        videoInput.accept = 'video/mp4,video/webm,video/ogg';
        videoInput.hidden = true;
        videoInput.addEventListener('change', function () {
            const file = videoInput.files && videoInput.files[0];
            videoInput.value = '';
            if (!file) {
                return;
            }
            uploadMedia(file)
                .then(function (data) {
                    insertVideoBlock(editorElement, data.url);
                    decorateVideoFigures(editorElement);
                })
                .catch(function () {
                    alert('Không upload được video. Thử lại hoặc chọn file MP4/WebM nhỏ hơn 100MB.');
                });
        });
        videoBtn.addEventListener('click', function (e) {
            e.preventDefault();
            videoInput.click();
        });

        group.appendChild(paraBtn);
        group.appendChild(clearBtn);
        group.appendChild(videoBtn);
        group.appendChild(videoInput);

        const row = toolbar.querySelector('.flex.gap-x-3') || toolbar.querySelector('.flex');
        if (row) {
            row.appendChild(group);
        }
    }

    function setupEditorListeners(editorElement) {
        if (editorElement.dataset.blogTrixListeners === '1') {
            return;
        }
        editorElement.dataset.blogTrixListeners = '1';

        editorElement.addEventListener('trix-attachment-add', function (event) {
            const attachment = event.attachment;
            if (attachment.file && String(attachment.file.type || '').startsWith('video/')) {
                event.preventDefault();
                uploadMedia(attachment.file)
                    .then(function (data) {
                        insertVideoBlock(editorElement, data.url);
                        decorateVideoFigures(editorElement);
                    })
                    .catch(function () {
                        alert('Không upload được video.');
                    });
            }
        });

        editorElement.addEventListener('trix-initialize', function () {
            decorateVideoFigures(editorElement);
        }, { once: true });
    }

    function initBlogEditor(editorElement) {
        if (!isBlogEditor(editorElement)) {
            return;
        }

        const toolbarId = editorElement.getAttribute('toolbar');
        const toolbar = toolbarId
            ? document.getElementById(toolbarId)
            : editorElement.closest('.blog-rich-editor-field')?.querySelector('trix-toolbar');
        if (toolbar) {
            addToolbarTools(toolbar, editorElement);
        }

        setupEditorListeners(editorElement);
        decorateVideoFigures(editorElement);
    }

    document.addEventListener('trix-initialize', function (event) {
        initBlogEditor(event.target);
    });
})();
</script>
