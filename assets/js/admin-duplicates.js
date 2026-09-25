/** Outils doublons du tableau existant. Les décisions et les nombres viennent du serveur. */
(function () {
    'use strict';
    if (!window.uebAdminDashboard) return;
    const cfg = window.uebAdminDashboard;
    const rights = cfg.duplicates || {};
    const $ = id => document.getElementById(id);
    const esc = text => String(text == null ? '' : text).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const nf = value => new Intl.NumberFormat('fr-FR').format(Number(value));
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    let callbacks, scanPromise, scanned = false, processed = 0, plan, undoBatch, lastFocus, auditBefore;
    let selection = new Set();
    async function api(task, params = {}) {
        const response = await fetch(cfg.ajax_url, {method:'POST', body:new URLSearchParams({action:'ueb_duplicates', nonce:cfg.nonce, task, ...params})});
        const json = await response.json();
        if (!json.success) throw new Error(json.data?.message || 'Cette action n’a pas abouti. Réessayez.');
        return json.data;
    }
    function notice(text, batch) {
        if (!$('dup-notice')) return;
        $('dup-notice').hidden = false;
        $('dup-notice').querySelector('span').textContent = text;
        undoBatch = batch || null;
        $('dup-undo').hidden = !batch;
    }
    function params() {
        return {duplicates:$('list-duplicates')?.value || '', duplicate_status:$('list-duplicate-status')?.value || '', date_from:$('list-date-from')?.value || '', date_to:$('list-date-to')?.value || ''};
    }
    async function scan(force = false) {
        if (!rights.view || (scanned && !force)) return;
        if (scanPromise) return scanPromise;
        processed = 0;
        scanPromise = (async () => {
            $('dup-rescan').disabled = true;
            $('dup-analysis').classList.add('is-scanning');
            let data;
            do {
                $('dup-analysis').textContent = 'Analyse des dossiers… ' + nf(processed) + ' vérifiés';
                data = await api('scan');
                processed += data.processed || 0;
            } while (data.more);
            scanned = true;
            $('dup-analysis').textContent = 'Analyse à jour · Le dossier le plus récent est protégé';
        })().catch(error => {
            scanned = false;
            $('dup-analysis').textContent = error.message;
            throw error;
        }).finally(() => {
            $('dup-rescan').disabled = false;
            $('dup-analysis').classList.remove('is-scanning');
            scanPromise = null;
        });
        return scanPromise;
    }
    function badge(row) {
        const d = row.duplicate;
        if (!d) return row.statut === 'doublon_desactive' ? '<span class="dup-badge dup-badge--disabled">Doublon désactivé</span>' : '';
        let html = '';
        if (d.keeper) html += '<span class="dup-badge dup-badge--keeper"><svg class="admin-icon admin-icon--sm dup-star" aria-hidden="true"><use href="#ueb-i-star"/></svg>Le plus récent créé</span>';
        if (d.disabled) html += '<span class="dup-badge dup-badge--disabled">Doublon désactivé</span>';
        else if (d.group && !d.keeper) html += '<span class="dup-badge dup-badge--old">Doublon</span>';
        return html;
    }
    function actions(row) {
        const d = row.duplicate;
        if (!d?.manage) return '';
        if (d.disabled) return '<button type="button" class="dup-action" data-dup-op="reactivate" data-id="' + row.id + '">Réactiver</button>';
        if (d.group && !d.keeper) return '<button type="button" class="dup-action" data-dup-op="disable" data-id="' + row.id + '">Désactiver</button>';
        return '';
    }
    function header(row, previous, index) {
        const d = row.duplicate;
        if (!params().duplicates || !d?.group || previous?.duplicate?.group === d.group) return '';
        const eligible = d.manage && d.confidence === 'certain' && !d.cross;
        // Une page peut s'ouvrir au milieu d'un groupe : le rang du dossier
        // dans son groupe vient du serveur, et on le dit plutôt que de laisser
        // croire que le groupe commence ici.
        const suite = index === 0 && d.rank > 0;
        const reste = suite ? d.size - d.rank : d.size;
        return '<tr class="dup-group' + (suite ? ' dup-group--suite' : '') + '" style="--i:' + Math.min(index, 8) + '"><td colspan="8"><div class="dup-group-inner"><div class="dup-group-title">' +
            (eligible ? '<input type="checkbox" data-dup-select="' + d.group + '" aria-label="Sélectionner le groupe ' + d.group + '"' + (selection.has(String(d.group)) ? ' checked' : '') + '>' : '') +
            '<div><strong>Groupe ' + d.group + (suite ? ' <span class="dup-suite">suite</span>' : '') + '</strong><span>' +
            (suite ? nf(reste) + ' dossier' + (reste > 1 ? 's' : '') + ' sur ' + nf(d.size) + ' · le début du groupe est en page précédente'
                   : nf(d.size) + ' dossiers') + ' · Référence : ' + esc(d.keeper_number) + '</span></div>' +
            '<span class="dup-badge ' + (d.confidence === 'certain' ? 'dup-badge--certain' : 'dup-badge--old') + '">' + (d.confidence === 'certain' ? 'Certain' : 'Probable') + '</span>' +
            (d.cross ? '<span class="dup-badge dup-badge--old">Inter-établissements · revue manuelle</span>' : '') + '</div>' +
            (d.manage ? '<div class="dup-group-actions"><button type="button" class="dup-action" data-dup-op="group" data-group="' + d.group + '">Désactiver les anciens</button><button type="button" class="dup-action dup-action--quiet" data-dup-op="dismiss" data-group="' + d.group + '">Ce n’est pas un doublon</button></div>' : '') +
            '</div></td></tr>';
    }
    function field(row, key, text) {
        const different = row.duplicate?.differences?.includes(key);
        return '<span' + (different ? ' class="dup-different" title="Différent du dossier le plus récent"' : '') + '>' + esc(text || '—') + (different ? '<span class="admin-sr-only"> (différent du plus récent)</span>' : '') + '</span>';
    }
    function identity(row) {
        if (!params().duplicates || !row.duplicate?.group) return '';
        const d = row.duplicate;
        return '<div class="dup-identity"><span>' + field(row, 'email', d.email) + '</span><span>' + esc(d.phones || 'Téléphone non renseigné') + '</span><span>Naissance : ' + field(row, 'date_naissance', d.birth) + '</span></div>';
    }
    /* Compteur progressif. Les chiffres animés sont aria-hidden et doublés
       par un texte lisible d'un seul tenant : une zone live qui égrène
       « 1, 4, 12, 37 » est inutilisable au lecteur d'écran. */
    function countUp(node, value) {
        if (reduced.matches) { node.textContent = nf(value); return; }
        const start = performance.now();
        (function step(now) {
            const p = Math.min(1, (now - start) / 600);
            node.textContent = nf(Math.round(value * (1 - Math.pow(1 - p, 3))));
            if (p < 1 && node.isConnected) requestAnimationFrame(step);
        })(start);
    }
    function summary(data) {
        const box = $('dup-bulk');
        if (box) {
            box.hidden = !data?.duplicates;
            if ($('dup-all')) $('dup-all').disabled = !Number(data?.duplicates?.old_count);
            if (data?.duplicates) {
                const groups = Number(data.duplicates.groups_count) || 0;
                const old = Number(data.duplicates.old_count) || 0;
                const phrase = nf(groups) + ' groupes de doublons · ' + nf(old) + ' dossiers à désactiver';
                $('dup-summary').innerHTML = '<span aria-hidden="true"><span class="dup-count"></span> groupes de doublons · ' +
                    '<span class="dup-count"></span> dossiers à désactiver</span>' +
                    '<span class="admin-sr-only">' + esc(phrase) + '</span>';
                const cells = $('dup-summary').querySelectorAll('.dup-count');
                countUp(cells[0], groups);
                countUp(cells[1], old);
            }
        }
        const hidden = $('dup-hidden');
        if (hidden) {
            hidden.hidden = !data?.hidden_disabled || !!params().duplicates || !!params().duplicate_status;
            hidden.innerHTML = nf(data?.hidden_disabled || 0) + ' doublons désactivés masqués · <button type="button" class="dup-link" id="dup-show-disabled">Les afficher</button>';
        }
    }
    async function preview(input, trigger) {
        lastFocus = trigger || document.activeElement;
        if (trigger) trigger.disabled = true;
        try {
            plan = await api('preview', {...callbacks.filters(), ...params(), recherche:$('admin-recherche').value, ...input});
            if (['reactivate','undo'].includes(input.operation)) {
                const result = await api('commit', {token:plan.token});
                notice(nf(result.count) + ' dossier(s) mis à jour. Décision enregistrée.', result.operation === 'undo' ? null : result.batch);
                invalidate();
                if (!$('dup-audit').hidden) audit();
                return;
            }
            const labels = {disable:'Désactiver', group:'Désactiver les anciens', bulk:'Désactiver les anciens doublons', reactivate:'Réactiver', dismiss:'Écarter ce signalement', undo:'Annuler cette décision'};
            $('dup-confirm-title').textContent = labels[plan.operation];
            $('dup-confirm-description').textContent = nf(plan.count) + ' dossier' + (plan.count > 1 ? 's' : '') + ' concerné' + (plan.count > 1 ? 's' : '') + '. ' + (plan.operation === 'bulk' ? 'Uniquement les groupes « Certain », sans doublon inter-établissements.' : 'Vérifiez les dossiers du groupe avant de confirmer.');
            $('dup-confirm-submit').textContent = labels[plan.operation];
            $('dup-confirm-error').textContent = '';
            $('dup-reason').value = '';
            $('dup-confirm').showModal();
            $('dup-cancel').focus();
        } catch (error) { notice(error.message); }
        finally { if (trigger) trigger.disabled = false; }
    }
    function close() { $('dup-confirm').close(); if (lastFocus?.isConnected) lastFocus.focus(); }
    function invalidate() {
        selection.clear(); updateSelection();
        document.dispatchEvent(new CustomEvent('uebDuplicatesChanged'));
        try { localStorage.setItem('ueb-statistics-change', String(Date.now())); } catch (_) {}
    }
    function updateSelection() {
        if (!$('dup-selected')) return;
        $('dup-selected').disabled = !selection.size;
        $('dup-selected').textContent = selection.size ? 'Désactiver la sélection (' + selection.size + ' groupes)' : 'Désactiver la sélection';
    }
    async function audit(more = false) {
        const area = $('dup-audit-content');
        if (!more) { area.textContent = 'Chargement du journal…'; auditBefore = null; }
        try {
            const rows = await api('audit', more ? {before:auditBefore} : {});
            if (['reactivate','undo'].includes(input.operation)) {
                const result = await api('commit', {token:plan.token});
                notice(nf(result.count) + ' dossier(s) mis à jour. Décision enregistrée.', result.operation === 'undo' ? null : result.batch);
                invalidate();
                if (!$('dup-audit').hidden) audit();
                return;
            }
            const labels = {disable:'Désactivation', group:'Désactivation du groupe', bulk:'Désactivation groupée', reactivate:'Réactivation', dismiss:'Faux positif', undo:'Annulation', rollback:'Retour arrière'};
            const statuses = {soumis:'Soumis', brouillon:'Brouillon', doublon_desactive:'Doublon désactivé'};
            const html = rows.map(row => '<article class="dup-audit-entry"><strong>' + esc(labels[row.operation] || row.operation) + ' · ' + esc(row.numero_dossier) + '</strong><span>' + esc(row.actor || 'Compte #' + row.actor_id) + ' · ' + esc(row.created_at) + '</span><p>' + esc(statuses[row.before_status] || row.before_status) + ' → ' + esc(statuses[row.after_status] || row.after_status) + (row.reason ? ' · ' + esc(row.reason) : '') + '</p></article>').join('');
            if (more) area.insertAdjacentHTML('beforeend', html); else area.innerHTML = html || '<p>Aucune décision enregistrée pour votre portée.</p>';
            auditBefore = rows.at(-1)?.id;
            $('dup-audit-more').hidden = rows.length < 50;
        } catch (error) { area.textContent = error.message; }
    }
    function init(handlers) {
        callbacks = handlers;
        ['list-duplicates','list-duplicate-status','list-date-from','list-date-to'].forEach(id => $(id)?.addEventListener('change', () => {
            selection.clear(); updateSelection();
            if (id === 'list-duplicates' && $(id).value) { $('list-duplicate-status').value = 'all'; $('list-duplicate-status').disabled = true; }
            else if (id === 'list-duplicates') { $('list-duplicate-status').disabled = false; $('list-duplicate-status').value = ''; }
            callbacks.reload(true);
        }));
        if (!rights.view) return;
        $('dup-rescan').onclick = () => scan(true).then(() => callbacks.reload(true)).catch(error => notice(error.message));
        const form = $('dup-settings-form');
        if (form) {
            ['email','phone','identity'].forEach(name => form.elements[name].checked = !!rights.settings[name]);
            form.elements.country.value = rights.settings.country;
            form.onsubmit = async event => {
                event.preventDefault();
                const button = form.querySelector('button'); button.disabled = true;
                try {
                    await api('configure', {email:form.elements.email.checked ? 1 : '', phone:form.elements.phone.checked ? 1 : '', identity:form.elements.identity.checked ? 1 : '', country:form.elements.country.value});
                    await scan(true); selection.clear(); updateSelection(); callbacks.reload(true);
                } catch (error) { notice(error.message); }
                finally { button.disabled = false; }
            };
        }
        $('dup-confirm-form').onsubmit = async event => {
            event.preventDefault();
            const button = $('dup-confirm-submit'); button.disabled = true;
            $('dup-cancel').disabled = true;
            try {
                const result = await api('commit', {token:plan.token, reason:$('dup-reason').value});
                close();
                const container = $('admin-liste-container');
                if (!reduced.matches) { container.classList.add('dup-changing'); await new Promise(resolve => setTimeout(resolve, 160)); container.classList.remove('dup-changing'); }
                notice(nf(result.count) + ' dossier' + (result.count > 1 ? 's' : '') + ' mis à jour. Décision enregistrée.', result.operation === 'undo' ? null : result.batch);
                invalidate();
                if (!$('dup-audit').hidden) audit();
            } catch (error) { $('dup-confirm-error').textContent = error.message; }
            finally { button.disabled = false; $('dup-cancel').disabled = false; }
        };
        $('dup-cancel').onclick = close;
        $('dup-confirm').addEventListener('cancel', event => { event.preventDefault(); if (!$('dup-confirm-submit').disabled) close(); });
        $('dup-all')?.addEventListener('click', event => preview({operation:'bulk'}, event.currentTarget));
        $('dup-selected')?.addEventListener('click', event => preview({operation:'bulk',groups:[...selection].join(',')}, event.currentTarget));
        $('dup-undo').onclick = event => preview({operation:'undo',batch:undoBatch}, event.currentTarget);
        $('dup-notice').querySelector('.dup-close').onclick = () => $('dup-notice').hidden = true;
        $('dup-audit-toggle').onclick = () => { const open = $('dup-audit').hidden; $('dup-audit').hidden = !open; $('dup-audit-toggle').setAttribute('aria-expanded', String(open)); if (open) audit(); };
        $('dup-audit-more').onclick = () => audit(true);
    }
    document.addEventListener('click', event => {
        const action = event.target.closest('[data-dup-op]');
        if (action) preview({operation:action.dataset.dupOp, id:action.dataset.id || '', group:action.dataset.group || ''}, action);
        if (event.target.closest('#dup-show-disabled')) { $('list-duplicate-status').value = 'disabled'; callbacks.reload(true); }
    });
    document.addEventListener('change', event => {
        if (!event.target.matches('[data-dup-select]')) return;
        const id = event.target.dataset.dupSelect;
        event.target.checked ? selection.add(id) : selection.delete(id); updateSelection();
    });
    window.uebDuplicates = {init, params, scan, badge, actions, header, field, identity, summary};
})();
