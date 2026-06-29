/**
 * form-preinscription.js  v3
 * - Filières dynamiques par faculté + type de formation
 * - Séries du bac dynamiques par faculté
 * - Validation numérique téléphone
 * - Navigation multi-étapes, récapitulatif
 */
(function () {
    'use strict';

    const form = document.getElementById('form-preinscription');
    if (!form) return;

    const steps      = form.querySelectorAll('.form-step');
    const navItems   = document.querySelectorAll('.steps-nav .step-item');
    const separators = document.querySelectorAll('.steps-nav .step-separator');
    let currentStep  = 1;

    /* ================================================================
       DONNÉES : SÉRIES PAR FACULTÉ + DIPLÔME
       Clé : SERIES[faculte][diplome]  ('bac' | 'gce_ol')
       ================================================================ */
    const SERIES = {
        FS: {
            bac:    [
                { value: 'C',  label: 'Série C — Mathématiques et Sciences Physiques' },
                { value: 'D',  label: 'Série D — Sciences Naturelles' },
                { value: 'TI', label: 'Série TI — Technique Industrielle' },
                { value: 'F',  label: 'Série F — Sciences Techniques' },
            ],
            gce_ol: [
                { value: 'GCE_OL_SCI', label: 'GCE O/L — Sciences' },
            ],
        },
        FALSH: {
            bac:    [
                { value: 'A',  label: 'Série A — Lettres, Philosophie, Sciences Sociales' },
                { value: 'B',  label: 'Série B — Sciences Économiques et Sociales' },
            ],
            gce_ol: [
                { value: 'GCE_OL_ART', label: 'GCE O/L — Arts & Humanities' },
                { value: 'GCE_OL_SOC', label: 'GCE O/L — Social Sciences' },
            ],
        },
        FSEG: {
            bac:    [
                { value: 'B',  label: 'Série B — Sciences Économiques et Sociales' },
                { value: 'G',  label: 'Série G — Techniques de Gestion' },
                { value: 'TI', label: 'Série TI — Technique Industrielle' },
                { value: 'C',  label: 'Série C — Mathématiques et Sciences Physiques' },
                { value: 'D',  label: 'Série D — Sciences Naturelles' },
            ],
            gce_ol: [
                { value: 'GCE_OL_COM', label: 'GCE O/L — Commerce / Economics' },
                { value: 'GCE_OL_GEN', label: 'GCE O/L — General' },
            ],
        },
        FSJP: {
            bac:    [
                { value: 'A',  label: 'Série A — Lettres, Philosophie, Sciences Sociales' },
                { value: 'B',  label: 'Série B — Sciences Économiques et Sociales' },
                { value: 'C',  label: 'Série C — Mathématiques et Sciences Physiques' },
                { value: 'D',  label: 'Série D — Sciences Naturelles' },
                { value: 'G',  label: 'Série G — Techniques de Gestion' },
            ],
            gce_ol: [
                { value: 'GCE_OL_ALL', label: 'GCE O/L — Toutes séries' },
            ],
        },
    };

    /* ================================================================
       DONNÉES : FILIÈRES PAR FACULTÉ + TYPE
       ================================================================ */
    const FILIERES = {
        FS: {
            classique: [
                { value: 'TIC',   label: 'TIC — Technologies de l\'Information et de la Communication' },
                { value: 'PHY',   label: 'Physique Appliquée' },
                { value: 'CHIM',  label: 'Chimie Appliquée' },
                { value: 'GEO',   label: 'Géosciences et Environnement' },
                { value: 'ROSE',  label: 'ROSE — Recherche Opérationnelle et Économétrie' },
                { value: 'BIO',   label: 'Biotechnologie et Pharmacognosie' },
            ],
            pro: [
                { value: 'LP_BIO_MED',  label: 'LP Sciences Biomédicales et Médico-Sanitaires' },
                { value: 'LP_BIO_AGR',  label: 'LP Sciences Biologiques Appliquées à l\'Agriculture' },
            ],
        },
        FALSH: {
            classique: [
                { value: 'LMF',   label: 'Lettres Modernes Françaises' },
                { value: 'LEA',   label: 'Langues Étrangères Appliquées' },
                { value: 'HIST',  label: 'Histoire' },
                { value: 'GEO',   label: 'Géographie' },
                { value: 'PHILO', label: 'Philosophie' },
                { value: 'SOCIO', label: 'Sociologie' },
            ],
            pro: [],
        },
        FSEG: {
            classique: [
                { value: 'ECO',   label: 'Économie' },
                { value: 'GEST',  label: 'Gestion' },
                { value: 'COMPTA',label: 'Comptabilité et Finance' },
                { value: 'BANQUE',label: 'Banque et Finance' },
                { value: 'MKT',   label: 'Marketing' },
            ],
            pro: [],
        },
        FSJP: {
            classique: [
                { value: 'DPRIV', label: 'Droit Privé' },
                { value: 'DPUB',  label: 'Droit Public' },
                { value: 'SCPOL', label: 'Science Politique' },
                { value: 'RI',    label: 'Relations Internationales' },
            ],
            pro: [],
        },
    };

    /* ================================================================
       ÉLÉMENTS DOM
       ================================================================ */
    const selectFaculte   = document.getElementById('faculte');
    const selectType      = document.getElementById('type_formation');
    const selectFiliere1  = document.getElementById('filiere_1');
    const selectFiliere2  = document.getElementById('filiere_2');
    const serieContainer  = document.getElementById('serie-container');
    const serieSelect     = document.getElementById('serie_diplome_select');
    const niveauInput     = document.getElementById('niveau_lmd');
    const proNotice       = document.getElementById('pro-filiere-notice');
    const typeGroup       = document.getElementById('type-formation-group');

    /* ================================================================
       SÉRIES DYNAMIQUES — par faculté ET diplôme
       ================================================================ */
    const selectDiplome = document.getElementById('diplome_admission');

    function updateSeries() {
        const fac     = selectFaculte.value;
        const diplome = selectDiplome ? selectDiplome.value : '';
        const facData = SERIES[fac] || {};
        const series  = (diplome && facData[diplome]) ? facData[diplome] : [];

        serieSelect.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = (!fac || !diplome)
            ? '— Choisir d'abord la faculté et le diplôme —'
            : '— Choisir la série —';
        serieSelect.appendChild(placeholder);

        series.forEach(function (s) {
            const opt = document.createElement('option');
            opt.value       = s.value;
            opt.textContent = s.label;
            serieSelect.appendChild(opt);
        });

        serieSelect.disabled = series.length === 0;
        // Reset hidden
        if (serieHiddenEarly) serieHiddenEarly.value = '';
    }

    // Référence early pour le reset dans updateSeries
    const serieHiddenEarly = document.getElementById('serie_diplome');

    /* ================================================================
       TYPE DE FORMATION : visible uniquement pour FS
       ================================================================ */
    function updateTypeFormation() {
        const fac = selectFaculte.value;
        if (fac === 'FS') {
            typeGroup.style.display = '';
            selectType.disabled = false;
        } else {
            // Autres facultés : dossier uniquement, champ caché
            typeGroup.style.display = 'none';
            selectType.value    = 'classique';
            selectType.disabled = false;
        }
        updateFilieres();
    }

    /* ================================================================
       FILIÈRES DYNAMIQUES
       ================================================================ */
    function buildOptions(select, filieres, placeholder, excludeValue) {
        select.innerHTML = '';
        const opt0 = document.createElement('option');
        opt0.value = '';
        opt0.textContent = placeholder;
        select.appendChild(opt0);
        filieres.forEach(function (f) {
            if (f.value === excludeValue) return;
            const opt = document.createElement('option');
            opt.value       = f.value;
            opt.textContent = f.label;
            select.appendChild(opt);
        });
    }

    function updateFilieres() {
        const fac  = selectFaculte.value;
        const type = selectType.value || 'classique';
        const data = FILIERES[fac] || null;

        // Cacher la notice pro par défaut
        if (proNotice) proNotice.style.display = 'none';

        if (!fac || !data) {
            [selectFiliere1, selectFiliere2].forEach(function (s) {
                s.innerHTML = '<option value="">— Choisir d\'abord une faculté —</option>';
                s.disabled = true;
            });
            return;
        }

        let filieres1 = [];
        let filieres2 = [];

        if (type === 'pro' && fac === 'FS') {
            // Filières LP uniquement en 1er choix
            filieres1 = data.pro;
            // 2e choix : filières classiques (en attente du concours)
            filieres2 = data.classique;
            if (proNotice) proNotice.style.display = '';
        } else {
            // Classique
            filieres1 = data.classique;
            filieres2 = data.classique;
        }

        if (filieres1.length === 0) {
            [selectFiliere1, selectFiliere2].forEach(function (s) {
                s.innerHTML = '<option value="">— Filières à venir —</option>';
                s.disabled = true;
            });
            return;
        }

        buildOptions(selectFiliere1, filieres1, '— Choisir une filière —', null);
        selectFiliere1.disabled = false;

        const excl = selectFiliere1.value || null;
        buildOptions(selectFiliere2, filieres2, '— Aucun deuxième choix (optionnel) —', excl);
        selectFiliere2.disabled = filieres2.length <= 1;
    }

    selectFaculte.addEventListener('change', function () {
        updateSeries();
        updateTypeFormation();
    });

    if (selectDiplome) {
        selectDiplome.addEventListener('change', updateSeries);
    }

    selectType.addEventListener('change', updateFilieres);

    selectFiliere1.addEventListener('change', function () {
        const fac  = selectFaculte.value;
        const type = selectType.value || 'classique';
        const data = FILIERES[fac] || null;
        if (!data) return;
        const pool = (type === 'pro' && fac === 'FS') ? data.classique : data.classique;
        buildOptions(selectFiliere2, pool, '— Aucun deuxième choix (optionnel) —', selectFiliere1.value || null);
        selectFiliere2.disabled = pool.length <= 1;
    });

    // Init
    updateSeries();
    updateTypeFormation();

    /* ================================================================
       VALIDATION + FORMAT TÉLÉPHONE  →  6XX XX XX XX  (9 chiffres)
       ================================================================ */
    function formatTel(raw) {
        // Garder uniquement les chiffres
        const digits = raw.replace(/\D/g, '').slice(0, 9);
        // Appliquer le masque 6XX XX XX XX
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
            // Repositionner le curseur
            const diff = this.value.length - before;
            this.setSelectionRange(pos + diff, pos + diff);
        });
        input.addEventListener('keydown', function (e) {
            const allowed = ['Backspace','Delete','ArrowLeft','ArrowRight','Tab','Home','End'];
            if (allowed.includes(e.key)) return;
            // Bloquer tout ce qui n'est pas un chiffre
            if (!/^[0-9]$/.test(e.key)) {
                e.preventDefault();
            }
        });
        // Placeholder dynamique
        input.placeholder = '6XX XX XX XX';
        input.maxLength   = 12; // 9 chiffres + 3 espaces
    }

    // Appliquer sur tous les champs tel existants
    document.querySelectorAll('input[type="tel"]').forEach(enforceTelInput);

    /* ================================================================
       TÉLÉPHONES MULTIPLES
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

    const btnAddTel = document.getElementById('btn-add-tel');
    if (btnAddTel) {
        btnAddTel.addEventListener('click', function () {
            document.getElementById('telephones-container').appendChild(makeTelRow('telephone[]', false));
        });
    }

    document.querySelectorAll('.btn-add-field[data-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const container = document.getElementById(btn.dataset.target);
            if (container) container.appendChild(makeTelRow(btn.dataset.name || 'tel[]', false));
        });
    });

    /* ================================================================
       NAVIGATION ENTRE ÉTAPES
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
            if (field.id === 'serie_diplome_select') {
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

        // Email
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
        annee_obtention       : "Année d'obtention",
        nom                   : 'Nom',
        prenom                : 'Prénom(s)',
        sexe                  : 'Sexe',
        date_naissance        : 'Date de naissance',
        lieu_naissance        : 'Lieu de naissance',
        nationalite           : 'Nationalité',
        situation_matrimoniale: 'Situation matrimoniale',
        email                 : 'Adresse e-mail',
        telephone             : 'Téléphone(s)',
        adresse               : 'Adresse actuelle',
        region_origine        : "Région d'origine",
        departement_origine   : "Département d'origine",
        arrondissement_origine: "Arrondissement d'origine",
        nom_pere              : 'Nom du père',
        nom_mere              : 'Nom de la mère',
        tel_tuteur            : 'Tél. tuteur / parent',
        profession_pere       : 'Profession du père',
    };

    const SECTIONS = {
        formation : ['faculte','diplome_admission','serie_diplome','niveau_lmd','type_formation','filiere_1','filiere_2','annee_obtention'],
        etatCivil : ['nom','prenom','sexe','date_naissance','lieu_naissance','nationalite','situation_matrimoniale'],
        contact   : ['email','telephone','adresse','region_origine','departement_origine','arrondissement_origine','nom_pere','nom_mere','tel_tuteur','profession_pere'],
    };

    const SECTION_TITLES = {
        formation : 'Formation choisie',
        etatCivil : 'État civil',
        contact   : 'Contact & origine',
    };

    function getFieldValue(fieldName) {
        if (fieldName === 'telephone') {
            const vals = [];
            document.querySelectorAll('input[name="telephone[]"]').forEach(function (i) { if (i.value.trim()) vals.push(i.value.trim()); });
            return vals.join(', ');
        }
        if (fieldName === 'tel_tuteur') {
            const vals = [];
            document.querySelectorAll('input[name="tel_tuteur[]"]').forEach(function (i) { if (i.value.trim()) vals.push(i.value.trim()); });
            return vals.join(', ');
        }
        if (fieldName === 'serie_diplome') {
            const sel = document.getElementById('serie_diplome_select');
            if (!sel) return '';
            const opt = sel.options[sel.selectedIndex];
            return opt ? opt.text.split('—')[0].trim() : '';
        }
        if (fieldName === 'type_formation') {
            return selectType.value === 'pro' ? 'Formation Professionnelle (LP)' : 'Formation Initiale (Classique)';
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
    form.querySelectorAll('.btn-next').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const next = parseInt(btn.dataset.next, 10);
            if (!validateStep(currentStep)) return;
            if (next === 4) buildRecap();
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
        if (!validateStep(4)) e.preventDefault();
    });

    showStep(1);

    /* ================================================================
       SYNC SELECT SÉRIE → CHAMP HIDDEN (pour soumission PHP)
       ================================================================ */
    const serieHidden = document.getElementById('serie_diplome');
    const serieSelectEl = document.getElementById('serie_diplome_select');

    if (serieSelectEl && serieHidden) {
        serieSelectEl.addEventListener('change', function () {
            serieHidden.value = this.value;
        });
    }


}());