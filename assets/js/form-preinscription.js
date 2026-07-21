(function () {
    'use strict';

    const form = document.getElementById('form-preinscription');
    if (!form) return;

    const steps      = form.querySelectorAll('.form-step');
    const navItems   = document.querySelectorAll('.steps-nav .step-item');
    const separators = document.querySelectorAll('.steps-nav .step-separator');
    let currentStep  = 1;

    /* ================================================================
       APPEL AJAX GÉNÉRIQUE VERS admin-ajax.php
       ================================================================ */
    function uebFetchRaw(action, params) {
        const body = new URLSearchParams(Object.assign({
            action: action,
            nonce: (window.uebAjax && window.uebAjax.nonce) || ''
        }, params || {}));

        return fetch((window.uebAjax && window.uebAjax.ajax_url) || '', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
            .then(function (r) { return r.json(); })
            .catch(function (err) {
                console.error('Erreur réseau AJAX (' + action + ')', err);
                return null;
            });
    }

    function uebFetch(action, params) {
        return uebFetchRaw(action, params).then(function (json) {
            if (!json || !json.success) {
                console.error('Erreur AJAX (' + action + ')', json);
                return [];
            }
            return json.data || [];
        });
    }

    /* ================================================================
       CACHES (évite de refaire des appels identiques)
       ================================================================ */
    let facultesCache = [];

    // Listes "brutes" des filières (avant filtrage croisé), mises à jour
    // à chaque changement de faculté/type de formation, et relues par
    // refreshFiliereCrossFilter() ci-dessous.
    let filiere1Data  = []; // options pour le 1er choix
    let filiere23Data = []; // options pour les 2e et 3e choix

    function getFaculteCode(faculteId) {
        const f = facultesCache.find(function (x) { return String(x.id) === String(faculteId); });
        return f ? f.code : '';
    }

    /* ================================================================
       ÉLÉMENTS DOM
       ================================================================ */
    const selectFaculte   = document.getElementById('faculte');
    const selectDiplome   = document.getElementById('diplome_admission');
    const selectType      = document.getElementById('type_formation');
    const selectFiliere1  = document.getElementById('filiere_1');
    const selectFiliere2  = document.getElementById('filiere_2');
    const selectFiliere3  = document.getElementById('filiere_3');
    const serieSelect     = document.getElementById('serie_diplome_select');
    const proNotice       = document.getElementById('pro-filiere-notice');
    const typeGroup       = document.getElementById('type-formation-group');
    const serieHidden     = document.getElementById('serie_diplome');

    const selectRegion       = document.getElementById('region_origine');
    const selectDepartement  = document.getElementById('departement_origine');
    const selectCommune      = document.getElementById('commune_origine');

    /* ================================================================
       HELPER : peupler un <select> à partir d'un tableau {id, libelle}
       ================================================================ */
    function fillSelect(select, items, placeholder, enable) {
        if (!select) return;

        select.innerHTML = '';
        const opt0 = document.createElement('option');
        opt0.value = '';
        opt0.textContent = placeholder;
        select.appendChild(opt0);

        items.forEach(function (item) {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.libelle;
            select.appendChild(opt);
        });

        select.disabled = !enable || items.length === 0;
    }

    /* ================================================================
       CHARGEMENT INITIAL : facultés, diplômes, régions, listes de
       référence "statiques" (pas de cascade dépendante d'un autre champ)
       ================================================================ */
    const facultesPromise = uebFetch('ueb_get_facultes').then(function (data) {
        facultesCache = data;
        fillSelect(selectFaculte, data, '— Choisir —', true);
        return data;
    });

    const diplomesPromise = uebFetch('ueb_get_diplomes').then(function (data) {
        fillSelect(selectDiplome, data, '— Choisir —', true);
        return data;
    });

    const regionsPromise = uebFetch('ueb_get_regions').then(function (data) {
        fillSelect(selectRegion, data, '— Choisir —', true);
        return data;
    });

    const statutsPromise = uebFetch('ueb_get_statuts_socio_pro').then(function (data) {
        fillSelect(document.getElementById('statut_socio_professionnel'), data, '— Choisir —', true);
        return data;
    });

    const nationalitesPromise = uebFetch('ueb_get_nationalites').then(function (data) {
        fillSelect(document.getElementById('nationalite'), data, '— Choisir —', true);
        return data;
    });

    const situationsPromise = uebFetch('ueb_get_situations_matrimoniales').then(function (data) {
        fillSelect(document.getElementById('situation_matrimoniale'), data, '— Choisir —', true);
        return data;
    });

    const niveauxPromise = uebFetch('ueb_get_niveaux_lmd').then(function (data) {
        fillSelect(document.getElementById('niveau_lmd'), data, '— Choisir —', true);
        return data;
    });

    const mentionsPromise = uebFetch('ueb_get_mentions').then(function (data) {
        fillSelect(document.getElementById('mention'), data, '— Choisir —', true);
        return data;
    });

    const statutsEtudiantPromise = uebFetch('ueb_get_statuts_etudiant').then(function (data) {
        fillSelect(document.getElementById('statut_etudiant'), data, '— Choisir —', true);
        return data;
    });

    const languesPromise = uebFetch('ueb_get_langues').then(function (data) {
        fillSelect(document.getElementById('premiere_langue'), data, '— Choisir —', true);
        return data;
    });

    const sportsPromise = uebFetch('ueb_get_sports').then(function (data) {
        fillSelect(document.getElementById('sport_prefere'), data, '— Choisir —', true);
        return data;
    });

    const artsPromise = uebFetch('ueb_get_arts').then(function (data) {
        fillSelect(document.getElementById('art_pratique'), data, '— Choisir —', true);
        return data;
    });

    /* ================================================================
       SÉRIE / SPÉCIALITÉ — dépend de faculté + diplôme
       ================================================================ */
    function updateSeries() {
        const faculteId = selectFaculte.value;
        const diplomeId = selectDiplome.value;

        if (serieHidden) serieHidden.value = '';

        if (!faculteId || !diplomeId) {
            fillSelect(serieSelect, [], "— Choisir d'abord la faculté et le diplôme —", false);
            return;
        }

        fillSelect(serieSelect, [], '— Chargement... —', false);

        uebFetch('ueb_get_specialites', { faculte_id: faculteId, diplome_id: diplomeId })
            .then(function (data) {
                fillSelect(serieSelect, data, '— Choisir la série —', true);
            });
    }

    /* ================================================================
       TYPE DE FORMATION : visible uniquement pour la faculté FS
       ================================================================ */
    function updateTypeFormation() {
        const code = getFaculteCode(selectFaculte.value);

        if (code === 'FS') {
            typeGroup.style.display = '';
            selectType.disabled = false;
        } else {
            typeGroup.style.display = 'none';
            selectType.value    = 'classique';
            selectType.disabled = false;
        }
        updateFilieres();
    }

    /* ================================================================
       FILTRAGE CROISÉ DES 3 CHOIX DE FILIÈRE
       ------------------------------------------------------------
       Reconstruit les options de filiere_1 / filiere_2 / filiere_3 à
       partir des listes "brutes" (filiere1Data / filiere23Data), en
       retirant de chaque select les filières déjà choisies dans LES
       AUTRES selects (une même filière ne peut pas être choisie deux
       fois). La valeur actuellement sélectionnée d'un select est
       toujours conservée si elle reste valide.
       ================================================================ */
    function refreshFiliereCrossFilter() {
        const configs = [
            { select: selectFiliere1, base: filiere1Data,  placeholder: '— Choisir une filière —' },
            { select: selectFiliere2, base: filiere23Data, placeholder: '— Aucun deuxième choix (optionnel) —' },
            { select: selectFiliere3, base: filiere23Data, placeholder: '— Aucun troisième choix (optionnel) —' }
        ];

        configs.forEach(function (cfg) {
            const currentVal = cfg.select.value;

            const usedElsewhere = configs
                .filter(function (other) { return other.select !== cfg.select; })
                .map(function (other) { return other.select.value; })
                .filter(Boolean);

            const available = cfg.base.filter(function (item) {
                return usedElsewhere.indexOf(String(item.id)) === -1;
            });

            fillSelect(cfg.select, available, cfg.placeholder, true);

            if (currentVal && available.some(function (item) { return String(item.id) === currentVal; })) {
                cfg.select.value = currentVal;
            }
        });
    }

    // Dès qu'un des 3 choix change, on réapplique le filtrage sur les
    // deux autres (et sur lui-même, sans effet néfaste).
    [selectFiliere1, selectFiliere2, selectFiliere3].forEach(function (select) {
        select.addEventListener('change', refreshFiliereCrossFilter);
    });

    /* ================================================================
       FILIÈRES — dépend de faculté + type de formation
       (1er choix : selon le type ; 2e et 3e choix : toujours la liste
       "classique" de la faculté)
       ================================================================ */
    function updateFilieres() {
        const faculteId = selectFaculte.value;
        const type      = selectType.value || 'classique';
        const code      = getFaculteCode(faculteId);

        if (proNotice) proNotice.style.display = 'none';

        if (!faculteId) {
            filiere1Data  = [];
            filiere23Data = [];
            [selectFiliere1, selectFiliere2, selectFiliere3].forEach(function (s) {
                fillSelect(s, [], "— Choisir d'abord une faculté —", false);
            });
            return;
        }

        if (type === 'pro' && code === 'FS' && proNotice) {
            proNotice.style.display = '';
        }

        [selectFiliere1, selectFiliere2, selectFiliere3].forEach(function (s) {
            fillSelect(s, [], '— Chargement... —', false);
        });

        uebFetch('ueb_get_filieres', { faculte_id: faculteId, type_formation: type })
            .then(function (data) {
                filiere1Data = data;

                // 2e et 3e choix de filière : toujours les filières "classique"
                // de la faculté (même en formation pro).
                if (type === 'pro') {
                    return uebFetch('ueb_get_filieres', { faculte_id: faculteId, type_formation: 'classique' })
                        .then(function (data2) {
                            filiere23Data = data2;
                        });
                }

                filiere23Data = data;
            })
            .then(refreshFiliereCrossFilter);
    }

    selectFaculte.addEventListener('change', function () {
        updateSeries();
        updateTypeFormation();
    });

    selectDiplome.addEventListener('change', updateSeries);
    selectType.addEventListener('change', updateFilieres);

    serieSelect.addEventListener('change', function () {
        if (serieHidden) serieHidden.value = this.value;
    });

    /* ================================================================
       RÉGION → DÉPARTEMENT → COMMUNE
       ================================================================ */
    selectRegion.addEventListener('change', function () {
        const regionId = this.value;

        fillSelect(selectDepartement, [], '— Choisir d\'abord une région —', false);
        fillSelect(selectCommune, [], '— Choisir d\'abord un département —', false);

        if (!regionId) return;

        fillSelect(selectDepartement, [], '— Chargement... —', false);

        uebFetch('ueb_get_departements', { region_id: regionId })
            .then(function (data) {
                fillSelect(selectDepartement, data, '— Choisir —', true);
            });
    });

    selectDepartement.addEventListener('change', function () {
        const departementId = this.value;

        fillSelect(selectCommune, [], '— Choisir d\'abord un département —', false);

        if (!departementId) return;

        fillSelect(selectCommune, [], '— Chargement... —', false);

        uebFetch('ueb_get_communes', { departement_id: departementId })
            .then(function (data) {
                fillSelect(selectCommune, data, '— Choisir —', true);
            });
    });

    /* ================================================================
       VALIDATION + FORMAT TÉLÉPHONE  →  6XX XX XX XX  (9 chiffres)
       ================================================================ */
    function formatTel(raw) {
        const digits = raw.replace(/\D/g, '').slice(0, 9);
        let out = '';
        for (let i = 0; i < digits.length; i++) {
            if (i === 3 || i === 5 || i === 7) out += ' ';
            out += digits[i];
        }
        return out;
    }

    function enforceTelInput(input) {
        input.addEventListener('input', function () {
            const pos    = this.selectionStart;
            const before = this.value.length;
            this.value   = formatTel(this.value);
            const diff = this.value.length - before;
            this.setSelectionRange(pos + diff, pos + diff);
        });
        input.addEventListener('keydown', function (e) {
            const allowed = ['Backspace','Delete','ArrowLeft','ArrowRight','Tab','Home','End'];
            if (allowed.includes(e.key)) return;
            if (!/^[0-9]$/.test(e.key)) {
                e.preventDefault();
            }
        });
        input.placeholder = '6XX XX XX XX';
        input.maxLength   = 12;
    }

    document.querySelectorAll('input[type="tel"]').forEach(enforceTelInput);

    /* ================================================================
       TÉLÉPHONES MULTIPLES (candidat uniquement — les numéros du père,
       de la mère et du tuteur sont désormais des champs simples, gérés
       comme n'importe quel autre input du formulaire)
       ================================================================ */
    function makeTelRow(name, required) {
        const row = document.createElement('div');
        row.className = 'tel-row';

        const input = document.createElement('input');
        input.type        = 'tel';
        input.name        = name;
        input.placeholder = '6X XX XX XX XX';
        input.className   = 'tel-input';
        if (required) input.required = true;
        enforceTelInput(input);

        const btnRemove = document.createElement('button');
        btnRemove.type      = 'button';
        btnRemove.className = 'btn-remove-tel';
        btnRemove.setAttribute('aria-label', 'Supprimer ce numéro');
        btnRemove.textContent = '×';
        btnRemove.addEventListener('click', function () { row.remove(); });

        row.appendChild(input);
        row.appendChild(btnRemove);
        return row;
    }

    function fillTelRows(containerId, name, values) {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = '';
        const list = (values && values.length) ? values : [''];
        list.forEach(function (val, i) {
            const row = makeTelRow(name, i === 0);
            row.querySelector('input').value = val;
            container.appendChild(row);
        });
    }

    const btnAddTel = document.getElementById('btn-add-tel');
    if (btnAddTel) {
        btnAddTel.addEventListener('click', function () {
            document.getElementById('telephones-container').appendChild(makeTelRow('telephone[]', false));
        });
    }

    /* ================================================================
       NAVIGATION ENTRE ÉTAPES (5 étapes désormais)
       ================================================================ */
    function showStep(n) {
        steps.forEach(function (s) { s.classList.remove('active'); });
        const target = form.querySelector('.form-step[data-step="' + n + '"]');
        if (target) target.classList.add('active');

        navItems.forEach(function (item, i) {
            item.classList.remove('active', 'done');
            if (i + 1 === n) item.classList.add('active');
            if (i + 1 < n)  item.classList.add('done');
        });

        separators.forEach(function (sep, i) {
            sep.classList.toggle('done', i + 1 < n);
        });

        currentStep = n;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /* ================================================================
       VALIDATION
       ================================================================ */
    function validateStep(n) {
        const fieldset = form.querySelector('.form-step[data-step="' + n + '"]');
        if (!fieldset) return true;

        fieldset.querySelectorAll('.error').forEach(function (el) { el.classList.remove('error'); });
        fieldset.querySelectorAll('.field-error').forEach(function (el) { el.remove(); });

        let valid = true;

        function addError(container, msg) {
            valid = false;
            container.querySelectorAll('.field-error').forEach(function (e) { e.remove(); });
            const span = document.createElement('span');
            span.className   = 'field-error';
            span.textContent = msg;
            container.appendChild(span);
        }

        fieldset.querySelectorAll('[required]').forEach(function (field) {
            if (field.type === 'radio') {
                const group   = field.name;
                const checked = fieldset.querySelector('input[name="' + group + '"]:checked');
                if (field !== fieldset.querySelector('input[name="' + group + '"]')) return;
                if (!checked) addError(field.closest('.form-group') || field.parentElement, 'Ce champ est requis.');
                return;
            }
            if (field.type === 'checkbox') {
                if (!field.checked) addError(field.closest('.form-group') || field.parentElement, 'Vous devez cocher cette case.');
                return;
            }
            if (field.tagName === 'SELECT') {
                if (!field.value) {
                    field.classList.add('error');
                    addError(field.closest('.form-group') || field.parentElement, 'Ce champ est requis.');
                }
                return;
            }
            if (!field.value.trim()) {
                field.classList.add('error');
                addError(field.closest('.form-group') || field.parentElement, 'Ce champ est requis.');
            }
        });

        const emailField = fieldset.querySelector('input[type="email"]');
        if (emailField && emailField.value.trim()) {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailField.value.trim())) {
                emailField.classList.add('error');
                addError(emailField.closest('.form-group') || emailField.parentElement, 'Adresse e-mail invalide.');
            }
        }

        return valid;
    }

    /* ================================================================
       RÉCAPITULATIF
       ================================================================ */
    const LABELS = {
        faculte               : 'Faculté / École',
        serie_diplome         : 'Série / Spécialité',
        diplome_admission     : "Diplôme d'admission",
        niveau_lmd            : 'Niveau LMD',
        type_formation        : 'Type de formation',
        filiere_1             : '1er choix de filière',
        filiere_2             : '2e choix de filière',
        filiere_3             : '3e choix de filière',
        annee_obtention       : "Année d'obtention",
        moyenne_diplome       : 'Moyenne obtenue',
        mention               : 'Mention',
        statut_etudiant       : 'Statut',
        nom                   : 'Nom',
        prenom                : 'Prénom(s)',
        sexe                  : 'Sexe',
        date_naissance        : 'Date de naissance',
        lieu_naissance        : 'Lieu de naissance',
        nationalite           : 'Nationalité',
        premiere_langue       : 'Première langue',
        situation_matrimoniale: 'Situation matrimoniale',
        statut_socio_professionnel: 'Statut socio-professionnel',
        handicap              : 'Situation de handicap',
        email                 : 'Adresse e-mail',
        telephone             : 'Téléphone(s)',
        adresse               : 'Adresse actuelle',
        region_origine        : "Région d'origine",
        departement_origine   : "Département d'origine",
        commune_origine       : "Commune d'origine",
        nom_pere              : 'Nom du père',
        numero_pere           : 'Numéro du père',
        profession_pere       : 'Profession du père',
        nom_mere              : 'Nom de la mère',
        numero_mere           : 'Numéro de la mère',
        profession_mere       : 'Profession de la mère',
        nom_tuteur            : 'Nom du tuteur',
        numero_tuteur         : 'Numéro du tuteur',
        sport_prefere         : 'Sport préféré',
        art_pratique          : 'Art pratiqué',
        numero_certificat_medical : 'N° certificat médical',
        lieu_obtention_certificat : "Lieu d'obtention du certificat",
    };

    const SECTIONS = {
    formation : ['faculte','diplome_admission','type_formation','serie_diplome','filiere_1','moyenne_diplome','filiere_2','mention','filiere_3','annee_obtention','niveau_lmd','statut_etudiant'],
    etatCivil : ['nom','nationalite','prenom','premiere_langue','lieu_naissance','situation_matrimoniale','date_naissance','statut_socio_professionnel','sexe','handicap'],
    contact : ['telephone','nom_pere','email','numero_pere','adresse','profession_pere','departement_origine','nom_mere','commune_origine','numero_mere','region_origine','profession_mere','nom_tuteur','numero_tuteur'],
    divers    : ['sport_prefere',,'numero_certificat_medical','art_pratique','lieu_obtention_certificat'],
    };

    

    const SECTION_TITLES = {
        formation : 'Formation choisie',
        etatCivil : 'État civil',
        contact   : 'Contact & origine',
        divers    : 'Informations diverses',
    };

    function getFieldValue(fieldName) {
        if (fieldName === 'telephone') {
            const vals = [];
            document.querySelectorAll('input[name="telephone[]"]').forEach(function (i) { if (i.value.trim()) vals.push(i.value.trim()); });
            return vals.join(', ');
        }
        if (fieldName === 'serie_diplome') {
            const sel = document.getElementById('serie_diplome_select');
            if (!sel) return '';
            const opt = sel.options[sel.selectedIndex];
            return opt ? opt.text : '';
        }
        if (fieldName === 'type_formation') {
            return selectType.value === 'pro' ? 'Formation Professionnelle (LP)' : 'Formation Initiale (Classique)';
        }
        if (fieldName === 'handicap') {
            const checked = form.querySelector('input[name="handicap"]:checked');
            return checked ? (checked.value === 'oui' ? 'Oui' : 'Non') : '';
        }
        const el = form.querySelector('[name="' + fieldName + '"]');
        if (!el) return '';
        if (el.type === 'radio') {
            const checked = form.querySelector('input[name="' + fieldName + '"]:checked');
            return checked ? (checked.value === 'M' ? 'Masculin' : 'Féminin') : '';
        }
        if (el.tagName === 'SELECT') {
            const opt = el.options[el.selectedIndex];
            const txt = opt ? opt.text : '';
            return txt.startsWith('—') ? '' : txt;
        }
        return el.value.trim();
    }

    function buildRecap() {
        const container = document.getElementById('recap-content');
        if (!container) return;
        container.innerHTML = '';

        Object.keys(SECTIONS).forEach(function (key) {
            const section = document.createElement('div');
            section.className = 'recap-section';
            const title = document.createElement('div');
            title.className   = 'recap-section-title';
            title.textContent = SECTION_TITLES[key];
            section.appendChild(title);
            const grid = document.createElement('div');
            grid.style.cssText = 'display:grid;grid-template-columns:1fr 1fr;gap:14px;';
            SECTIONS[key].forEach(function (fieldName) {
                const value = getFieldValue(fieldName);
                const item  = document.createElement('div');
                item.className = 'recap-item';
                item.innerHTML =
                    '<span class="recap-label">' + (LABELS[fieldName] || fieldName) + '</span>' +
                    '<span class="recap-value">' + (value || '') + '</span>';
                grid.appendChild(item);
            });
            section.appendChild(grid);
            container.appendChild(section);
        });
    }

    /* ================================================================
       ÉVÉNEMENTS
       ================================================================ */
    /* ================================================================
       COLLECTE DES DONNÉES DU FORMULAIRE (tous les champs, toutes étapes)
       ================================================================ */
    function collectFormData() {
        const data = {};
        const skip = ['action', 'preinscription_nonce', '_wpnonce', '_wp_http_referer', 'consent'];

        form.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (!el.name || skip.includes(el.name)) return;

            if (el.type === 'radio') {
                if (el.checked) data[el.name] = el.value;
                return;
            }
            if (el.type === 'checkbox') {
                return; // consent exclu, pas d'autre checkbox dans le formulaire
            }
            if (el.name.slice(-2) === '[]') {
                const key = el.name.slice(0, -2);
                if (!data[key]) data[key] = [];
                if (el.value.trim()) data[key].push(el.value.trim());
                return;
            }
            data[el.name] = el.value;
        });

        return data;
    }

    /* ================================================================
       SAUVEGARDE DE LA PROGRESSION — non bloquante (erreur silencieuse
       en console, la navigation continue dans tous les cas)
       ================================================================ */
    function saveProgression(etape) {
        const numeroDossierEl = document.getElementById('numero_dossier');
        const numeroDossier = numeroDossierEl ? numeroDossierEl.value : '';
        if (!numeroDossier) return;

        const donnees = collectFormData();

        uebFetch('ueb_save_progression', {
            numero_dossier: numeroDossier,
            etape: etape,
            donnees: JSON.stringify(donnees)
        });
        // uebFetch logue déjà toute erreur en console ; on n'attend pas
        // la réponse et on ne bloque jamais la navigation ici.
    }

    /* ================================================================
       SAUVEGARDE AUTOMATIQUE SUR PERTE DE FOCUS (blur), avec anti-rebond
       pour ne pas déclencher un appel réseau à chaque frappe. Complète la
       sauvegarde sur "Suivant" : accord du chef de projet (Esso Daniel)
       pour éviter la perte de champs non validés en cas de fermeture
       accidentelle de l'onglet ou de coupure réseau.
       ================================================================ */
    let autoSaveTimeout = null;

    function scheduleAutoSave() {
        if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
        autoSaveTimeout = setTimeout(function () {
            saveProgression(currentStep);
        }, 600);
    }

    form.addEventListener('blur', function (e) {
        const el = e.target;
        if (!el || !el.name) return;
        // Ignore les clics sur les boutons/labels, ne cible que les vrais champs.
        const tag = el.tagName;
        if (tag !== 'INPUT' && tag !== 'SELECT' && tag !== 'TEXTAREA') return;
        scheduleAutoSave();
    }, true); // capture=true, car "blur" ne bubble pas nativement

    form.querySelectorAll('.btn-next').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const next = parseInt(btn.dataset.next, 10);
            if (!validateStep(currentStep)) return;
            saveProgression(next);
            if (next === 5) buildRecap();
            showStep(next);
        });
    });

    form.querySelectorAll('.btn-prev').forEach(function (btn) {
        btn.addEventListener('click', function () { showStep(parseInt(btn.dataset.prev, 10)); });
    });

    form.addEventListener('input', function (e) {
        if (e.target.classList.contains('error')) {
            e.target.classList.remove('error');
            const err = e.target.parentElement.querySelector('.field-error');
            if (err) err.remove();
        }
    });

    form.addEventListener('submit', function (e) {
        if (!validateStep(5)) e.preventDefault();
    });

    /* ================================================================
       REPRISE D'UN DOSSIER
       ================================================================ */
    async function applyResumeData(numeroDossier, etapeAtteinte, donnees) {
        const numeroHidden = document.getElementById('numero_dossier');
        if (numeroHidden) numeroHidden.value = numeroDossier;
        const numeroBanner = document.querySelector('.dossier-banner-numero');
        if (numeroBanner) numeroBanner.textContent = numeroDossier;

        const simpleFields = [
            'nom', 'prenom', 'date_naissance', 'lieu_naissance',
            'email', 'adresse',
            'nom_pere', 'numero_pere', 'profession_pere',
            'nom_mere', 'numero_mere', 'profession_mere',
            'nom_tuteur', 'numero_tuteur',
            'annee_obtention', 'moyenne_diplome',
            'numero_certificat_medical', 'lieu_obtention_certificat'
        ];
        simpleFields.forEach(function (name) {
            if (donnees[name] === undefined) return;
            const el = form.querySelector('[name="' + name + '"]');
            if (el) el.value = donnees[name];
        });

        if (donnees.sexe) {
            const radio = form.querySelector('input[name="sexe"][value="' + donnees.sexe + '"]');
            if (radio) radio.checked = true;
        }

        if (donnees.handicap) {
            const radio = form.querySelector('input[name="handicap"][value="' + donnees.handicap + '"]');
            if (radio) radio.checked = true;
        }

        fillTelRows('telephones-container', 'telephone[]', donnees.telephone);

        // Les listes facultés/diplômes/régions/statuts/niveaux/mentions/
        // langues/sports/arts sont déjà en cours de chargement depuis
        // l'arrivée sur la page : on attend qu'elles soient prêtes.
        await Promise.all([
            facultesPromise, diplomesPromise, regionsPromise, statutsPromise,
            nationalitesPromise, situationsPromise, niveauxPromise, mentionsPromise,
            statutsEtudiantPromise, languesPromise, sportsPromise, artsPromise
        ]);

        if (donnees.statut_socio_professionnel) {
            const el = document.getElementById('statut_socio_professionnel');
            if (el) el.value = donnees.statut_socio_professionnel;
        }

        if (donnees.nationalite) {
            const elNat = document.getElementById('nationalite');
            if (elNat) elNat.value = donnees.nationalite;
        }

        if (donnees.situation_matrimoniale) {
            const elSit = document.getElementById('situation_matrimoniale');
            if (elSit) elSit.value = donnees.situation_matrimoniale;
        }

        if (donnees.niveau_lmd) {
            const elNiveau = document.getElementById('niveau_lmd');
            if (elNiveau) elNiveau.value = donnees.niveau_lmd;
        }

        if (donnees.mention) {
            const elMention = document.getElementById('mention');
            if (elMention) elMention.value = donnees.mention;
        }

        if (donnees.statut_etudiant) {
            const elStatutEtu = document.getElementById('statut_etudiant');
            if (elStatutEtu) elStatutEtu.value = donnees.statut_etudiant;
        }

        if (donnees.premiere_langue) {
            const elLangue = document.getElementById('premiere_langue');
            if (elLangue) elLangue.value = donnees.premiere_langue;
        }

        if (donnees.sport_prefere) {
            const elSport = document.getElementById('sport_prefere');
            if (elSport) elSport.value = donnees.sport_prefere;
        }

        if (donnees.art_pratique) {
            const elArt = document.getElementById('art_pratique');
            if (elArt) elArt.value = donnees.art_pratique;
        }

        if (donnees.faculte) selectFaculte.value = donnees.faculte;
        if (donnees.diplome_admission) selectDiplome.value = donnees.diplome_admission;

        const type = donnees.type_formation || 'classique';
        const faculteCode = getFaculteCode(donnees.faculte);
        typeGroup.style.display = (faculteCode === 'FS') ? '' : 'none';
        selectType.value = type;

        if (donnees.faculte && donnees.diplome_admission) {
            const series = await uebFetch('ueb_get_specialites', {
                faculte_id: donnees.faculte,
                diplome_id: donnees.diplome_admission
            });
            fillSelect(serieSelect, series, '— Choisir la série —', true);
            if (donnees.serie_diplome) {
                serieSelect.value = donnees.serie_diplome;
                if (serieHidden) serieHidden.value = donnees.serie_diplome;
            }
        }

        if (donnees.faculte) {
            if (type === 'pro' && faculteCode === 'FS' && proNotice) {
                proNotice.style.display = '';
            }

            filiere1Data = await uebFetch('ueb_get_filieres', {
                faculte_id: donnees.faculte,
                type_formation: type
            });

            filiere23Data = (type === 'pro')
                ? await uebFetch('ueb_get_filieres', { faculte_id: donnees.faculte, type_formation: 'classique' })
                : filiere1Data;

            // Premier passage : peuple les 3 selects avec la liste complète
            // pour pouvoir y injecter les valeurs sauvegardées.
            refreshFiliereCrossFilter();
            if (donnees.filiere_1 && selectFiliere1.querySelector('option[value="' + donnees.filiere_1 + '"]')) {
                selectFiliere1.value = donnees.filiere_1;
            }
            if (donnees.filiere_2 && selectFiliere2.querySelector('option[value="' + donnees.filiere_2 + '"]')) {
                selectFiliere2.value = donnees.filiere_2;
            }
            if (donnees.filiere_3 && selectFiliere3.querySelector('option[value="' + donnees.filiere_3 + '"]')) {
                selectFiliere3.value = donnees.filiere_3;
            }
            // Second passage : réapplique le filtrage croisé maintenant que
            // les 3 valeurs sont replacées (retire les doublons des autres
            // listes, comme lors d'une saisie normale).
            refreshFiliereCrossFilter();
        }

        if (donnees.region_origine) {
            selectRegion.value = donnees.region_origine;
            const departements = await uebFetch('ueb_get_departements', { region_id: donnees.region_origine });
            fillSelect(selectDepartement, departements, '— Choisir —', true);

            if (donnees.departement_origine) {
                selectDepartement.value = donnees.departement_origine;
                const communes = await uebFetch('ueb_get_communes', { departement_id: donnees.departement_origine });
                fillSelect(selectCommune, communes, '— Choisir —', true);
                if (donnees.commune_origine) selectCommune.value = donnees.commune_origine;
            }
        }

        const cible = etapeAtteinte || 1;
        if (cible >= 5) buildRecap();
        showStep(cible);
    }

    const btnToggleReprise   = document.getElementById('btn-toggle-reprise');
    const reprisePanel       = document.getElementById('reprise-panel');
    const repriseInput       = document.getElementById('reprise-numero');
    const btnRepriseValider  = document.getElementById('btn-reprise-valider');
    const repriseMessage     = document.getElementById('reprise-message');

    function showRepriseMessage(msg, isError) {
        if (!repriseMessage) return;
        repriseMessage.textContent = msg;
        repriseMessage.style.display = '';
        repriseMessage.className = 'reprise-message' + (isError ? ' reprise-message--error' : ' reprise-message--success');
    }

    if (btnToggleReprise && reprisePanel) {
        btnToggleReprise.addEventListener('click', function () {
            reprisePanel.style.display = (reprisePanel.style.display === 'none') ? '' : 'none';
        });
    }

    if (btnRepriseValider) {
        btnRepriseValider.addEventListener('click', function () {
            const numero = (repriseInput.value || '').trim();
            if (!numero) {
                showRepriseMessage('Merci de saisir un numéro de dossier.', true);
                return;
            }

            btnRepriseValider.disabled = true;
            btnRepriseValider.textContent = 'Recherche...';

            uebFetchRaw('ueb_get_progression', { numero_dossier: numero }).then(function (json) {
                btnRepriseValider.disabled = false;
                btnRepriseValider.textContent = 'Reprendre';

                if (!json || !json.success) {
                    showRepriseMessage((json && json.data && json.data.message) || 'Numéro introuvable.', true);
                    return;
                }

                showRepriseMessage('Dossier retrouvé, chargement en cours...', false);
                applyResumeData(json.data.numero_dossier, json.data.etape_atteinte, json.data.donnees || {}).then(function () {
                    reprisePanel.style.display = 'none';
                    btnToggleReprise.style.display = 'none';
                    repriseMessage.style.display = 'none';
                });
            });
        });
    }

    if (new URLSearchParams(window.location.search).get('reprise') === '1' && reprisePanel) {
        reprisePanel.style.display = '';
    }

    showStep(1);

}());