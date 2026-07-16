@props(['label' => '', 'name', 'value' => '', 'required' => false, 'placeholder' => 'Type your content here...'])

@php
    $editorId = 'editor-' . $name . '-' . uniqid();
@endphp

<div>
    @if($label)
        <label class="form-label">{{ $label }} @if($required)<span class="required">*</span>@endif</label>
    @endif
    <div class="editor-wrapper">
        <div id="{{ $editorId }}">{!! old($name, $value) !!}</div>
        <textarea id="{{ $editorId }}-html" class="editor-html-source"></textarea>
    </div>
    <input type="hidden" name="{{ $name }}" id="{{ $editorId }}-input" value="{{ old($name, $value) }}" />
    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror
</div>

@once
@push('styles')
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet" />
@endpush
@endonce

@once
@push('scripts')
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
@endpush
@endonce

@push('scripts')
<script>
    (function() {
        function initQuillEditor() {
            const editorEl = document.getElementById('{{ $editorId }}');
            if (!editorEl || typeof Quill === 'undefined') return;

            const quill = new Quill(editorEl, {
                theme: 'snow',
                placeholder: @js($placeholder),
                modules: {
                    toolbar: {
                        container: [
                            [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ 'color': [] }, { 'background': [] }],
                            ['blockquote', 'code-block'],
                            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                            [{ 'direction': 'rtl' }],
                            [{ 'align': [] }],
                            ['link', 'image', 'video'],
                            ['clean'],
                        ],
                        handlers: {}
                    }
                },
            });

            const hiddenInput = document.getElementById('{{ $editorId }}-input');
            const htmlTextarea = document.getElementById('{{ $editorId }}-html');
            let htmlMode = false;

            quill.on('text-change', function() {
                if (!htmlMode) {
                    hiddenInput.value = quill.root.innerHTML;
                }
            });

            // Add HTML source toggle button to toolbar
            const toolbar = editorEl.closest('.editor-wrapper').querySelector('.ql-toolbar');
            if (toolbar) {
                const htmlBtnGroup = document.createElement('span');
                htmlBtnGroup.className = 'ql-formats';
                const htmlBtn = document.createElement('button');
                htmlBtn.type = 'button';
                htmlBtn.className = 'ql-html-btn';
                htmlBtn.innerHTML = '&lt;/&gt;';
                htmlBtn.title = 'HTML Source';
                htmlBtnGroup.appendChild(htmlBtn);
                toolbar.appendChild(htmlBtnGroup);

                htmlBtn.addEventListener('click', function() {
                    htmlMode = !htmlMode;
                    htmlBtn.classList.toggle('ql-active', htmlMode);

                    if (htmlMode) {
                        htmlTextarea.value = quill.root.innerHTML;
                        htmlTextarea.classList.add('active');
                        editorEl.querySelector('.ql-editor').style.display = 'none';
                    } else {
                        quill.root.innerHTML = htmlTextarea.value;
                        hiddenInput.value = htmlTextarea.value;
                        htmlTextarea.classList.remove('active');
                        editorEl.querySelector('.ql-editor').style.display = '';
                    }
                });

                // Sync HTML textarea changes back
                htmlTextarea.addEventListener('input', function() {
                    hiddenInput.value = htmlTextarea.value;
                });
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initQuillEditor);
        } else {
            initQuillEditor();
        }
    })();
</script>
@endpush
