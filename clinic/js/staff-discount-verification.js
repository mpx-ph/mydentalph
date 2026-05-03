/**
 * Staff Discount Verification — database-backed (see clinic/api/discount_*.php).
 */
(function () {
    var CFG = window.STAFF_DISCOUNT_V || {};
    var programsCache = [];
    var recordsCache = [];
    var approversCache = [];

    function apiJson(url, options) {
        options = options || {};
        options.credentials = 'same-origin';
        if (!options.headers) options.headers = {};
        if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }
        return fetch(url, options).then(function (res) {
            return res.json();
        });
    }

    function snapshotRequirements(prog) {
        if (!prog) return { reqUploadProof: false, reqNotes: false };
        return {
            reqUploadProof: !!prog.reqUploadProof,
            reqNotes: !!prog.reqNotes
        };
    }

    function maskId(num) {
        if (!num || String(num).length < 5) return '••••';
        var s = String(num);
        return s.slice(0, 3) + '••••' + s.slice(-2);
    }

    function requirementsSummaryFromFlags(upload, notes) {
        var parts = [];
        if (upload) parts.push('Upload proof');
        if (notes) parts.push('Notes');
        return parts.length ? parts.join(' · ') : 'No requirements set';
    }

    function requirementsSummaryProgram(p) {
        if (!p) return '—';
        return requirementsSummaryFromFlags(!!p.reqUploadProof, !!p.reqNotes);
    }

    function requirementsSummaryRecord(r) {
        if (!r) return '—';
        return requirementsSummaryFromFlags(!!r.reqUploadProof, !!r.reqNotes);
    }

    function effectiveStatus(r) {
        if (!r) return 'pending';
        if (r.status !== 'pending') return r.status;
        var vto = r.programValidTo || '';
        if (vto) {
            var end = new Date(vto + 'T23:59:59');
            if (new Date() > end) return 'expired';
        }
        return 'pending';
    }

    function formatDiscountSummary(prog) {
        if (!prog) return '—';
        var base;
        if (prog.discountType === 'percentage') {
            base = prog.name + ' (' + prog.value + '%)';
        } else {
            base = prog.name + ' (₱' + Number(prog.value).toLocaleString() + ' off)';
        }
        var min = typeof prog.minSpend === 'number' && prog.minSpend > 0 ? prog.minSpend : 0;
        if (min > 0) base += ' · Min. spend ₱' + min.toLocaleString();
        return base;
    }

    function openOverlay(el) {
        el.classList.remove('hidden');
        el.classList.add('flex');
        el.setAttribute('aria-hidden', 'false');
    }
    function closeOverlay(el) {
        el.classList.add('hidden');
        el.classList.remove('flex');
        el.setAttribute('aria-hidden', 'true');
    }

    function buildVerificationQuery() {
        var p = new URLSearchParams();
        var req = document.getElementById('filterRequirements');
        var st = document.getElementById('filterStatus');
        var df = document.getElementById('filterDateFrom');
        var dt = document.getElementById('filterDateTo');
        var staff = document.getElementById('filterStaff');
        var pat = document.getElementById('filterPatient');
        if (req && req.value !== 'all') p.set('requirements', req.value);
        if (st && st.value !== 'all') p.set('status', st.value);
        if (df && df.value) p.set('date_from', df.value);
        if (dt && dt.value) p.set('date_to', dt.value);
        if (staff && staff.value !== 'all') p.set('approved_by', staff.value);
        if (pat && pat.value.trim()) p.set('patient', pat.value.trim());
        var qs = p.toString();
        return qs ? ('?' + qs) : '';
    }

    function loadPrograms() {
        return apiJson(CFG.programsApi).then(function (data) {
            if (!data.success || !data.data || !Array.isArray(data.data.programs)) {
                programsCache = [];
                return;
            }
            programsCache = data.data.programs;
        }).catch(function () {
            programsCache = [];
        });
    }

    function loadVerifications() {
        var q = buildVerificationQuery();
        return apiJson(CFG.verificationsApi + q).then(function (data) {
            if (!data.success || !data.data) {
                recordsCache = [];
                approversCache = [];
                return;
            }
            recordsCache = Array.isArray(data.data.verifications) ? data.data.verifications : [];
            approversCache = Array.isArray(data.data.approvers) ? data.data.approvers : [];
        }).catch(function () {
            recordsCache = [];
            approversCache = [];
        });
    }

    var clinicServices = [];

    function fetchServices() {
        apiJson(CFG.servicesApi + '?limit=500')
            .then(function (data) {
                var payload = data && data.data ? data.data : {};
                var list = Array.isArray(payload.services) ? payload.services : [];
                clinicServices = list.map(function (s) {
                    var sid = s.service_id != null ? s.service_id : (s.id != null ? s.id : '');
                    return { id: String(sid), name: s.service_name || s.name || 'Service' };
                });
                renderProgramServiceCheckboxes();
            })
            .catch(function () {
                clinicServices = [
                    { id: '1', name: 'Oral prophylaxis' },
                    { id: '2', name: 'Tooth extraction' }
                ];
                renderProgramServiceCheckboxes();
            });
    }

    function renderProgramServiceCheckboxes() {
        var box = document.getElementById('programServicesList');
        if (!box) return;
        box.innerHTML = '';
        clinicServices.forEach(function (s) {
            var lab = document.createElement('label');
            lab.className = 'flex items-center gap-2 text-sm text-slate-700';
            lab.innerHTML = '<input type="checkbox" class="svc-cb rounded border-slate-300 text-primary focus:ring-primary" data-id="' + String(s.id).replace(/"/g, '&quot;') + '"/> <span>' + String(s.name).replace(/</g, '&lt;') + '</span>';
            box.appendChild(lab);
        });
    }

    function setProgramServiceChecks(ids) {
        var set = {};
        (ids || []).forEach(function (id) { set[id] = true; });
        document.querySelectorAll('#programServicesList .svc-cb').forEach(function (cb) {
            cb.checked = !!set[cb.getAttribute('data-id')];
        });
    }

    function getCheckedServiceIds() {
        var ids = [];
        document.querySelectorAll('#programServicesList .svc-cb:checked').forEach(function (cb) {
            ids.push(cb.getAttribute('data-id'));
        });
        return ids;
    }

    function renderPrograms() {
        var grid = document.getElementById('programsGrid');
        if (!grid) return;
        var programs = programsCache;
        if (!programs.length) {
            grid.innerHTML = '<p class="text-slate-500 col-span-full py-8 text-center text-sm">No discount programs yet. Click “New discount program” to add one.</p>';
            return;
        }
        grid.innerHTML = programs.map(function (p) {
            var statusCls = p.enabled ? 'bg-emerald-50 text-emerald-800 border-emerald-100' : 'bg-slate-100 text-slate-600 border-slate-200';
            var stackTxt = p.stacking === 'yes' ? 'May stack' : 'No stacking';
            var scopeTxt = p.serviceScope === 'all' ? 'All services' : 'Selected procedures';
            return (
                '<article class="elevated-card rounded-2xl p-6 flex flex-col gap-4 border border-slate-100">' +
                '<div class="flex items-start justify-between gap-3">' +
                '<div class="min-w-0">' +
                '<h4 class="font-headline font-bold text-lg text-slate-900 leading-tight">' + String(p.name).replace(/</g, '&lt;') + '</h4>' +
                '<p class="text-xs font-bold text-primary mt-1 uppercase tracking-wide">' + requirementsSummaryProgram(p).replace(/</g, '&lt;') + '</p>' +
                '</div>' +
                '<span class="shrink-0 text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-lg border ' + statusCls + '">' + (p.enabled ? 'Enabled' : 'Disabled') + '</span>' +
                '</div>' +
                '<p class="text-sm font-semibold text-slate-700">' + formatDiscountSummary(p).replace(/</g, '&lt;') + '</p>' +
                '<p class="text-xs text-slate-500">' + (p.validFrom || '—') + ' → ' + (p.validTo || '—') + '</p>' +
                '<div class="flex flex-wrap gap-2 text-[11px] font-bold text-slate-600">' +
                '<span class="px-2 py-1 rounded-md bg-slate-50 border border-slate-100">' + scopeTxt + '</span>' +
                '<span class="px-2 py-1 rounded-md bg-slate-50 border border-slate-100">' + stackTxt + '</span>' +
                '</div>' +
                '<div class="flex items-center justify-between pt-2 mt-auto border-t border-slate-100">' +
                '<label class="relative inline-flex cursor-pointer items-center">' +
                '<input type="checkbox" class="toggle-quick sr-only peer" data-id="' + p.id + '" ' + (p.enabled ? 'checked' : '') + '/>' +
                '<span class="h-7 w-12 rounded-full bg-slate-300 transition-colors peer-checked:bg-primary peer-focus:ring-2 peer-focus:ring-primary/30 relative">' +
                '<span class="absolute top-0.5 left-0.5 h-6 w-6 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></span>' +
                '</span>' +
                '<span class="ml-2 text-xs font-bold text-slate-600">Quick enable</span>' +
                '</label>' +
                '<div class="flex gap-2">' +
                '<button type="button" class="edit-program px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-50" data-id="' + p.id + '">Edit</button>' +
                '</div>' +
                '</div>' +
                '</article>'
            );
        }).join('');

        grid.querySelectorAll('.toggle-quick').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var id = cb.getAttribute('data-id');
                var prog = programsCache.find(function (x) { return String(x.id) === String(id); });
                if (!prog) return;
                var next = Object.assign({}, prog, { enabled: cb.checked });
                apiJson(CFG.programsApi, { method: 'PUT', body: next }).then(function (data) {
                    if (!data.success) {
                        alert(data.message || 'Could not update program.');
                        cb.checked = !cb.checked;
                        return;
                    }
                    if (data.data && data.data.program) {
                        var ix = programsCache.findIndex(function (x) { return String(x.id) === String(id); });
                        if (ix >= 0) programsCache[ix] = data.data.program;
                    }
                    populateApplicationPrograms();
                }).catch(function () {
                    alert('Network error.');
                    cb.checked = !cb.checked;
                });
            });
        });
        grid.querySelectorAll('.edit-program').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openProgramModal(btn.getAttribute('data-id'));
            });
        });
    }

    function updateAppFormForProgram() {
        var sel = document.getElementById('appDiscountProgram');
        var hint = document.getElementById('appReqHint');
        var proofWrap = document.getElementById('appProofWrap');
        var notesWrap = document.getElementById('appNotesWrap');
        var proofTag = document.getElementById('appProofRequiredTag');
        var proofInput = document.getElementById('appProofFile');
        var notesTa = document.getElementById('appNotes');
        if (!sel || !hint || !proofWrap || !notesWrap || !proofInput || !notesTa) return;
        var pid = sel.value;
        var prog = programsCache.find(function (x) { return String(x.id) === String(pid); });
        if (!prog) {
            hint.classList.add('hidden');
            return;
        }
        var minLine = (typeof prog.minSpend === 'number' && prog.minSpend > 0)
            ? ' Min. spend ₱' + prog.minSpend.toLocaleString() + '.'
            : '';
        hint.textContent = 'Requires: ' + requirementsSummaryProgram(prog) + '.' + minLine;
        hint.classList.remove('hidden');
        proofWrap.classList.toggle('hidden', !prog.reqUploadProof);
        notesWrap.classList.toggle('hidden', !prog.reqNotes);
        if (proofTag) proofTag.classList.toggle('hidden', !prog.reqUploadProof);
        if (prog.reqNotes) notesTa.setAttribute('required', 'required'); else notesTa.removeAttribute('required');
        if (prog.reqUploadProof) proofInput.setAttribute('required', 'required'); else proofInput.removeAttribute('required');
    }

    function populateApplicationPrograms() {
        var sel = document.getElementById('appDiscountProgram');
        if (!sel) return;
        var programs = programsCache.filter(function (p) { return p.enabled; });
        sel.innerHTML = programs.map(function (p) {
            return '<option value="' + String(p.id).replace(/"/g, '&quot;') + '">' + String(p.name).replace(/</g, '&lt;') + '</option>';
        }).join('');
        if (!programs.length) {
            sel.innerHTML = '<option value="">No enabled programs — add one first</option>';
        }
        updateAppFormForProgram();
    }

    function updateProgramValueLabel() {
        var t = document.getElementById('programDiscountType');
        var lbl = document.getElementById('programValueLabel');
        if (!t || !lbl) return;
        lbl.textContent = t.value === 'percentage' ? 'Percentage (%)' : 'Fixed amount (₱)';
    }

    function openProgramModal(editId) {
        document.getElementById('programModalTitle').textContent = editId ? 'Edit discount program' : 'New discount program';
        document.getElementById('programId').value = editId || '';
        if (editId) {
            var p = programsCache.find(function (x) { return String(x.id) === String(editId); });
            if (!p) return;
            document.getElementById('programName').value = p.name;
            document.getElementById('programDiscountType').value = p.discountType;
            document.getElementById('programValue').value = p.value;
            document.getElementById('programMinSpend').value = typeof p.minSpend === 'number' && p.minSpend > 0 ? p.minSpend : '';
            document.getElementById('reqUploadProof').checked = !!p.reqUploadProof;
            document.getElementById('reqNotes').checked = !!p.reqNotes;
            document.getElementById('programEnabled').checked = !!p.enabled;
            document.getElementById('programStart').value = p.validFrom || '';
            document.getElementById('programEnd').value = p.validTo || '';
            document.getElementById('programStacking').value = p.stacking || 'no';
            document.querySelector('input[name="programScope"][value="' + (p.serviceScope || 'all') + '"]').checked = true;
            setProgramServiceChecks(p.serviceIds || []);
        } else {
            document.getElementById('programForm').reset();
            document.getElementById('programId').value = '';
            document.getElementById('programEnabled').checked = true;
            document.getElementById('reqUploadProof').checked = true;
            document.getElementById('reqNotes').checked = false;
            document.getElementById('programMinSpend').value = '';
            document.querySelector('input[name="programScope"][value="all"]').checked = true;
            setProgramServiceChecks([]);
        }
        updateProgramValueLabel();
        document.getElementById('programServicesWrap').classList.toggle('hidden', document.querySelector('input[name="programScope"]:checked').value !== 'selected');
        document.getElementById('programEnabledLabel').textContent = document.getElementById('programEnabled').checked ? 'Enabled' : 'Disabled';
        openOverlay(document.getElementById('programModal'));
    }

    function refreshStaffFilterOptions() {
        var staffSel = document.getElementById('filterStaff');
        if (!staffSel) return;
        var keep = staffSel.value;
        var opts = '<option value="all">Any staff</option>';
        approversCache.forEach(function (a) {
            opts += '<option value="' + String(a.user_id).replace(/"/g, '&quot;') + '">' + String(a.full_name).replace(/</g, '&lt;') + '</option>';
        });
        staffSel.innerHTML = opts;
        if (keep && Array.prototype.some.call(staffSel.options, function (o) { return o.value === keep; })) {
            staffSel.value = keep;
        }
    }

    function renderHistory() {
        var records = recordsCache;
        var fReq = document.getElementById('filterRequirements').value;
        var fStat = document.getElementById('filterStatus').value;
        var fStaff = document.getElementById('filterStaff').value;

        refreshStaffFilterOptions();

        var filtered = records.filter(function (r) {
            if (fReq === 'proof' && !r.reqUploadProof) return false;
            if (fReq === 'notes' && !r.reqNotes) return false;
            if (fReq === 'both' && (!r.reqUploadProof || !r.reqNotes)) return false;
            var eff = effectiveStatus(r);
            if (fStat !== 'all' && eff !== fStat) return false;
            if (fStaff !== 'all') {
                if (eff !== 'approved' || String(r.approvedByUserId || '') !== String(fStaff)) return false;
            }
            return true;
        });

        filtered.sort(function (a, b) {
            return String(b.dateApplied).localeCompare(String(a.dateApplied));
        });

        var pendingN = records.filter(function (r) { return effectiveStatus(r) === 'pending'; }).length;
        document.getElementById('pendingCount').textContent = String(pendingN);

        var tbody = document.getElementById('historyTableBody');

        if (!filtered.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="px-6 py-10 text-center text-slate-500">No records match filters.</td></tr>';
        } else {
            tbody.innerHTML = filtered.map(function (r) {
                var eff = effectiveStatus(r);
                var badgeCls = 'bg-slate-100 text-slate-700';
                if (eff === 'pending') badgeCls = 'bg-amber-50 text-amber-800 border border-amber-100';
                if (eff === 'approved') badgeCls = 'bg-emerald-50 text-emerald-800 border border-emerald-100';
                if (eff === 'rejected') badgeCls = 'bg-red-50 text-red-800 border border-red-100';
                if (eff === 'expired') badgeCls = 'bg-slate-200 text-slate-700 border border-slate-300';
                var actions = '';
                if (eff === 'pending') {
                    actions = '<button type="button" class="verify-open text-primary font-bold text-xs uppercase tracking-wide hover:underline mr-2" data-id="' + r.id + '">Verify</button>';
                }
                actions += '<button type="button" class="view-open text-slate-600 font-bold text-xs uppercase tracking-wide hover:underline" data-id="' + r.id + '">View</button>';
                return '<tr class="hover:bg-slate-50/80">' +
                    '<td class="px-6 py-4 text-sm font-semibold text-slate-800 whitespace-nowrap">' + (r.dateApplied || '—') + '</td>' +
                    '<td class="px-6 py-4 text-sm font-bold text-slate-900">' + String(r.patientName || '').replace(/</g, '&lt;') +
                    (r.patientRef ? '<span class="block text-xs font-medium text-slate-500">' + String(r.patientRef).replace(/</g, '&lt;') + '</span>' : '') + '</td>' +
                    '<td class="px-6 py-4 text-sm text-slate-700">' + String(r.programName || '').replace(/</g, '&lt;') + '</td>' +
                    '<td class="px-6 py-4 text-sm font-mono text-slate-600">' + maskId(r.idNumber) + '</td>' +
                    '<td class="px-6 py-4"><span class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-lg ' + badgeCls + '">' + eff + '</span></td>' +
                    '<td class="px-6 py-4 text-sm text-slate-700">' + (eff === 'approved' ? String(r.approvedBy || '—').replace(/</g, '&lt;') : '—') + '</td>' +
                    '<td class="px-6 py-4 text-sm text-slate-600 max-w-[200px] truncate" title="' + String(r.remarks || '').replace(/"/g, '&quot;') + '">' + (r.remarks ? String(r.remarks).replace(/</g, '&lt;') : '—') + '</td>' +
                    '<td class="px-6 py-4 text-right whitespace-nowrap">' + actions + '</td>' +
                    '</tr>';
            }).join('');
        }

        document.getElementById('historySummary').textContent = 'Showing ' + filtered.length + ' of ' + records.length + ' records';

        tbody.querySelectorAll('.verify-open').forEach(function (b) {
            b.addEventListener('click', function () { openVerifyModal(b.getAttribute('data-id'), true); });
        });
        tbody.querySelectorAll('.view-open').forEach(function (b) {
            b.addEventListener('click', function () { openVerifyModal(b.getAttribute('data-id'), false); });
        });
    }

    function openVerifyModal(recId, allowActions) {
        var r = recordsCache.find(function (x) { return String(x.id) === String(recId); });
        if (!r) return;
        var eff = effectiveStatus(r);
        document.getElementById('verifyRecordId').value = r.id;
        document.getElementById('verifyPatientLine').textContent = (r.patientName || '—') + (r.patientRef ? ' · ' + r.patientRef : '');
        var vProg = programsCache.find(function (x) { return String(x.id) === String(r.programId); });
        var discLine = (r.programName || '—') + ' · ' + requirementsSummaryRecord(r);
        var minSpend = vProg && typeof vProg.minSpend === 'number' ? vProg.minSpend : (typeof r.programMinSpend === 'number' ? r.programMinSpend : 0);
        if (minSpend > 0) {
            discLine += ' · Min. spend ₱' + minSpend.toLocaleString();
        }
        document.getElementById('verifyDiscountLine').textContent = discLine;
        var pnw = document.getElementById('verifyPatientNotesWrap');
        var pnt = document.getElementById('verifyPatientNotes');
        if (r.applicationNotes && String(r.applicationNotes).trim() !== '') {
            pnw.classList.remove('hidden');
            pnt.textContent = r.applicationNotes;
        } else {
            pnw.classList.add('hidden');
        }
        document.getElementById('verifyIdFull').textContent = r.idNumber || '—';
        document.getElementById('verifyMetaLine').textContent = (r.dateApplied || '—') + ' · Assigned: ' + (r.staffAssigned || '—');
        var box = document.getElementById('verifyProofBox');
        box.innerHTML = '';
        if (r.proofImageUrl) {
            var im = document.createElement('img');
            im.src = r.proofImageUrl;
            im.alt = 'ID proof';
            im.className = 'max-h-64 w-auto mx-auto object-contain';
            box.appendChild(im);
        } else {
            box.innerHTML = '<div class="text-center p-6"><span class="material-symbols-outlined text-4xl text-slate-300">image</span><p class="text-sm text-slate-500 mt-2">No image on file</p></div>';
        }
        var readonlyNote = document.getElementById('verifyReadonlyNote');
        var remarksRead = document.getElementById('verifyRemarksRead');
        if (r.remarks) {
            readonlyNote.classList.remove('hidden');
            remarksRead.textContent = r.remarks;
        } else {
            readonlyNote.classList.add('hidden');
        }
        var actionBlock = document.getElementById('verifyActionBlock');
        var canAct = allowActions && eff === 'pending';
        actionBlock.style.display = canAct ? 'block' : 'none';
        document.getElementById('verifyRemarksInput').value = '';
        document.getElementById('verifyModalTitle').textContent = canAct ? 'Verify application' : 'Application details';
        openOverlay(document.getElementById('verifyModal'));
    }

    function patchRecord(id, action, remarks) {
        apiJson(CFG.verificationsApi, {
            method: 'PATCH',
            body: { id: parseInt(id, 10), action: action, remarks: remarks }
        }).then(function (data) {
            if (!data.success) {
                alert(data.message || 'Update failed.');
                return;
            }
            return loadVerifications().then(function () {
                renderHistory();
                closeOverlay(document.getElementById('verifyModal'));
            });
        }).catch(function () {
            alert('Network error.');
        });
    }

    var filterDebounceTimer = null;
    function scheduleReloadVerifications() {
        if (filterDebounceTimer) clearTimeout(filterDebounceTimer);
        filterDebounceTimer = setTimeout(function () {
            loadVerifications().then(function () {
                renderHistory();
            });
        }, 320);
    }
    function renderHistoryOnly() {
        renderHistory();
    }

    document.querySelectorAll('.program-modal-close').forEach(function (b) {
        b.addEventListener('click', function () { closeOverlay(document.getElementById('programModal')); });
    });
    document.getElementById('btnNewProgram').addEventListener('click', function () { openProgramModal(null); });

    document.getElementById('programDiscountType').addEventListener('change', updateProgramValueLabel);
    document.getElementById('programEnabled').addEventListener('change', function () {
        document.getElementById('programEnabledLabel').textContent = document.getElementById('programEnabled').checked ? 'Enabled' : 'Disabled';
    });
    document.querySelectorAll('input[name="programScope"]').forEach(function (r) {
        r.addEventListener('change', function () {
            var sel = document.querySelector('input[name="programScope"]:checked').value === 'selected';
            document.getElementById('programServicesWrap').classList.toggle('hidden', !sel);
        });
    });

    document.getElementById('programForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var id = document.getElementById('programId').value;
        var scope = document.querySelector('input[name="programScope"]:checked').value;
        var payload = {
            name: document.getElementById('programName').value.trim(),
            discountType: document.getElementById('programDiscountType').value,
            value: parseFloat(document.getElementById('programValue').value) || 0,
            minSpend: Math.max(0, Math.round(parseFloat(document.getElementById('programMinSpend').value) || 0)),
            reqUploadProof: document.getElementById('reqUploadProof').checked,
            reqNotes: document.getElementById('reqNotes').checked,
            enabled: document.getElementById('programEnabled').checked,
            validFrom: document.getElementById('programStart').value,
            validTo: document.getElementById('programEnd').value,
            serviceScope: scope,
            serviceIds: scope === 'selected' ? getCheckedServiceIds() : [],
            stacking: document.getElementById('programStacking').value
        };
        var method = id ? 'PUT' : 'POST';
        if (id) payload.id = id;
        apiJson(CFG.programsApi, { method: method, body: payload }).then(function (data) {
            if (!data.success) {
                alert(data.message || 'Could not save program.');
                return;
            }
            closeOverlay(document.getElementById('programModal'));
            loadPrograms().then(function () {
                renderPrograms();
                populateApplicationPrograms();
            });
        }).catch(function () {
            alert('Network error.');
        });
    });

    document.querySelectorAll('.application-modal-close').forEach(function (b) {
        b.addEventListener('click', function () { closeOverlay(document.getElementById('applicationModal')); });
    });
    document.getElementById('appDiscountProgram').addEventListener('change', updateAppFormForProgram);

    document.getElementById('btnNewApplication').addEventListener('click', function () {
        document.getElementById('applicationForm').reset();
        document.getElementById('appStaffAssigned').value = CFG.staffName || '';
        document.getElementById('appDateApplied').value = new Date().toISOString().slice(0, 10);
        document.getElementById('appProofPreview').classList.add('hidden');
        var imgEl = document.getElementById('appProofImg');
        if (imgEl) imgEl.removeAttribute('src');
        populateApplicationPrograms();
        openOverlay(document.getElementById('applicationModal'));
    });

    document.getElementById('appProofFile').addEventListener('change', function (ev) {
        var f = ev.target.files && ev.target.files[0];
        var prev = document.getElementById('appProofPreview');
        var img = document.getElementById('appProofImg');
        if (!f || !f.type.match(/^image\//)) {
            prev.classList.add('hidden');
            return;
        }
        var reader = new FileReader();
        reader.onload = function () {
            img.src = reader.result;
            prev.classList.remove('hidden');
        };
        reader.readAsDataURL(f);
    });

    document.getElementById('applicationForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var pid = document.getElementById('appDiscountProgram').value;
        if (!pid) {
            alert('Enable at least one discount program first.');
            return;
        }
        var prog = programsCache.find(function (x) { return String(x.id) === String(pid); });
        if (!prog) return;
        var snap = snapshotRequirements(prog);
        var proofPayload = '';
        var imgEl = document.getElementById('appProofImg');
        if (imgEl && imgEl.src && imgEl.src.indexOf('data:') === 0) proofPayload = imgEl.src;
        var notesVal = document.getElementById('appNotes').value.trim();
        if (snap.reqUploadProof && !proofPayload) {
            alert('This discount program requires an uploaded proof image.');
            return;
        }
        if (snap.reqNotes && !notesVal) {
            alert('This discount program requires notes.');
            return;
        }
        apiJson(CFG.verificationsApi, {
            method: 'POST',
            body: {
                discount_program_id: parseInt(pid, 10),
                patient_name: document.getElementById('appPatientName').value.trim(),
                patient_ref: document.getElementById('appPatientRef').value.trim(),
                id_number: document.getElementById('appIdNumber').value.trim(),
                application_notes: notesVal,
                date_applied: document.getElementById('appDateApplied').value,
                proof_image_base64: proofPayload || undefined
            }
        }).then(function (data) {
            if (!data.success) {
                alert(data.message || 'Could not save application.');
                return;
            }
            closeOverlay(document.getElementById('applicationModal'));
            loadVerifications().then(function () { renderHistory(); });
        }).catch(function () {
            alert('Network error.');
        });
    });

    document.getElementById('verifyModalClose').addEventListener('click', function () {
        closeOverlay(document.getElementById('verifyModal'));
    });

    document.getElementById('btnApprove').addEventListener('click', function () {
        var id = document.getElementById('verifyRecordId').value;
        var note = document.getElementById('verifyRemarksInput').value.trim();
        patchRecord(id, 'approve', note || 'Approved.');
    });
    document.getElementById('btnReject').addEventListener('click', function () {
        var id = document.getElementById('verifyRecordId').value;
        var note = document.getElementById('verifyRemarksInput').value.trim();
        if (!note) {
            alert('Please add a short remark when rejecting (e.g. reason).');
            return;
        }
        patchRecord(id, 'reject', note);
    });
    document.getElementById('btnRequestInfo').addEventListener('click', function () {
        var id = document.getElementById('verifyRecordId').value;
        var note = document.getElementById('verifyRemarksInput').value.trim();
        patchRecord(id, 'request_info', note || 'Additional information requested.');
    });

    ['filterRequirements', 'filterDateFrom', 'filterDateTo'].forEach(function (fid) {
        var el = document.getElementById(fid);
        if (el) el.addEventListener('change', scheduleReloadVerifications);
    });
    var fp = document.getElementById('filterPatient');
    if (fp) {
        fp.addEventListener('input', scheduleReloadVerifications);
    }
    var fs = document.getElementById('filterStaff');
    if (fs) {
        fs.addEventListener('change', renderHistoryOnly);
    }
    var fst = document.getElementById('filterStatus');
    if (fst) fst.addEventListener('change', renderHistoryOnly);

    fetchServices();

    document.getElementById('programsGrid').innerHTML = '<p class="text-slate-500 col-span-full py-6 text-center text-sm">Loading programs…</p>';
    document.getElementById('historyTableBody').innerHTML = '<tr><td colspan="8" class="px-6 py-10 text-center text-slate-500">Loading…</td></tr>';

    loadPrograms().then(function () {
        renderPrograms();
        populateApplicationPrograms();
        return loadVerifications();
    }).then(function () {
        renderHistory();
    }).catch(function () {
        document.getElementById('programsGrid').innerHTML = '<p class="text-red-600 col-span-full py-8 text-center text-sm">Could not load discount programs. Ensure database tables exist (see schema.sql / migrations).</p>';
    });
})();
