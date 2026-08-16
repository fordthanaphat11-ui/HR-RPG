(function (window, document, $) {
    'use strict';

    if (!$) return;

    const App = {
        loadingTimer: null,
        finishTimer: null,
        chartTooltip: null,
        employeePickerTrigger: null,

        init() {
            this.bindGlobalEvents();
            this.initPage(document);
            this.updateChrome();
        },

        bindGlobalEvents() {
            const self = this;

            $(document)
                .off('.payrollApp')
                .on('click.payrollApp', '#openMobileSidebar', function () { self.setMobileSidebar(true); })
                .on('click.payrollApp', '#closeMobileSidebar, #mobileSidebarBackdrop', function () { self.setMobileSidebar(false); })
                .on('keydown.payrollApp', function (event) {
                    if (event.key === 'Escape') {
                        if (self.isPaymentConfirmationOpen()) {
                            self.closePaymentConfirmation();
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
                .on('input.payrollApp change.payrollApp', '[data-setting-input]', function () { self.updatePayrollSettingsPreview(); })
                .on('click.payrollApp', '.js-delete-link', function (event) {
                    event.preventDefault();
                    $('#confirmDeleteLink').attr('href', this.href);
                    $('#deleteModalRecord').text($(this).data('record') || 'ข้อมูลที่เลือก');
                    $('#deleteConfirmModal').removeClass('hidden').addClass('flex');
                    self.syncBodyScrollLock();
                    $('#deleteConfirmModal [data-close-delete-modal]').last().trigger('focus');
                })
                .on('click.payrollApp', '[data-close-delete-modal]', function () { self.closeDeleteModal(); })
                .on('click.payrollApp', '#routeErrorClose', function () { self.hideRouteError(); })
                .on('click.payrollApp', '#routeReloadButton', function () { window.location.reload(); })
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
                .on('click.payrollApp', function () {
                    $('.dt-menu').prop('hidden', true);
                    $('.dt-dropdown > .dt-tool-button').attr('aria-expanded', 'false');
                });

            document.body.addEventListener('htmx:beforeRequest', function () {
                self.setMobileSidebar(false);
                self.closeEmployeePicker(false);
                self.closePaymentConfirmation(false);
                self.hideRouteError();
                self.startLoading();
            });
            document.body.addEventListener('htmx:beforeHistorySave', function () {
                self.cleanupPage(document.querySelector('#app-content'));
            });
            document.body.addEventListener('htmx:beforeSwap', function (event) {
                const status = event.detail.xhr ? event.detail.xhr.status : 0;
                if (event.detail.target && event.detail.target.id === 'attendance-workspace') self.cleanupPage(event.detail.target);
                if (status === 404) {
                    event.detail.shouldSwap = true;
                    event.detail.isError = false;
                }
            });
            document.body.addEventListener('htmx:afterSwap', function (event) {
                if (event.detail.target && event.detail.target.id === 'app-content') {
                    self.initPage(document.querySelector('#app-content'));
                    self.updateChrome();
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
                self.showRouteError(status ? 'เซิร์ฟเวอร์ตอบกลับด้วยรหัส ' + status : 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
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
            this.initDataTables(root);
            this.initCharts(root);
            this.initEmployeePicker(root);
            this.initPayrollPayment(root);
            this.initPayrollSettings(root);
            this.initAttendance(root);
            if (window.htmx) window.htmx.process(root);
        },

        cleanupPage(root) {
            if (!root) return;
            this.destroyDataTables(root);
            this.destroyCharts(root);
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
            $('body').toggleClass('overflow-hidden', sidebarOpen || deleteOpen || this.isEmployeePickerOpen() || this.isPaymentConfirmationOpen());
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
            if (selectedId) {
                const $selectedItem = $('[data-employee-picker-item]').filter(function () { return String($(this).attr('data-employee-id')) === selectedId; }).first();
                selectedPaid = $selectedItem.length ? this.employeeIsPaid($selectedItem) : false;
            }
            $('[data-selected-paid-warning]').toggleClass('hidden', !selectedPaid).toggleClass('flex', selectedPaid);
            $('[data-payment-submit]').prop('disabled', !selectedId || selectedPaid);
            $('[data-payment-submit-label]').text(selectedPaid ? 'จ่ายแล้วสำหรับงวดนี้' : 'ตรวจสอบและยืนยัน');

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
            let visible = 0;

            $items.each(function () {
                const $item = $(this);
                const paid = self.employeeIsPaid($item);
                const matchesSearch = !keyword || String($item.attr('data-employee-search') || '').includes(keyword);
                const matchesDepartment = !department || String($item.attr('data-employee-department-id')) === department;
                const matchesStatus = !status || (status === 'paid' ? paid : !paid);
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
            const absenceRate = $calculator.attr('data-absence-enabled') === '1' ? Math.max(0, Number($calculator.attr('data-absence-rate')) || 0) : 0;
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
            const state = { base: base, additions: additions, deductions: deductions, net: net };
            $calculator.data('calculation', state);

            $('[data-auto-medical]').text(this.money(medical));
            $('[data-auto-housing]').text(this.money(housing));
            $('[data-auto-overtime]').text(this.money(overtime));
            $('[data-auto-loan]').text('-' + this.money(loanCut));
            $('[data-auto-fund]').text('-' + this.money(fundCut));
            $('[data-auto-absence]').text('-' + this.money(absence));
            $('[data-auto-late]').text('-' + this.money(late));
            $('[data-absence-formula]').text(absenceDays === null ? 'ขาดงาน: ไม่มีข้อมูลสำหรับงวดนี้' : (absenceDays + ' วัน × ' + this.money(absenceRate) + '/วัน'));
            $('[data-late-formula]').text(lateFormula);
            $('[data-summary-base]').text(this.money(base));
            $('[data-summary-additions]').text('+' + this.money(additions));
            $('[data-summary-deductions]').text('-' + this.money(deductions));
            $('[data-summary-net]').text(this.money(net));
            $('[data-negative-net]').toggleClass('hidden', net >= 0);
            const selected = Boolean($('#selected_employee_id').val());
            const selectedPaid = !$('[data-selected-paid-warning]').hasClass('hidden');
            $('[data-payment-submit]').prop('disabled', !selected || selectedPaid || net < 0);
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
            if (!state || state.net < 0 || !$('#selected_employee_id').val()) return;
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
            if (!$(root).find('[data-payroll-settings]').addBack('[data-payroll-settings]').length) return;
            this.updatePayrollSettingsPreview();
        },

        updatePayrollSettingsPreview() {
            if (!$('[data-payroll-settings]').length) return;
            const mode = String($('input[name="late_mode"]:checked').val() || 'none');
            $('[data-late-occurrence-fields]').toggleClass('hidden', mode !== 'per_occurrence');
            $('[data-late-minute-fields]').toggleClass('hidden', mode !== 'per_minutes');
            const absence = $('input[name="absence_enabled"]').is(':checked') ? 2 * (Number($('input[name="absence_rate"]').val()) || 0) : 0;
            let late = 0;
            let formula = 'ปิดการหักเงินกรณีมาสาย';
            if (mode === 'per_occurrence') {
                const rate = Number($('input[name="late_occurrence_rate"]').val()) || 0;
                late = 3 * rate;
                formula = '3 ครั้ง × ' + this.money(rate) + '/ครั้ง';
            } else if (mode === 'per_minutes') {
                const interval = Math.max(1, Number($('input[name="late_interval_minutes"]').val()) || 1);
                const rate = Number($('input[name="late_interval_rate"]').val()) || 0;
                const grace = 0;
                const raw = 75 / interval;
                const count = $('select[name="late_rounding"]').val() === 'floor' ? Math.floor(raw) : Math.ceil(raw);
                late = count * rate;
                formula = 'มาสายจากระบบลงเวลา 75 นาที; ' + count + ' รอบ × ' + this.money(rate);
            }
            const maximum = String($('input[name="max_late_deduction"]').val() || '').trim();
            if (maximum !== '') late = Math.min(late, Math.max(0, Number(maximum) || 0));
            $('[data-settings-absence-preview]').text('-' + this.money(absence));
            $('[data-settings-late-preview]').text('-' + this.money(late));
            $('[data-settings-net-preview]').text(this.money(20000 - absence - late));
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
            $('#routeErrorMessage').text(message || 'กรุณาลองใหม่อีกครั้ง');
            $('#routeError').removeClass('hidden');
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
            $copy.find('thead th, tfoot th').filter(function () { return self.plainText(this.innerHTML) === 'จัดการ'; }).each(function () {
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
            if (this.chartTooltip && document.body.contains(this.chartTooltip)) return this.chartTooltip;
            const tooltip = document.createElement('div');
            tooltip.className = 'dashboard-chart-tooltip';
            tooltip.setAttribute('role', 'tooltip');
            document.body.appendChild(tooltip);
            this.chartTooltip = tooltip;
            return tooltip;
        },

        attachChartTooltip(node, title, value) {
            const self = this;
            function show(event) {
                const tooltip = self.ensureChartTooltip();
                const strong = document.createElement('strong');
                strong.textContent = title;
                tooltip.replaceChildren(strong, document.createElement('br'), document.createTextNode(value));
                tooltip.classList.add('is-visible');
                const source = event.currentTarget.getBoundingClientRect();
                const x = event.clientX || (source.left + source.width / 2);
                const y = event.clientY || source.top;
                tooltip.style.left = Math.max(8, Math.min(x + 12, window.innerWidth - 218)) + 'px';
                tooltip.style.top = Math.max(8, y - 52) + 'px';
            }
            function hide() { if (self.chartTooltip) self.chartTooltip.classList.remove('is-visible'); }
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
            if (this.chartTooltip) this.chartTooltip.classList.remove('is-visible');
        }
    };

    window.PayrollApp = App;
    $(function () { App.init(); });
})(window, document, window.jQuery);
