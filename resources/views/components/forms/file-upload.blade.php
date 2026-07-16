@props(['label' => '', 'name', 'accept' => '*', 'multiple' => false, 'hint' => ''])

<div>
    @if($label)
        <label class="form-label">{{ $label }}</label>
    @endif
    <div
        class="file-upload-zone"
        onclick="this.querySelector('input[type=file]').click()"
        ondragover="event.preventDefault(); this.classList.add('dragover')"
        ondragleave="this.classList.remove('dragover')"
        ondrop="event.preventDefault(); this.classList.remove('dragover'); this.querySelector('input[type=file]').files = event.dataTransfer.files"
    >
        <div class="file-upload-content">
            <i class="ph ph-cloud-arrow-up file-upload-icon"></i>
            <p class="file-upload-text">Drag & drop files here or <span class="file-upload-link">browse</span></p>
            @if($hint)
                <p class="form-hint">{{ $hint }}</p>
            @elseif($accept !== '*')
                <p class="form-hint">Accepted file types: {{ $accept }}</p>
            @endif
        </div>
        <input
            type="file"
            name="{{ $multiple ? $name . '[]' : $name }}"
            id="{{ $name }}"
            accept="{{ $accept }}"
            @if($multiple) multiple @endif
            class="file-upload-input"
            style="display: none;"
            {{ $attributes }}
        />
    </div>
    @error($name)
        <p class="form-error">{{ $message }}</p>
    @enderror
    @if($multiple)
        @error($name . '.*')
            <p class="form-error">{{ $message }}</p>
        @enderror
    @endif
</div>
