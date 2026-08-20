(function (window, document) {
    'use strict';
    if (window.AttendanceLocationUI) return;

    const UI = {
        assetsPromise: null,
        adminMap: null,
        historyMap: null,
        dirty: false,
        editableLayer: null,
        editHandler: null,
        config: null,

        loadStyle(id, href) {
            if (document.getElementById(id)) return;
            const link = document.createElement('link');
            link.id = id; link.rel = 'stylesheet'; link.href = href;
            document.head.appendChild(link);
        },

        loadScript(id, src) {
            return new Promise(function (resolve, reject) {
                const existing = document.getElementById(id);
                if (existing) {
                    if (existing.dataset.loaded === '1') resolve();
                    else { existing.addEventListener('load', resolve, { once: true }); existing.addEventListener('error', reject, { once: true }); }
                    return;
                }
                const script = document.createElement('script');
                script.id = id; script.src = src;
                script.addEventListener('load', function () { script.dataset.loaded = '1'; resolve(); }, { once: true });
                script.addEventListener('error', reject, { once: true });
                document.head.appendChild(script);
            });
        },

        loadMapAssets() {
            if (window.L && window.L.Draw) return Promise.resolve();
            if (this.assetsPromise) return this.assetsPromise;
            this.loadStyle('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
            this.loadStyle('leaflet-draw-css', 'https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css');
            const self = this;
            this.assetsPromise = (window.L ? Promise.resolve() : this.loadScript('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'))
                .then(function () { return window.L && window.L.Draw ? null : self.loadScript('leaflet-draw-js', 'https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js'); });
            return this.assetsPromise;
        },

        init(root) {
            this.initLocationForms(root);
            if ((root.querySelector && root.querySelector('[data-geofence-settings-page]')) || (root.matches && root.matches('[data-geofence-settings-page]'))) this.initAdminMap(root);
            this.initHistoryLocation(root);
        },

        initLocationForms(root) {
            const forms = root.querySelectorAll ? root.querySelectorAll('[data-attendance-location-form]') : [];
            forms.forEach(function (form) { form.dataset.locationBound = '1'; });
        },

        handleLocationSubmit(event) {
            const form = event.target.closest && event.target.closest('[data-attendance-location-form]');
            if (!form || form.dataset.locationRequired !== '1' || form.dataset.locationReady === '1') {
                if (form) delete form.dataset.locationReady;
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            this.requestAttendanceLocation(form, event.submitter || form.querySelector('button[type="submit"]'));
        },

        setLocationMessage(form, message, type) {
            const node = form.closest('[data-attendance-location-scope]')?.querySelector('[data-location-client-status]');
            if (!node) return;
            node.textContent = message;
            node.classList.toggle('text-red-700', type === 'error');
            node.classList.toggle('text-[#615d59]', type !== 'error');
        },

        requestAttendanceLocation(form, button) {
            const self = this;
            if (!window.isSecureContext) {
                this.setLocationMessage(form, 'ไม่สามารถใช้ GPS ได้ · กรุณาเปิดระบบผ่าน HTTPS แล้วลองใหม่', 'error');
                window.App?.toast.error('ไม่สามารถใช้ GPS ได้ กรุณาเปิดระบบผ่าน HTTPS');
                return;
            }
            if (!navigator.geolocation) {
                this.setLocationMessage(form, 'อุปกรณ์นี้ไม่รองรับการระบุตำแหน่ง กรุณาใช้เบราว์เซอร์ที่รองรับ GPS', 'error');
                window.App?.toast.error('อุปกรณ์นี้ไม่รองรับการระบุตำแหน่ง');
                return;
            }
            if (form.dataset.locating === '1') return;
            form.dataset.locating = '1';
            if (button) {
                button.dataset.locationOriginalHtml = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>กำลังตรวจสอบตำแหน่ง...';
            }
            this.setLocationMessage(form, 'กำลังตรวจสอบว่าคุณอยู่ในพื้นที่ที่อนุญาต', 'normal');
            const locationToastId = window.App?.toast.loading('กำลังตรวจสอบตำแหน่ง...');
            navigator.geolocation.getCurrentPosition(function (position) {
                if (locationToastId) window.App?.toast.dismiss(locationToastId);
                form.querySelector('[name="latitude"]').value = String(position.coords.latitude);
                form.querySelector('[name="longitude"]').value = String(position.coords.longitude);
                form.querySelector('[name="accuracy"]').value = String(position.coords.accuracy);
                form.dataset.locationReady = '1';
                form.dataset.locating = '0';
                self.setLocationMessage(form, 'ได้ตำแหน่งแล้ว · ความแม่นยำ ±' + Math.round(position.coords.accuracy) + ' เมตร · กำลังตรวจสอบพื้นที่', 'normal');
                if (button) { button.disabled = false; button.innerHTML = button.dataset.locationOriginalHtml || button.innerHTML; }
                form.requestSubmit(button || undefined);
            }, function (error) {
                if (locationToastId) window.App?.toast.dismiss(locationToastId);
                form.dataset.locating = '0';
                let message = 'ไม่พบตำแหน่งปัจจุบัน กรุณาเปิด GPS แล้วลองใหม่อีกครั้ง';
                if (error.code === 1) message = 'ไม่สามารถตรวจสอบตำแหน่งได้ · กรุณาอนุญาตการเข้าถึงตำแหน่งเพื่อเช็คชื่อ';
                if (error.code === 3) message = 'ใช้เวลาค้นหาตำแหน่งนานเกินไป กรุณาเปิด GPS แล้วลองใหม่';
                self.setLocationMessage(form, message, 'error');
                window.App?.toast.warning(message);
                if (button) { button.disabled = false; button.innerHTML = button.dataset.locationOriginalHtml || button.innerHTML; }
            }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 });
        },

        parseConfig() {
            const node = document.getElementById('geofenceMapConfig');
            if (!node) return null;
            try { return JSON.parse(node.textContent || '{}'); } catch (_) { return null; }
        },

        initAdminMap(root) {
            const container = (root.querySelector && root.querySelector('#attendanceGeofenceMap')) || document.getElementById('attendanceGeofenceMap');
            if (!container || container.dataset.mapReady === '1') return;
            container.dataset.mapReady = '1';
            const self = this;
            this.config = this.parseConfig();
            this.loadMapAssets().then(function () { self.buildAdminMap(container); }).catch(function () {
                const error = document.querySelector('[data-geofence-map-error]');
                if (error) { error.classList.remove('hidden'); error.textContent = 'โหลดแผนที่ไม่สำเร็จ กรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ตแล้วลองใหม่'; }
            });
        },

        buildAdminMap(container) {
            if (!window.L || !this.config) return;
            if (this.adminMap) { try { this.adminMap.remove(); } catch (_) {} }
            const center = this.config.default_center || { lat: 13.7563, lng: 100.5018 };
            const map = window.L.map(container, { zoomControl: true }).setView([center.lat, center.lng], Number(this.config.default_zoom) || 6);
            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
            this.adminMap = map;
            this.displayGroup = window.L.featureGroup().addTo(map);
            this.editableGroup = window.L.featureGroup().addTo(map);
            this.renderDisplayPolygons();
            if ((this.config.geofences || []).length) map.fitBounds(this.displayGroup.getBounds(), { padding: [24, 24], maxZoom: 18 });
            this.bindAdminMapEvents();
            window.setTimeout(function () { map.invalidateSize(); }, 100);
        },

        polygonStyle(record) {
            return { color: record.is_active ? '#0075de' : '#8a8580', weight: 2, fillColor: record.is_active ? '#0075de' : '#8a8580', fillOpacity: record.is_active ? 0.16 : 0.08 };
        },

        renderDisplayPolygons(excludeId) {
            if (!this.displayGroup) return;
            this.displayGroup.clearLayers();
            const self = this;
            (this.config.geofences || []).forEach(function (record) {
                if (Number(record.id) === Number(excludeId) || !Array.isArray(record.points) || record.points.length < 3) return;
                const layer = window.L.polygon(record.points.map(function (point) { return [point.lat, point.lng]; }), self.polygonStyle(record));
                layer.bindTooltip(record.name, { sticky: true });
                layer._geofenceId = record.id;
                self.displayGroup.addLayer(layer);
            });
        },

        recordById(id) {
            return (this.config.geofences || []).find(function (record) { return Number(record.id) === Number(id); });
        },

        fitRecord(id) {
            const record = this.recordById(id);
            if (!record || !this.adminMap || !record.points.length) return;
            this.adminMap.fitBounds(window.L.latLngBounds(record.points.map(function (point) { return [point.lat, point.lng]; })), { padding: [28, 28], maxZoom: 19 });
        },

        bindAdminMapEvents() {
            const self = this;
            this.adminMap.on(window.L.Draw.Event.CREATED, function (event) {
                self.stopEditing();
                self.editableGroup.clearLayers();
                self.editableLayer = event.layer;
                self.editableGroup.addLayer(event.layer);
                self.openEditor(null);
                self.syncPolygonField();
                self.enableLayerEditing();
                self.setDirty(true);
            });
            this.adminMap.on('draw:edited draw:editvertex', function () { self.syncPolygonField(); self.setDirty(true); });

            document.querySelectorAll('[data-geofence-add]').forEach(function (button) { button.addEventListener('click', function () { self.withDiscardConfirmation(function () {
                self.cancelEdit(false);
                const drawer = new window.L.Draw.Polygon(self.adminMap, { allowIntersection: false, showArea: true, shapeOptions: self.polygonStyle({ is_active: true }) });
                drawer.enable();
            }); }); });
            document.querySelectorAll('[data-geofence-focus]').forEach(function (button) { button.addEventListener('click', function () { self.fitRecord(button.dataset.geofenceFocus); }); });
            document.querySelectorAll('[data-geofence-edit]').forEach(function (button) { button.addEventListener('click', function () { self.withDiscardConfirmation(function () { self.startEdit(button.dataset.geofenceEdit); }); }); });
            document.querySelectorAll('[data-geofence-cancel]').forEach(function (button) { button.addEventListener('click', function () { self.cancelEdit(true); }); });
            document.querySelectorAll('[data-geofence-save-trigger]').forEach(function (button) { button.addEventListener('click', function () { document.getElementById('geofenceEditorForm')?.requestSubmit(); }); });
            document.querySelectorAll('[data-geofence-current-location]').forEach(function (button) { button.addEventListener('click', function () { self.centerCurrentLocation(button); }); });
            document.querySelectorAll('[data-geofence-delete]').forEach(function (button) { button.addEventListener('click', function () { self.openDeleteModal(button.dataset.geofenceDelete, button.dataset.geofenceName); }); });
            document.querySelectorAll('[data-geofence-delete-close]').forEach(function (button) { button.addEventListener('click', function () { self.closeDeleteModal(); }); });
            document.querySelectorAll('[data-geofence-scope]').forEach(function (select) { select.addEventListener('change', function () { self.updateScopeField(); self.setDirty(true); }); });
            document.getElementById('geofenceEditorForm')?.addEventListener('input', function () { self.setDirty(true); });
            document.getElementById('geofenceEditorForm')?.addEventListener('submit', function () { self.syncPolygonField(); self.setDirty(false); }, true);
        },

        startEdit(id) {
            const record = this.recordById(id);
            if (!record) return;
            this.stopEditing(); this.editableGroup.clearLayers(); this.renderDisplayPolygons(record.id);
            this.editableLayer = window.L.polygon(record.points.map(function (point) { return [point.lat, point.lng]; }), this.polygonStyle(record));
            this.editableGroup.addLayer(this.editableLayer);
            this.openEditor(record); this.syncPolygonField(); this.enableLayerEditing(); this.setDirty(false);
            this.adminMap.fitBounds(this.editableLayer.getBounds(), { padding: [28, 28], maxZoom: 19 });
        },

        enableLayerEditing() {
            if (!this.editableLayer) return;
            this.stopEditing();
            this.editHandler = new window.L.EditToolbar.Edit(this.adminMap, { featureGroup: this.editableGroup, selectedPathOptions: { maintainColor: true, opacity: 0.9 } });
            this.editHandler.enable();
        },

        stopEditing() {
            if (this.editHandler) { try { this.editHandler.disable(); } catch (_) {} this.editHandler = null; }
        },

        openEditor(record) {
            const editor = document.getElementById('geofenceEditor');
            const form = document.getElementById('geofenceEditorForm');
            if (!editor || !form) return;
            editor.classList.remove('hidden');
            form.querySelector('[data-geofence-field="id"]').value = record ? record.id : '';
            form.elements.name.value = record ? record.name : '';
            form.elements.description.value = record ? (record.description || '') : '';
            form.elements.scope_type.value = record ? record.scope_type : 'all';
            form.elements.department_id.value = record && record.department_id ? record.department_id : '';
            form.querySelector('[data-geofence-field="active"]').checked = record ? record.is_active : true;
            this.updateScopeField();
            editor.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        },

        updateScopeField() {
            const form = document.getElementById('geofenceEditorForm');
            if (!form) return;
            const departmental = form.elements.scope_type.value === 'department';
            document.querySelector('[data-geofence-department-wrap]')?.classList.toggle('hidden', !departmental);
            form.elements.department_id.required = departmental;
        },

        syncPolygonField() {
            if (!this.editableLayer) return;
            const latlngs = this.editableLayer.getLatLngs()[0] || [];
            const points = latlngs.map(function (point) { return { lat: Number(point.lat.toFixed(7)), lng: Number(point.lng.toFixed(7)) }; });
            const input = document.querySelector('[data-geofence-field="polygon"]');
            if (input) input.value = JSON.stringify(points);
        },

        setDirty(dirty) {
            this.dirty = !!dirty;
            document.querySelector('[data-geofence-unsaved]')?.classList.toggle('hidden', !this.dirty);
        },

        withDiscardConfirmation(callback) {
            if (!this.dirty) { callback(); return; }
            if (window.App?.confirm) window.App.confirm('มีการแก้ไขพื้นที่ที่ยังไม่ได้บันทึก ต้องการยกเลิกการแก้ไขหรือไม่?', callback, { title: 'ยกเลิกการแก้ไข?', confirmLabel: 'ยกเลิกการแก้ไข' });
            else window.App?.toast.warning('มีข้อมูลพื้นที่ที่ยังไม่ได้บันทึก');
        },

        cancelEdit(requireConfirm) {
            if (requireConfirm && this.dirty) { const self = this; this.withDiscardConfirmation(function () { self.cancelEdit(false); }); return; }
            this.stopEditing();
            if (this.editableGroup) this.editableGroup.clearLayers();
            this.editableLayer = null;
            this.renderDisplayPolygons();
            document.getElementById('geofenceEditor')?.classList.add('hidden');
            this.setDirty(false);
        },

        centerCurrentLocation(button) {
            const self = this;
            if (!navigator.geolocation) { window.App?.toast.error('อุปกรณ์นี้ไม่รองรับการระบุตำแหน่ง'); return; }
            const original = button.innerHTML; button.disabled = true; button.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i>กำลังค้นหา...';
            const toastId = window.App?.toast.loading('กำลังอ่านตำแหน่งปัจจุบัน...');
            navigator.geolocation.getCurrentPosition(function (position) {
                if (toastId) window.App?.toast.dismiss(toastId);
                self.adminMap.setView([position.coords.latitude, position.coords.longitude], 18);
                window.L.circleMarker([position.coords.latitude, position.coords.longitude], { radius: 6, color: '#0075de', fillOpacity: 0.9 }).addTo(self.adminMap).bindTooltip('ตำแหน่งปัจจุบัน').openTooltip();
                button.disabled = false; button.innerHTML = original;
            }, function () { if (toastId) window.App?.toast.dismiss(toastId); button.disabled = false; button.innerHTML = original; window.App?.toast.warning('ไม่สามารถอ่านตำแหน่งปัจจุบันได้ กรุณาอนุญาต GPS แล้วลองใหม่'); }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
        },

        openDeleteModal(id, name) {
            const modal = document.getElementById('geofenceDeleteModal');
            if (!modal) return;
            modal.querySelector('[data-geofence-delete-id]').value = id;
            modal.querySelector('[data-geofence-delete-name]').textContent = name;
            modal.classList.remove('hidden'); modal.classList.add('flex');
        },

        closeDeleteModal() {
            const modal = document.getElementById('geofenceDeleteModal');
            if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
        },

        initHistoryLocation(root) {
            const modal = document.getElementById('attendanceLocationModal');
            if (!modal || modal.dataset.bound === '1') return;
            modal.dataset.bound = '1';
            const self = this;
            document.querySelectorAll('[data-location-view]').forEach(function (button) { button.addEventListener('click', function () { self.openHistoryLocation(button); }); });
            modal.querySelectorAll('[data-location-modal-close]').forEach(function (button) { button.addEventListener('click', function () { self.closeHistoryLocation(); }); });
        },

        openHistoryLocation(button) {
            const modal = document.getElementById('attendanceLocationModal');
            if (!modal) return;
            modal.classList.remove('hidden'); modal.classList.add('flex');
            modal.querySelector('[data-location-modal-name]').textContent = button.dataset.locationName || 'ตำแหน่งเช็คชื่อ';
            modal.querySelector('[data-location-modal-accuracy]').textContent = 'ความแม่นยำ ±' + Math.round(Number(button.dataset.locationAccuracy) || 0) + ' เมตร';
            const lat = Number(button.dataset.locationLat), lng = Number(button.dataset.locationLng), self = this;
            this.loadMapAssets().then(function () {
                if (self.historyMap) { try { self.historyMap.remove(); } catch (_) {} }
                const map = window.L.map('attendanceLocationMap', { zoomControl: true }).setView([lat, lng], 18);
                window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
                window.L.marker([lat, lng]).addTo(map);
                self.historyMap = map;
                window.setTimeout(function () { map.invalidateSize(); }, 80);
            });
        },

        closeHistoryLocation() {
            const modal = document.getElementById('attendanceLocationModal');
            if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
            if (this.historyMap) { try { this.historyMap.remove(); } catch (_) {} this.historyMap = null; }
        },

        destroy(root) {
            if (root && root.querySelector && root.querySelector('#attendanceGeofenceMap') && this.adminMap) { try { this.adminMap.remove(); } catch (_) {} this.adminMap = null; this.setDirty(false); }
            if (root && root.querySelector && root.querySelector('#attendanceLocationMap')) this.closeHistoryLocation();
        }
    };

    window.AttendanceLocationUI = UI;
    document.addEventListener('submit', function (event) { UI.handleLocationSubmit(event); }, true);
    window.addEventListener('beforeunload', function (event) { if (UI.dirty) { event.preventDefault(); event.returnValue = ''; } });
    document.addEventListener('click', function (event) {
        if (!UI.dirty) return;
        const link = event.target.closest('a[href]');
        if (link && !link.closest('[data-geofence-settings-page]') && !UI.confirmDiscard()) { event.preventDefault(); event.stopImmediatePropagation(); }
    }, true);
    document.addEventListener('DOMContentLoaded', function () { UI.init(document); });
    document.body.addEventListener('htmx:beforeSwap', function (event) { if (event.detail.target) UI.destroy(event.detail.target); });
    document.body.addEventListener('htmx:afterSwap', function (event) { if (event.detail.target) UI.init(event.detail.target); });
    // HX-Location can settle a newly selected fragment after the swap event's
    // original target has been detached. Re-scan the live document once HTMX
    // finishes settling; data-map-ready keeps this idempotent.
    document.body.addEventListener('htmx:afterSettle', function () { UI.init(document); });
    document.body.addEventListener('htmx:load', function (event) { UI.init(event.detail && event.detail.elt ? event.detail.elt : document); });
})(window, document);
