/**
 * Import Modal - Alpine.js Component
 *
 * Multi-step CSV import flow: upload → preview/map → import → results.
 * Works inside the <x-tables.import-modal> Blade component.
 *
 * Usage:
 *   <div x-data="importModal({ previewUrl: '/preview', importUrl: '/import', modalId: 'importCsv' })">
 */

import Alpine from 'alpinejs';

Alpine.data('importModal', (config = {}) => ({
    previewUrl: config.previewUrl || '',
    importUrl: config.importUrl || '',
    modalId: config.modalId || 'importCsv',

    // State
    step: 'upload', // upload | preview | results
    file: null,
    fileName: '',
    isDragging: false,
    uploadError: '',

    // Preview
    previewLoading: false,
    csvHeaders: [],
    previewRows: [],
    totalRows: 0,
    dbColumns: [],
    columnMap: {},

    // Import
    importLoading: false,
    resultSuccess: 0,
    resultFailed: 0,
    resultErrors: {},

    handleFileSelect(event) {
        const file = event.target.files[0];
        if (file) {
            this.setFile(file);
        }
    },

    handleFileDrop(event) {
        const file = event.dataTransfer.files[0];
        if (file) {
            this.setFile(file);
        }
    },

    setFile(file) {
        this.uploadError = '';

        // Validate file type
        const validTypes = ['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        const validExtensions = ['.csv', '.txt', '.xlsx', '.xls'];
        const extension = '.' + file.name.split('.').pop().toLowerCase();

        if (!validTypes.includes(file.type) && !validExtensions.includes(extension)) {
            this.uploadError = 'Please select a valid CSV file.';
            return;
        }

        // Validate file size (10MB max)
        if (file.size > 10 * 1024 * 1024) {
            this.uploadError = 'File size must not exceed 10MB.';
            return;
        }

        this.file = file;
        this.fileName = file.name;
    },

    clearFile() {
        this.file = null;
        this.fileName = '';
        this.uploadError = '';
        if (this.$refs.csvFileInput) {
            this.$refs.csvFileInput.value = '';
        }
    },

    async uploadPreview() {
        if (!this.file) return;

        this.previewLoading = true;
        this.uploadError = '';

        const formData = new FormData();
        formData.append('file', this.file);

        try {
            const response = await window.axios.post(this.previewUrl, formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });

            const data = response.data;
            this.csvHeaders = data.headers || [];
            this.previewRows = data.rows || [];
            this.totalRows = data.total || 0;
            this.dbColumns = data.db_columns || [];

            // Set suggested column mapping
            this.columnMap = {};
            (data.suggested_map || []).forEach((dbCol, index) => {
                this.columnMap[index] = dbCol || 'skip';
            });

            this.step = 'preview';
        } catch (error) {
            const message = error.response?.data?.message
                || error.response?.data?.errors?.file?.[0]
                || 'Failed to read CSV file. Please check the format and try again.';
            this.uploadError = message;
        } finally {
            this.previewLoading = false;
        }
    },

    hasMappedColumns() {
        return Object.values(this.columnMap).some(val => val && val !== 'skip');
    },

    async executeImport() {
        if (!this.file || !this.hasMappedColumns()) return;

        this.importLoading = true;

        const formData = new FormData();
        formData.append('file', this.file);

        // Append column_map as individual form fields
        Object.entries(this.columnMap).forEach(([index, dbColumn]) => {
            formData.append(`column_map[${index}]`, dbColumn);
        });

        try {
            const response = await window.axios.post(this.importUrl, formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });

            const data = response.data;
            this.resultSuccess = data.success || 0;
            this.resultFailed = data.failed || 0;
            this.resultErrors = data.errors || {};

            this.step = 'results';

            // Refresh datatable if records were imported
            if (this.resultSuccess > 0) {
                this.refreshDataTable();
            }
        } catch (error) {
            const message = error.response?.data?.message || 'Import failed. Please try again.';
            window.showToast('Error', message, 'error');
        } finally {
            this.importLoading = false;
        }
    },

    goToUpload() {
        this.step = 'upload';
    },

    resetAndClose() {
        this.resetState();
        // Close the modal
        const closeBtn = document.querySelector(`[data-modal-close="${this.modalId}"]`);
        if (closeBtn) {
            closeBtn.click();
        }
    },

    resetForNew() {
        this.resetState();
    },

    resetState() {
        this.step = 'upload';
        this.file = null;
        this.fileName = '';
        this.uploadError = '';
        this.csvHeaders = [];
        this.previewRows = [];
        this.totalRows = 0;
        this.dbColumns = [];
        this.columnMap = {};
        this.resultSuccess = 0;
        this.resultFailed = 0;
        this.resultErrors = {};
        if (this.$refs.csvFileInput) {
            this.$refs.csvFileInput.value = '';
        }
    },

    /**
     * Refresh the datatable on the page after a successful import.
     */
    refreshDataTable() {
        const datatableBody = document.querySelector('[data-datatable-body]');
        if (datatableBody) {
            const dtRoot = datatableBody.closest('[x-data]');
            if (dtRoot && dtRoot._x_dataStack) {
                const dt = dtRoot._x_dataStack[0];
                if (typeof dt.fetchData === 'function') {
                    dt.fetchData();
                    return;
                }
            }
        }
    },
}));
