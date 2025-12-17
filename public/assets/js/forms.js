// Forms JavaScript - RTL Support

class FormHandler {
    constructor(formElement, formSlug) {
        this.form = formElement;
        this.formSlug = formSlug;
        this.totalFields = 0;
        this.filledFields = 0;
        this.draftKey = `form_draft_${formSlug}`;
        this.repeaterCounts = {};
        
        this.init();
    }
    
    init() {
        this.setupValidation();
        this.setupFileUploads();
        this.setupRepeaters();
        this.setupProgressBar();
        this.setupDraftSaving();
        this.loadDraft();
        this.setupPreview();
    }
    
    setupValidation() {
        const inputs = this.form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            input.addEventListener('blur', (e) => this.validateField(e.target));
            input.addEventListener('input', (e) => this.updateProgress());
        });
        
        this.form.addEventListener('submit', (e) => {
            if (!this.validateForm()) {
                e.preventDefault();
                this.showError('يرجى تصحيح الأخطاء قبل الإرسال');
            }
        });
    }
    
    validateField(field) {
        if (!field || field.type === 'button' || field.type === 'submit') {
            return true;
        }
        
        const isRequired = field.hasAttribute('required');
        const value = field.value.trim();
        const type = field.type;
        
        let isValid = true;
        let errorMessage = '';
        
        if (isRequired && !value) {
            isValid = false;
            errorMessage = 'هذا الحقل مطلوب';
        } else if (value) {
            if (type === 'email') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    isValid = false;
                    errorMessage = 'البريد الإلكتروني غير صحيح';
                }
            } else if (type === 'number') {
                const min = field.getAttribute('min');
                const max = field.getAttribute('max');
                const numValue = parseFloat(value);
                
                if (min !== null && numValue < parseFloat(min)) {
                    isValid = false;
                    errorMessage = `القيمة يجب أن تكون أكبر من أو تساوي ${min}`;
                } else if (max !== null && numValue > parseFloat(max)) {
                    isValid = false;
                    errorMessage = `القيمة يجب أن تكون أصغر من أو تساوي ${max}`;
                }
            }
            
            const minLength = field.getAttribute('minlength');
            const maxLength = field.getAttribute('maxlength');
            
            if (minLength !== null && value.length < parseInt(minLength)) {
                isValid = false;
                errorMessage = `يجب أن يكون على الأقل ${minLength} حروف`;
            } else if (maxLength !== null && value.length > parseInt(maxLength)) {
                isValid = false;
                errorMessage = `يجب أن لا يتجاوز ${maxLength} حروف`;
            }
        }
        
        this.setFieldValidation(field, isValid, errorMessage);
        return isValid;
    }
    
    setFieldValidation(field, isValid, errorMessage = '') {
        field.classList.remove('is-valid', 'is-invalid');
        
        const feedback = field.parentElement.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.textContent = errorMessage;
        }
        
        if (field.value.trim()) {
            field.classList.add(isValid ? 'is-valid' : 'is-invalid');
        }
    }
    
    validateForm() {
        const inputs = this.form.querySelectorAll('input, select, textarea');
        let isValid = true;
        
        inputs.forEach(input => {
            if (input.type !== 'button' && input.type !== 'submit') {
                if (!this.validateField(input)) {
                    isValid = false;
                }
            }
        });
        
        const requiredFiles = this.form.querySelectorAll('input[type="file"][required]');
        requiredFiles.forEach(fileInput => {
            if (!fileInput.files.length) {
                isValid = false;
                this.setFieldValidation(fileInput, false, 'يرجى اختيار ملف');
            }
        });
        
        return isValid;
    }
    
    setupFileUploads() {
        const fileInputs = this.form.querySelectorAll('input[type="file"]');
        
        fileInputs.forEach(input => {
            const container = input.closest('.file-upload-container');
            if (!container) return;
            
            container.addEventListener('dragover', (e) => {
                e.preventDefault();
                container.classList.add('dragover');
            });
            
            container.addEventListener('dragleave', () => {
                container.classList.remove('dragover');
            });
            
            container.addEventListener('drop', (e) => {
                e.preventDefault();
                container.classList.remove('dragover');
                input.files = e.dataTransfer.files;
                this.previewFile(input);
            });
            
            input.addEventListener('change', () => this.previewFile(input));
        });
    }
    
    previewFile(input) {
        const previewContainer = input.parentElement.querySelector('.file-preview');
        if (!previewContainer) return;
        
        previewContainer.innerHTML = '';
        
        if (input.files.length === 0) return;
        
        Array.from(input.files).forEach(file => {
            const item = document.createElement('div');
            item.className = 'file-preview-item';
            
            const icon = this.getFileIcon(file.name);
            const size = this.formatFileSize(file.size);
            
            item.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas ${icon.icon} file-icon ${icon.class}"></i>
                    <div>
                        <div class="fw-bold">${this.escapeHtml(file.name)}</div>
                        <small class="text-muted">${size}</small>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.file-preview-item').remove(); document.getElementById('${input.id}').value = '';">
                    <i class="fas fa-times"></i>
                </button>
            `;
            
            previewContainer.appendChild(item);
        });
        
        this.validateField(input);
    }
    
    getFileIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        
        const icons = {
            'pdf': { icon: 'fa-file-pdf', class: 'pdf' },
            'doc': { icon: 'fa-file-word', class: 'doc' },
            'docx': { icon: 'fa-file-word', class: 'doc' },
            'xls': { icon: 'fa-file-excel', class: 'xls' },
            'xlsx': { icon: 'fa-file-excel', class: 'xls' },
            'ppt': { icon: 'fa-file-powerpoint', class: 'doc' },
            'pptx': { icon: 'fa-file-powerpoint', class: 'doc' },
            'jpg': { icon: 'fa-file-image', class: 'img' },
            'jpeg': { icon: 'fa-file-image', class: 'img' },
            'png': { icon: 'fa-file-image', class: 'img' },
            'gif': { icon: 'fa-file-image', class: 'img' },
            'zip': { icon: 'fa-file-archive', class: 'zip' },
            'rar': { icon: 'fa-file-archive', class: 'zip' },
        };
        
        return icons[ext] || { icon: 'fa-file', class: '' };
    }
    
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
    
    setupRepeaters() {
        const repeaters = this.form.querySelectorAll('.repeater-container');
        
        repeaters.forEach(repeater => {
            const addBtn = repeater.querySelector('.btn-add-group');
            const template = repeater.querySelector('.repeater-group');
            const key = repeater.dataset.repeaterKey;
            
            this.repeaterCounts[key] = 1;
            
            if (addBtn && template) {
                addBtn.addEventListener('click', () => {
                    const newGroup = template.cloneNode(true);
                    const index = this.repeaterCounts[key]++;
                    
                    newGroup.querySelectorAll('input, select, textarea').forEach(field => {
                        const name = field.getAttribute('name');
                        const id = field.getAttribute('id');
                        if (name) {
                            const pattern = new RegExp(`${key}\\[\\d+\\]`);
                            const newName = name.replace(pattern, `${key}[${index}]`);
                            field.setAttribute('name', newName);
                            field.value = '';
                        }
                        if (id) {
                            const pattern = new RegExp(`${key}_\\d+_`);
                            const newId = id.replace(pattern, `${key}_${index}_`);
                            field.setAttribute('id', newId);
                        }
                        field.classList.remove('is-valid', 'is-invalid');
                    });
                    
                    const title = newGroup.querySelector('.repeater-group-title');
                    if (title) {
                        title.textContent = `المجموعة ${index + 1}`;
                    }
                    
                    addBtn.parentElement.insertBefore(newGroup, addBtn);
                    
                    const removeBtn = newGroup.querySelector('.btn-remove-group');
                    if (removeBtn) {
                        removeBtn.addEventListener('click', () => {
                            newGroup.remove();
                            this.updateProgress();
                        });
                    }
                    
                    this.setupFileUploads();
                });
                
                const initialRemoveBtn = template.querySelector('.btn-remove-group');
                if (initialRemoveBtn) {
                    initialRemoveBtn.addEventListener('click', () => {
                        if (repeater.querySelectorAll('.repeater-group').length > 1) {
                            template.remove();
                            this.updateProgress();
                        } else {
                            this.showError('يجب أن يكون هناك مجموعة واحدة على الأقل');
                        }
                    });
                }
            }
        });
    }
    
    setupProgressBar() {
        const progressBar = document.getElementById('formProgress');
        if (!progressBar) return;
        
        const fields = this.form.querySelectorAll('input:not([type="button"]):not([type="submit"]), select, textarea');
        this.totalFields = fields.length;
        
        this.updateProgress();
    }
    
    updateProgress() {
        const progressBar = document.getElementById('formProgress');
        const progressText = document.getElementById('progressText');
        if (!progressBar) return;
        
        const fields = this.form.querySelectorAll('input:not([type="button"]):not([type="submit"]), select, textarea');
        let filled = 0;
        
        fields.forEach(field => {
            if (field.value.trim() !== '' || (field.type === 'checkbox' && field.checked)) {
                filled++;
            }
        });
        
        this.filledFields = filled;
        const percentage = this.totalFields > 0 ? Math.round((filled / this.totalFields) * 100) : 0;
        
        progressBar.style.width = percentage + '%';
        progressBar.setAttribute('aria-valuenow', percentage);
        
        if (progressText) {
            progressText.textContent = `${percentage}% مكتمل (${filled} من ${this.totalFields})`;
        }
    }
    
    setupDraftSaving() {
        const saveInterval = 30000;
        
        setInterval(() => {
            this.saveDraft();
        }, saveInterval);
        
        window.addEventListener('beforeunload', () => {
            this.saveDraft();
        });
    }
    
    saveDraft() {
        const formData = new FormData(this.form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            if (key.includes('[')) {
                if (!data[key]) {
                    data[key] = [];
                }
                data[key].push(value);
            } else {
                data[key] = value;
            }
        }
        
        try {
            localStorage.setItem(this.draftKey, JSON.stringify(data));
            this.showDraftSaved();
        } catch (e) {
            console.error('Failed to save draft:', e);
        }
    }
    
    loadDraft() {
        try {
            const saved = localStorage.getItem(this.draftKey);
            if (!saved) return;
            
            const data = JSON.parse(saved);
            
            for (let [key, value] of Object.entries(data)) {
                const field = this.form.querySelector(`[name="${key}"]`);
                if (field && field.type !== 'file') {
                    if (Array.isArray(value)) {
                        value.forEach((val, idx) => {
                            const arrayField = this.form.querySelector(`[name="${key}"]`);
                            if (arrayField) {
                                arrayField.value = val;
                            }
                        });
                    } else {
                        field.value = value;
                    }
                }
            }
            
            this.updateProgress();
            
            const confirmation = confirm('تم العثور على نسخة محفوظة من الاستمارة. هل تريد استعادتها؟');
            if (!confirmation) {
                this.clearDraft();
            }
        } catch (e) {
            console.error('Failed to load draft:', e);
        }
    }
    
    clearDraft() {
        try {
            localStorage.removeItem(this.draftKey);
        } catch (e) {
            console.error('Failed to clear draft:', e);
        }
    }
    
    showDraftSaved() {
        const indicator = document.getElementById('draftIndicator');
        if (!indicator) return;
        
        indicator.classList.add('show');
        setTimeout(() => {
            indicator.classList.remove('show');
        }, 2000);
    }
    
    setupPreview() {
        const previewBtn = document.getElementById('previewBtn');
        if (!previewBtn) return;
        
        previewBtn.addEventListener('click', (e) => {
            e.preventDefault();
            this.showPreview();
        });
    }
    
    showPreview() {
        const formData = new FormData(this.form);
        const previewContent = document.getElementById('previewContent');
        if (!previewContent) return;
        
        let html = '';
        
        for (let [key, value] of formData.entries()) {
            if (value && typeof value === 'string') {
                const field = this.form.querySelector(`[name="${key}"]`);
                const label = field ? (field.previousElementSibling?.textContent || key) : key;
                
                html += `
                    <div class="preview-section">
                        <div class="preview-label">${this.escapeHtml(label)}</div>
                        <div class="preview-value">${this.escapeHtml(value)}</div>
                    </div>
                `;
            }
        }
        
        previewContent.innerHTML = html || '<p class="text-muted">لا توجد بيانات للمعاينة</p>';
        
        const modal = new bootstrap.Modal(document.getElementById('previewModal'));
        modal.show();
    }
    
    showLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = 'flex';
        }
    }
    
    hideLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }
    
    showError(message) {
        alert(message);
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('submissionForm');
    if (form) {
        const formSlug = form.dataset.formSlug;
        const handler = new FormHandler(form, formSlug);
        
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            
            if (!handler.validateForm()) {
                return;
            }
            
            handler.showLoading();
            
            const formData = new FormData(form);
            
            fetch('submit.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                handler.hideLoading();
                
                if (data.success) {
                    handler.clearDraft();
                    window.location.href = `success.php?ref=${encodeURIComponent(data.reference_code)}`;
                } else {
                    handler.showError(data.message || 'حدث خطأ أثناء الإرسال');
                }
            })
            .catch(error => {
                handler.hideLoading();
                handler.showError('حدث خطأ في الاتصال. يرجى المحاولة مرة أخرى.');
                console.error('Submission error:', error);
            });
        });
    }
});
