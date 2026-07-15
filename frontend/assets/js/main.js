/**
 * Smart Grading - Main JavaScript File
 * File: assets/js/main.js
 * Fungsi: Menangani interaksi frontend, AJAX, validasi, dan UI enhancements
 */

$(document).ready(function() {
    console.log('Smart Grading JS loaded successfully');
    
    // ============================================
    // 1. FILE UPLOAD HANDLING
    // ============================================
    
    /**
     * Handle file upload with drag & drop
     */
    function initFileUpload() {
        $('.upload-zone').each(function() {
            const zone = $(this);
            const fileInput = zone.find('input[type="file"]');
            const preview = zone.find('.file-preview');
            
            // Click to upload
            zone.on('click', function(e) {
                if (e.target === this || $(e.target).closest('.upload-zone-content').length) {
                    fileInput.click();
                }
            });
            
            // Drag & drop events
            zone.on('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });
            
            zone.on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });
            
            zone.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
                
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    fileInput[0].files = files;
                    fileInput.trigger('change');
                }
            });
            
            // File selected
            fileInput.on('change', function() {
                const files = this.files;
                if (files.length > 0) {
                    const file = files[0];
                    updateFilePreview(preview, file);
                }
            });
        });
    }
    
    /**
     * Update file preview
     */
    function updateFilePreview(preview, file) {
        const size = formatFileSize(file.size);
        const icon = getFileIcon(file.type);
        const ext = file.name.split('.').pop().toUpperCase();
        
        preview.html(`
            <div class="file-info alert alert-success">
                <i class="fas ${icon} fa-2x"></i>
                <div class="file-details">
                    <strong>${escapeHtml(file.name)}</strong>
                    <br>
                    <small class="text-muted">${size} | ${ext}</small>
                </div>
                <button type="button" class="btn btn-sm btn-danger remove-file">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `);
        
        // Remove file button
        preview.find('.remove-file').on('click', function(e) {
            e.stopPropagation();
            preview.empty();
            const input = preview.closest('.upload-zone').find('input[type="file"]');
            input.val('');
        });
    }
    
    /**
     * Get file icon based on mime type
     */
    function getFileIcon(mimeType) {
        const icons = {
            'application/pdf': 'fa-file-pdf',
            'image/jpeg': 'fa-file-image',
            'image/png': 'fa-file-image',
            'text/plain': 'fa-file-alt',
            'application/vnd.ms-excel': 'fa-file-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'fa-file-excel',
            'application/msword': 'fa-file-word',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'fa-file-word'
        };
        return icons[mimeType] || 'fa-file';
    }
    
    /**
     * Format file size
     */
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    
    // ============================================
    // 2. FORM VALIDATION
    // ============================================
    
    /**
     * Validate form before submit
     */
    function initFormValidation() {
        $('form[data-validate]').on('submit', function(e) {
            const form = $(this);
            let isValid = true;
            
            form.find('[required]').each(function() {
                const input = $(this);
                const value = input.val().trim();
                const errorContainer = input.closest('.form-group').find('.invalid-feedback');
                
                // Reset validation state
                input.removeClass('is-invalid is-valid');
                if (errorContainer.length) {
                    errorContainer.remove();
                }
                
                if (!value) {
                    isValid = false;
                    input.addClass('is-invalid');
                    showError(input, 'Field ini wajib diisi');
                } else {
                    // Additional validation based on input type
                    if (input.attr('type') === 'email' && !isValidEmail(value)) {
                        isValid = false;
                        input.addClass('is-invalid');
                        showError(input, 'Format email tidak valid');
                    } else if (input.attr('type') === 'number' && isNaN(value)) {
                        isValid = false;
                        input.addClass('is-invalid');
                        showError(input, 'Harus berupa angka');
                    } else if (input.attr('maxlength') && value.length > parseInt(input.attr('maxlength'))) {
                        isValid = false;
                        input.addClass('is-invalid');
                        showError(input, `Maksimal ${input.attr('maxlength')} karakter`);
                    } else {
                        input.addClass('is-valid');
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                // Scroll to first error
                $('.is-invalid').first().focus();
            }
        });
    }
    
    /**
     * Show error message for input
     */
    function showError(input, message) {
        const formGroup = input.closest('.form-group');
        let errorDiv = formGroup.find('.invalid-feedback');
        
        if (!errorDiv.length) {
            errorDiv = $('<div class="invalid-feedback"></div>');
            formGroup.append(errorDiv);
        }
        
        errorDiv.text(message);
    }
    
    /**
     * Validate email format
     */
    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    /**
     * Real-time validation on input
     */
    function initLiveValidation() {
        $('input[required], select[required]').on('input change', function() {
            const input = $(this);
            const value = input.val().trim();
            
            if (value) {
                input.removeClass('is-invalid').addClass('is-valid');
                const error = input.closest('.form-group').find('.invalid-feedback');
                if (error.length) {
                    error.remove();
                }
            } else {
                input.removeClass('is-valid').addClass('is-invalid');
            }
        });
    }
    
    
    // ============================================
    // 3. AJAX / API CALLS
    // ============================================
    
    /**
     * Generic AJAX call with loading state
     */
    function callAPI(endpoint, method = 'GET', data = null, showLoading = true) {
        return new Promise((resolve, reject) => {
            if (showLoading) {
                showLoadingOverlay();
            }
            
            const options = {
                url: endpoint,
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    if (showLoading) {
                        hideLoadingOverlay();
                    }
                    resolve(response);
                },
                error: function(xhr, status, error) {
                    if (showLoading) {
                        hideLoadingOverlay();
                    }
                    reject({xhr, status, error});
                }
            };
            
            if (data) {
                options.data = JSON.stringify(data);
            }
            
            $.ajax(options);
        });
    }
    
    /**
     * Upload file via AJAX
     */
    function uploadFile(file, endpoint, additionalData = {}) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('file', file);
            
            Object.keys(additionalData).forEach(key => {
                formData.append(key, additionalData[key]);
            });
            
            showLoadingOverlay();
            
            $.ajax({
                url: endpoint,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const progress = Math.round((e.loaded / e.total) * 100);
                            updateUploadProgress(progress);
                        }
                    });
                    return xhr;
                },
                success: function(response) {
                    hideLoadingOverlay();
                    resolve(response);
                },
                error: function(xhr, status, error) {
                    hideLoadingOverlay();
                    reject({xhr, status, error});
                }
            });
        });
    }
    
    
    // ============================================
    // 4. LOADING OVERLAY
    // ============================================
    
    /**
     * Show loading overlay with optional message
     */
    function showLoadingOverlay(message = 'Memproses...') {
        let overlay = $('.spinner-overlay');
        
        if (!overlay.length) {
            overlay = $(`
                <div class="spinner-overlay">
                    <div class="spinner-container">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                        <p class="mt-3 text-white loading-message">${message}</p>
                    </div>
                </div>
            `);
            $('body').append(overlay);
        }
        
        overlay.find('.loading-message').text(message);
        overlay.addClass('active');
    }
    
    /**
     * Hide loading overlay
     */
    function hideLoadingOverlay() {
        $('.spinner-overlay').removeClass('active');
    }
    
    /**
     * Update upload progress
     */
    function updateUploadProgress(progress) {
        let progressBar = $('.upload-progress-bar');
        
        if (!progressBar.length) {
            progressBar = $(`
                <div class="upload-progress">
                    <div class="progress">
                        <div class="progress-bar upload-progress-bar" role="progressbar" 
                             style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                            0%
                        </div>
                    </div>
                </div>
            `);
            $('.upload-zone').append(progressBar);
        }
        
        progressBar.css('width', progress + '%');
        progressBar.attr('aria-valuenow', progress);
        progressBar.text(progress + '%');
        
        if (progress >= 100) {
            setTimeout(() => {
                progressBar.closest('.upload-progress').remove();
            }, 1000);
        }
    }
    
    
    // ============================================
    // 5. NOTIFICATIONS
    // ============================================
    
    /**
     * Show toast notification
     */
    function showToast(message, type = 'success', duration = 3000) {
        const colors = {
            success: 'bg-success text-white',
            error: 'bg-danger text-white',
            warning: 'bg-warning text-dark',
            info: 'bg-info text-white'
        };
        
        const toast = $(`
            <div class="toast-notification ${colors[type] || colors.info}" 
                 style="position: fixed; top: 20px; right: 20px; z-index: 9999; 
                        padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                        transform: translateX(120%); transition: transform 0.3s ease;">
                <i class="fas ${getToastIcon(type)} mr-2"></i>
                ${message}
            </div>
        `);
        
        $('body').append(toast);
        
        setTimeout(() => {
            toast.css('transform', 'translateX(0)');
        }, 100);
        
        setTimeout(() => {
            toast.css('transform', 'translateX(120%)');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, duration);
    }
    
    /**
     * Get toast icon based on type
     */
    function getToastIcon(type) {
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        return icons[type] || 'fa-info-circle';
    }
    
    
    // ============================================
    // 6. TABLE FUNCTIONS
    // ============================================
    
    /**
     * Search/filter table rows
     */
    function initTableSearch() {
        $('.table-search').on('keyup', function() {
            const search = $(this).val().toLowerCase();
            const table = $(this).closest('.card').find('table');
            
            table.find('tbody tr').each(function() {
                const text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(search) > -1);
            });
        });
    }
    
    /**
     * Sort table columns
     */
    function initTableSort() {
        $('table thead th[data-sort]').on('click', function() {
            const table = $(this).closest('table');
            const column = $(this).data('sort');
            const isAsc = $(this).hasClass('asc');
            
            // Clear other sorting indicators
            table.find('thead th').removeClass('asc desc');
            $(this).toggleClass('asc', !isAsc).toggleClass('desc', isAsc);
            
            const rows = table.find('tbody tr').get();
            const sortOrder = isAsc ? -1 : 1;
            
            rows.sort((a, b) => {
                const valA = $(a).find('td:eq(' + column + ')').text().toLowerCase();
                const valB = $(b).find('td:eq(' + column + ')').text().toLowerCase();
                
                if ($.isNumeric(valA) && $.isNumeric(valB)) {
                    return sortOrder * (parseFloat(valA) - parseFloat(valB));
                }
                return sortOrder * valA.localeCompare(valB);
            });
            
            $.each(rows, function(index, row) {
                table.find('tbody').append(row);
            });
        });
    }
    
    
    // ============================================
    // 7. CONFIRM DIALOG
    // ============================================
    
    /**
     * Custom confirm dialog
     */
    function confirmDialog(message, callback) {
        const dialog = $(`
            <div class="modal fade" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Konfirmasi</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p>${message}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                            <button type="button" class="btn btn-danger confirm-btn">Ya, Lanjutkan</button>
                        </div>
                    </div>
                </div>
            </div>
        `);
        
        dialog.modal('show');
        
        dialog.find('.confirm-btn').on('click', function() {
            dialog.modal('hide');
            if (callback) callback(true);
        });
        
        dialog.on('hidden.bs.modal', function() {
            dialog.remove();
        });
    }
    
    /**
     * Initialize delete confirmation
     */
    function initDeleteConfirmation() {
        $('[data-confirm-delete]').on('click', function(e) {
            e.preventDefault();
            const href = $(this).attr('href');
            const message = $(this).data('confirm-message') || 'Apakah Anda yakin ingin menghapus data ini?';
            
            confirmDialog(message, function(confirmed) {
                if (confirmed) {
                    window.location.href = href;
                }
            });
        });
    }
    
    
    // ============================================
    // 8. DATA TABLE (AJAX Loading)
    // ============================================
    
    /**
     * Initialize AJAX data table
     */
    function initDataTable(tableId, endpoint, columns) {
        const table = $(tableId);
        const tbody = table.find('tbody');
        const pagination = table.find('.pagination');
        let currentPage = 1;
        let totalPages = 1;
        
        function loadData(page = 1) {
            showLoadingOverlay('Memuat data...');
            
            $.ajax({
                url: endpoint,
                method: 'GET',
                data: { page: page },
                success: function(response) {
                    hideLoadingOverlay();
                    renderData(response.data);
                    renderPagination(response);
                },
                error: function() {
                    hideLoadingOverlay();
                    showToast('Gagal memuat data', 'error');
                }
            });
        }
        
        function renderData(data) {
            tbody.empty();
            
            if (!data || data.length === 0) {
                tbody.html(`
                    <tr>
                        <td colspan="${columns.length}" class="text-center">
                            <div class="py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Tidak ada data</p>
                            </div>
                        </td>
                    </tr>
                `);
                return;
            }
            
            data.forEach((row, index) => {
                let html = '<tr>';
                columns.forEach(col => {
                    const value = row[col.key] || '';
                    html += `<td>${col.render ? col.render(value, row, index) : escapeHtml(String(value))}</td>`;
                });
                html += '</tr>';
                tbody.append(html);
            });
        }
        
        function renderPagination(response) {
            if (response.total_pages <= 1) {
                pagination.hide();
                return;
            }
            
            pagination.show();
            currentPage = response.current_page;
            totalPages = response.total_pages;
            
            let html = '';
            
            // Previous
            html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a>
            </li>`;
            
            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>`;
            }
            
            // Next
            html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${currentPage + 1}">Next</a>
            </li>`;
            
            pagination.html(html);
            
            pagination.find('a.page-link').on('click', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                if (page >= 1 && page <= totalPages) {
                    loadData(page);
                }
            });
        }
        
        // Initial load
        loadData();
    }
    
    
    // ============================================
    // 9. KEYBOARD SHORTCUTS
    // ============================================
    
    /**
     * Keyboard shortcuts for common actions
     */
    function initKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            // Ctrl+S = Save
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                const form = $('form[data-validate]');
                if (form.length) {
                    form.submit();
                }
            }
            
            // Escape = Close modal / Cancel
            if (e.key === 'Escape') {
                $('.modal.show').modal('hide');
            }
        });
    }
    
    
    // ============================================
    // 10. INITIALIZATION
    // ============================================
    
    // Initialize all features
    function init() {
        initFileUpload();
        initFormValidation();
        initLiveValidation();
        initTableSearch();
        initTableSort();
        initDeleteConfirmation();
        initKeyboardShortcuts();
        
        // Auto-dismiss alerts after 5 seconds
        $('.alert').not('.alert-permanent').each(function() {
            const alert = $(this);
            setTimeout(() => {
                alert.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
        });
        
        console.log('All features initialized successfully');
    }
    
    // Run initialization
    init();
    
    // Make functions globally accessible
    window.SmartGrading = {
        showToast: showToast,
        showLoading: showLoadingOverlay,
        hideLoading: hideLoadingOverlay,
        callAPI: callAPI,
        uploadFile: uploadFile,
        confirmDialog: confirmDialog,
        initDataTable: initDataTable
    };
});