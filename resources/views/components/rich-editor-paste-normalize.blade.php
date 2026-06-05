@php
    $uploadUrl = route('blog.upload-image');
@endphp
<meta name="upload-image-url" content="{{ $uploadUrl }}">
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Wait for Filament/Livewire to initialize
    document.addEventListener('livewire:init', function() {
        // Function to normalize fonts in pasted content
        function normalizePastedContent(editor) {
            if (!editor) return;
            
            // Get the TipTap editor instance
            const editorElement = editor.querySelector('[contenteditable="true"]');
            if (!editorElement) return;
            
            // Listen for paste events
            editorElement.addEventListener('paste', function(e) {
                // Handle image paste
                const items = (e.clipboardData || e.originalEvent?.clipboardData)?.items;
                if (items) {
                    for (let i = 0; i < items.length; i++) {
                        if (items[i].type.indexOf('image') !== -1) {
                            e.preventDefault();
                            const blob = items[i].getAsFile();
                            if (blob) {
                                // Create FormData to upload image
                                const formData = new FormData();
                                formData.append('file', blob);
                                formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
                                
                                // Upload image
                                const uploadUrl = document.querySelector('meta[name="upload-image-url"]')?.getAttribute('content') || '/admin/blog/upload-image';
                                fetch(uploadUrl, {
                                    method: 'POST',
                                    body: formData,
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Accept': 'application/json',
                                    }
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.url) {
                                        // Find TipTap editor instance
                                        const editorContainer = editorElement.closest('[data-field-wrapper]');
                                        if (editorContainer) {
                                            // Try to find TipTap editor
                                            const tiptapEditorElement = editorContainer.querySelector('.ProseMirror, [data-tiptap-editor]');
                                            if (tiptapEditorElement) {
                                                // Get TipTap editor instance from Alpine or global
                                                const editor = tiptapEditorElement.__tiptapEditor || 
                                                              (window.Alpine && Alpine.$data(tiptapEditorElement)?.editor) ||
                                                              tiptapEditorElement._tiptapEditor;
                                                
                                                if (editor && typeof editor.chain === 'function') {
                                                    // Use TipTap API to insert image
                                                    editor.chain().focus().setImage({ src: data.url }).run();
                                                } else {
                                                    // Fallback: insert img tag as HTML
                                                    const imgHtml = `<img src="${data.url}" style="max-width: 100%; height: auto;" />`;
                                                    const selection = window.getSelection();
                                                    if (selection.rangeCount > 0) {
                                                        const range = selection.getRangeAt(0);
                                                        range.deleteContents();
                                                        const tempDiv = document.createElement('div');
                                                        tempDiv.innerHTML = imgHtml;
                                                        const img = tempDiv.firstChild;
                                                        range.insertNode(img);
                                                        range.setStartAfter(img);
                                                        range.collapse(true);
                                                        selection.removeAllRanges();
                                                        selection.addRange(range);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                })
                                .catch(error => {
                                    console.error('Error uploading image:', error);
                                });
                                return;
                            }
                        }
                    }
                }
                
                // Normalize fonts after paste
                setTimeout(function() {
                    // Get all elements with style attributes
                    const styledElements = editorElement.querySelectorAll('[style*="font-family"], [style*="font-size"], [style*="fontFamily"], [style*="fontSize"]');
                    
                    styledElements.forEach(function(el) {
                        // Remove font-family and font-size from style
                        el.style.removeProperty('font-family');
                        el.style.removeProperty('font-size');
                        el.style.removeProperty('fontFamily');
                        el.style.removeProperty('fontSize');
                    });
                    
                    // Also handle inline styles in the root
                    if (editorElement.style.fontFamily) {
                        editorElement.style.removeProperty('font-family');
                    }
                    if (editorElement.style.fontSize) {
                        editorElement.style.removeProperty('font-size');
                    }
                }, 10);
            });
            
            // Also handle input events to catch any remaining styled content
            editorElement.addEventListener('input', function() {
                const styledElements = editorElement.querySelectorAll('[style*="font-family"], [style*="font-size"]');
                styledElements.forEach(function(el) {
                    el.style.removeProperty('font-family');
                    el.style.removeProperty('font-size');
                });
            });
        }
        
        // Find all RichEditor instances and apply normalization
        function initializeEditors() {
            // Filament RichEditor uses TipTap, find the editor containers
            const editorContainers = document.querySelectorAll('[data-field-wrapper]');
            editorContainers.forEach(function(container) {
                const richEditor = container.querySelector('.tiptap-editor, [data-tiptap-editor], .ProseMirror');
                if (richEditor) {
                    normalizePastedContent(richEditor);
                }
            });
            
            // Also check for dynamically loaded editors
            setTimeout(function() {
                const allEditors = document.querySelectorAll('.tiptap-editor, [data-tiptap-editor], .ProseMirror');
                allEditors.forEach(function(editor) {
                    if (!editor.hasAttribute('data-normalized')) {
                        editor.setAttribute('data-normalized', 'true');
                        normalizePastedContent(editor);
                    }
                });
            }, 500);
        }
        
        // Initialize on page load
        initializeEditors();
        
        // Re-initialize when Livewire updates
        Livewire.hook('morph.updated', function() {
            setTimeout(initializeEditors, 100);
        });
    });
    
    // Fallback for when Livewire is already initialized
    if (window.Livewire && window.Livewire.initialized) {
        const editorContainers = document.querySelectorAll('[data-field-wrapper]');
        editorContainers.forEach(function(container) {
            const richEditor = container.querySelector('.tiptap-editor, [data-tiptap-editor], .ProseMirror');
            if (richEditor) {
                const editorElement = richEditor.querySelector('[contenteditable="true"]');
                if (editorElement) {
                    editorElement.addEventListener('paste', function(e) {
                        setTimeout(function() {
                            const styledElements = editorElement.querySelectorAll('[style*="font-family"], [style*="font-size"]');
                            styledElements.forEach(function(el) {
                                el.style.removeProperty('font-family');
                                el.style.removeProperty('font-size');
                            });
                        }, 10);
                    });
                }
            }
        });
    }
});
</script>

