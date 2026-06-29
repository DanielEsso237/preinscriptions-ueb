(function () {
    'use strict';

    const form = document.getElementById('form-preinscription');
    if (!form) return;

    const steps      = form.querySelectorAll('.form-step');
    const navItems   = document.querySelectorAll('.steps-nav .step-item');
    const separators = document.querySelectorAll('.steps-nav .step-separator');
    let currentStep  = 1;

    /*DONNÉES FILIÈRES PAR FACULTÉ*/
    const FILIERES = {
        FS: [
            { value: 'TIC',   label: 'TIC (Technologies de l\'Information et de la Communication)' },
            { value: 'PHY',   label: 'Physique Appliquée' },
            { value: 'CHIM',  label: 'Chimie Appliquée' },
            { value: 'ROSE',  label: 'ROSE (Recherche Opérationnelle et Économétrie)' },
            { value: 'GEO',   label: 'Géosciences et Environnement' },
            { value: 'BIO',   label: 'Biotechnologie et Pharmacognosie' },
        ],
        FSJP: [
            { value: 'DF',    label: 'Droit Fondamental' },
        ],
        FASEG: [],
        FALSH: [],
    };

    const PLACEHOLDER_FILIERE    = '— Choisir d\'abord une faculté —';
    const PLACEHOLDER_NO_DATA    = '— Filières à venir —';
    const PLACEHOLDER_CHOOSE     = '— Choisir une filière —';
    const PLACEHOLDER_2ND        = '— Aucun deuxième choix —';

    /*FILIÈRES DYNAMIQUES*/
    const selectFaculte  = document.getElementById('faculte');
    const selectFiliere1 = document.getElementById('filiere_1');
    const selectFiliere2 = document.getElementById('filiere_2');

    function buildOptions(select, filieres, placeholder, excludeValue) {
        select.innerHTML = '';

        const opt0 = document.createElement('option');
        opt0.value = '';
        opt0.textContent = placeholder;
        select.appendChild(opt0);

        filieres.forEach(function (f) {
            if (f.value === excludeValue) return; 
            const opt = document.createElement('option');
            opt.value = f.value;
            opt.textContent = f.label;
            select.appendChild(opt);
        });
    }

    function updateFilieres() {
        const fac      = selectFaculte.value;
        const filieres = FILIERES[fac] || [];

        if (!fac) {
            // Pas de faculté choisie
            [selectFiliere1, selectFiliere2].forEach(function (s) {
                s.innerHTML = '<option value="">' + PLACEHOLDER_FILIERE + '</option>';
                s.disabled = true;
            });
            return;
        }

        if (filieres.length === 0) {
            // Faculté sans données encore
            [selectFiliere1, selectFiliere2].forEach(function (s) {
                s.innerHTML = '<option value="">' + PLACEHOLDER_NO_DATA + '</option>';
                s.disabled = true;
            });
            return;
        }

        // Remplir filière 1
        buildOptions(selectFiliere1, filieres, PLACEHOLDER_CHOOSE, null);
        selectFiliere1.disabled = false;

        // Filière 2 : dépend du 1er choix
        const excl = selectFiliere1.value || null;
        buildOptions(selectFiliere2, filieres, PLACEHOLDER_2ND, excl);
        selectFiliere2.disabled = filieres.length <= 1;
    }

    selectFaculte.addEventListener('change', updateFilieres);

    selectFiliere1.addEventListener('change', function () {
        const excl = selectFiliere1.value || null;
        const fac  = selectFaculte.value;
        buildOptions(selectFiliere2, FILIERES[fac] || [], PLACEHOLDER_2ND, excl);
        selectFiliere2.disabled = (FILIERES[fac] || []).length <= 1;
    });

    // Init au chargement (au cas où la page est rechargée avec valeurs)
    updateFilieres();

    /*SÉRIE : curseur positionné après le préfixe au focus*/
    const serieInput = document.getElementById('serie_diplome');
    if (serieInput) {
        serieInput.addEventListener('focus', function () {
            const len = this.value.length;
            this.setSelectionRange(len, len);
        });

        // Empêche de supprimer le préfixe "Série "
        serieInput.addEventListener('input', function () {
            const prefix = 'Série ';
            if (!this.value.startsWith(prefix)) {
                this.value = prefix;
                this.setSelectionRange(prefix.length, prefix.length);
            }
        });
    }

    function makeTelRow(name, required) {
        const row = document.createElement('div');
        row.className = 'tel-row';

        const input = document.createElement('input');
        input.type        = 'tel';
        input.name        = name;
        input.placeholder = '6X XX XX XX XX';
        input.className   = 'tel-input';
        if (required) input.required = true;

        const btnRemove = document.createElement('button');
        btnRemove.type      = 'button';
        btnRemove.className = 'btn-remove-tel';
        btnRemove.setAttribute('aria-label', 'Supprimer ce numéro');
        btnRemove.textContent = '×';
        btnRemove.addEventListener('click', function () {
            row.remove();
        });

        row.appendChild(input);
        row.appendChild(btnRemove);
        return row;
    }

    // Bouton "Ajouter un numéro" — téléphone étudiant
    const btnAddTel = document.getElementById('btn-add-tel');
    if (btnAddTel) {
        btnAddTel.addEventListener('click', function () {
            const container = document.getElementById('telephones-container');
            container.appendChild(makeTelRow('telephone[]', false));
        });
    }

    // Boutons "Ajouter un numéro" génériques (data-target / data-name)
    document.querySelectorAll('.btn-add-field[data-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const container = document.getElementById(btn.dataset.target);
            const name      = btn.dataset.name || 'tel[]';
            if (container) container.appendChild(makeTelRow(name, false));
        });
    });

    /*NAVIGATION ENTRE ÉTAPES*/
    function showStep(n) {
        steps.forEach(function (s) { s.classList.remove('active'); });
        const target = form.querySelector('.form-step[data-step="' + n + '"]');
        if (target) target.classList.add('active');

        navItems.forEach(function (item, i) {
            const stepN = i + 1;
            item.classList.remove('active', 'done');
            if (stepN === n) item.classList.add('active');
            if (stepN < n)  item.classList.add('done');
        });

        separators.forEach(function (sep, i) {
            sep.classList.toggle('done', i + 1 < n);
        });

        currentStep = n;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /*VALIDATION*/
    function validateStep(n) {
        const fieldset = form.querySelector('.form-step[data-step="' + n + '"]');
        if (!fieldset) return true;

        // Nettoyer erreurs
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
                // Traiter une seule fois par groupe
                if (field !== fieldset.querySelector('input[name="' + group + '"]')) return;
                if (!checked) addError(field.closest('.form-group') || field.parentElement, 'Ce champ est requis.');
                return;
            }
            if (field.type === 'checkbox') {
                if (!field.checked) addError(field.closest('.form-group') || field.parentElement, 'Vous devez cocher cette case.');
                return;
            }
            if (!field.value.trim()) {
                field.classList.add('error');
                addError(field.closest('.form-group') || field.parentElement, 'Ce champ est requis.');
            }
        });

        // Validation email
        const emailField = fieldset.querySelector('input[type="email"]');
        if (emailField && emailField.value.trim()) {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailField.value.trim())) {
                emailField.classList.add('error');
                addError(emailField.closest('.form-group') || emailField.parentElement, 'Adresse e-mail invalide.');
            }
        }

        // Vérifier série : doit être plus que juste "Série "
        if (n === 1 && serieInput) {
            if (serieInput.value.trim() === 'Série' || serieInput.value.trim() === 'Série ') {
                serieInput.classList.add('error');
                addError(serieInput.closest('.form-group') || serieInput.parentElement, 'Précise la série ou spécialité après "Série ".');
                valid = false;
            }
        }

        return valid;
    }

    const LABELS = {
        faculte               : 'Faculté / École',
        diplome_admission     : "Diplôme d'admission",
        serie_diplome         : 'Série / Spécialité',
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
        // Champs multiples (téléphones)
        if (fieldName === 'telephone') {
            const inputs = form.querySelectorAll('input[name="telephone[]"]');
            const vals   = [];
            inputs.forEach(function (i) { if (i.value.trim()) vals.push(i.value.trim()); });
            return vals.join(', ') || '';
        }
        if (fieldName === 'tel_tuteur') {
            const inputs = form.querySelectorAll('input[name="tel_tuteur[]"]');
            const vals   = [];
            inputs.forEach(function (i) { if (i.value.trim()) vals.push(i.value.trim()); });
            return vals.join(', ') || '';
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
            return (txt === '— Choisir —' || txt === PLACEHOLDER_CHOOSE || txt === PLACEHOLDER_NO_DATA || txt === PLACEHOLDER_FILIERE) ? '' : txt;
        }
        return el.value.trim();
    }

    function buildRecap() {
        const container = document.getElementById('recap-content');
        if (!container) return;
        container.innerHTML = '';

        Object.keys(SECTIONS).forEach(function (key) {
            const fields = SECTIONS[key];
            const section = document.createElement('div');
            section.className = 'recap-section';

            const title = document.createElement('div');
            title.className   = 'recap-section-title';
            title.textContent = SECTION_TITLES[key];
            section.appendChild(title);

            const grid = document.createElement('div');
            grid.style.cssText = 'display:grid;grid-template-columns:1fr 1fr;gap:14px;';

            fields.forEach(function (fieldName) {
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

    /*ÉVÉNEMENTS NAVIGATION*/
    form.querySelectorAll('.btn-next').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const next = parseInt(btn.dataset.next, 10);
            if (!validateStep(currentStep)) return;
            if (next === 4) buildRecap();
            showStep(next);
        });
    });

    form.querySelectorAll('.btn-prev').forEach(function (btn) {
        btn.addEventListener('click', function () {
            showStep(parseInt(btn.dataset.prev, 10));
        });
    });

    // Retirer classe error à la saisie
    form.addEventListener('input', function (e) {
        if (e.target.classList.contains('error')) {
            e.target.classList.remove('error');
            const err = e.target.parentElement.querySelector('.field-error');
            if (err) err.remove();
        }
    });

    // Soumission finale
    form.addEventListener('submit', function (e) {
        if (!validateStep(4)) e.preventDefault();
    });

    // Init
    showStep(1);

}());