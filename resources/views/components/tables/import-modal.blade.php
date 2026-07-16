@props(['id' => 'importCsv', 'previewUrl', 'importUrl'])

<x-ui.modal :id="$id" :title="__('Import CSV')" size="lg">
    <div
        x-data="importModal({
            previewUrl: '{{ $previewUrl }}',
            importUrl: '{{ $importUrl }}',
            modalId: '{{ $id }}'
        })"
    >
        {{-- Step 1: File Upload --}}
        <div x-show="step === 'upload'" x-transition>
            <div class="space-y-4">
                <p class="text-sm text-neutral-600">{{ __('Select a CSV file to import. The first row must contain column headers.') }}</p>

                <div
                    class="file-upload-zone"
                    :class="{ 'dragover': isDragging }"
                    @click="$refs.csvFileInput.click()"
                    @dragover.prevent="isDragging = true"
                    @dragleave="isDragging = false"
                    @drop.prevent="isDragging = false; handleFileDrop($event)"
                >
                    <div class="file-upload-content">
                        <i class="ph ph-file-csv file-upload-icon"></i>
                        <p class="file-upload-text">{{ __('Drag & drop your file here or') }} <span class="file-upload-link">{{ __('browse') }}</span></p>
                        <p class="form-hint">{{ __('Accepted file types: .csv, .xlsx, .xls (max 10MB)') }}</p>
                    </div>
                    <input
                        type="file"
                        x-ref="csvFileInput"
                        accept=".csv,.txt,.xlsx,.xls"
                        class="file-upload-input"
                        style="display: none;"
                        @change="handleFileSelect($event)"
                    />
                </div>

                <template x-if="fileName">
                    <div class="flex items-center gap-2 rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3">
                        <i class="ph ph-file-csv text-lg text-primary-600"></i>
                        <span class="text-sm font-medium text-neutral-700" x-text="fileName"></span>
                        <button type="button" class="ml-auto text-neutral-400 hover:text-danger-600" @click="clearFile()">
                            <i class="ph ph-x"></i>
                        </button>
                    </div>
                </template>

                <p x-show="uploadError" x-text="uploadError" class="text-sm text-danger-600"></p>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-ui.button variant="outline" type="button" :data-modal-close="$id">
                    {{ __('Cancel') }}
                </x-ui.button>
                <x-ui.button variant="primary" type="button" @click="uploadPreview()" :disabled="false" x-bind:disabled="!file || previewLoading">
                    <template x-if="previewLoading">
                        <span class="flex items-center gap-2">
                            <i class="ph ph-spinner-gap animate-spin"></i> {{ __('Processing...') }}
                        </span>
                    </template>
                    <template x-if="!previewLoading">
                        <span class="flex items-center gap-2">
                            <i class="ph ph-eye"></i> {{ __('Preview') }}
                        </span>
                    </template>
                </x-ui.button>
            </div>
        </div>

        {{-- Step 2: Preview & Column Mapping --}}
        <div x-show="step === 'preview'" x-transition>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-neutral-600">
                        {{ __('Found') }} <strong x-text="totalRows"></strong> {{ __('rows. Map CSV columns to database fields.') }}
                    </p>
                    <button type="button" class="text-sm text-primary-600 hover:underline" @click="goToUpload()">
                        <i class="ph ph-arrow-left"></i> {{ __('Change file') }}
                    </button>
                </div>

                {{-- Column Mapping --}}
                <div class="rounded-lg border border-neutral-200">
                    <div class="border-b border-neutral-200 bg-neutral-50 px-4 py-3">
                        <h4 class="text-sm font-semibold text-neutral-700">{{ __('Column Mapping') }}</h4>
                    </div>
                    <div class="divide-y divide-neutral-100">
                        <template x-for="(header, index) in csvHeaders" :key="index">
                            <div class="flex items-center gap-4 px-4 py-3">
                                <div class="w-1/3">
                                    <span class="text-sm font-medium text-neutral-700" x-text="header"></span>
                                </div>
                                <div class="flex items-center text-neutral-400">
                                    <i class="ph ph-arrow-right"></i>
                                </div>
                                <div class="w-1/3">
                                    <select
                                        class="input-field w-full rounded-lg px-3 py-1.5 text-sm"
                                        x-model="columnMap[index]"
                                    >
                                        <option value="skip">{{ __('-- Skip --') }}</option>
                                        <template x-for="col in dbColumns" :key="col">
                                            <option :value="col" x-text="col"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Preview Table --}}
                <div class="rounded-lg border border-neutral-200">
                    <div class="border-b border-neutral-200 bg-neutral-50 px-4 py-3">
                        <h4 class="text-sm font-semibold text-neutral-700">{{ __('Data Preview') }} <span class="font-normal text-neutral-400">({{ __('first 5 rows') }})</span></h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-neutral-200 bg-neutral-50">
                                    <template x-for="(header, index) in csvHeaders" :key="'th-'+index">
                                        <th class="whitespace-nowrap px-4 py-2 text-xs font-semibold uppercase text-neutral-500" x-text="header"></th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(row, rowIndex) in previewRows" :key="'row-'+rowIndex">
                                    <tr class="border-b border-neutral-100">
                                        <template x-for="(cell, cellIndex) in row" :key="'cell-'+rowIndex+'-'+cellIndex">
                                            <td class="whitespace-nowrap px-4 py-2 text-neutral-600" x-text="cell || '-'"></td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-ui.button variant="outline" type="button" @click="goToUpload()">
                    {{ __('Back') }}
                </x-ui.button>
                <x-ui.button variant="primary" type="button" @click="executeImport()" x-bind:disabled="importLoading || !hasMappedColumns()">
                    <template x-if="importLoading">
                        <span class="flex items-center gap-2">
                            <i class="ph ph-spinner-gap animate-spin"></i> {{ __('Importing...') }}
                        </span>
                    </template>
                    <template x-if="!importLoading">
                        <span class="flex items-center gap-2">
                            <i class="ph ph-upload-simple"></i> {{ __('Import') }}
                        </span>
                    </template>
                </x-ui.button>
            </div>
        </div>

        {{-- Step 3: Results --}}
        <div x-show="step === 'results'" x-transition>
            <div class="space-y-4">
                {{-- Summary --}}
                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-lg border border-success-200 bg-success-50 p-4 text-center">
                        <p class="text-2xl font-bold text-success-700" x-text="resultSuccess"></p>
                        <p class="text-sm text-success-600">{{ __('Imported') }}</p>
                    </div>
                    <div class="rounded-lg border p-4 text-center" :class="resultFailed > 0 ? 'border-danger-200 bg-danger-50' : 'border-neutral-200 bg-neutral-50'">
                        <p class="text-2xl font-bold" :class="resultFailed > 0 ? 'text-danger-700' : 'text-neutral-400'" x-text="resultFailed"></p>
                        <p class="text-sm" :class="resultFailed > 0 ? 'text-danger-600' : 'text-neutral-400'">{{ __('Failed') }}</p>
                    </div>
                </div>

                {{-- Errors --}}
                <template x-if="Object.keys(resultErrors).length > 0">
                    <div class="rounded-lg border border-danger-200 bg-danger-50 p-4">
                        <h4 class="mb-2 text-sm font-semibold text-danger-700">{{ __('Errors') }}</h4>
                        <div class="max-h-48 space-y-1 overflow-y-auto">
                            <template x-for="(messages, row) in resultErrors" :key="row">
                                <div class="text-sm text-danger-600">
                                    <strong x-text="row + ':'"></strong>
                                    <span x-text="Array.isArray(messages) ? messages.join(', ') : messages"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-ui.button variant="outline" type="button" @click="resetAndClose()">
                    {{ __('Close') }}
                </x-ui.button>
                <x-ui.button variant="primary" type="button" @click="resetForNew()">
                    <i class="ph ph-upload-simple"></i> {{ __('Import Another') }}
                </x-ui.button>
            </div>
        </div>
    </div>
</x-ui.modal>
