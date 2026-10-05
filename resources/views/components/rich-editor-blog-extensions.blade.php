@php
    $mediaUploadUrl = route('blog.upload-media');
@endphp
<meta name="blog-upload-media-url" content="{{ $mediaUploadUrl }}">
<datalist id="blog-trix-font-size-presets">
    <option value="8"></option>
    <option value="9"></option>
    <option value="10"></option>
    <option value="11"></option>
    <option value="12"></option>
    <option value="14"></option>
    <option value="16"></option>
    <option value="18"></option>
    <option value="20"></option>
    <option value="22"></option>
    <option value="24"></option>
    <option value="26"></option>
    <option value="28"></option>
    <option value="36"></option>
    <option value="48"></option>
    <option value="72"></option>
</datalist>
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
    .blog-rich-editor-field trix-editor .blog-fs-sm { font-size: 0.875em; }
    .blog-rich-editor-field trix-editor .blog-fs-lg { font-size: 1.125em; }
    .blog-rich-editor-field trix-editor .blog-fs-xl { font-size: 1.35em; }
    .blog-rich-editor-field trix-editor.prose :where(span[style*="font-size"]) {
        line-height: inherit;
    }
    .blog-rich-editor-field .trix-button-group--blog-tools {
        margin-inline-end: 0.35rem;
    }
    .blog-rich-editor-field trix-toolbar .trix-button-group.blog-trix-font-size-group {
        --blog-fs-zone: 2.625rem;
        --blog-fs-h: 2rem;
        display: inline-grid !important;
        grid-template-columns: var(--blog-fs-zone) minmax(3.5rem, 1fr) 1.75rem var(--blog-fs-zone);
        align-items: stretch;
        flex-shrink: 0;
        height: var(--blog-fs-h);
        margin-inline-end: 0.15rem;
        padding: 0;
        border-radius: 0.375rem;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(255, 255, 255, 0.04);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .blog-rich-editor-field .blog-trix-font-size-group:focus-within {
        border-color: rgba(29, 155, 240, 0.5);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, 0.05),
            0 0 0 2px rgba(29, 155, 240, 0.16);
    }
    .blog-rich-editor-field .blog-trix-font-size-label {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        padding: 0;
        border-inline-end: 1px solid rgba(255, 255, 255, 0.08);
        user-select: none;
        line-height: 1;
    }
    .blog-rich-editor-field .blog-trix-font-size-a-cap {
        font-size: 0.8125rem;
        font-weight: 800;
        letter-spacing: -0.04em;
        color: #1d9bf0;
        transform: translateY(0.5px);
    }
    .blog-rich-editor-field .blog-trix-font-size-a-low {
        font-size: 0.6875rem;
        font-weight: 600;
        color: rgba(226, 232, 240, 0.88);
        transform: translateY(1px);
    }
    .blog-rich-editor-field .blog-trix-font-size-field {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.25rem;
        height: 100%;
        margin: 0;
        padding: 0 0.4rem;
        min-width: 0;
        background: rgba(0, 0, 0, 0.22);
    }
    .blog-rich-editor-field .blog-trix-font-size-preset-zone {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        border-inline-start: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(0, 0, 0, 0.12);
    }
    .blog-rich-editor-field .blog-trix-font-size-input {
        width: 2.625rem;
        min-width: 2.625rem;
        height: 100%;
        margin: 0;
        padding: 0;
        border: none;
        background: transparent;
        color: rgb(248, 250, 252);
        font-size: 0.8125rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        text-align: center;
        line-height: var(--blog-fs-h);
        -moz-appearance: textfield;
    }
    .blog-rich-editor-field .blog-trix-font-size-input::-webkit-outer-spin-button,
    .blog-rich-editor-field .blog-trix-font-size-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    .blog-rich-editor-field .blog-trix-font-size-input {
        cursor: text;
        caret-color: #38bdf8;
    }
    .blog-rich-editor-field .blog-trix-font-size-input:focus {
        outline: none;
        background: rgba(29, 155, 240, 0.14);
        border-radius: 0.2rem;
    }
    .blog-rich-editor-field .blog-trix-font-size-input::placeholder {
        color: rgba(148, 163, 184, 0.65);
    }
    .blog-rich-editor-field .blog-trix-font-size-unit {
        flex-shrink: 0;
        font-size: 0.625rem;
        font-weight: 600;
        letter-spacing: 0.03em;
        color: rgba(148, 163, 184, 0.9);
        user-select: none;
        line-height: 1;
    }
    .blog-rich-editor-field .blog-trix-font-size-apply {
        display: inline-flex;
        flex-direction: row;
        align-items: center;
        justify-content: center;
        gap: 0.2rem;
        height: 100%;
        width: 100%;
        margin: 0;
        padding: 0 0.125rem;
        border: none;
        border-inline-start: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 0;
        background: transparent;
        color: rgba(186, 230, 253, 0.95);
        font-size: 0.625rem;
        font-weight: 600;
        letter-spacing: 0.01em;
        cursor: pointer;
        line-height: 1;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .blog-rich-editor-field .blog-trix-font-size-apply svg {
        width: 0.75rem;
        height: 0.75rem;
        flex-shrink: 0;
    }
    .blog-rich-editor-field .blog-trix-font-size-apply span {
        display: block;
        line-height: 1;
    }
    .blog-rich-editor-field .blog-trix-font-size-apply:hover {
        background: rgba(29, 155, 240, 0.16);
        color: #e0f2fe;
    }
    .blog-rich-editor-field .blog-trix-font-size-apply:active {
        background: rgba(29, 155, 240, 0.26);
    }
    .blog-rich-editor-field .blog-trix-font-size-shell {
        position: relative;
        display: inline-flex;
        flex-shrink: 0;
        vertical-align: top;
        overflow: visible;
    }
    .blog-rich-editor-field trix-toolbar .flex.overflow-x-auto,
    .blog-rich-editor-field trix-toolbar .flex.gap-x-3 {
        overflow-y: visible;
    }
    .blog-rich-editor-field .blog-trix-font-size-group.is-key-mode {
        border-color: rgba(29, 155, 240, 0.45);
        box-shadow: 0 0 0 2px rgba(29, 155, 240, 0.12);
    }
    .blog-rich-editor-field .blog-trix-font-size-preset-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 100%;
        height: 100%;
        margin: 0;
        padding: 0;
        border: none;
        border-radius: 0;
        background: transparent;
        color: rgba(148, 163, 184, 0.95);
        font-size: 0.6875rem;
        line-height: 1;
        cursor: pointer;
        transition: background 0.15s ease, color 0.15s ease;
    }
    .blog-rich-editor-field .blog-trix-font-size-preset-toggle:hover,
    .blog-rich-editor-field .blog-trix-font-size-preset-toggle[aria-expanded="true"] {
        background: rgba(29, 155, 240, 0.18);
        color: #bae6fd;
    }
    .blog-trix-font-size-presets {
        position: fixed;
        z-index: 99999;
        min-width: 14.5rem;
        padding: 0.4rem;
        border-radius: 0.5rem;
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: rgb(22, 27, 34);
        box-shadow: 0 10px 28px rgba(0, 0, 0, 0.45);
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.3rem;
        box-sizing: border-box;
    }
    .blog-trix-font-size-presets[hidden] {
        display: none !important;
    }
    .blog-trix-font-size-preset {
        margin: 0;
        padding: 0.35rem 0.25rem;
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 0.35rem;
        background: rgba(255, 255, 255, 0.04);
        color: rgba(226, 232, 240, 0.95);
        font-size: 0.6875rem;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
        cursor: pointer;
        line-height: 1.2;
        transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }
    .blog-trix-font-size-preset:hover {
        border-color: rgba(29, 155, 240, 0.45);
        background: rgba(29, 155, 240, 0.14);
        color: #e0f2fe;
    }
    .blog-trix-font-size-preset.is-active {
        border-color: rgba(29, 155, 240, 0.65);
        background: rgba(29, 155, 240, 0.22);
        color: #f0f9ff;
    }
    ::highlight(blog-font-size-sel) {
        background-color: rgba(29, 155, 240, 0.38);
        color: inherit;
    }
    .blog-fs-sel-overlay-rect {
        position: fixed;
        pointer-events: none;
        z-index: 99998;
        background: rgba(29, 155, 240, 0.38);
        border-radius: 2px;
        box-sizing: border-box;
    }
</style>
<script>
(function () {
    if (typeof window.__blogRegisterTrixFontConfig === 'function') {
        window.__blogRegisterTrixFontConfig();
    }

    const BLOG_FONT_SIZE_PRESETS = [10, 12, 14, 16, 18, 20, 24, 36];

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

    function fontSizeConfig() {
        return window.__blogTrixFontSize || {
            textAttr: 'blogFontSize',
            blockAttr: 'blogBlockFontSize',
            defaultPt: 12,
            minPt: 6,
            maxPt: 96,
        };
    }

    function parseFontSizePt(value) {
        if (!value) {
            return null;
        }

        const match = String(value).trim().match(/^([\d.]+)\s*(pt|px)?$/i);
        if (!match) {
            return null;
        }

        let num = parseFloat(match[1]);
        if (Number.isNaN(num)) {
            return null;
        }

        const unit = (match[2] || 'pt').toLowerCase();
        if (unit === 'px') {
            num = Math.round(num * 0.75 * 10) / 10;
        }

        return num;
    }

    function clampFontPt(pt, cfg) {
        const min = cfg.minPt || 6;
        const max = cfg.maxPt || 96;

        return Math.min(max, Math.max(min, Math.round(pt * 10) / 10));
    }

    function escapeHtmlText(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function isExpandedRange(range) {
        return Boolean(range && range.length === 2 && range[1] > range[0]);
    }

    function getTrixRangeText(editor, range) {
        if (!editor || !isExpandedRange(range)) {
            return '';
        }

        const doc = editor.getDocument();
        let text = '';

        if (typeof doc.getStringAtRange === 'function') {
            try {
                text = doc.getStringAtRange(range);
            } catch (error) {
                text = '';
            }
        }

        if (!text && typeof doc.getDocumentAtRange === 'function') {
            try {
                text = doc.getDocumentAtRange(range).toString();
            } catch (error) {
                text = '';
            }
        }

        if (!text && typeof doc.toString === 'function') {
            text = doc.toString().substring(range[0], range[1]);
        }

        return text;
    }

    function resolveTextSelectionRange(editorElement, rangeOverride) {
        const editor = editorElement.editor;
        if (!editor) {
            return null;
        }

        const seen = [];
        const candidates = [];

        function pushRange(range) {
            if (!isExpandedRange(range)) {
                return;
            }

            const key = range[0] + ':' + range[1];
            if (seen.indexOf(key) !== -1) {
                return;
            }

            seen.push(key);
            candidates.push(range.slice());
        }

        pushRange(getSavedEditorSelection(editorElement));
        pushRange(rangeOverride);
        pushRange(editor.getSelectedRange());

        if (candidates.length === 0) {
            return null;
        }

        return candidates[0];
    }

    function applyInlineFontSizeSpan(editorElement, cssValue, range) {
        const editor = editorElement.editor;
        const cfg = fontSizeConfig();
        if (!editor || !isExpandedRange(range)) {
            return false;
        }

        restoreEditorSelection(editorElement, range);
        editor.recordUndoEntry('fontSize');
        editor.deactivateAttribute(cfg.blockAttr);
        editor.activateAttribute(cfg.textAttr, cssValue);

        restoreEditorSelection(editorElement, range);

        let appliedPt = null;
        const doc = editor.getDocument();
        if (typeof doc.getAttributesAtPosition === 'function') {
            appliedPt = parseFontSizePt(doc.getAttributesAtPosition(range[0])[cfg.textAttr]);
        }

        const wantedPt = parseFontSizePt(cssValue);

        if (appliedPt !== null && wantedPt !== null && appliedPt === wantedPt) {
            rememberEditorSelection(editorElement);

            return true;
        }

        const text = getTrixRangeText(editor, range);
        if (text !== '') {
            restoreEditorSelection(editorElement, range);
            const safeCss = String(cssValue).replace(/[<>"']/g, '');
            editor.insertHTML('<span style="font-size: ' + safeCss + '">' + escapeHtmlText(text) + '</span>');
            rememberEditorSelection(editorElement);

            return true;
        }

        rememberEditorSelection(editorElement);

        return true;
    }

    function getBlockElement(editorElement) {
        const selection = window.getSelection();
        if (!selection || selection.rangeCount === 0) {
            return null;
        }

        let node = selection.anchorNode;
        if (node && node.nodeType === Node.TEXT_NODE) {
            node = node.parentElement;
        }

        while (node && node !== editorElement) {
            if (node.parentElement === editorElement) {
                return node;
            }
            node = node.parentElement;
        }

        return null;
    }

    function deactivateAllFontSizes(editor) {
        const cfg = fontSizeConfig();
        editor.deactivateAttribute(cfg.textAttr);
        editor.deactivateAttribute(cfg.blockAttr);
    }

    function fontSizeFromLegacyClass(element) {
        if (!element || !element.classList) {
            return null;
        }
        if (element.classList.contains('blog-fs-sm')) {
            return 10;
        }
        if (element.classList.contains('blog-fs-lg')) {
            return 14;
        }
        if (element.classList.contains('blog-fs-xl')) {
            return 18;
        }

        return null;
    }

    function detectCurrentFontSizePt(editorElement) {
        const editor = editorElement.editor;
        const cfg = fontSizeConfig();
        if (!editor) {
            return cfg.defaultPt;
        }

        const range = editor.getSelectedRange();
        const attrs = editor.getDocument().getCommonAttributesAtRange(range);
        const attrPt = parseFontSizePt(attrs[cfg.textAttr] || attrs[cfg.blockAttr]);
        if (attrPt) {
            return attrPt;
        }

        const block = getBlockElement(editorElement);
        if (block) {
            const legacy = fontSizeFromLegacyClass(block);
            if (legacy) {
                return legacy;
            }
            const blockStylePt = parseFontSizePt(block.style.fontSize);
            if (blockStylePt) {
                return blockStylePt;
            }
        }

        if (block) {
            const px = parseFloat(window.getComputedStyle(block).fontSize);
            if (!Number.isNaN(px)) {
                return Math.round(px * 0.75);
            }
        }

        return cfg.defaultPt;
    }

    function canApplyBlockFontSize(editor) {
        return !['heading1', 'heading', 'subHeading', 'quote', 'code', 'bullet', 'number'].some(function (attr) {
            return editor.attributeIsActive(attr);
        });
    }

    function rememberEditorSelection(editorElement) {
        const editor = editorElement.editor;
        if (!editor) {
            return;
        }

        const live = editor.getSelectedRange();
        if (isExpandedRange(live)) {
            editorElement.__blogSavedRange = live.slice();

            return;
        }

        if (!isExpandedRange(editorElement.__blogSavedRange)) {
            editorElement.__blogSavedRange = live.slice();
        }
    }

    function getSavedEditorSelection(editorElement) {
        const range = editorElement.__blogSavedRange;
        if (!range || range.length !== 2) {
            return null;
        }

        return range;
    }

    function restoreEditorSelection(editorElement, range) {
        const editor = editorElement.editor;
        if (!editor || !range) {
            return;
        }

        try {
            editor.setSelectedRange([range[0], range[1]]);
        } catch (error) {
            // Ignore invalid range after document changes.
        }
    }

    function refreshEditorSelectionHighlight(editorElement) {
        const range = getSavedEditorSelection(editorElement);
        if (!range || range[1] <= range[0]) {
            return;
        }

        restoreEditorSelection(editorElement, range);
    }

    function canUseSelectionHighlightApi() {
        return typeof CSS !== 'undefined'
            && typeof CSS.highlights !== 'undefined'
            && typeof Highlight !== 'undefined';
    }

    function isNodeInsideEditor(editorElement, node) {
        while (node) {
            if (node === editorElement) {
                return true;
            }

            node = node.parentNode;
        }

        return false;
    }

    function getEditorOverlayKey(editorElement) {
        return editorElement.id || editorElement.dataset.blogOverlayKey || '';
    }

    function clearOverlayFallbackHighlight(editorElement) {
        const key = getEditorOverlayKey(editorElement);
        if (!key) {
            return;
        }

        document.querySelectorAll('.blog-fs-sel-overlay-rect[data-editor-id="' + key + '"]').forEach(function (node) {
            node.remove();
        });

        const handler = editorElement?.__blogOverlayRepositionHandler;
        if (handler) {
            window.removeEventListener('scroll', handler, true);
            window.removeEventListener('resize', handler);
            editorElement.__blogOverlayRepositionHandler = null;
        }
    }

    function clearSavedSelectionHighlight(editorElement) {
        if (canUseSelectionHighlightApi()) {
            CSS.highlights.delete('blog-font-size-sel');
        }

        if (editorElement) {
            clearOverlayFallbackHighlight(editorElement);
            editorElement.removeAttribute('data-blog-font-size-pseudo-sel');
        }
    }

    function paintOverlayFallbackHighlight(editorElement, returnFocusElement) {
        clearOverlayFallbackHighlight(editorElement);

        const range = getSavedEditorSelection(editorElement);
        if (!isExpandedRange(range)) {
            return;
        }

        if (!editorElement.id) {
            editorElement.dataset.blogOverlayKey = 'blog-editor-' + Math.random().toString(36).slice(2, 9);
        }

        const key = getEditorOverlayKey(editorElement);
        editorElement.focus({ preventScroll: true });
        restoreEditorSelection(editorElement, range);

        const selection = window.getSelection();
        if (!selection || selection.rangeCount === 0) {
            return;
        }

        const anchor = selection.anchorNode;
        if (!isNodeInsideEditor(editorElement, anchor)) {
            return;
        }

        const rects = selection.getRangeAt(0).getClientRects();
        for (let i = 0; i < rects.length; i++) {
            const rect = rects[i];
            if (rect.width <= 0 && rect.height <= 0) {
                continue;
            }

            const overlay = document.createElement('div');
            overlay.className = 'blog-fs-sel-overlay-rect';
            overlay.setAttribute('data-editor-id', key);
            overlay.style.left = rect.left + 'px';
            overlay.style.top = rect.top + 'px';
            overlay.style.width = rect.width + 'px';
            overlay.style.height = rect.height + 'px';
            document.body.appendChild(overlay);
        }

        editorElement.setAttribute('data-blog-font-size-pseudo-sel', '1');

        if (returnFocusElement && typeof returnFocusElement.focus === 'function') {
            returnFocusElement.focus({ preventScroll: true });
        }

        const handler = function () {
            if (editorElement.getAttribute('data-blog-font-size-pseudo-sel') !== '1') {
                return;
            }

            const refocus = document.activeElement;
            paintOverlayFallbackHighlight(editorElement, refocus);
        };

        editorElement.__blogOverlayRepositionHandler = handler;
        window.addEventListener('scroll', handler, true);
        window.addEventListener('resize', handler);
    }

    function showSavedSelectionHighlight(editorElement, returnFocusElement) {
        const range = getSavedEditorSelection(editorElement);
        if (!isExpandedRange(range)) {
            clearSavedSelectionHighlight(editorElement);

            return;
        }

        editorElement.focus({ preventScroll: true });
        restoreEditorSelection(editorElement, range);

        const selection = window.getSelection();
        if (!selection || selection.rangeCount === 0) {
            return;
        }

        const anchor = selection.anchorNode;
        if (!isNodeInsideEditor(editorElement, anchor)) {
            return;
        }

        let painted = false;

        if (canUseSelectionHighlightApi()) {
            try {
                CSS.highlights.set('blog-font-size-sel', new Highlight(selection.getRangeAt(0).cloneRange()));
                editorElement.setAttribute('data-blog-font-size-pseudo-sel', '1');
                painted = true;
            } catch (error) {
                CSS.highlights.delete('blog-font-size-sel');
            }
        }

        if (!painted) {
            paintOverlayFallbackHighlight(editorElement, returnFocusElement);

            return;
        }

        if (returnFocusElement && typeof returnFocusElement.focus === 'function') {
            returnFocusElement.focus({ preventScroll: true });
        }
    }

    function editorHasFocus(editorElement) {
        const active = document.activeElement;
        if (!active) {
            return false;
        }

        return active === editorElement || editorElement.contains(active);
    }

    function disableFontSizeKeyMode(editorElement) {
        if (!editorElement) {
            return;
        }

        editorElement.removeAttribute('data-blog-font-size-key-mode');
        getFontGroupForEditor(editorElement)?.classList.remove('is-key-mode');
    }

    function enableFontSizeKeyMode(editorElement) {
        if (!editorElement) {
            return;
        }

        editorElement.setAttribute('data-blog-font-size-key-mode', '1');
        getFontGroupForEditor(editorElement)?.classList.add('is-key-mode');
        editorElement.focus({ preventScroll: true });
        refreshEditorSelectionHighlight(editorElement);
    }

    function reassertEditorSelectionHighlight(editorElement, rangeOverride) {
        const range = isExpandedRange(rangeOverride)
            ? rangeOverride.slice()
            : getSavedEditorSelection(editorElement);

        disableFontSizeKeyMode(editorElement);
        clearSavedSelectionHighlight(editorElement);

        if (!isExpandedRange(range)) {
            editorElement.focus({ preventScroll: true });

            return;
        }

        editorElement.__blogSavedRange = range.slice();

        function step() {
            editorElement.focus({ preventScroll: true });
            restoreEditorSelection(editorElement, range);
        }

        step();
        window.requestAnimationFrame(function () {
            step();
            window.requestAnimationFrame(step);
        });
    }

    function getFontSizeShellForPanel(panel) {
        if (!panel) {
            return null;
        }

        const shellId = panel.getAttribute('data-shell-id');
        if (shellId) {
            return document.getElementById(shellId);
        }

        return panel.__blogFontSizeShell || null;
    }

    function repositionFontSizePresetPanel(presetPanel) {
        const shell = getFontSizeShellForPanel(presetPanel);
        if (!shell || presetPanel.hidden) {
            return;
        }

        const rect = shell.getBoundingClientRect();
        const panelWidth = Math.max(Math.round(rect.width), 232);
        let left = Math.round(rect.left);
        const maxLeft = window.innerWidth - panelWidth - 8;

        if (left > maxLeft) {
            left = Math.max(8, maxLeft);
        }

        presetPanel.style.top = Math.round(rect.bottom + 6) + 'px';
        presetPanel.style.left = left + 'px';
        presetPanel.style.minWidth = panelWidth + 'px';
    }

    function unbindFontSizePresetPanelReposition(presetPanel) {
        const handler = presetPanel?.__blogRepositionHandler;
        if (!handler) {
            return;
        }

        window.removeEventListener('scroll', handler, true);
        window.removeEventListener('resize', handler);
        presetPanel.__blogRepositionHandler = null;
    }

    function bindFontSizePresetPanelReposition(presetPanel) {
        unbindFontSizePresetPanelReposition(presetPanel);

        const handler = function () {
            repositionFontSizePresetPanel(presetPanel);
        };

        presetPanel.__blogRepositionHandler = handler;
        window.addEventListener('scroll', handler, true);
        window.addEventListener('resize', handler);
    }

    function closeFontSizePresetPanel(presetPanel) {
        if (!presetPanel) {
            return;
        }

        unbindFontSizePresetPanelReposition(presetPanel);
        presetPanel.hidden = true;
        const shell = getFontSizeShellForPanel(presetPanel);
        const toggle = shell?.querySelector('[data-blog-font-size-preset-toggle]');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }

        if (shell && presetPanel.parentElement === document.body) {
            shell.appendChild(presetPanel);
        }
    }

    function openFontSizePresetPanel(shell, presetPanel, toggle) {
        if (!shell || !presetPanel || !toggle) {
            return;
        }

        presetPanel.__blogFontSizeShell = shell;
        presetPanel.hidden = false;
        document.body.appendChild(presetPanel);
        repositionFontSizePresetPanel(presetPanel);
        bindFontSizePresetPanelReposition(presetPanel);

        toggle.setAttribute('aria-expanded', 'true');
    }

    function closeAllFontSizePresetPanels(exceptPanel) {
        document.querySelectorAll('.blog-trix-font-size-presets').forEach(function (panel) {
            if (exceptPanel && panel === exceptPanel) {
                return;
            }

            closeFontSizePresetPanel(panel);
        });
    }

    function getPresetPanelForShell(shell) {
        if (!shell) {
            return null;
        }

        if (shell.id) {
            const floating = document.querySelector('.blog-trix-font-size-presets[data-shell-id="' + shell.id + '"]');
            if (floating) {
                return floating;
            }
        }

        return shell.querySelector('.blog-trix-font-size-presets');
    }

    function syncFontSizePresetActiveState(fontGroup, pt) {
        if (!fontGroup) {
            return;
        }

        const shell = fontGroup.closest('.blog-trix-font-size-shell') || fontGroup.parentElement;
        const panel = getPresetPanelForShell(shell);
        if (!panel) {
            return;
        }

        panel.querySelectorAll('.blog-trix-font-size-preset').forEach(function (btn) {
            const btnPt = parseFloat(btn.getAttribute('data-pt') || '');
            btn.classList.toggle('is-active', !Number.isNaN(btnPt) && pt !== null && btnPt === pt);
        });
    }

    function bindFontSizeKeyModeListener() {
        if (window.__blogFontSizeKeyModeBound) {
            return;
        }

        window.__blogFontSizeKeyModeBound = true;

        document.addEventListener('keydown', function (event) {
            const editorElement = document.querySelector('trix-editor[data-blog-font-size-key-mode="1"]');
            if (!editorElement || !editorHasFocus(editorElement)) {
                return;
            }

            const fontGroup = getFontGroupForEditor(editorElement);
            const fontInput = fontGroup?.querySelector('[data-blog-font-size-input]');
            if (!fontGroup || !fontInput) {
                return;
            }

            const cfg = fontSizeConfig();
            const savedRange = getSavedEditorSelection(editorElement);

            if (event.key >= '0' && event.key <= '9') {
                event.preventDefault();
                const next = String(fontInput.value || '').trim() + event.key;
                fontInput.value = next.replace(/^0+(?=\d)/, '');
                refreshEditorSelectionHighlight(editorElement);

                return;
            }

            if (event.key === 'Backspace') {
                event.preventDefault();
                fontInput.value = String(fontInput.value || '').slice(0, -1);
                refreshEditorSelectionHighlight(editorElement);

                return;
            }

            if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
                event.preventDefault();
                const base = parseFontSizePt((fontInput.value || '') + 'pt')
                    ?? detectCurrentFontSizePt(editorElement);
                const next = clampFontPt(base + (event.key === 'ArrowUp' ? 1 : -1), cfg);
                fontInput.value = String(next);
                applyFontSizePt(editorElement, next, savedRange);
                reassertEditorSelectionHighlight(editorElement, savedRange);
                editorElement.dispatchEvent(new Event('trix-change', { bubbles: true }));

                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                commitFontSizeInput(editorElement, fontGroup);

                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                disableFontSizeKeyMode(editorElement);
                closeAllFontSizePresetPanels();
            }
        }, true);

        document.addEventListener('mousedown', function (event) {
            if (event.target.closest('.blog-trix-font-size-shell')
                || event.target.closest('.blog-trix-font-size-presets')) {
                return;
            }

            document.querySelectorAll('trix-editor[data-blog-font-size-key-mode]').forEach(disableFontSizeKeyMode);
            closeAllFontSizePresetPanels();
        });
    }

    function applyFontSizePt(editorElement, pt, rangeOverride) {
        const editor = editorElement.editor;
        if (!editor) {
            return;
        }

        const cfg = fontSizeConfig();
        const value = pt ? clampFontPt(pt, cfg) + 'pt' : null;
        const saved = rangeOverride || getSavedEditorSelection(editorElement);
        const selectionRange = resolveTextSelectionRange(editorElement, saved);

        if (selectionRange) {
            if (!value) {
                restoreEditorSelection(editorElement, selectionRange);
                editor.deactivateAttribute(cfg.textAttr);

                return;
            }

            applyInlineFontSizeSpan(editorElement, value, selectionRange);
            editorElement.__blogSavedRange = selectionRange.slice();
            editorElement.dispatchEvent(new Event('trix-change', { bubbles: true }));
            reassertEditorSelectionHighlight(editorElement, selectionRange);

            return;
        }

        if (rangeOverride) {
            restoreEditorSelection(editorElement, rangeOverride);
        }

        editor.deactivateAttribute(cfg.textAttr);
        if (!canApplyBlockFontSize(editor)) {
            return;
        }

        if (!value) {
            editor.deactivateAttribute(cfg.blockAttr);
            const block = getBlockElement(editorElement);
            if (block) {
                block.style.removeProperty('font-size');
            }

            return;
        }

        editor.recordUndoEntry('fontSize');
        editor.activateAttribute(cfg.blockAttr, value);

        const block = getBlockElement(editorElement);
        if (block) {
            block.style.fontSize = value;
        }
    }

    function syncFontSizeUi(editorElement, uiRoot) {
        if (!uiRoot) {
            return;
        }

        const shell = uiRoot.closest('[data-blog-font-size-ui]') || uiRoot;
        const input = shell.querySelector('[data-blog-font-size-input]');
        if (!input || input === document.activeElement) {
            return;
        }

        const pt = detectCurrentFontSizePt(editorElement);
        input.value = String(pt);
        syncFontSizePresetActiveState(shell.querySelector('.blog-trix-font-size-group') || shell, pt);
    }

    function setParagraphBlock(editorElement) {
        const editor = editorElement.editor;
        ['heading1', 'heading', 'subHeading', 'quote', 'code'].forEach(function (attr) {
            editor.deactivateAttribute(attr);
        });
    }

    function findToolbarForEditor(editorElement) {
        const toolbarId = editorElement.getAttribute('toolbar');
        if (toolbarId) {
            return document.getElementById(toolbarId);
        }

        return editorElement.closest('.blog-rich-editor-field')?.querySelector('trix-toolbar') || null;
    }

    function getFontGroupForEditor(editorElement) {
        return editorElement.closest('.blog-rich-editor-field')?.querySelector('.blog-trix-font-size-group') || null;
    }

    let ensureBlogUiTimer = null;

    function scheduleEnsureBlogEditorUi() {
        if (ensureBlogUiTimer) {
            clearTimeout(ensureBlogUiTimer);
        }

        ensureBlogUiTimer = window.setTimeout(function () {
            ensureBlogUiTimer = null;
            document.querySelectorAll('trix-editor[data-blog-content-editor], .blog-rich-editor-field trix-editor').forEach(initBlogEditor);
        }, 60);
    }

    function ensureBlogEditorUi(editorElement) {
        if (!isBlogEditor(editorElement)) {
            return;
        }

        const toolbar = findToolbarForEditor(editorElement);
        const blogTools = toolbar?.querySelector('[data-blog-trix-tools]');
        const fontUi = toolbar?.querySelector('[data-blog-font-size-ui]');
        if (toolbar && (
            !blogTools
            || blogTools.getAttribute('data-version') !== '2'
            || !fontUi
            || fontUi.closest('.blog-trix-font-size-shell')?.getAttribute('data-version') !== '5'
        )) {
            addToolbarTools(toolbar, editorElement);
        }

        decorateVideoFigures(editorElement);
    }

    function observeBlogEditorFields() {
        if (window.__blogEditorFieldObserverBound) {
            return;
        }

        window.__blogEditorFieldObserverBound = true;

        const observeField = function (field) {
            if (!field || field.dataset.blogFieldObserved === '1') {
                return;
            }

            field.dataset.blogFieldObserved = '1';
            const observer = new MutationObserver(function () {
                scheduleEnsureBlogEditorUi();
            });
            observer.observe(field, { childList: true, subtree: true });
        };

        document.querySelectorAll('.blog-rich-editor-field').forEach(observeField);

        const rootObserver = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) {
                        return;
                    }

                    if (node.matches && node.matches('.blog-rich-editor-field')) {
                        observeField(node);
                    }

                    node.querySelectorAll?.('.blog-rich-editor-field').forEach(observeField);
                });
            });

            scheduleEnsureBlogEditorUi();
        });

        if (document.body) {
            rootObserver.observe(document.body, { childList: true, subtree: true });
        }
    }

    function bindLivewireToolbarRestore() {
        if (window.__blogLivewireToolbarRestoreBound) {
            return;
        }

        window.__blogLivewireToolbarRestoreBound = true;

        const hook = function () {
            scheduleEnsureBlogEditorUi();
        };

        document.addEventListener('livewire:init', function () {
            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                window.Livewire.hook('morph.updated', hook);
            }
        });

        if (window.Livewire && typeof window.Livewire.hook === 'function') {
            window.Livewire.hook('morph.updated', hook);
        }
    }

    function captureFontSizeSelection(editorElement) {
        const editor = editorElement.editor;
        if (!editor) {
            return;
        }

        const live = editor.getSelectedRange();
        if (isExpandedRange(live)) {
            editorElement.__blogSavedRange = live.slice();
        }
    }

    function commitFontSizeInput(editorElement, fontGroup) {
        if (!editorElement || !fontGroup) {
            return;
        }

        const fontInput = fontGroup.querySelector('[data-blog-font-size-input]');
        if (!fontInput) {
            return;
        }

        const cfg = fontSizeConfig();
        const savedRange = getSavedEditorSelection(editorElement);
        const raw = String(fontInput.value || '').trim();

        if (raw === '') {
            applyFontSizePt(editorElement, null, savedRange);
            syncFontSizeUi(editorElement, fontGroup);
            reassertEditorSelectionHighlight(editorElement, savedRange);
            editorElement.dispatchEvent(new Event('trix-change', { bubbles: true }));

            return;
        }

        const pt = parseFontSizePt(raw + 'pt');
        if (pt === null) {
            syncFontSizeUi(editorElement, fontGroup);

            return;
        }

        applyFontSizePt(editorElement, pt, savedRange);
        fontInput.value = String(clampFontPt(pt, cfg));
        syncFontSizePresetActiveState(fontGroup, pt);
        reassertEditorSelectionHighlight(editorElement, savedRange);
        editorElement.dispatchEvent(new Event('trix-change', { bubbles: true }));
    }

    function applyPresetFontSize(editorElement, fontGroup, fontInput, pt) {
        captureFontSizeSelection(editorElement);
        const savedRange = getSavedEditorSelection(editorElement);
        const cfg = fontSizeConfig();
        const clamped = clampFontPt(pt, cfg);

        fontInput.value = String(clamped);
        applyFontSizePt(editorElement, clamped, savedRange);
        syncFontSizePresetActiveState(fontGroup, clamped);
        reassertEditorSelectionHighlight(editorElement, savedRange);
        editorElement.dispatchEvent(new Event('trix-change', { bubbles: true }));
    }

    function ensureBlogToolGroup(toolbar, editorElement) {
        const existingGroup = toolbar.querySelector('[data-blog-trix-tools]');
        if (existingGroup?.getAttribute('data-version') === '2') {
            return;
        }

        existingGroup?.remove();

        const group = document.createElement('div');
        group.className = 'trix-button-group trix-button-group--blog-tools';
        group.setAttribute('data-trix-button-group', 'blog-tools');
        group.setAttribute('data-blog-trix-tools', '1');
        group.setAttribute('data-version', '2');

        const paraBtn = document.createElement('button');
        paraBtn.type = 'button';
        paraBtn.className = 'trix-button trix-button--icon';
        paraBtn.title = 'Đoạn văn (P)';
        paraBtn.innerHTML = '<span class="blog-trix-btn-label">P</span>';
        paraBtn.addEventListener('click', function (e) {
            e.preventDefault();
            setParagraphBlock(editorElement);
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
                    scheduleEnsureBlogEditorUi();
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
        group.appendChild(videoBtn);
        group.appendChild(videoInput);

        const row = toolbar.querySelector('.flex.gap-x-3.overflow-x-auto')
            || toolbar.querySelector('.flex.gap-x-3')
            || toolbar.querySelector('.flex');
        if (row) {
            row.appendChild(group);
        }
    }

    function ensureFontSizeGroup(toolbar, editorElement) {
        const existingShell = toolbar.querySelector('.blog-trix-font-size-shell');
        if (existingShell?.getAttribute('data-version') === '5') {
            return;
        }

        existingShell?.remove();

        bindFontSizeKeyModeListener();

        const shell = document.createElement('div');
        shell.className = 'blog-trix-font-size-shell';
        shell.setAttribute('data-blog-font-size-ui', '1');
        shell.setAttribute('data-version', '5');
        shell.id = 'blog-fs-shell-' + (editorElement.id || Math.random().toString(36).slice(2, 9));

        const fontGroup = document.createElement('div');
        fontGroup.className = 'trix-button-group blog-trix-font-size-group';
        fontGroup.title = 'Cỡ chữ (pt): bôi đen → nhập cỡ → Enter/Áp. ▾ chọn cỡ thường dùng. Không bôi đen: áp dụng cho đoạn tại con trỏ.';

        const fontLabel = document.createElement('span');
        fontLabel.className = 'blog-trix-font-size-label';
        fontLabel.innerHTML = '<span class="blog-trix-font-size-a-cap">A</span><span class="blog-trix-font-size-a-low">a</span>';

        const fontField = document.createElement('div');
        fontField.className = 'blog-trix-font-size-field';

        const fontInput = document.createElement('input');
        fontInput.type = 'text';
        fontInput.className = 'blog-trix-font-size-input';
        fontInput.setAttribute('data-blog-font-size-input', '1');
        fontInput.setAttribute('inputmode', 'numeric');
        fontInput.setAttribute('pattern', '[0-9]*');
        fontInput.setAttribute('autocomplete', 'off');
        fontInput.setAttribute('aria-label', 'Cỡ chữ (pt)');

        const fontUnit = document.createElement('span');
        fontUnit.className = 'blog-trix-font-size-unit';
        fontUnit.textContent = 'pt';

        const presetToggle = document.createElement('button');
        presetToggle.type = 'button';
        presetToggle.className = 'blog-trix-font-size-preset-toggle';
        presetToggle.setAttribute('data-blog-font-size-preset-toggle', '1');
        presetToggle.setAttribute('aria-expanded', 'false');
        presetToggle.title = 'Cỡ chữ thường dùng';
        presetToggle.textContent = '▾';

        const presetZone = document.createElement('div');
        presetZone.className = 'blog-trix-font-size-preset-zone';

        const presetPanel = document.createElement('div');
        presetPanel.className = 'blog-trix-font-size-presets';
        presetPanel.setAttribute('data-shell-id', shell.id);
        presetPanel.hidden = true;
        presetPanel.setAttribute('role', 'menu');
        presetPanel.setAttribute('aria-label', 'Cỡ chữ thường dùng');

        BLOG_FONT_SIZE_PRESETS.forEach(function (presetPt) {
            const presetBtn = document.createElement('button');
            presetBtn.type = 'button';
            presetBtn.className = 'blog-trix-font-size-preset';
            presetBtn.setAttribute('data-pt', String(presetPt));
            presetBtn.textContent = presetPt + ' pt';
            presetBtn.addEventListener('mousedown', function (e) {
                e.preventDefault();
                captureFontSizeSelection(editorElement);
            });
            presetBtn.addEventListener('click', function (e) {
                e.preventDefault();
                applyPresetFontSize(editorElement, fontGroup, fontInput, presetPt);
                closeFontSizePresetPanel(presetPanel);
            });
            presetPanel.appendChild(presetBtn);
        });

        const applyBtn = document.createElement('button');
        applyBtn.type = 'button';
        applyBtn.className = 'blog-trix-font-size-apply';
        applyBtn.title = 'Áp dụng cỡ chữ';
        applyBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg><span>Áp</span>';
        applyBtn.setAttribute('aria-label', 'Áp dụng cỡ chữ');

        fontGroup.__blogEditorElement = editorElement;

        fontGroup.addEventListener('mousedown', function (e) {
            if (e.target.closest('[data-blog-font-size-preset-toggle]')
                || e.target.closest('.blog-trix-font-size-preset-zone')
                || e.target.closest('.blog-trix-font-size-apply')
                || e.target.closest('[data-blog-font-size-input]')
                || e.target.closest('.blog-trix-font-size-field')) {
                return;
            }

            e.preventDefault();
            captureFontSizeSelection(editorElement);
            enableFontSizeKeyMode(editorElement);
        });

        fontField.addEventListener('mousedown', function (e) {
            if (e.target === fontInput) {
                return;
            }

            e.preventDefault();
            captureFontSizeSelection(editorElement);
            showSavedSelectionHighlight(editorElement, fontInput);
            disableFontSizeKeyMode(editorElement);
            window.setTimeout(function () {
                fontInput.focus();
                fontInput.select();
                showSavedSelectionHighlight(editorElement, fontInput);
            }, 0);
        });

        fontInput.addEventListener('mousedown', function (e) {
            e.stopPropagation();
            captureFontSizeSelection(editorElement);
            disableFontSizeKeyMode(editorElement);
        });

        fontInput.addEventListener('focus', function () {
            captureFontSizeSelection(editorElement);
            showSavedSelectionHighlight(editorElement, fontInput);
            disableFontSizeKeyMode(editorElement);
        });

        fontInput.addEventListener('input', function () {
            if (editorElement.getAttribute('data-blog-font-size-pseudo-sel') === '1') {
                showSavedSelectionHighlight(editorElement, fontInput);
            }
        });

        fontInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                commitFontSizeInput(editorElement, fontGroup);
            }
        });

        fontInput.addEventListener('blur', function () {
            window.setTimeout(function () {
                if (document.activeElement === fontInput) {
                    return;
                }

                if (!shell.contains(document.activeElement)) {
                    disableFontSizeKeyMode(editorElement);
                }

                if (document.activeElement !== fontInput
                    && !document.activeElement?.closest?.('.blog-trix-font-size-apply')) {
                    clearSavedSelectionHighlight(editorElement);
                }
            }, 0);
        });

        presetToggle.addEventListener('mousedown', function (e) {
            e.preventDefault();
            e.stopPropagation();
            captureFontSizeSelection(editorElement);
        });

        presetToggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const isOpen = presetToggle.getAttribute('aria-expanded') === 'true';
            closeAllFontSizePresetPanels();
            if (!isOpen) {
                openFontSizePresetPanel(shell, presetPanel, presetToggle);
                syncFontSizePresetActiveState(fontGroup, detectCurrentFontSizePt(editorElement));
            }
        });

        applyBtn.addEventListener('mousedown', function (e) {
            e.preventDefault();
            captureFontSizeSelection(editorElement);
            showSavedSelectionHighlight(editorElement, fontInput);
        });

        applyBtn.addEventListener('click', function (e) {
            e.preventDefault();
            commitFontSizeInput(editorElement, fontGroup);
        });

        presetZone.appendChild(presetToggle);

        fontField.appendChild(fontInput);
        fontField.appendChild(fontUnit);
        fontGroup.appendChild(fontLabel);
        fontGroup.appendChild(fontField);
        fontGroup.appendChild(presetZone);
        fontGroup.appendChild(applyBtn);
        shell.appendChild(fontGroup);
        shell.appendChild(presetPanel);

        syncFontSizeUi(editorElement, shell);

        const row = toolbar.querySelector('.flex.gap-x-3.overflow-x-auto')
            || toolbar.querySelector('.flex.gap-x-3')
            || toolbar.querySelector('.flex');
        if (row) {
            row.appendChild(shell);
        }
    }

    function addToolbarTools(toolbar, editorElement) {
        ensureBlogToolGroup(toolbar, editorElement);
        ensureFontSizeGroup(toolbar, editorElement);
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
                        scheduleEnsureBlogEditorUi();
                    })
                    .catch(function () {
                        alert('Không upload được video.');
                    });
            }

            scheduleEnsureBlogEditorUi();
        });

        editorElement.addEventListener('trix-change', function () {
            scheduleEnsureBlogEditorUi();
        });

        if (!editorElement.dataset.blogTrixSelectionSync) {
            editorElement.dataset.blogTrixSelectionSync = '1';
            editorElement.addEventListener('trix-selection-change', function () {
                rememberEditorSelection(editorElement);
                syncFontSizeUi(editorElement, getFontGroupForEditor(editorElement));
            });
        }

        editorElement.addEventListener('trix-initialize', function () {
            decorateVideoFigures(editorElement);
            scheduleEnsureBlogEditorUi();
        });
    }

    function initBlogEditor(editorElement) {
        if (!isBlogEditor(editorElement)) {
            return;
        }

        ensureBlogEditorUi(editorElement);
        setupEditorListeners(editorElement);
        bindLivewireToolbarRestore();
        observeBlogEditorFields();
    }

    document.addEventListener('trix-initialize', function (event) {
        initBlogEditor(event.target);
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('trix-editor[data-blog-content-editor], .blog-rich-editor-field trix-editor').forEach(initBlogEditor);
        bindLivewireToolbarRestore();
        observeBlogEditorFields();
    });

    if (document.readyState !== 'loading') {
        document.querySelectorAll('trix-editor[data-blog-content-editor], .blog-rich-editor-field trix-editor').forEach(initBlogEditor);
        bindLivewireToolbarRestore();
        observeBlogEditorFields();
    }
})();
</script>
