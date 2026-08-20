(function (window, document, $) {
    'use strict';

    if (!$) return;

    const App = {
        loadingTimer: null,
        finishTimer: null,
        chartTooltip: null,
        employeePickerTrigger: null,
        toastCounter: 0,
        toastRecent: new Map(),
        confirmCallback: null,
        payrollSettingsDirty: false,
        payrollSettingsSubmitting: false,
        attendanceCalendarData: null,
        attendanceTooltipTimer: null,
        toast: {
            success(message, options) { return App.showToast('success', message, options); },
            error(message, options) { return App.showToast('error', message, options); },
            warning(message, options) { return App.showToast('warning', message, options); },
            info(message, options) { return App.showToast('info', message, options); },
            loading(message, options) { return App.showToast('loading', message, Object.assign({ duration: 0 }, options || {})); },
            dismiss(id) { App.dismissToast(id); }
        },

        init() {
            window.App = this;
            this.bindGlobalEvents();
            this.initPage(document);
            this.updateChrome();
        },

        bindGlobalEvents() {
            const self = this;

            if (!this.salarySubmitCaptureBound) {
                document.addEventListener('submit', function (event) {
                    const form = event.target;
                    if (!form || form.id !== 'salaryManagementForm' || String($(form).find('[data-salary-confirmed]').val()) === '1') return;
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    if (!form.reportValidity()) return;
                    const $input = $(form).find('[data-salary-display]');
                    const amount = Number($(form).find('[data-salary-amount]').val());
                    if (!Number.isFinite(amount) || amount <= 0) {
                        $input.get(0).setCustomValidity('กรุณาระบุเงินเดือนมากกว่า 0');
                        $input.get(0).reportValidity();
                        return;
                    }
                    $input.get(0).setCustomValidity('');
                    self.openSalaryConfirmation();
                }, true);
                this.salarySubmitCaptureBound = true;
            }

            $(document)
                .off('.payrollApp')
                .on('click.payrollApp', '#openMobileSidebar', function () { self.setMobileSidebar(true); })
                .on('click.payrollApp', '#closeMobileSidebar, #mobileSidebarBackdrop', function () { self.setMobileSidebar(false); })
                .on('keydown.payrollApp', function (event) {
                    if (event.key === 'Escape') {
                        if (!$('#appConfirmModal').hasClass('hidden')) {
                            self.closeAppConfirmation(false);
                            return;
                        }
                        if (self.isSalaryConfirmationOpen()) {
                            self.closeSalaryConfirmation();
                            return;
                        }
                        if (self.isPaymentConfirmationOpen()) {
                            self.closePaymentConfirmation();
                            return;
                        }
                        if (self.isAttendanceDayModalOpen()) {
                            self.closeAttendanceDayModal();
                            return;
                        }
                        if (self.isEmployeePickerOpen()) {
                            self.closeEmployeePicker();
                            return;
                        }
                        if (!$('#deleteConfirmModal').hasClass('hidden')) {
                            self.closeDeleteModal();
                            return;
                        }
                        self.setMobileSidebar(false);
                    }
                    if (event.key === 'Tab' && self.isEmployeePickerOpen()) {
                        self.trapEmployeePickerFocus(event);
                    }
                })
                .on('click.payrollApp', '[data-open-employee-picker]', function () { self.openEmployeePicker(this); })
                .on('click.payrollApp', '[data-close-employee-picker]', function () { self.closeEmployeePicker(); })
                .on('click.payrollApp', '[data-select-employee]', function () { self.selectEmployee($(this).closest('[data-employee-picker-item]')); })
                .on('input.payrollApp', '[data-employee-picker-search]', function () { self.filterEmployeePicker(); })
                .on('change.payrollApp', '[data-employee-picker-department], [data-employee-picker-status]', function () { self.filterEmployeePicker(); })
                .on('change.payrollApp', '[data-payment-period-field]', function () {
                    if (self.reloadPayrollAttendance()) return;
                    self.updateEmployeePickerStatuses();
                    self.filterEmployeePicker();
                    self.calculatePayrollPreview();
                })
                .on('input.payrollApp change.payrollApp', '[data-payroll-input]', function () {
                    $('[data-payment-confirmed]').val('0');
                    self.calculatePayrollPreview();
                })
                .on('click.payrollApp', '[data-add-adjustment]', function () { self.addPayrollAdjustment($(this).data('add-adjustment')); })
                .on('click.payrollApp', '[data-remove-adjustment]', function () {
                    $(this).closest('[data-adjustment-row]').remove();
                    $('[data-payment-confirmed]').val('0');
                    self.calculatePayrollPreview();
                })
                .on('click.payrollApp', '[data-close-payment-confirmation]', function () { self.closePaymentConfirmation(); })
                .on('click.payrollApp', '[data-confirm-payment]', function () { self.confirmPayrollPayment(); })
                .on('input.payrollApp', '[data-salary-display]', function () { self.formatSalaryInput(this); self.updateSalaryPreview(); })
                .on('change.payrollApp input.payrollApp', '[data-salary-effective-date], [data-salary-reason], [data-salary-note]', function () { $('[data-salary-confirmed]').val('0'); self.updateSalaryPreview(); })
                .on('click.payrollApp', '[data-close-salary-confirmation]', function () { self.closeSalaryConfirmation(); })
                .on('click.payrollApp', '[data-confirm-salary]', function () { self.confirmSalaryChange(); })
                .on('input.payrollApp change.payrollApp', '[data-setting-input]', function () { self.updatePayrollSettingsPreview(); self.updatePayrollSettingsDirtyState(); })
                .on('input.payrollApp change.payrollApp', '[data-settings-sample]', function () { self.updatePayrollSettingsPreview(); })
                .on('click.payrollApp', '[data-reset-payroll-settings]', function () { self.resetPayrollSettingsChanges(); })
                .on('click.payrollApp', '[data-dismiss-server-toast]', function () { $(this).closest('[data-server-toast]').remove(); })
                .on('click.payrollApp', '[data-open-attendance-day]', function () { self.openAttendanceDayModal(String($(this).data('date') || '')); })
                .on('click.payrollApp', '[data-close-attendance-day]', function () { self.closeAttendanceDayModal(); })
                .on('click.payrollApp', '[data-select-attendance-day]', function () { self.selectAttendanceCalendarDay(String($(this).data('date') || '')); })
                .on('click.payrollApp', '[data-day-status-filter]', function () { self.filterAttendanceDay(String($(this).data('day-status-filter') || 'all')); })
                .on('mouseenter.payrollApp focusin.payrollApp', '[data-attendance-person]', function () { self.showAttendancePersonTooltip(this); })
                .on('mouseleave.payrollApp focusout.payrollApp', '[data-attendance-person]', function () { self.scheduleAttendanceTooltipClose(); })
                .on('click.payrollApp', '[data-attendance-person]', function (event) { event.stopPropagation(); self.showAttendancePersonTooltip(this); })
                .on('mouseenter.payrollApp', '#attendancePersonTooltip', function () { window.clearTimeout(self.attendanceTooltipTimer); })
                .on('mouseleave.payrollApp', '#attendancePersonTooltip', function () { self.scheduleAttendanceTooltipClose(); })
                .on('click.payrollApp', 'a[href]', function (event) {
                    if (!self.payrollSettingsDirty || self.payrollSettingsSubmitting || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || this.target === '_blank') return;
                    const href = String($(this).attr('href') || '');
                    if (!href || href.charAt(0) === '#') return;
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    const destination = this.href;
                    self.confirm('ต้องการออกจากหน้านี้หรือไม่?', function () {
                        self.payrollSettingsDirty = false;
                        window.location.href = destination;
                    }, { title:'มีการตั้งค่าที่ยังไม่ได้บันทึก', confirmLabel:'ออกโดยไม่บันทึก' });
                })
                .on('click.payrollApp', '.js-delete-link', function (event) {
                    event.preventDefault();
                    $('#confirmDeleteLink').attr('href', this.href);
                    $('#deleteModalRecord').text($(this).data('record') || 'ข้อมูลที่เลือก');
                    $('#deleteConfirmModal').removeClass('hidden').addClass('flex');
                    self.syncBodyScrollLock();
                    $('#deleteConfirmModal [data-close-delete-modal]').last().trigger('focus');
                })
                .on('click.payrollApp', '[data-close-delete-modal]', function () { self.closeDeleteModal(); })
                .on('click.payrollApp', '[data-app-confirm-cancel]', function () { self.closeAppConfirmation(false); })
                .on('click.payrollApp', '[data-app-confirm-accept]', function () { self.acceptAppConfirmation(); })
                .on('submit.payrollApp', '#payrollPaymentForm', function (event) {
                    if (String($(this).find('[data-payment-confirmed]').val()) === '1') return;
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    if (!$('#selected_employee_id').val()) {
                        self.openEmployeePicker($('[data-open-employee-picker]').first().get(0));
                        return;
                    }
                    self.openPaymentConfirmation();
                })
                .on('submit.payrollApp', 'form', function (event) {
                    const $form = $(this);
                    if (this.id === 'payrollSettingsForm') self.payrollSettingsSubmitting = true;
                    if (this.id === 'payrollPaymentForm' && !$('#selected_employee_id').val()) {
                        event.preventDefault();
                        self.openEmployeePicker($('[data-open-employee-picker]').first().get(0));
                        return;
                    }
                    if ($form.data('submitting')) {
                        event.preventDefault();
                        return;
                    }
                    $form.data('submitting', true);
                    const $button = $form.find('button[type="submit"]').first();
                    if (!$button.length) return;
                    if (String(this.method || '').toLowerCase() === 'post' && !$form.data('loading-toast-id')) {
                        $form.data('loading-toast-id', self.toast.loading($button.data('loading-text') || 'กำลังบันทึก...'));
                    }
                    $button.attr('aria-disabled', 'true').addClass('opacity-70 cursor-wait pointer-events-none');
                    $button.data('original-html', $button.html()).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i>' + ($button.data('loading-text') || 'กำลังดำเนินการ...'));
                })
                .on('change.payrollApp', '.dashboard-period-select', function () {
                    if (this.form) this.form.requestSubmit();
                })
                .on('input.payrollApp', '.js-mobile-search', function () { self.filterMobileList($(this)); })
                .on('change.payrollApp', '.js-mobile-filter', function () {
                    $(this).closest('.js-mobile-list-section').find('.js-mobile-search').trigger('input');
                })
                .on('change.payrollApp', '.js-mobile-sort', function () { self.sortMobileList($(this)); })
                .on('click.payrollApp', function (event) {
                    $('.dt-menu').prop('hidden', true);
                    $('.dt-dropdown > .dt-tool-button').attr('aria-expanded', 'false');
                    if (!$(event.target).closest('[data-attendance-person], #attendancePersonTooltip').length) self.hideAttendancePersonTooltip();
                });

            if (!this.payrollSettingsBeforeUnloadBound) {
                window.addEventListener('beforeunload', function (event) {
                    if (!self.payrollSettingsDirty || self.payrollSettingsSubmitting) return;
                    event.preventDefault();
                    event.returnValue = '';
                });
                this.payrollSettingsBeforeUnloadBound = true;
            }

            document.body.addEventListener('htmx:beforeRequest', function (event) {
                self.removeChartTooltip();
                self.setMobileSidebar(false);
                self.closeEmployeePicker(false);
                self.closePaymentConfirmation(false);
                self.closeSalaryConfirmation(false);
                self.closeAttendanceDayModal(false);
                self.hideAttendancePersonTooltip();
                self.hideRouteError();
                self.startLoading();
                const form = event && event.detail && event.detail.elt && event.detail.elt.closest ? event.detail.elt.closest('form') : null;
                if (form && String(form.method || '').toLowerCase() === 'post' && !$(form).data('loading-toast-id')) {
                    const message = $(form).find('button[type="submit"]').first().data('loading-text') || 'กำลังบันทึก...';
                    $(form).data('loading-toast-id', self.toast.loading(message));
                }
            });
            document.body.addEventListener('htmx:afterRequest', function (event) {
                const form = event.detail && event.detail.elt && event.detail.elt.closest ? event.detail.elt.closest('form') : null;
                const toastId = form ? $(form).data('loading-toast-id') : null;
                if (toastId) self.toast.dismiss(toastId);
                if (form) $(form).removeData('loading-toast-id');
                if (form && form.id === 'payrollSettingsForm') self.payrollSettingsSubmitting = false;
            });
            document.body.addEventListener('showToast', function (event) {
                const detail = event.detail || {};
                self.showToast(detail.type || 'info', detail.message || '', { duration: detail.duration });
            });
            document.body.addEventListener('htmx:beforeHistorySave', function () {
                self.cleanupPage(document.querySelector('#app-content'));
            });
            document.body.addEventListener('htmx:beforeSwap', function (event) {
                const status = event.detail.xhr ? event.detail.xhr.status : 0;
                if (event.detail.target && event.detail.target.id === 'app-content') self.cleanupPage(event.detail.target);
                if (event.detail.target && event.detail.target.id === 'attendance-workspace') self.cleanupPage(event.detail.target);
                if (event.detail.target && event.detail.target.id === 'payroll-settings-workspace') self.cleanupPage(event.detail.target);
                if (event.detail.target && event.detail.target.id === 'attendance-calendar-workspace') self.cleanupPage(event.detail.target);
                if (status === 404) {
                    event.detail.shouldSwap = true;
                    event.detail.isError = false;
                }
            });
            document.body.addEventListener('htmx:afterSwap', function (event) {
                $('#hotToastViewport [data-toast-type="loading"]').each(function () { self.toast.dismiss(this.id); });
                const payrollSettingsWorkspace = document.querySelector('#payroll-settings-workspace');
                if (payrollSettingsWorkspace) self.initPage(payrollSettingsWorkspace);
                const attendanceCalendarWorkspace = document.querySelector('#attendance-calendar-workspace');
                if (attendanceCalendarWorkspace) self.initAttendanceCalendar(attendanceCalendarWorkspace);
                $('#hotToastViewport [data-server-toast]').each(function () {
                    const toast = this;
                    window.setTimeout(function () { $(toast).fadeOut(150, function () { $(this).remove(); }); }, 3200);
                });
                if (event.detail.target && event.detail.target.id === 'app-content') {
                    self.initPage(document.querySelector('#app-content'));
                    self.updateChrome();
                } else if (event.detail.target && event.detail.target.id === 'payroll-settings-workspace') {
                    self.initPage(document.querySelector('#payroll-settings-workspace'));
                } else if (event.detail.target && event.detail.target.id === 'attendance-calendar-workspace') {
                    self.initPage(document.querySelector('#attendance-calendar-workspace'));
                } else if (event.detail.target) {
                    self.initPage(event.detail.target);
                }
            });
            document.body.addEventListener('htmx:afterSettle', function (event) {
                self.stopLoading();
                if (event.detail.target && event.detail.target.id === 'app-content') {
                    const currentContent = document.querySelector('#app-content');
                    self.announcePage();
                    const heading = currentContent ? currentContent.querySelector('h1') : null;
                    (heading || currentContent).focus({ preventScroll: true });
                    window.scrollTo({ top: 0, behavior: 'instant' });
                }
            });
            document.body.addEventListener('htmx:historyRestore', function () {
                const content = document.querySelector('#app-content');
                self.initPage(content || document);
                self.updateChrome();
                self.stopLoading();
            });
            document.body.addEventListener('htmx:responseError', function (event) {
                self.resetForm(event.detail.elt && event.detail.elt.closest ? event.detail.elt.closest('form') : null);
                self.stopLoading();
                const status = event.detail.xhr ? event.detail.xhr.status : 0;
                self.showRouteError(self.httpErrorMessage(status));
            });
            document.body.addEventListener('htmx:sendError', function (event) {
                self.resetForm(event.detail.elt && event.detail.elt.closest ? event.detail.elt.closest('form') : null);
                self.stopLoading();
                self.showRouteError('ตรวจสอบการเชื่อมต่อ แล้วลองใหม่อีกครั้ง');
            });
            document.body.addEventListener('htmx:timeout', function () {
                self.stopLoading();
                self.showRouteError('ใช้เวลาโหลดนานเกินไป กรุณาลองใหม่');
            });
        },

        initPage(root) {
            if (!root) return;
            this.consumeFlashToasts(root);
            this.initDataTables(root);
            this.initCharts(root);
            this.initEmployeePicker(root);
            this.initPayrollPayment(root);
            this.initSalaryManagement(root);
            this.initPayrollSettings(root);
            this.initAttendance(root);
            this.initAttendanceCalendar(root);
            if (window.htmx) window.htmx.process(root);
        },

        cleanupPage(root) {
            if (!root) return;
            this.destroyDataTables(root);
            this.destroyCharts(root);
            if ($(root).find('[data-payroll-settings]').addBack('[data-payroll-settings]').length) {
                this.payrollSettingsDirty = false;
                this.payrollSettingsSubmitting = false;
            }
            if ($(root).find('[data-attendance-calendar]').addBack('[data-attendance-calendar]').length) {
                this.closeAttendanceDayModal(false);
                this.hideAttendancePersonTooltip();
                this.attendanceCalendarData = null;
            }
        },

        consumeFlashToasts(root) {
            $(root).find('[data-flash-toast]').addBack('[data-flash-toast]').each((_, element) => {
                const $element = $(element);
                this.showToast(String($element.attr('data-type') || 'info'), String($element.attr('data-message') || ''), { duration: Number($element.attr('data-duration')) || undefined });
                $element.remove();
            });
        },

        showToast(type, message, options) {
            message = String(message || '').trim();
            if (!message) return null;
            options = options || {};
            const normalizedType = ['success','error','warning','info','loading'].indexOf(type) !== -1 ? type : 'info';
            const duplicateKey = normalizedType + '|' + message;
            const now = Date.now();
            if (!options.id && this.toastRecent.has(duplicateKey) && now - this.toastRecent.get(duplicateKey) < 900) return null;
            this.toastRecent.set(duplicateKey, now);
            const id = options.id || ('toast-' + (++this.toastCounter));
            const palette = {
                success: ['fa-circle-check','text-emerald-600','border-emerald-200'],
                error: ['fa-circle-exclamation','text-red-600','border-red-200'],
                warning: ['fa-triangle-exclamation','text-amber-600','border-amber-200'],
                info: ['fa-circle-info','text-blue-600','border-blue-200'],
                loading: ['fa-spinner fa-spin','text-[#0075de]','border-blue-200']
            }[normalizedType];
            let $toast = $('#' + id);
            const markup = '<div class="flex items-start gap-3"><i class="fa-solid ' + palette[0] + ' mt-0.5 shrink-0 ' + palette[1] + '" aria-hidden="true"></i><p class="min-w-0 flex-1 text-sm leading-5 text-[#202223]"></p><button type="button" class="pointer-events-auto -mr-1 -mt-1 h-8 w-8 shrink-0 rounded-md text-[#77716c] hover:bg-[#f6f5f4]" aria-label="ปิด"><i class="fa-solid fa-xmark"></i></button></div>';
            if (!$toast.length) {
                $toast = $('<div>', { id: id, role: normalizedType === 'error' ? 'alert' : 'status', 'data-toast-type':normalizedType, class: 'pointer-events-auto translate-y-[-4px] rounded-lg border bg-white px-3.5 py-3 opacity-0 shadow-lg transition duration-150 ' + palette[2] }).html(markup).appendTo('#hotToastViewport');
                window.requestAnimationFrame(function () { $toast.removeClass('translate-y-[-4px] opacity-0'); });
            } else {
                $toast.attr({'class':'pointer-events-auto rounded-lg border bg-white px-3.5 py-3 shadow-lg transition duration-150 ' + palette[2], 'data-toast-type':normalizedType}).html(markup);
            }
            $toast.find('p').text(message);
            $toast.find('button').on('click', () => this.dismissToast(id));
            if ($toast.data('timer')) window.clearTimeout($toast.data('timer'));
            const defaults = { success:3000, error:5500, warning:4500, info:3500, loading:0 };
            const duration = Number.isFinite(Number(options.duration)) ? Number(options.duration) : defaults[normalizedType];
            if (duration > 0) $toast.data('timer', window.setTimeout(() => this.dismissToast(id), duration));
            while ($('#hotToastViewport').children().length > 4) this.dismissToast($('#hotToastViewport').children().first().attr('id'));
            return id;
        },

        dismissToast(id) {
            const $toast = $('#' + id);
            if (!$toast.length) return;
            if ($toast.data('timer')) window.clearTimeout($toast.data('timer'));
            $toast.addClass('translate-y-[-4px] opacity-0');
            window.setTimeout(function () { $toast.remove(); }, 160);
        },

        confirm(message, onConfirm, options) {
            options = options || {};
            this.confirmCallback = typeof onConfirm === 'function' ? onConfirm : null;
            const $modal = $('#appConfirmModal');
            $modal.find('#appConfirmTitle').text(options.title || 'ยืนยันการดำเนินการ?');
            $modal.find('[data-app-confirm-message]').text(String(message || ''));
            $modal.find('[data-app-confirm-accept]').text(options.confirmLabel || 'ยืนยัน');
            $modal.removeClass('hidden').addClass('flex').attr('aria-hidden', 'false');
            this.syncBodyScrollLock();
            $modal.find('[data-app-confirm-accept]').trigger('focus');
        },

        closeAppConfirmation(runCallback) {
            const callback = this.confirmCallback;
            this.confirmCallback = null;
            $('#appConfirmModal').addClass('hidden').removeClass('flex').attr('aria-hidden', 'true');
            this.syncBodyScrollLock();
            if (runCallback && callback) callback();
        },

        acceptAppConfirmation() {
            this.closeAppConfirmation(true);
        },

        setMobileSidebar(open) {
            const $sidebar = $('#mobileSidebar');
            if (!$sidebar.length) return;
            $sidebar.toggleClass('-translate-x-full', !open).attr('aria-hidden', open ? 'false' : 'true');
            $('#mobileSidebarBackdrop').toggleClass('opacity-0 pointer-events-none', !open).attr('aria-hidden', open ? 'false' : 'true');
            this.syncBodyScrollLock();
            if (open) $('#closeMobileSidebar').trigger('focus');
        },

        closeDeleteModal() {
            $('#deleteConfirmModal').addClass('hidden').removeClass('flex');
            this.syncBodyScrollLock();
        },

        syncBodyScrollLock() {
            const sidebarOpen = $('#mobileSidebar').attr('aria-hidden') === 'false';
            const deleteOpen = !$('#deleteConfirmModal').hasClass('hidden');
            const confirmOpen = !$('#appConfirmModal').hasClass('hidden');
            $('body').toggleClass('overflow-hidden', sidebarOpen || deleteOpen || confirmOpen || this.isEmployeePickerOpen() || this.isPaymentConfirmationOpen() || this.isSalaryConfirmationOpen() || this.isAttendanceDayModalOpen());
        },

        isEmployeePickerOpen() {
            const $modal = $('#employeePickerModal');
            return $modal.length > 0 && !$modal.hasClass('hidden');
        },

        openEmployeePicker(trigger) {
            const $modal = $('#employeePickerModal');
            if (!$modal.length || !$('#deleteConfirmModal').hasClass('hidden')) return;
            this.employeePickerTrigger = trigger || document.activeElement;
            $modal.find('[data-employee-picker-search], [data-employee-picker-department], [data-employee-picker-status]').val('');
            $modal.removeClass('hidden').addClass('flex').attr('aria-hidden', 'false');
            this.updateEmployeePickerStatuses();
            this.filterEmployeePicker();
            this.syncBodyScrollLock();
            window.setTimeout(function () { $('#employeePickerSearch').trigger('focus'); }, 30);
        },

        closeEmployeePicker(returnFocus) {
            const $modal = $('#employeePickerModal');
            if (!$modal.length) {
                this.employeePickerTrigger = null;
                return;
            }
            const shouldReturnFocus = returnFocus !== false;
            const trigger = this.employeePickerTrigger;
            $modal.addClass('hidden').removeClass('flex').attr('aria-hidden', 'true');
            this.employeePickerTrigger = null;
            this.syncBodyScrollLock();
            if (shouldReturnFocus) {
                const fallbackTrigger = $('[data-open-employee-picker]').filter(':visible').first().get(0);
                if (trigger && document.contains(trigger) && $(trigger).is(':visible')) trigger.focus();
                else if (fallbackTrigger) fallbackTrigger.focus();
            }
        },

        trapEmployeePickerFocus(event) {
            const focusable = $('#employeePickerModal')
                .find('a[href], button:not(:disabled), input:not(:disabled), select:not(:disabled), [tabindex]:not([tabindex="-1"])')
                .filter(':visible')
                .get();
            if (!focusable.length) return;
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },

        initEmployeePicker(root) {
            if (!$(root).find('#employeePickerModal').addBack('#employeePickerModal').length) return;
            this.employeePickerTrigger = null;
            this.updateEmployeePickerStatuses();
            this.filterEmployeePicker();
        },

        paymentPeriodKey() {
            const year = String($('#paymentYear').val() || '');
            const month = String($('#paymentMonth').val() || '').toLowerCase();
            return year && month ? year + '|' + month : '';
        },

        employeeIsPaid($item) {
            const period = this.paymentPeriodKey();
            const periods = String($item.attr('data-employee-paid-periods') || '').split(',').filter(Boolean);
            return Boolean(period && periods.indexOf(period) !== -1);
        },

        updateEmployeePickerStatuses() {
            const self = this;
            const $modal = $('#employeePickerModal');
            const context = String($modal.attr('data-employee-picker-context') || 'payment');
            if (context !== 'payment') {
                const historySelectedId = String($modal.attr('data-employee-picker-selected-id') || '');
                $modal.find('[data-employee-picker-item]').each(function () {
                    const $item = $(this);
                    const selected = String($item.attr('data-employee-id')) === historySelectedId;
                    $item.toggleClass('bg-[#eef6fd]', selected).toggleClass('bg-white', !selected);
                });
                return;
            }
            const selectedId = String($('#selected_employee_id').val() || '');
            $('[data-employee-picker-item]').each(function () {
                const $item = $(this);
                const selected = String($item.attr('data-employee-id')) === selectedId;
                const paid = self.employeeIsPaid($item);
                const $button = $item.find('[data-select-employee]');
                const label = selected ? '✓ เลือกอยู่' : (paid ? 'จ่ายแล้ว ✓' : 'เลือก');

                $item.toggleClass('bg-[#eef6fd]', selected).toggleClass('bg-white', !selected);
                $item.find('[data-employee-paid-badge]').toggleClass('hidden', !paid).toggleClass('inline-flex', paid);
                $button
                    .prop('disabled', selected || paid)
                    .removeClass('border-[#b7d6f1] bg-[#eef6fd] text-[#0075de] border-[#e6e6e6] bg-[#f6f5f4] text-[#77716c] border-[#0075de] bg-white hover:bg-[#eef6fd]')
                    .addClass(selected ? 'border-[#b7d6f1] bg-[#eef6fd] text-[#0075de]' : (paid ? 'border-[#e6e6e6] bg-[#f6f5f4] text-[#77716c]' : 'border-[#0075de] bg-white text-[#0075de] hover:bg-[#eef6fd]'));
                $button.find('[data-employee-select-label]').text(label);
            });

            let selectedPaid = false;
            let selectedHasSalary = true;
            if (selectedId) {
                const $selectedItem = $('[data-employee-picker-item]').filter(function () { return String($(this).attr('data-employee-id')) === selectedId; }).first();
                selectedPaid = $selectedItem.length ? this.employeeIsPaid($selectedItem) : false;
                selectedHasSalary = $selectedItem.length ? String($selectedItem.attr('data-employee-salary-status')) === 'configured' : false;
            }
            $('[data-selected-paid-warning]').toggleClass('hidden', !selectedPaid).toggleClass('flex', selectedPaid);
            $('[data-payment-submit]').prop('disabled', !selectedId || selectedPaid || !selectedHasSalary);
            $('[data-payment-submit-label]').text(selectedPaid ? 'จ่ายแล้วสำหรับงวดนี้' : (!selectedHasSalary ? 'ต้องกำหนดเงินเดือนก่อน' : 'ตรวจสอบและยืนยัน'));

            const $list = $('[data-employee-picker-list]');
            const items = $list.children('[data-employee-picker-item]').get();
            items.sort(function (a, b) {
                const paidDifference = Number(self.employeeIsPaid($(a))) - Number(self.employeeIsPaid($(b)));
                if (paidDifference !== 0) return paidDifference;
                return String($(a).attr('data-employee-name')).localeCompare(String($(b).attr('data-employee-name')), 'th');
            });
            $.each(items, function (_, item) { $list.append(item); });
        },

        filterEmployeePicker() {
            const self = this;
            const $items = $('[data-employee-picker-item]');
            if (!$items.length) return;
            const keyword = String($('[data-employee-picker-search]').val() || '').toLocaleLowerCase('th-TH').trim();
            const department = String($('[data-employee-picker-department]').val() || '');
            const status = String($('[data-employee-picker-status]').val() || '');
            const context = String($('#employeePickerModal').attr('data-employee-picker-context') || 'payment');
            let visible = 0;

            $items.each(function () {
                const $item = $(this);
                const paid = self.employeeIsPaid($item);
                const matchesSearch = !keyword || String($item.attr('data-employee-search') || '').includes(keyword);
                const matchesDepartment = !department || String($item.attr('data-employee-department-id')) === department;
                const salaryStatus = String($item.attr('data-employee-salary-status') || 'unconfigured');
                const matchesStatus = !status || (context === 'salary' ? salaryStatus === status : (status === 'paid' ? paid : !paid));
                const matches = matchesSearch && matchesDepartment && matchesStatus;
                $item.toggleClass('hidden', !matches);
                if (matches) visible++;
            });

            $('[data-employee-picker-count], [data-employee-picker-footer-count]').text(visible);
            $('[data-employee-picker-list]').toggleClass('hidden', visible === 0);
            $('[data-employee-picker-empty]').toggleClass('hidden', visible !== 0).toggleClass('flex', visible === 0);
        },

        selectEmployee($item) {
            if (!$item.length || this.employeeIsPaid($item)) return;
            const id = String($item.attr('data-employee-id') || '');
            if (!id) return;

            if ($('[data-payroll-calculator]').attr('data-attendance-powered') === '1') {
                this.navigatePayrollAttendance(id);
                return;
            }

            $('#selected_employee_id').val(id);
            $('[data-selected-employee-initials]').text($item.attr('data-employee-initials') || 'พ');
            $('[data-selected-employee-name], [data-payment-employee-name]').text($item.attr('data-employee-name') || '');
            $('[data-selected-employee-code], [data-payment-employee-code]').text(id);
            $('[data-selected-employee-department]').text($item.attr('data-employee-department') || 'ไม่ระบุแผนก');
            $('[data-selected-employee-position]').text($item.attr('data-employee-position') || 'ไม่ระบุตำแหน่ง');
            $('[data-payment-employee-salary]').text(new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB', maximumFractionDigits: 0 }).format(Number($item.attr('data-employee-salary') || 0)));
            const $calculator = $('[data-payroll-calculator]');
            $calculator.attr('data-base-salary', $item.attr('data-employee-salary') || '0');
            $calculator.attr('data-loan-balance', $item.attr('data-employee-loan') || '0');
            $calculator.attr('data-fund-balance', $item.attr('data-employee-fund') || '0');

            $('#employeeSelectionEmpty').addClass('hidden').removeClass('flex');
            $('#employeeSelectionSelected').removeClass('hidden').addClass('flex');
            $('#paymentEmptyState').addClass('hidden').removeClass('flex');
            $('#paymentFields').removeClass('hidden').addClass('block');
            this.updateEmployeePickerStatuses();
            this.filterEmployeePicker();
            this.calculatePayrollPreview();
            this.closeEmployeePicker();
        },

        money(value) {
            return new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB', minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value) || 0);
        },

        numericValue(selector) {
            const raw = String($(selector).val() == null ? '' : $(selector).val()).trim();
            if (raw === '') return null;
            const value = Number(raw);
            return Number.isFinite(value) && value >= 0 ? value : 0;
        },

        initPayrollPayment(root) {
            if (!$(root).find('[data-payroll-calculator]').addBack('[data-payroll-calculator]').length) return;
            $('[data-payment-confirmed]').val('0');
            this.calculatePayrollPreview();
        },

        initSalaryManagement(root) {
            const $workspace = $(root).find('[data-salary-management]').addBack('[data-salary-management]').first();
            if (!$workspace.length) return;
            const input = $workspace.find('[data-salary-display]').get(0);
            if (input) this.formatSalaryInput(input);
            $workspace.find('[data-salary-confirmed]').val('0');
            this.updateSalaryPreview();
        },

        formatSalaryInput(input) {
            const $input = $(input);
            let raw = String($input.val() || '').replace(/,/g, '').replace(/[^0-9.]/g, '');
            const firstDot = raw.indexOf('.');
            if (firstDot !== -1) raw = raw.slice(0, firstDot + 1) + raw.slice(firstDot + 1).replace(/\./g, '').slice(0, 2);
            const numeric = raw === '' || raw === '.' ? '' : Number(raw);
            if (numeric === '' || !Number.isFinite(numeric)) {
                $input.val('');
                $('[data-salary-amount]').val('');
            } else {
                const decimals = firstDot === -1 ? 0 : Math.min(2, raw.length - firstDot - 1);
                $input.val(new Intl.NumberFormat('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: 2 }).format(numeric));
                $('[data-salary-amount]').val(numeric.toFixed(2));
            }
            $('[data-salary-confirmed]').val('0');
        },

        salaryDate(value) {
            if (!value) return '-';
            const parts = String(value).split('-').map(Number);
            if (parts.length !== 3 || !parts[0] || !parts[1] || !parts[2]) return '-';
            return new Intl.DateTimeFormat('th-TH', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(parts[0], parts[1] - 1, parts[2]));
        },

        updateSalaryPreview() {
            const $preview = $('[data-salary-preview]');
            if (!$preview.length) return;
            const currentRaw = String($preview.attr('data-current-salary') || '');
            const hasCurrent = currentRaw !== '';
            const current = hasCurrent ? Number(currentRaw) : 0;
            const newRaw = String($('[data-salary-amount]').val() || '');
            const next = newRaw === '' ? null : Number(newRaw);
            const valid = next !== null && Number.isFinite(next);
            const difference = valid ? next - current : 0;
            $('[data-preview-new]').text(valid ? this.money(next) : '-');
            $('[data-preview-date]').text(this.salaryDate($('[data-salary-effective-date]').val()));
            const $difference = $('[data-preview-difference]').removeClass('text-emerald-700 text-red-700');
            if (!valid) {
                $difference.text('-');
                $('[data-preview-percentage]').text('-');
            } else if (!hasCurrent) {
                $difference.text('กำหนดครั้งแรก').addClass('text-emerald-700');
                $('[data-preview-percentage]').text('ยังไม่มีฐานเดิมสำหรับคำนวณเปอร์เซ็นต์');
            } else {
                const sign = difference >= 0 ? '+' : '-';
                $difference.text(sign + this.money(Math.abs(difference))).addClass(difference >= 0 ? 'text-emerald-700' : 'text-red-700');
                $('[data-preview-percentage]').text(current === 0 ? 'ไม่สามารถคำนวณเปอร์เซ็นต์' : ((difference >= 0 ? '+' : '') + ((difference / current) * 100).toFixed(2) + '%'));
            }
            const decreased = valid && hasCurrent && difference < 0;
            $('[data-salary-decrease-warning]').toggleClass('hidden', !decreased).find('span').text(decreased ? 'เงินเดือนใหม่ต่ำกว่าเงินเดือนปัจจุบัน ' + this.money(Math.abs(difference)) : '');
        },

        isSalaryConfirmationOpen() {
            const $modal = $('#salaryConfirmationModal');
            return $modal.length > 0 && !$modal.hasClass('hidden');
        },

        openSalaryConfirmation() {
            const $modal = $('#salaryConfirmationModal');
            const $preview = $('[data-salary-preview]');
            if (!$modal.length || !$preview.length) return;
            this.updateSalaryPreview();
            const currentRaw = String($preview.attr('data-current-salary') || '');
            const hasCurrent = currentRaw !== '';
            const current = hasCurrent ? Number(currentRaw) : 0;
            const next = Number($('[data-salary-amount]').val());
            const difference = next - current;
            $modal.find('[data-salary-confirm-title]').text(hasCurrent ? 'ยืนยันการเปลี่ยนเงินเดือน?' : 'กำหนดเงินเดือนพื้นฐาน?');
            $modal.find('[data-salary-confirm-current]').text(hasCurrent ? this.money(current) : 'ยังไม่กำหนด');
            $modal.find('[data-salary-confirm-new]').text(this.money(next));
            $modal.find('[data-salary-confirm-change]').text(hasCurrent ? ((difference >= 0 ? '+' : '-') + this.money(Math.abs(difference))) : 'กำหนดครั้งแรก');
            $modal.find('[data-salary-confirm-date]').text(this.salaryDate($('[data-salary-effective-date]').val()));
            $modal.removeClass('hidden').addClass('flex').attr('aria-hidden', 'false');
            this.syncBodyScrollLock();
            $modal.find('[data-confirm-salary]').trigger('focus');
        },

        closeSalaryConfirmation(returnFocus) {
            const wasOpen = this.isSalaryConfirmationOpen();
            $('#salaryConfirmationModal').addClass('hidden').removeClass('flex').attr('aria-hidden', 'true');
            this.syncBodyScrollLock();
            if (wasOpen && returnFocus !== false) $('#salaryManagementForm button[type="submit"]').trigger('focus');
        },

        confirmSalaryChange() {
            const form = document.getElementById('salaryManagementForm');
            if (!form) return;
            $('[data-salary-confirmed]').val('1');
            this.closeSalaryConfirmation(false);
            form.requestSubmit();
        },

        reloadPayrollAttendance() {
            if ($('[data-payroll-calculator]').attr('data-attendance-powered') !== '1') return false;
            const id = String($('#selected_employee_id').val() || '');
            if (!id) return false;
            this.navigatePayrollAttendance(id);
            return true;
        },

        navigatePayrollAttendance(employeeId) {
            const year = String($('#paymentYear').val() || '');
            const month = String($('#paymentMonth').val() || '').toLowerCase();
            const url = '/employee/payment?employee_id=' + encodeURIComponent(employeeId) + '&year=' + encodeURIComponent(year) + '&month=' + encodeURIComponent(month);
            this.closeEmployeePicker(false);
            if (window.htmx) {
                const request = window.htmx.ajax('GET', url, { target: '#app-content', select: '#app-content', swap: 'outerHTML transition:true' });
                if (request && typeof request.then === 'function') request.then(function () { window.history.pushState({}, '', url); });
            } else {
                window.location.href = url;
            }
        },

        initAttendance(root) {
            if (this.attendanceClockTimer) {
                window.clearInterval(this.attendanceClockTimer);
                this.attendanceClockTimer = null;
            }
            const $clock = $(root).find('[data-attendance-clock]').addBack('[data-attendance-clock]').first();
            if (!$clock.length) return;
            const timezone = String($clock.attr('data-timezone') || 'Asia/Bangkok');
            const tick = function () {
                try {
                    $clock.text(new Intl.DateTimeFormat('th-TH', { timeZone: timezone, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false }).format(new Date()));
                } catch (_) {}
            };
            tick();
            this.attendanceClockTimer = window.setInterval(tick, 1000);
        },

        initAttendanceCalendar(root) {
            const $workspace = $(root).find('[data-attendance-calendar]').addBack('[data-attendance-calendar]').first();
            if (!$workspace.length) return;
            const raw = $workspace.find('[data-attendance-calendar-data]').text();
            try {
                this.attendanceCalendarData = JSON.parse(raw || '{}');
            } catch (_) {
                this.attendanceCalendarData = { days:{} };
                this.toast.error('ไม่สามารถโหลดข้อมูลปฏิทินได้');
            }
            const selected = String($workspace.attr('data-selected-date') || this.attendanceCalendarData.selected_date || '');
            if (selected) this.selectAttendanceCalendarDay(selected, false);
        },

        attendanceCalendarDay(date) {
            return this.attendanceCalendarData && this.attendanceCalendarData.days
                ? this.attendanceCalendarData.days[String(date)] || null
                : null;
        },

        attendanceCalendarPerson(date, employeeId) {
            const day = this.attendanceCalendarDay(date);
            if (!day) return null;
            return (day.people || []).find(function (person) { return String(person.id) === String(employeeId); }) || null;
        },

        thaiAttendanceDate(date) {
            try {
                return new Intl.DateTimeFormat('th-TH', { day:'numeric', month:'long', year:'numeric' }).format(new Date(String(date) + 'T12:00:00'));
            } catch (_) { return String(date); }
        },

        escapeHtml(value) {
            return $('<div>').text(value == null ? '' : String(value)).html();
        },

        attendanceAvatarMarkup(person, sizeClass) {
            const size = sizeClass || 'h-8 w-8';
            return '<span class="relative flex ' + size + ' shrink-0 items-center justify-center rounded-full bg-white text-[10px] font-bold text-[#31302e] ring-2 ' + this.escapeHtml(person.position_ring) + ' ring-offset-1">' +
                '<span aria-hidden="true">' + this.escapeHtml(person.initials) + '</span><span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white ' + this.escapeHtml(person.status_dot) + '"></span></span>';
        },

        attendanceDaySummaryMarkup(day) {
            const holiday = day.holiday ? '<span class="font-medium text-violet-700"><i class="fa-solid fa-umbrella-beach mr-1"></i>' + this.escapeHtml(day.holiday) + '</span><span class="mx-2">·</span>' : '';
            return holiday + 'มา <strong class="text-[#31302e]">' + Number(day.present_count || 0) + '</strong> คน <span class="mx-1">·</span> มาสาย <strong class="text-amber-700">' + Number(day.late_count || 0) + '</strong> คน <span class="mx-1">·</span> ขาด <strong class="text-red-700">' + Number(day.absent_count || 0) + '</strong> คน <span class="mx-1">·</span> ออกงานแล้ว <strong class="text-[#31302e]">' + Number(day.checked_out_count || 0) + '</strong> คน';
        },

        attendancePersonCardsMarkup(people) {
            const self = this;
            if (!people.length) return '<div class="p-8 text-center text-sm text-[#6d7175]">ไม่มีรายการเข้างานสำหรับวันนี้</div>';
            return '<div class="divide-y divide-[#e6e6e6]">' + people.map(function (person) {
                return '<article data-day-person data-status="' + self.escapeHtml(person.status) + '" class="p-4">' +
                    '<div class="flex items-start gap-3">' + self.attendanceAvatarMarkup(person) + '<div class="min-w-0 flex-1"><div class="flex items-start justify-between gap-2"><div><p class="truncate text-sm font-semibold">' + self.escapeHtml(person.name) + '</p><p class="text-xs text-[#6d7175]">' + self.escapeHtml(person.position) + ' · ' + self.escapeHtml(person.department) + '</p></div><span class="shrink-0 text-xs font-medium ' + self.escapeHtml(person.status_text) + '">' + self.escapeHtml(person.status_label) + '</span></div>' +
                    '<dl class="mt-3 grid grid-cols-2 gap-2 text-xs"><div><dt class="text-[#6d7175]">เข้า</dt><dd class="font-medium">' + self.escapeHtml(person.check_in || '--') + '</dd></div><div><dt class="text-[#6d7175]">ออก</dt><dd class="font-medium">' + self.escapeHtml(person.check_out || '--') + '</dd></div></dl>' +
                    '<a href="/attendance/history?employee_id=' + encodeURIComponent(person.id) + '" class="mt-3 inline-flex text-xs font-medium text-[#0075de]">ดูประวัติการเข้างาน <i class="fa-solid fa-arrow-right ml-1"></i></a></div></div></article>';
            }).join('') + '</div>';
        },

        attendancePersonTableMarkup(people) {
            const self = this;
            if (!people.length) return '<div class="p-8 text-center text-sm text-[#6d7175]">ไม่มีรายการเข้างานสำหรับวันนี้</div>';
            const rows = people.map(function (person) {
                return '<tr data-day-person data-status="' + self.escapeHtml(person.status) + '" class="border-b border-[#e6e6e6] last:border-0"><td class="px-4 py-2.5"><div class="flex items-center gap-3">' + self.attendanceAvatarMarkup(person) + '<div class="min-w-0"><p class="truncate text-sm font-semibold">' + self.escapeHtml(person.name) + '</p><p class="text-xs text-[#6d7175]">' + self.escapeHtml(person.employee_code) + '</p></div></div></td><td class="px-4 py-2.5 text-xs"><p class="font-medium">' + self.escapeHtml(person.position) + '</p><p class="text-[#6d7175]">' + self.escapeHtml(person.department) + '</p></td><td class="px-4 py-2.5 text-sm">' + self.escapeHtml(person.check_in || '--') + '</td><td class="px-4 py-2.5 text-sm">' + self.escapeHtml(person.check_out || '--') + '</td><td class="px-4 py-2.5"><span class="text-xs font-medium ' + self.escapeHtml(person.status_text) + '">' + self.escapeHtml(person.status_label) + '</span></td><td class="px-4 py-2.5 text-right"><a href="/attendance/history?employee_id=' + encodeURIComponent(person.id) + '" class="text-xs font-medium text-[#0075de]">ดูประวัติ</a></td></tr>';
            }).join('');
            return '<table class="w-full text-left"><thead class="sticky top-0 bg-[#fafafa] text-xs text-[#6d7175]"><tr><th class="px-4 py-2.5 font-medium">พนักงาน</th><th class="px-4 py-2.5 font-medium">ตำแหน่ง / แผนก</th><th class="px-4 py-2.5 font-medium">เข้า</th><th class="px-4 py-2.5 font-medium">ออก</th><th class="px-4 py-2.5 font-medium">สถานะ</th><th class="px-4 py-2.5"></th></tr></thead><tbody>' + rows + '</tbody></table>';
        },

        selectAttendanceCalendarDay(date, focusAgenda) {
            const day = this.attendanceCalendarDay(date);
            if (!day) return;
            $('[data-select-attendance-day]').each(function () {
                const selected = String($(this).data('date')) === String(date);
                $(this).toggleClass('bg-[#303030] text-white', selected).toggleClass('hover:bg-[#f6f5f4]', !selected).attr('aria-pressed', selected ? 'true' : 'false');
            });
            const $agenda = $('[data-mobile-day-agenda]');
            if (!$agenda.length) return;
            $agenda.html('<div class="flex items-start justify-between gap-3"><div><h3 class="text-sm font-semibold">' + this.escapeHtml(this.thaiAttendanceDate(date)) + '</h3><p class="mt-1 text-xs text-[#6d7175]">' + this.attendanceDaySummaryMarkup(day) + '</p></div><button type="button" data-open-attendance-day data-date="' + this.escapeHtml(date) + '" class="shrink-0 text-xs font-medium text-[#0075de]">ดูทั้งหมด</button></div><div class="mt-3 overflow-hidden rounded-md border border-[#e6e6e6]">' + this.attendancePersonCardsMarkup((day.people || []).slice(0, 5)) + '</div>');
            if (focusAgenda !== false && window.matchMedia('(max-width: 767px)').matches) $agenda.get(0).scrollIntoView({ behavior:'smooth', block:'nearest' });
        },

        isAttendanceDayModalOpen() {
            const $modal = $('#attendanceDayModal');
            return $modal.length > 0 && !$modal.hasClass('hidden');
        },

        openAttendanceDayModal(date) {
            const day = this.attendanceCalendarDay(date);
            if (!day) return;
            this.hideAttendancePersonTooltip();
            const people = day.people || [];
            $('[data-attendance-day-label]').text(this.thaiAttendanceDate(date));
            $('[data-attendance-day-summary]').html(this.attendanceDaySummaryMarkup(day));
            $('[data-attendance-day-content]').html('<div class="md:hidden">' + this.attendancePersonCardsMarkup(people) + '</div><div class="hidden md:block">' + this.attendancePersonTableMarkup(people) + '</div>');
            $('#attendanceDayModal').attr({'aria-hidden':'false','data-current-date':date}).removeClass('hidden').addClass('flex');
            this.filterAttendanceDay('all');
            this.syncBodyScrollLock();
            $('#attendanceDayModal [data-close-attendance-day]').last().trigger('focus');
        },

        closeAttendanceDayModal(returnFocus) {
            const wasOpen = this.isAttendanceDayModalOpen();
            $('#attendanceDayModal').addClass('hidden').removeClass('flex').attr('aria-hidden','true');
            this.syncBodyScrollLock();
            if (wasOpen && returnFocus !== false) $('[data-open-attendance-day]').first().trigger('focus');
        },

        filterAttendanceDay(status) {
            status = ['all','on_time','late','absent'].indexOf(status) !== -1 ? status : 'all';
            $('#attendanceDayModal [data-day-person]').each(function () { $(this).toggleClass('hidden', status !== 'all' && String($(this).data('status')) !== status); });
            $('#attendanceDayModal [data-day-status-filter]').each(function () {
                const active = String($(this).data('day-status-filter')) === status;
                $(this).toggleClass('bg-[#303030] text-white border-transparent', active).toggleClass('border border-[#d5d3d0]', !active).attr('aria-pressed', active ? 'true' : 'false');
            });
        },

        showAttendancePersonTooltip(trigger) {
            window.clearTimeout(this.attendanceTooltipTimer);
            const $trigger = $(trigger);
            const person = this.attendanceCalendarPerson(String($trigger.data('attendance-date') || ''), String($trigger.data('employee-id') || ''));
            const $tooltip = $('#attendancePersonTooltip');
            if (!person || !$tooltip.length) return;
            const location = person.location ? '<p class="mt-2 text-[#6d7175]"><i class="fa-solid fa-location-dot mr-1.5"></i>' + this.escapeHtml(person.location) + '</p>' : '';
            $tooltip.html('<div class="flex items-start gap-3">' + this.attendanceAvatarMarkup(person, 'h-9 w-9') + '<div class="min-w-0"><p class="truncate text-sm font-semibold">' + this.escapeHtml(person.name) + '</p><p class="text-[#6d7175]">' + this.escapeHtml(person.employee_code) + '</p></div></div><p class="mt-3 font-medium">' + this.escapeHtml(person.position) + ' <span class="font-normal text-[#6d7175]">· ' + this.escapeHtml(person.department) + '</span></p><dl class="mt-3 grid grid-cols-2 gap-2"><div><dt class="text-[#6d7175]">เข้า</dt><dd class="font-medium">' + this.escapeHtml(person.check_in || '--') + '</dd></div><div><dt class="text-[#6d7175]">ออก</dt><dd class="font-medium">' + this.escapeHtml(person.check_out || '--') + '</dd></div></dl><p class="mt-3 font-medium ' + this.escapeHtml(person.status_text) + '">' + this.escapeHtml(person.status_label) + '</p>' + location + '<a href="/attendance/history?employee_id=' + encodeURIComponent(person.id) + '" class="mt-3 inline-flex font-medium text-[#0075de]">ดูประวัติ <i class="fa-solid fa-arrow-right ml-1"></i></a>');
            $tooltip.removeClass('hidden').attr('aria-hidden','false');
            $trigger.attr('aria-describedby','attendancePersonTooltip');
            const rect = trigger.getBoundingClientRect();
            const width = $tooltip.outerWidth() || 260;
            const height = $tooltip.outerHeight() || 220;
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            let left = rect.right + 8;
            if (left + width > viewportWidth - 8) left = rect.left - width - 8;
            left = Math.max(8, Math.min(left, viewportWidth - width - 8));
            let top = rect.top;
            if (top + height > viewportHeight - 8) top = viewportHeight - height - 8;
            top = Math.max(8, top);
            $tooltip.css({ left:left + 'px', top:top + 'px' });
        },

        scheduleAttendanceTooltipClose() {
            window.clearTimeout(this.attendanceTooltipTimer);
            this.attendanceTooltipTimer = window.setTimeout(() => this.hideAttendancePersonTooltip(), 120);
        },

        hideAttendancePersonTooltip() {
            window.clearTimeout(this.attendanceTooltipTimer);
            $('[data-attendance-person][aria-describedby="attendancePersonTooltip"]').removeAttr('aria-describedby');
            $('#attendancePersonTooltip').addClass('hidden').attr('aria-hidden','true').empty();
        },

        calculatePayrollPreview() {
            const $calculator = $('[data-payroll-calculator]');
            if (!$calculator.length) return null;
            const base = Math.max(0, Number($calculator.attr('data-base-salary')) || 0);
            const loan = Math.max(0, Number($calculator.attr('data-loan-balance')) || 0);
            const medical = Math.round(base * 0.03 * 100) / 100;
            const housing = Math.round(base * 0.08 * 100) / 100;
            const overtime = Math.round((this.numericValue('#paymentOvertime') || 0) * 300 * 100) / 100;
            const loanCut = Math.round(loan * 0.05 * 100) / 100;
            const fundCut = Math.round(base * 0.025 * 100) / 100;
            const absenceDays = this.numericValue('#paymentAbsence');
            const absenceEnabled = $calculator.attr('data-absence-enabled') === '1';
            const absenceMode = String($calculator.attr('data-absence-mode') || 'fixed');
            const absenceDivisor = Math.max(1, Number($calculator.attr('data-absence-divisor')) || 30);
            const absenceRate = absenceEnabled
                ? (absenceMode === 'daily_salary' ? Math.round((base / absenceDivisor) * 100) / 100 : Math.max(0, Number($calculator.attr('data-absence-rate')) || 0))
                : 0;
            const absence = absenceDays === null ? 0 : Math.round(absenceDays * absenceRate * 100) / 100;
            const lateCount = this.numericValue('#paymentLateCount');
            const lateMinutes = this.numericValue('#paymentLateMinutes');
            const lateMode = String($calculator.attr('data-late-mode') || 'none');
            let late = 0;
            let lateFormula = 'ปิดการหักเงินกรณีมาสาย';
            if (lateMode === 'per_occurrence') {
                const rate = Math.max(0, Number($calculator.attr('data-late-occurrence-rate')) || 0);
                late = (lateCount || 0) * rate;
                lateFormula = (lateCount || 0) + ' ครั้ง × ' + this.money(rate) + '/ครั้ง';
            } else if (lateMode === 'per_minutes') {
                const interval = Math.max(1, Number($calculator.attr('data-late-interval-minutes')) || 1);
                const rate = Math.max(0, Number($calculator.attr('data-late-interval-rate')) || 0);
                const grace = $calculator.attr('data-attendance-source') === 'attendance' ? 0 : Math.max(0, Number($calculator.attr('data-late-grace-minutes')) || 0) * (lateCount || 0);
                const charged = Math.max(0, (lateMinutes || 0) - grace);
                const intervals = $calculator.attr('data-late-rounding') === 'floor' ? Math.floor(charged / interval) : Math.ceil(charged / interval);
                late = intervals * rate;
                lateFormula = (lateMinutes || 0) + ' นาที − ผ่อนผัน ' + grace + ' นาที; ' + intervals + ' รอบ × ' + this.money(rate);
            } else if (lateMode === 'per_actual_minute') {
                const rate = Math.max(0, Number($calculator.attr('data-late-minute-rate')) || 0);
                late = (lateMinutes || 0) * rate;
                lateFormula = (lateMinutes || 0) + ' นาที × ' + this.money(rate) + '/นาที';
            }
            const maximumRaw = String($calculator.attr('data-late-maximum') || '').trim();
            if (maximumRaw !== '') late = Math.min(late, Math.max(0, Number(maximumRaw) || 0));
            late = Math.round(late * 100) / 100;

            let manualAdditions = 0;
            $('[data-adjustment-list="addition"] [data-adjustment-amount]').each(function () { manualAdditions += Math.max(0, Number(this.value) || 0); });
            let manualDeductions = 0;
            $('[data-adjustment-list="deduction"] [data-adjustment-amount]').each(function () { manualDeductions += Math.max(0, Number(this.value) || 0); });
            const additions = Math.round((medical + housing + overtime + manualAdditions) * 100) / 100;
            const deductions = Math.round((loanCut + fundCut + absence + late + manualDeductions) * 100) / 100;
            const net = Math.round((base + additions - deductions) * 100) / 100;
            const allowNegative = $calculator.attr('data-allow-negative-net') === '1';
            const state = { base: base, additions: additions, deductions: deductions, net: net, allowNegative: allowNegative };
            $calculator.data('calculation', state);

            $('[data-auto-medical]').text(this.money(medical));
            $('[data-auto-housing]').text(this.money(housing));
            $('[data-auto-overtime]').text(this.money(overtime));
            $('[data-auto-loan]').text('-' + this.money(loanCut));
            $('[data-auto-fund]').text('-' + this.money(fundCut));
            $('[data-auto-absence]').text('-' + this.money(absence));
            $('[data-auto-late]').text('-' + this.money(late));
            $('[data-absence-formula]').text(absenceDays === null ? 'ขาดงาน: ไม่มีข้อมูลสำหรับงวดนี้' : (absenceMode === 'daily_salary' ? 'เงินเดือน ÷ ' + absenceDivisor + ' วัน = ' + this.money(absenceRate) + '/วัน; ขาด ' + absenceDays + ' วัน' : absenceDays + ' วัน × ' + this.money(absenceRate) + '/วัน'));
            $('[data-late-formula]').text(lateFormula);
            $('[data-summary-base]').text(this.money(base));
            $('[data-summary-additions]').text('+' + this.money(additions));
            $('[data-summary-deductions]').text('-' + this.money(deductions));
            $('[data-summary-net]').text(this.money(net));
            $('[data-negative-net]').toggleClass('hidden', net >= 0 || allowNegative);
            const selected = Boolean($('#selected_employee_id').val());
            const selectedPaid = !$('[data-selected-paid-warning]').hasClass('hidden');
            $('[data-payment-submit]').prop('disabled', !selected || selectedPaid || (net < 0 && !allowNegative));
            return state;
        },

        addPayrollAdjustment(type) {
            if (type !== 'addition' && type !== 'deduction') return;
            const prefix = type === 'addition' ? 'additions' : 'deductions';
            const row = '<div data-adjustment-row class="grid grid-cols-1 gap-2 rounded-[8px] bg-[#f6f5f4] p-3 sm:grid-cols-[minmax(0,1fr)_140px_44px]">' +
                '<input name="' + prefix + '[name][]" class="min-h-10 rounded-[7px] border border-[#e6e6e6] px-3" placeholder="ชื่อรายการ" required>' +
                '<input type="number" min="0" step="0.01" name="' + prefix + '[amount][]" data-adjustment-amount data-payroll-input class="min-h-10 rounded-[7px] border border-[#e6e6e6] px-3" placeholder="จำนวนเงิน" required>' +
                '<button type="button" data-remove-adjustment class="min-h-10 rounded-[7px] text-red-600" aria-label="ลบ"><i class="fa-solid fa-trash"></i></button>' +
                '<input name="' + prefix + '[note][]" class="min-h-10 rounded-[7px] border border-[#e6e6e6] px-3 sm:col-span-3" placeholder="หมายเหตุ (ไม่บังคับ)"></div>';
            const $row = $(row).appendTo('[data-adjustment-list="' + type + '"]');
            $row.find('input').first().trigger('focus');
            $('[data-payment-confirmed]').val('0');
        },

        isPaymentConfirmationOpen() {
            const $modal = $('#paymentConfirmationModal');
            return $modal.length > 0 && !$modal.hasClass('hidden');
        },

        openPaymentConfirmation() {
            const state = this.calculatePayrollPreview();
            if (!state || (state.net < 0 && !state.allowNegative) || !$('#selected_employee_id').val()) return;
            $('[data-confirm-employee]').text($('[data-payment-employee-name]').text() + ' (' + $('#selected_employee_id').val() + ')');
            $('[data-confirm-period]').text('งวด ' + $('#paymentMonth option:selected').text() + ' ' + $('#paymentYear').val());
            $('[data-confirm-base]').text(this.money(state.base));
            $('[data-confirm-additions]').text('+' + this.money(state.additions));
            $('[data-confirm-deductions]').text('-' + this.money(state.deductions));
            $('[data-confirm-net]').text(this.money(state.net));
            $('#paymentConfirmationModal').removeClass('hidden').addClass('flex').attr('aria-hidden', 'false');
            this.syncBodyScrollLock();
            window.setTimeout(function () { $('[data-confirm-payment]').trigger('focus'); }, 30);
        },

        closePaymentConfirmation(returnFocus) {
            const wasOpen = this.isPaymentConfirmationOpen();
            $('#paymentConfirmationModal').addClass('hidden').removeClass('flex').attr('aria-hidden', 'true');
            this.syncBodyScrollLock();
            if (wasOpen && returnFocus !== false) $('[data-payment-submit]').trigger('focus');
        },

        confirmPayrollPayment() {
            const form = document.getElementById('payrollPaymentForm');
            if (!form) return;
            $('[data-payment-confirmed]').val('1');
            this.closePaymentConfirmation(false);
            form.requestSubmit();
        },

        initPayrollSettings(root) {
            const $workspace = $(root).find('[data-payroll-settings]').addBack('[data-payroll-settings]').first();
            if (!$workspace.length) return;
            const form = $workspace.find('#payrollSettingsForm').get(0);
            if (form) $(form).data('settings-initial', this.serializePayrollSettings(form));
            this.payrollSettingsDirty = $workspace.attr('data-settings-invalid') === '1';
            this.payrollSettingsSubmitting = false;
            this.updatePayrollSettingsPreview();
            this.renderPayrollSettingsDirtyState();
        },

        serializePayrollSettings(form) {
            if (!form) return '';
            const entries = [];
            new FormData(form).forEach(function (value, key) { entries.push([key, String(value)]); });
            entries.sort(function (a, b) { return a[0] === b[0] ? a[1].localeCompare(b[1]) : a[0].localeCompare(b[0]); });
            return JSON.stringify(entries);
        },

        updatePayrollSettingsDirtyState() {
            const form = document.getElementById('payrollSettingsForm');
            if (!form) return;
            this.payrollSettingsDirty = this.serializePayrollSettings(form) !== String($(form).data('settings-initial') || '');
            this.renderPayrollSettingsDirtyState();
        },

        renderPayrollSettingsDirtyState() {
            $('[data-settings-dirty]').toggleClass('hidden', !this.payrollSettingsDirty);
            $('[data-reset-payroll-settings]').prop('disabled', !this.payrollSettingsDirty);
            $('[data-settings-save]').prop('disabled', !this.payrollSettingsDirty);
        },

        resetPayrollSettingsChanges() {
            this.payrollSettingsDirty = false;
            this.renderPayrollSettingsDirtyState();
            if (window.htmx) {
                window.htmx.ajax('GET', '/settings/payroll', { target:'#payroll-settings-workspace', select:'#payroll-settings-workspace', swap:'outerHTML' });
                this.toast.info('ยกเลิกการเปลี่ยนแปลงแล้ว');
            } else {
                window.location.href = '/settings/payroll';
            }
        },

        updatePayrollSettingsPreview() {
            if (!$('[data-payroll-settings]').length) return;
            const absenceEnabled = $('input[name="absence_enabled"]').is(':checked');
            const absenceMode = String($('input[name="absence_mode"]:checked').val() || 'fixed');
            const lateEnabled = $('input[name="late_enabled"]').is(':checked');
            const mode = lateEnabled ? String($('input[name="late_mode"]:checked').val() || 'per_occurrence') : 'none';
            const capEnabled = $('input[name="late_maximum_enabled"]').is(':checked');
            $('[data-settings-toggle]').each(function () { $(this).closest('label').find('[data-toggle-label]').text(this.checked ? 'เปิด' : 'ปิด'); });
            $('[data-absence-details]').toggleClass('hidden', !absenceEnabled);
            $('[data-absence-fixed-fields]').toggleClass('hidden', absenceMode !== 'fixed');
            $('[data-absence-salary-fields]').toggleClass('hidden', absenceMode !== 'daily_salary');
            $('[data-late-details]').toggleClass('hidden', !lateEnabled);
            $('[data-late-occurrence-fields]').toggleClass('hidden', mode !== 'per_occurrence');
            $('[data-late-minute-fields]').toggleClass('hidden', mode !== 'per_minutes');
            $('[data-late-actual-minute-fields]').toggleClass('hidden', mode !== 'per_actual_minute');
            $('[data-late-maximum-fields]').toggleClass('hidden', !capEnabled);

            const sample = function (name) { return Math.max(0, Number($('[data-settings-sample="' + name + '"]').val()) || 0); };
            const base = sample('salary');
            const absenceDays = sample('absence');
            const lateCount = sample('late-count');
            const lateMinutes = sample('late-minutes');
            const bonus = sample('bonus');
            const divisor = Math.max(1, Number($('input[name="absence_divisor_days"]').val()) || 30);
            const absenceRate = absenceMode === 'daily_salary' ? Math.round((base / divisor) * 100) / 100 : Math.max(0, Number($('input[name="absence_rate"]').val()) || 0);
            const absence = absenceEnabled ? Math.round(absenceDays * absenceRate * 100) / 100 : 0;
            let late = 0;
            let formula = 'ปิดการหักเงินกรณีมาสาย';
            if (mode === 'per_occurrence') {
                const rate = Number($('input[name="late_occurrence_rate"]').val()) || 0;
                late = lateCount * rate;
                formula = lateCount + ' ครั้ง × ' + this.money(rate) + '/ครั้ง';
            } else if (mode === 'per_minutes') {
                const interval = Math.max(1, Number($('input[name="late_interval_minutes"]').val()) || 1);
                const rate = Number($('input[name="late_interval_rate"]').val()) || 0;
                const raw = lateMinutes / interval;
                const count = $('select[name="late_rounding"]').val() === 'floor' ? Math.floor(raw) : Math.ceil(raw);
                late = count * rate;
                formula = lateMinutes + ' นาที ÷ ' + interval + ' นาที = ' + count + ' ช่วง × ' + this.money(rate);
            } else if (mode === 'per_actual_minute') {
                const rate = Number($('input[name="late_minute_rate"]').val()) || 0;
                late = lateMinutes * rate;
                formula = lateMinutes + ' นาที × ' + this.money(rate) + '/นาที';
            }
            const maximum = capEnabled ? String($('input[name="max_late_deduction"]').val() || '').trim() : '';
            if (maximum !== '') { late = Math.min(late, Math.max(0, Number(maximum) || 0)); formula += ' (สูงสุด ' + this.money(maximum) + ')'; }
            late = Math.round(late * 100) / 100;
            const net = Math.round((base + bonus - absence - late) * 100) / 100;
            const allowNegative = $('input[name="allow_negative_net_salary"]').is(':checked');
            $('[data-settings-base-preview]').text(this.money(base));
            $('[data-settings-bonus-preview]').text('+' + this.money(bonus));
            $('[data-settings-absence-preview]').text('-' + this.money(absence));
            $('[data-settings-late-preview]').text('-' + this.money(late));
            $('[data-settings-net-preview]').text(this.money(net)).toggleClass('text-red-700', net < 0).toggleClass('text-[#0075de]', net >= 0);
            $('[data-settings-negative-warning]').toggleClass('hidden', net >= 0 || allowNegative);
            $('[data-settings-absence-formula]').text(!absenceEnabled ? 'ปิดการหักเงินกรณีขาดงาน' : (absenceMode === 'daily_salary' ? this.money(base) + ' ÷ ' + divisor + ' วัน = ' + this.money(absenceRate) + '/วัน; ขาด ' + absenceDays + ' วัน' : absenceDays + ' วัน × ' + this.money(absenceRate) + '/วัน'));
            $('[data-absence-rate-helper]').text('ขาดงาน 1 วัน จะหัก ' + this.money(absenceRate));
            $('[data-settings-formula]').text(formula);
        },

        startLoading() {
            clearTimeout(this.loadingTimer);
            clearTimeout(this.finishTimer);
            $('#globalProgress').removeClass('is-finishing').addClass('is-loading');
            $('#app-content').addClass('is-loading').attr('aria-busy', 'true');
            $('#routeStatus').text('กำลังโหลดหน้า');
        },

        stopLoading() {
            const $progress = $('#globalProgress');
            $progress.removeClass('is-loading').addClass('is-finishing');
            $('#app-content').removeClass('is-loading').removeAttr('aria-busy');
            clearTimeout(this.finishTimer);
            this.finishTimer = setTimeout(function () { $progress.removeClass('is-finishing'); }, 220);
        },

        showRouteError(message) {
            this.toast.error(message || 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้ กรุณาลองใหม่อีกครั้ง');
        },

        httpErrorMessage(status) {
            const messages = {
                400:'คำขอไม่ถูกต้อง กรุณาตรวจสอบข้อมูลแล้วลองใหม่',
                401:'เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่',
                403:'คุณไม่มีสิทธิ์ดำเนินการนี้',
                404:'ไม่พบหน้าหรือข้อมูลที่ร้องขอ',
                422:'กรุณาตรวจสอบข้อมูลที่กรอก',
                500:'เกิดข้อผิดพลาดภายในระบบ กรุณาลองใหม่อีกครั้ง'
            };
            return messages[Number(status)] || 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้ กรุณาลองใหม่อีกครั้ง';
        },

        hideRouteError() {
            $('#routeError').addClass('hidden');
        },

        announcePage() {
            const title = $('#app-content').data('page-title') || document.title;
            $('#routeStatus').text('โหลดหน้า ' + String(title).split(' - ')[0] + ' แล้ว');
        },

        updateChrome() {
            const content = document.querySelector('#app-content');
            if (!content) return;
            const title = content.dataset.pageTitle || document.title;
            document.title = title;
            $('#mobilePageTitle').text(String(title).split(' - ')[0]);
            const path = window.location.pathname.replace(/\/$/, '') || '/';
            $('[data-app-nav]').each(function () {
                const href = new URL(this.href, window.location.origin).pathname.replace(/\/$/, '') || '/';
                let active = path === href;
                if (href === '/department') active = path.indexOf('/department') === 0;
                if (href === '/employee') active = ['/employee', '/employee/add', '/employee/update'].indexOf(path) !== -1;
                $(this)
                    .toggleClass('bg-white bg-[#ffffff] text-[#000000] font-semibold border-[#e6e6e6]', active)
                    .toggleClass('text-[#31302e] border-transparent', !active)
                    .attr('aria-current', active ? 'page' : null);
            });
        },

        resetForm(form) {
            if (!form) return;
            const $form = $(form).removeData('submitting');
            const toastId = $form.data('loading-toast-id');
            if (toastId) this.toast.dismiss(toastId);
            $form.removeData('loading-toast-id');
            const $button = $form.find('button[type="submit"]').first();
            if ($button.data('original-html')) $button.html($button.data('original-html'));
            $button.removeAttr('aria-disabled').removeClass('opacity-70 cursor-wait pointer-events-none');
        },

        filterMobileList($input) {
            const keyword = String($input.val() || '').toLowerCase().trim();
            const $section = $input.closest('.js-mobile-list-section');
            const filter = $section.find('.js-mobile-filter').val();
            let visible = 0;
            $section.find('.js-mobile-record').each(function () {
                const haystack = String($(this).data('search') || $(this).text()).toLowerCase();
                const matches = (!keyword || haystack.includes(keyword)) && (!filter || String($(this).data('filter')) === String(filter));
                $(this).toggle(matches);
                if (matches) visible++;
            });
            $section.find('.js-mobile-empty-filter').toggleClass('hidden', visible !== 0);
        },

        sortMobileList($select) {
            const mode = $select.val();
            const $records = $select.closest('.js-mobile-list-section').find('.js-mobile-records');
            const items = $records.children('.js-mobile-record').get();
            items.sort(function (a, b) {
                const $a = $(a), $b = $(b);
                if (mode === 'name-asc') return String($a.data('name')).localeCompare(String($b.data('name')), 'th');
                if (mode === 'name-desc') return String($b.data('name')).localeCompare(String($a.data('name')), 'th');
                if (mode === 'salary-asc') return Number($a.data('salary')) - Number($b.data('salary'));
                if (mode === 'salary-desc') return Number($b.data('salary')) - Number($a.data('salary'));
                return Number($a.data('original')) - Number($b.data('original'));
            });
            $.each(items, function (_, item) { $records.append(item); });
        },

        plainText(value) {
            return $('<div>').html(value == null ? '' : String(value)).text().trim();
        },

        createButton(label, icon, className) {
            return $('<button>', {
                type: 'button',
                class: 'dt-tool-button ' + (className || ''),
                html: '<i class="fa-solid ' + icon + '" aria-hidden="true"></i><span>' + label + '</span>'
            });
        },

        createDropdown(label, icon) {
            const self = this;
            const $dropdown = $('<div>', { class: 'dt-dropdown' });
            const $trigger = this.createButton(label, icon).attr('aria-expanded', 'false');
            const $menu = $('<div>', { class: 'dt-menu', hidden: true });
            $trigger.on('click', function (event) {
                event.stopPropagation();
                const willOpen = $menu.prop('hidden');
                $('.dt-menu').prop('hidden', true);
                $('.dt-dropdown > .dt-tool-button').attr('aria-expanded', 'false');
                $menu.prop('hidden', !willOpen);
                $trigger.attr('aria-expanded', willOpen ? 'true' : 'false');
            });
            $menu.on('click', function (event) { event.stopPropagation(); });
            return { $dropdown: $dropdown.append($trigger, $menu), $trigger: $trigger, $menu: $menu, self: self };
        },

        selectedRows($table) {
            return $table.find('tbody .dt-row-check:checked').closest('tr');
        },

        csvCell(value) {
            return '"' + this.plainText(value).replace(/"/g, '""') + '"';
        },

        exportCsv(api, $table) {
            const self = this;
            const columns = api.columns(':visible').indexes().toArray().filter(function (index) {
                const title = self.plainText(api.column(index).header().innerHTML);
                return index !== 0 && title !== 'จัดการ';
            });
            const rows = [columns.map(function (index) { return self.csvCell(api.column(index).header().innerHTML); }).join(',')];
            api.rows({ search: 'applied', order: 'applied' }).every(function () {
                const data = this.data();
                rows.push(columns.map(function (index) { return self.csvCell(data[index]); }).join(','));
            });
            const blob = new Blob(['\ufeff' + rows.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = ($table.data('export-name') || 'ข้อมูล') + '.csv';
            document.body.appendChild(link);
            link.click();
            URL.revokeObjectURL(link.href);
            link.remove();
        },

        printTable(api, $table) {
            const self = this;
            const $copy = $table.clone();
            $copy.find('.dt-select-heading, .dt-select-cell').remove();
            // DataTables adds a generated footer for its screen layout. It is
            // not report data and would otherwise print as a duplicate header.
            $copy.find('tfoot').remove();
            $copy.find('thead th').filter(function () { return self.plainText(this.innerHTML) === 'จัดการ'; }).each(function () {
                const index = this.cellIndex;
                $copy.find('tr').each(function () { $(this).children().eq(index).remove(); });
            });
            const printWindow = window.open('', '_blank', 'width=1000,height=720');
            if (!printWindow) return;
            printWindow.document.write('<!doctype html><html lang="th"><head><meta charset="utf-8"><title>' + self.plainText(document.title) + '</title><style>body{font-family:Arial,sans-serif;padding:28px;color:#000}h1{font-size:22px}table{width:100%;border-collapse:collapse}th,td{padding:9px;border:1px solid #ddd;text-align:left;font-size:13px}th{background:#f6f5f4}</style></head><body><h1>' + self.plainText($table.data('export-name') || document.title) + '</h1>' + $copy.prop('outerHTML') + '</body></html>');
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        },

        buildToolbar(api, $table) {
            const self = this;
            const $toolbar = $(api.table().container()).find('.dt-toolbar');
            const createUrl = $table.data('create-url');
            const $edit = this.createButton('แก้ไข', 'fa-pen').prop('disabled', true);
            const $remove = this.createButton('ลบ', 'fa-trash-can').prop('disabled', true);
            const $export = this.createDropdown('ส่งออก', 'fa-file-export');
            const $columns = this.createDropdown('มุมมอง', 'fa-table-columns');

            if (createUrl) {
                $toolbar.append($('<a>', {
                    href: createUrl,
                    class: 'dt-tool-button dt-tool-button--primary',
                    'hx-boost': 'true',
                    'hx-target': '#app-content',
                    'hx-select': '#app-content',
                    'hx-swap': 'outerHTML transition:true',
                    'hx-push-url': 'true',
                    html: '<i class="fa-solid fa-plus" aria-hidden="true"></i><span>เพิ่ม</span>'
                }));
            }
            if ($table.find('tbody a[href*="/update"]').length) $toolbar.append($edit);
            if ($table.find('tbody a[href*="/delete"]').length) $toolbar.append($remove);

            $export.$menu.append(
                $('<button>', { type: 'button', html: '<i class="fa-solid fa-file-csv"></i><span>ไฟล์ CSV</span>' }).on('click', function () { self.exportCsv(api, $table); }),
                $('<button>', { type: 'button', html: '<i class="fa-solid fa-print"></i><span>พิมพ์ตาราง</span>' }).on('click', function () { self.printTable(api, $table); })
            );
            $toolbar.append($export.$dropdown);

            api.columns().every(function (index) {
                if (index === 0) return;
                const column = this;
                const title = self.plainText(column.header().innerHTML);
                if (!title || title === 'จัดการ') return;
                const $item = $('<button>', { type: 'button', html: '<i class="fa-solid fa-check"></i><span>' + title + '</span>' });
                $item.on('click', function () {
                    const visible = !column.visible();
                    column.visible(visible);
                    $(this).find('i').toggleClass('fa-check', visible).toggleClass('fa-minus', !visible);
                });
                $columns.$menu.append($item);
            });
            $toolbar.append($columns.$dropdown);

            function updateActions() {
                const $rows = self.selectedRows($table);
                $table.find('tbody tr').removeClass('dt-row-selected');
                $rows.addClass('dt-row-selected');
                $edit.prop('disabled', $rows.length !== 1 || !$rows.eq(0).find('a[href*="/update"]').length);
                $remove.prop('disabled', $rows.length !== 1 || !$rows.eq(0).find('a[href*="/delete"]').length);
                const visibleChecks = $table.find('tbody .dt-row-check:visible');
                const checkedVisible = visibleChecks.filter(':checked').length;
                const checkAll = $table.find('.dt-check-all').get(0);
                if (checkAll) {
                    checkAll.checked = visibleChecks.length > 0 && checkedVisible === visibleChecks.length;
                    checkAll.indeterminate = checkedVisible > 0 && checkedVisible < visibleChecks.length;
                }
            }

            $table.on('change.payrollTable', '.dt-row-check', updateActions);
            $table.on('change.payrollTable', '.dt-check-all', function () {
                $table.find('tbody .dt-row-check:visible').prop('checked', this.checked);
                updateActions();
            });
            $edit.on('click', function () {
                const link = self.selectedRows($table).eq(0).find('a[href*="/update"]').get(0);
                if (link) link.click();
            });
            $remove.on('click', function () {
                const link = self.selectedRows($table).eq(0).find('a[href*="/delete"]').get(0);
                if (link) link.click();
            });
            api.on('draw.payrollTable', updateActions);
            updateActions();
            if (window.htmx) window.htmx.process($toolbar.get(0));
        },

        initDataTables(root) {
            if (!$.fn.DataTable) return;
            const self = this;
            $(root).find('.js-data-table').addBack('.js-data-table').each(function () {
                if ($.fn.DataTable.isDataTable(this) || this.dataset.datatableReady === 'true') return;
                const $table = $(this);
                this.dataset.datatableReady = 'true';
                $table.find('tbody tr').filter(function () {
                    return this.cells.length === 1 && Number(this.cells[0].getAttribute('colspan')) > 1;
                }).remove();
                const $headerRow = $table.find('thead tr').first();
                $headerRow.prepend('<th class="dt-select-heading"><input type="checkbox" class="dt-check-all" aria-label="เลือกทั้งหมด"></th>');
                $table.find('tbody tr').each(function () {
                    $(this).prepend('<td class="dt-select-cell"><input type="checkbox" class="dt-row-check" aria-label="เลือกแถวนี้"></td>');
                });
                const $footer = $('<tfoot>', { 'data-app-generated': 'true' });
                const $footerRow = $('<tr>');
                $headerRow.children().each(function (index) {
                    const label = index === 0 || self.plainText(this.innerHTML) === 'จัดการ' ? '' : self.plainText(this.innerHTML);
                    $footerRow.append($('<th>').text(label));
                });
                $table.append($footer.append($footerRow));
                const lastIndex = $headerRow.children().length - 1;
                const lastTitle = self.plainText($headerRow.children().last().html());
                const api = $table.DataTable({
                    responsive: true,
                    pageLength: 10,
                    autoWidth: false,
                    order: [],
                    dom: '<"dt-top"<"dt-toolbar"><"dt-search"f>><"dt-table-shell"t><"dt-bottom"<"dt-meta"li>p>',
                    columnDefs: [
                        { targets: 0, orderable: false, searchable: false },
                        ...(lastTitle === 'จัดการ' ? [{ targets: lastIndex, orderable: false, searchable: false }] : [])
                    ],
                    language: {
                        search: 'ค้นหา:',
                        lengthMenu: 'แสดง _MENU_ รายการ',
                        info: 'แสดงรายการที่ _START_ ถึง _END_ จากทั้งหมด _TOTAL_ รายการ',
                        infoEmpty: 'ไม่มีข้อมูล',
                        zeroRecords: 'ไม่พบข้อมูลที่ตรงกับการค้นหา',
                        emptyTable: 'ไม่มีข้อมูลในตาราง',
                        paginate: {
                            next: '<i class="fa-solid fa-chevron-right" aria-label="หน้าถัดไป"></i>',
                            previous: '<i class="fa-solid fa-chevron-left" aria-label="หน้าก่อนหน้า"></i>'
                        }
                    }
                });
                self.buildToolbar(api, $table);
            });
        },

        destroyDataTables(root) {
            if (!$.fn.DataTable) return;
            $(root).find('.js-data-table').addBack('.js-data-table').each(function () {
                const $table = $(this);
                if ($.fn.DataTable.isDataTable(this)) $table.DataTable().destroy();
                $table.off('.payrollTable');
                $table.find('.dt-select-heading, .dt-select-cell').remove();
                $table.find('tfoot[data-app-generated]').remove();
                this.removeAttribute('data-datatable-ready');
            });
        },

        ensureChartTooltip() {
            const dashboard = document.querySelector('[data-dashboard-page]');
            if (!dashboard) {
                this.removeChartTooltip();
                return null;
            }
            if (this.chartTooltip && dashboard.contains(this.chartTooltip)) return this.chartTooltip;
            this.removeChartTooltip();
            const tooltip = document.createElement('div');
            tooltip.className = 'dashboard-chart-tooltip';
            tooltip.setAttribute('role', 'tooltip');
            tooltip.setAttribute('aria-hidden', 'true');
            dashboard.appendChild(tooltip);
            this.chartTooltip = tooltip;
            return tooltip;
        },

        removeChartTooltip() {
            document.querySelectorAll('.dashboard-chart-tooltip').forEach(function (tooltip) { tooltip.remove(); });
            this.chartTooltip = null;
        },

        attachChartTooltip(node, title, value) {
            const self = this;
            function show(event) {
                const tooltip = self.ensureChartTooltip();
                if (!tooltip || !node.isConnected) return;
                const strong = document.createElement('strong');
                strong.textContent = title;
                tooltip.replaceChildren(strong, document.createElement('br'), document.createTextNode(value));
                tooltip.classList.add('is-visible');
                tooltip.setAttribute('aria-hidden', 'false');
                const source = event.currentTarget.getBoundingClientRect();
                const x = event.clientX || (source.left + source.width / 2);
                const y = event.clientY || source.top;
                tooltip.style.left = Math.max(8, Math.min(x + 12, window.innerWidth - 218)) + 'px';
                tooltip.style.top = Math.max(8, y - 52) + 'px';
            }
            function hide() {
                if (!self.chartTooltip) return;
                self.chartTooltip.classList.remove('is-visible');
                self.chartTooltip.setAttribute('aria-hidden', 'true');
            }
            node.addEventListener('mouseenter', show);
            node.addEventListener('mousemove', show);
            node.addEventListener('mouseleave', hide);
            node.addEventListener('focus', show);
            node.addEventListener('blur', hide);
            node.setAttribute('tabindex', '0');
        },

        initCharts(root) {
            if (!window.Chartist) return;
            const configNode = root.querySelector ? root.querySelector('#dashboard-chart-data') : null;
            if (!configNode) return;
            let data;
            try { data = JSON.parse(configNode.textContent); } catch (_) { return; }
            const baht = new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB', maximumFractionDigits: 0 });
            const compact = new Intl.NumberFormat('th-TH', { notation: 'compact', maximumFractionDigits: 1 });
            const self = this;
            const payrollElement = root.querySelector('#payrollTrendChart');
            if (payrollElement && data.payrollTotals.some(function (value) { return Number(value) > 0; })) {
                payrollElement.innerHTML = '';
                const chart = new Chartist.LineChart(payrollElement, { labels: data.payrollLabels, series: [data.payrollTotals] }, {
                    fullWidth: true, showArea: true, showPoint: true, lineSmooth: true, low: 0,
                    chartPadding: { top: 12, right: 18, bottom: 0, left: 2 },
                    axisY: { onlyInteger: true, labelInterpolationFnc: function (value) { return compact.format(value); } },
                    axisX: { showGrid: false }
                }, [['screen and (max-width: 480px)', { chartPadding: { top: 10, right: 8, bottom: 0, left: 0 }, axisX: { showGrid: false, labelInterpolationFnc: function (value, index) { return index % 2 === 0 ? value : ''; } } }]]);
                payrollElement._appChart = chart;
                chart.on('draw', function (item) { if (item.type === 'point') self.attachChartTooltip(item.element._node, data.payrollLabels[item.index], 'ยอดจ่าย ' + baht.format(item.value.y)); });
            }
            const departmentElement = root.querySelector('#departmentChart');
            if (departmentElement && data.departmentLabels.length) {
                departmentElement.innerHTML = '';
                const labels = data.departmentLabels.slice().reverse();
                const chart = new Chartist.BarChart(departmentElement, { labels: data.departmentLabels, series: [data.departmentCounts] }, {
                    horizontalBars: true, reverseData: true, seriesBarDistance: 12, low: 0,
                    chartPadding: { top: 4, right: 28, bottom: 0, left: 4 },
                    axisX: { onlyInteger: true, allowDecimals: false }, axisY: { offset: 105, showGrid: false }
                }, [['screen and (max-width: 480px)', { chartPadding: { top: 4, right: 18, bottom: 0, left: 0 }, axisY: { offset: 82, showGrid: false } }]]);
                departmentElement._appChart = chart;
                chart.on('draw', function (item) { if (item.type === 'bar') self.attachChartTooltip(item.element._node, labels[item.index], Number(item.value.x) + ' คน'); });
            }
            const statusElement = root.querySelector('#paymentStatusChart');
            if (statusElement && (Number(data.paidEmployees) + Number(data.pendingEmployees)) > 0) {
                statusElement.innerHTML = '';
                const labels = ['จ่ายแล้ว', 'รอจ่าย'];
                const chart = new Chartist.PieChart(statusElement, { series: [data.paidEmployees, data.pendingEmployees] }, { donut: true, donutWidth: 18, showLabel: false, startAngle: 270, chartPadding: 8 });
                statusElement._appChart = chart;
                chart.on('draw', function (item) { if (item.type === 'slice') self.attachChartTooltip(item.element._node, labels[item.index], Number(item.value) + ' คน'); });
            }
        },

        destroyCharts(root) {
            $(root).find('#payrollTrendChart, #departmentChart, #paymentStatusChart').each(function () {
                if (this._appChart && typeof this._appChart.detach === 'function') this._appChart.detach();
                this._appChart = null;
                this.innerHTML = '';
            });
            this.removeChartTooltip();
        }
    };

    window.PayrollApp = App;
    $(function () { App.init(); });
})(window, document, window.jQuery);
