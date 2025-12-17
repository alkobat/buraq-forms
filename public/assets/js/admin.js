/**
 * Admin Panel JavaScript
 * الوظائف المساعدة للوحة التحكم الإدارية
 */

// إنشاء namespace للوظائف
window.AdminPanel = {
    
    // CSRF Token
    csrfToken: null,
    
    // API Base URL
    apiBase: './api/',
    
    // Initialize
    init() {
        this.loadCSRFToken();
        this.setupEventListeners();
        this.initializeComponents();
    },
    
    // تحميل CSRF Token
    loadCSRFToken() {
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            this.csrfToken = csrfMeta.getAttribute('content');
        } else {
            this.csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
        }
    },
    
    // إعداد مستمعات الأحداث
    setupEventListeners() {
        // التحقق من النماذج
        document.addEventListener('submit', (e) => {
            if (e.target.matches('form[data-ajax]')) {
                e.preventDefault();
                this.handleAjaxForm(e.target);
            }
        });
        
        // التحقق من أزرار AJAX
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-ajax]') || e.target.closest('[data-ajax]')) {
                e.preventDefault();
                const element = e.target.matches('[data-ajax]') ? e.target : e.target.closest('[data-ajax]');
                this.handleAjaxRequest(element);
            }
        });
        
        // النقر خارج المودال لإغلاقه
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                const modal = bootstrap.Modal.getInstance(e.target);
                if (modal) modal.hide();
            }
        });
    },
    
    // تهيئة المكونات
    initializeComponents() {
        // تفعيل Tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // تفعيل Popovers
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    },
    
    // معالجة النماذج عبر AJAX
    async handleAjaxForm(form) {
        const formData = new FormData(form);
        const submitBtn = form.querySelector('[type="submit"]');
        const originalText = submitBtn?.innerHTML;
        
        try {
            // إظهار حالة التحميل
            if (submitBtn) {
                submitBtn.innerHTML = '<span class="loading"></span> جاري الحفظ...';
                submitBtn.disabled = true;
            }
            
            // إرسال الطلب
            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showToast('نجحت العملية', result.message || 'تمت العملية بنجاح', 'success');
                
                // إعادة تحميل الصفحة أو تحديث البيانات
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
                
            } else {
                this.showToast('خطأ في العملية', result.error || 'حدث خطأ غير متوقع', 'error');
            }
            
        } catch (error) {
            console.error('خطأ في الطلب:', error);
            this.showToast('خطأ في الشبكة', 'فشل في الاتصال بالخادم', 'error');
        } finally {
            // استعادة حالة الزر
            if (submitBtn && originalText) {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        }
    },
    
    // معالجة الطلبات عبر AJAX
    async handleAjaxRequest(element) {
        const url = element.getAttribute('href') || element.getAttribute('data-url');
        const method = element.getAttribute('data-method') || 'GET';
        const confirmMessage = element.getAttribute('data-confirm');
        
        // عرض رسالة تأكيد
        if (confirmMessage && !confirm(confirmMessage)) {
            return;
        }
        
        const originalText = element.innerHTML;
        
        try {
            // إظهار حالة التحميل
            element.innerHTML = '<span class="loading"></span>';
            element.style.pointerEvents = 'none';
            
            // إرسال الطلب
            const response = await fetch(url, {
                method: method,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken
                }
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showToast('نجحت العملية', result.message || 'تمت العملية بنجاح', 'success');
                
                // تنفيذ callback إذا كان موجود
                const callback = element.getAttribute('data-callback');
                if (callback && typeof window[callback] === 'function') {
                    window[callback](result.data);
                }
                
                // إعادة تحميل الصفحة
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
                
            } else {
                this.showToast('خطأ في العملية', result.error || 'حدث خطأ غير متوقع', 'error');
            }
            
        } catch (error) {
            console.error('خطأ في الطلب:', error);
            this.showToast('خطأ في الشبكة', 'فشل في الاتصال بالخادم', 'error');
        } finally {
            // استعادة حالة العنصر
            element.innerHTML = originalText;
            element.style.pointerEvents = 'auto';
        }
    },
    
    // عرض Toast Notification
    showToast(title, message, type = 'info') {
        // إنشاء العنصر
        const toastHtml = `
            <div class="toast align-items-center text-white bg-${type === 'error' ? 'danger' : type} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <strong>${title}</strong><br>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        
        // إنشاء الحاوية إذا لم تكن موجودة
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container position-fixed top-0 start-0 p-3';
            container.style.zIndex = '11';
            document.body.appendChild(container);
        }
        
        // إضافة Toast
        container.insertAdjacentHTML('beforeend', toastHtml);
        const toastElement = container.lastElementChild;
        const toast = new bootstrap.Toast(toastElement, {
            autohide: true,
            delay: 5000
        });
        
        toast.show();
        
        // حذف العنصر بعد الاختفاء
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    },
    
    // تأكيد الحذف
    confirmDelete(title, message, callback) {
        const confirmed = confirm(`${title}\n\n${message}`);
        if (confirmed && typeof callback === 'function') {
            callback();
        }
    },
    
    // تبديل الحالة (تفعيل/تعطيل)
    async toggleStatus(url, currentStatus, callback) {
        const newStatus = currentStatus ? 'false' : 'true';
        const action = currentStatus ? 'تعطيل' : 'تفعيل';
        
        this.confirmDelete(
            'تأكيد التغيير',
            `هل تريد ${action} هذا العنصر؟`,
            async () => {
                try {
                    const response = await fetch(`${url}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': this.csrfToken
                        },
                        body: JSON.stringify({ is_active: newStatus === 'true' })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        this.showToast('نجحت العملية', `تم ${action} العنصر بنجاح`, 'success');
                        if (typeof callback === 'function') {
                            callback();
                        } else {
                            window.location.reload();
                        }
                    } else {
                        this.showToast('خطأ في العملية', result.error, 'error');
                    }
                    
                } catch (error) {
                    console.error('خطأ في الطلب:', error);
                    this.showToast('خطأ في الشبكة', 'فشل في الاتصال بالخادم', 'error');
                }
            }
        );
    },
    
    // تحميل البيانات ديناميكياً
    async loadData(url, targetElement, callback) {
        try {
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                if (targetElement) {
                    targetElement.innerHTML = '';
                    callback(data.data, targetElement);
                } else if (typeof callback === 'function') {
                    callback(data.data);
                }
            } else {
                this.showToast('خطأ في تحميل البيانات', data.error, 'error');
            }
            
        } catch (error) {
            console.error('خطأ في تحميل البيانات:', error);
            this.showToast('خطأ في الشبكة', 'فشل في تحميل البيانات', 'error');
        }
    },
    
    // البحث في الجدول
    setupTableSearch(searchInput, tableBody) {
        searchInput.addEventListener('keyup', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            const rows = tableBody.querySelectorAll('tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const matches = text.includes(searchTerm);
                row.style.display = matches ? '' : 'none';
            });
        });
    },
    
    // ترتيب الجدول
    setupTableSorting(table) {
        const headers = table.querySelectorAll('th[data-sort]');
        
        headers.forEach(header => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                const column = header.getAttribute('data-sort');
                const currentOrder = header.getAttribute('data-order') || 'asc';
                const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
                
                // تحديث جميع الرؤوس
                headers.forEach(h => {
                    h.removeAttribute('data-order');
                    h.innerHTML = h.innerHTML.replace(/▲|▼/g, '');
                });
                
                // تحديث الترتيب
                header.setAttribute('data-order', newOrder);
                header.innerHTML += newOrder === 'asc' ? ' ▲' : ' ▼';
                
                // ترتيب البيانات
                this.sortTable(table, column, newOrder);
            });
        });
    },
    
    // ترتيب البيانات في الجدول
    sortTable(table, column, order) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        rows.sort((a, b) => {
            const aVal = a.cells[column].textContent.trim();
            const bVal = b.cells[column].textContent.trim();
            
            // محاولة ترتيب رقمي
            const aNum = parseFloat(aVal);
            const bNum = parseFloat(bVal);
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return order === 'asc' ? aNum - bNum : bNum - aNum;
            }
            
            // ترتيب نصي
            return order === 'asc' 
                ? aVal.localeCompare(bVal, 'ar')
                : bVal.localeCompare(aVal, 'ar');
        });
        
        // إعادة ترتيب الصفوف
        rows.forEach(row => tbody.appendChild(row));
    },
    
    // تصدير البيانات
    exportTable(table, filename = 'data', format = 'csv') {
        const rows = table.querySelectorAll('tr');
        const data = [];
        
        rows.forEach(row => {
            const cells = row.querySelectorAll('td, th');
            const rowData = [];
            
            cells.forEach(cell => {
                rowData.push(cell.textContent.trim());
            });
            
            data.push(rowData);
        });
        
        if (format === 'csv') {
            this.downloadCSV(data, filename);
        } else if (format === 'json') {
            this.downloadJSON(data, filename);
        }
    },
    
    // تحميل CSV
    downloadCSV(data, filename) {
        const csv = data.map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');
        this.downloadFile(csv, `${filename}.csv`, 'text/csv');
    },
    
    // تحميل JSON
    downloadJSON(data, filename) {
        const json = JSON.stringify(data, null, 2);
        this.downloadFile(json, `${filename}.json`, 'application/json');
    },
    
    // تحميل الملف
    downloadFile(content, filename, type) {
        const blob = new Blob([content], { type });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        
        link.href = url;
        link.download = filename;
        link.click();
        
        window.URL.revokeObjectURL(url);
    },
    
    // عرض نافذة تأكيد مخصصة
    showConfirmModal(title, message, confirmText = 'تأكيد', cancelText = 'إلغاء', callback) {
        const modalHtml = `
            <div class="modal fade" id="confirmModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${title}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>${message}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${cancelText}</button>
                            <button type="button" class="btn btn-primary" id="confirmAction">${confirmText}</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // إضافة المودال للصفحة
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
        
        // إضافة مستمع للتأكيد
        document.getElementById('confirmAction').addEventListener('click', () => {
            modal.hide();
            if (typeof callback === 'function') {
                callback();
            }
        });
        
        // إزالة المودال عند الإغلاق
        document.getElementById('confirmModal').addEventListener('hidden.bs.modal', () => {
            document.getElementById('confirmModal').remove();
        });
        
        modal.show();
    }
};

// تهيئة تلقائية عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', () => {
    AdminPanel.init();
});

// تصدير للاستخدام العام
window.AdminPanel = AdminPanel;