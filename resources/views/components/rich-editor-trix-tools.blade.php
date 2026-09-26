<style>
    .trix-button--table::before,
    .trix-button--hr::before {
        background-size: 1.15rem 1.15rem;
        background-repeat: no-repeat;
        background-position: center;
    }
    .trix-button--table::before {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.8' stroke='currentColor'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M3.75 6A2.25 2.25 0 016 3.75h12A2.25 2.25 0 0120.25 6v12A2.25 2.25 0 0118 20.25H6A2.25 2.25 0 013.75 18V6zM8.25 8.25h7.5M8.25 12h7.5M8.25 15.75h7.5M12 8.25v7.5'/%3E%3C/svg%3E");
    }
    .trix-button--hr::before {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.8' stroke='currentColor'%3E%3Cpath stroke-linecap='round' d='M4 12h16'/%3E%3C/svg%3E");
    }
    trix-editor table,
    .fi-fo-rich-editor-editor table {
        width: 100%;
        border-collapse: collapse;
        margin: 0.75rem 0;
        font-size: 0.875rem;
    }
    trix-editor table th,
    trix-editor table td,
    .fi-fo-rich-editor-editor table th,
    .fi-fo-rich-editor-editor table td {
        border: 1px solid rgba(148, 163, 184, 0.45);
        padding: 0.45rem 0.6rem;
        vertical-align: top;
        min-width: 3rem;
    }
    trix-editor table th,
    .fi-fo-rich-editor-editor table th {
        background: rgba(37, 99, 235, 0.12);
        font-weight: 600;
    }
</style>
<script>
(function () {
    function allowTableTagsInTrix() {
        if (!window.Trix || !window.Trix.config) {
            return;
        }

        window.Trix.config.dompurify = window.Trix.config.dompurify || {};
        var addTags = window.Trix.config.dompurify.ADD_TAGS || [];
        var addAttr = window.Trix.config.dompurify.ADD_ATTR || [];

        ['table', 'thead', 'tbody', 'tr', 'th', 'td'].forEach(function (tag) {
            if (addTags.indexOf(tag) === -1) {
                addTags.push(tag);
            }
        });

        ['colspan', 'rowspan', 'border', 'cellpadding', 'cellspacing', 'style', 'class'].forEach(function (attr) {
            if (addAttr.indexOf(attr) === -1) {
                addAttr.push(attr);
            }
        });

        window.Trix.config.dompurify.ADD_TAGS = addTags;
        window.Trix.config.dompurify.ADD_ATTR = addAttr;
    }

    document.addEventListener('trix-before-initialize', allowTableTagsInTrix);

    function buildTableHtml(rows, cols) {
        var html = '<table class="blog-content-table"><tbody>';
        for (var r = 0; r < rows; r++) {
            html += '<tr>';
            for (var c = 0; c < cols; c++) {
                if (r === 0) {
                    html += '<th>Header ' + (c + 1) + '</th>';
                } else {
                    html += '<td>Cell</td>';
                }
            }
            html += '</tr>';
        }
        html += '</tbody></table>';

        return html;
    }

    function getEditorForToolbar(toolbar) {
        var toolbarId = toolbar.getAttribute('id') || '';
        if (!toolbarId.startsWith('trix-toolbar-')) {
            return null;
        }

        var editorId = toolbarId.replace('trix-toolbar-', '');
        var editor = document.getElementById(editorId);

        return editor && editor.editor ? editor : null;
    }

    function createToolButton(className, title, onClick) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'trix-button trix-button--icon ' + className;
        button.title = title;
        button.tabIndex = -1;
        button.addEventListener('click', function (event) {
            event.preventDefault();
            onClick(event);
        });

        return button;
    }

    function enhanceTrixToolbar(toolbar) {
        if (!toolbar || toolbar.dataset.trixToolsEnhanced === '1') {
            return;
        }

        toolbar.dataset.trixToolsEnhanced = '1';

        var container = toolbar.querySelector('.flex.gap-x-3.overflow-x-auto')
            || toolbar.querySelector('.flex.gap-x-3')
            || toolbar.querySelector('.flex');

        if (!container) {
            return;
        }

        var group = document.createElement('div');
        group.className = 'trix-button-group';
        group.setAttribute('data-trix-button-group', 'extra-tools');

        var tableButton = createToolButton('trix-button--table', 'Insert table', function () {
            var editor = getEditorForToolbar(toolbar);
            if (!editor) {
                return;
            }

            var rows = parseInt(window.prompt('Number of rows:', '3'), 10);
            var cols = parseInt(window.prompt('Number of columns:', '3'), 10);

            if (!rows || !cols || rows < 1 || cols < 1 || rows > 20 || cols > 10) {
                return;
            }

            editor.editor.insertHTML(buildTableHtml(rows, cols));
        });

        var hrButton = createToolButton('trix-button--hr', 'Horizontal rule', function () {
            var editor = getEditorForToolbar(toolbar);
            if (!editor) {
                return;
            }

            editor.editor.insertHTML('<hr><p><br></p>');
        });

        group.appendChild(tableButton);
        group.appendChild(hrButton);

        var historyGroup = container.querySelector('[data-trix-button-group="history-tools"]');
        if (historyGroup) {
            container.insertBefore(group, historyGroup);
        } else {
            container.appendChild(group);
        }
    }

    function enhanceAllTrixToolbars() {
        document.querySelectorAll('trix-toolbar').forEach(enhanceTrixToolbar);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enhanceAllTrixToolbars);
    } else {
        enhanceAllTrixToolbars();
    }

    document.addEventListener('livewire:navigated', enhanceAllTrixToolbars);
    document.addEventListener('livewire:init', function () {
        enhanceAllTrixToolbars();

        if (window.Livewire && typeof window.Livewire.hook === 'function') {
            window.Livewire.hook('morph.updated', function () {
                window.requestAnimationFrame(enhanceAllTrixToolbars);
            });
        }
    });

    if (window.MutationObserver) {
        var observer = new MutationObserver(function () {
            enhanceAllTrixToolbars();
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
        });
    }
})();
</script>
