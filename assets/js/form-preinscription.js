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
    let diplomesCache = [];
    let niveauxCache  = [];
    let mentionsCache = [];

    // Diplômes pour lesquels "Série / Spécialité" a un sens (séries du
    // bac / GCE O-Level). Pour les autres (relevés de notes, licence,
    // master), ce champ est masqué et non requis.
    const DIPLOMES_AVEC_SERIE = ['bac', 'gce_ol'];

    // Listes "brutes" des filières (avant filtrage croisé), mises à jour
    // à chaque changement de faculté/type de formation, et relues par
    // refreshFiliereCrossFilter() ci-dessous.
    let filiere1Data  = []; // options pour le 1er choix
    let filiere23Data = []; // options pour les 2e et 3e choix

    function getFaculteCode(faculteId) {
        const f = facultesCache.find(function (x) { return String(x.id) === String(faculteId); });
        return f ? f.code : '';
    }

    function getDiplome(diplomeId) {
        return diplomesCache.find(function (x) { return String(x.id) === String(diplomeId); }) || null;
    }

    function getDiplomeCode(diplomeId) {
        const d = getDiplome(diplomeId);
        return d ? d.code : '';
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
    const serieContainer  = document.getElementById('serie-container');
    const proNotice       = document.getElementById('pro-filiere-notice');
    const typeGroup       = document.getElementById('type-formation-group');
    const serieHidden     = document.getElementById('serie_diplome');
    const niveauSelect    = document.getElementById('niveau_lmd_select');
    const niveauHidden    = document.getElementById('niveau_lmd');

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
        diplomesCache = data;
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

    // Niveau LMD : le select n'est pas rempli au chargement — il n'affiche
    // que les niveaux ouverts par le diplôme d'admission choisi (cf.
    // updateNiveauxDisponibles ci-dessous). On garde donc seulement le
    // cache de la liste complète, dans lequel on pioche ensuite.
    const niveauxPromise = uebFetch('ueb_get_niveaux_lmd').then(function (data) {
        niveauxCache = data;
        return data;
    });

    // Mention : le candidat ne la choisit pas, elle découle de sa moyenne.
    // Chaque entrée porte son intervalle (moyenne_min / moyenne_max) tel
    // que défini par ueb_mentions_bareme() côté PHP.
    const mentionsPromise = uebFetch('ueb_get_mentions').then(function (data) {
        mentionsCache = data;
        fillSelect(mentionSelect, data, '— Saisis d\'abord ta moyenne —', false);
        appliquerMention();
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
       Masqué et non requis pour les diplômes hors DIPLOMES_AVEC_SERIE
       (relevés de notes, licence, master : pas de "série" au sens bac).
       ================================================================ */
    function updateSeries() {
        const faculteId   = selectFaculte.value;
        const diplomeId   = selectDiplome.value;
        const diplomeCode = getDiplomeCode(diplomeId);

        if (serieHidden) serieHidden.value = '';

        if (diplomeCode && DIPLOMES_AVEC_SERIE.indexOf(diplomeCode) === -1) {
            if (serieContainer) serieContainer.style.display = 'none';
            serieSelect.required = false;
            fillSelect(serieSelect, [], '', false);
            return;
        }

        if (serieContainer) serieContainer.style.display = '';
        serieSelect.required = true;

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
       MENTION — déduite de la moyenne, jamais choisie

       Le barème vit dans ueb_mentions_bareme() (inc/ajax-functions.php)
       et arrive avec chaque mention (moyenne_min / moyenne_max). Le
       select visible n'est qu'un affichage verrouillé ; c'est le champ
       caché #mention qui part au serveur — lequel recalcule de toute
       façon la mention à l'enregistrement.
       ================================================================ */
    const moyenneInput  = document.getElementById('moyenne_diplome');
    const mentionSelect = document.getElementById('mention_select');
    const mentionHidden = document.getElementById('mention');

    /** Mention correspondant à une moyenne, ou null hors barème. */
    function mentionPourMoyenne(valeur) {
        if (valeur === '' || valeur === null || isNaN(valeur)) return null;

        const moyenne = Math.round(parseFloat(valeur) * 100) / 100;

        return mentionsCache.find(function (m) {
            return m.moyenne_min !== null && m.moyenne_max !== null &&
                   moyenne >= parseFloat(m.moyenne_min) && moyenne <= parseFloat(m.moyenne_max);
        }) || null;
    }

    /** Reporte la mention déduite de la moyenne sur le select et le champ soumis. */
    function appliquerMention() {
        if (!mentionSelect || !mentionHidden) return;

        const mention = moyenneInput ? mentionPourMoyenne(moyenneInput.value) : null;

        if (!mention) {
            mentionSelect.value = '';
            mentionHidden.value = '';
            // Le placeholder dit pourquoi le champ est vide : moyenne
            // manquante, ou hors de l'intervalle noté [10, 20].
            const opt0 = mentionSelect.options[0];
            if (opt0) {
                opt0.textContent = (moyenneInput && moyenneInput.value.trim())
                    ? '— Moyenne hors barème (10 à 20) —'
                    : '— Saisis d\'abord ta moyenne —';
            }
            return;
        }

        mentionSelect.value = mention.id;
        mentionHidden.value = mention.id;
        mentionSelect.classList.remove('error');

        const groupe = mentionSelect.closest('.form-group');
        const err = groupe ? groupe.querySelector('.field-error') : null;
        if (err) err.remove();
    }

    if (moyenneInput) {
        moyenneInput.addEventListener('input', appliquerMention);
        moyenneInput.addEventListener('change', appliquerMention);
    }

    /* ================================================================
       NIVEAU LMD — le candidat choisit, parmi les seuls niveaux que son
       diplôme d'admission lui ouvre. La liste des codes autorisés arrive
       avec chaque diplôme (champ "niveaux", cf. ueb_ajax_get_diplomes()).
       ================================================================ */
    function updateNiveauxDisponibles() {
        const diplome  = getDiplome(selectDiplome.value);
        const autorise = (diplome && diplome.niveaux) || [];
        const choixPrecedent = niveauSelect.value;

        niveauSelect.classList.remove('field-locked');

        if (!autorise.length) {
            fillSelect(niveauSelect, [], '— Choisir d\'abord le diplôme d\'admission —', false);
            if (niveauHidden) niveauHidden.value = '';
            return;
        }

        // On garde l'ordre de ueb_niveaux_lmd (L1, L2, L3, M1, M2, DOC).
        const options = niveauxCache.filter(function (n) {
            return autorise.indexOf(n.code) !== -1;
        });

        fillSelect(niveauSelect, options, '— Choisir ton niveau —', true);

        // Un seul niveau possible (Master → Doctorat) : on le pose
        // directement plutôt que d'imposer un choix sans alternative.
        const encoreValide = options.some(function (n) { return String(n.id) === choixPrecedent; });
        if (options.length === 1) {
            niveauSelect.value = options[0].id;
        } else if (encoreValide) {
            niveauSelect.value = choixPrecedent;
        }

        syncNiveauHidden();
    }

    function syncNiveauHidden() {
        if (niveauHidden) niveauHidden.value = niveauSelect.value;
    }

    niveauSelect.addEventListener('change', syncNiveauHidden);

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

    [selectFiliere1, selectFiliere2, selectFiliere3].forEach(function (select) {
        select.addEventListener('change', refreshFiliereCrossFilter);
    });

    /* ================================================================
       FILIÈRES — dépend de faculté + type de formation
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

    selectDiplome.addEventListener('change', function () {
        updateSeries();
        updateNiveauxDisponibles();
    });

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
       TÉLÉPHONE DU CANDIDAT — un seul numéro
       Le champ garde le nom "telephone[]" : la table
       ueb_preinscriptions_telephones et le PDF attendent un tableau.
       ================================================================ */
    function setTelephone(values) {
        const input = document.querySelector('input[name="telephone[]"]');
        if (!input) return;
        const liste = Array.isArray(values) ? values : (values ? [values] : []);
        input.value = liste.length ? formatTel(String(liste[0])) : '';
    }

    /* ================================================================
       DATE DE NAISSANCE — saisie au clavier OU calendrier natif

       Sur mobile, un <input type="date"> seul n'offre que le sélecteur
       roulant, pénible pour remonter trente ans en arrière. On expose
       donc un champ texte masqué en JJ/MM/AAAA, doublé du champ date
       natif (superposé à l'icône) qui reste la valeur soumise, au format
       ISO attendu par la colonne DATE.

       Le masque borne chaque segment dès la frappe et analyserDateFr()
       refuse ensuite les dates qui n'existent pas : ni 26/26/2222 ni
       31/02/2026 ne peuvent atteindre le champ soumis.
       ================================================================ */
    const dateSaisie = document.getElementById('date_naissance_saisie');
    const dateNative = document.getElementById('date_naissance');
    const dateField  = dateSaisie ? dateSaisie.closest('.date-field') : null;

    const DATE_MIN_ANNEE = 1900;
    const MOIS_FR = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                     'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

    /** ISO du jour dans le fuseau du visiteur (toISOString() renvoie de l'UTC). */
    function isoLocal(d) {
        const mois = String(d.getMonth() + 1).padStart(2, '0');
        const jour = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + mois + '-' + jour;
    }

    const AUJOURDHUI_ISO = isoLocal(new Date());

    // Bornes du calendrier : pas de date future, pas de siècle précédent.
    if (dateNative) {
        dateNative.max = AUJOURDHUI_ISO;
        dateNative.min = DATE_MIN_ANNEE + '-01-01';
    }

    /**
     * Masque JJ/MM/AAAA appliqué à la volée, chaque segment borné dès la
     * frappe : un premier chiffre supérieur à 3 ne peut être qu'un jour
     * écrit sans zéro (4 -> 04), un jour au-delà de 31 ou un mois au-delà
     * de 12 sont ramenés à leur maximum. Composer 26/26/2222 devient donc
     * impossible, y compris chiffre par chiffre.
     */
    function formatDateFr(raw) {
        let digits = String(raw).replace(/\D/g, '');
        if (!digits) return '';

        if (parseInt(digits[0], 10) > 3) {
            digits = '0' + digits;
        }
        if (digits.length >= 3 && parseInt(digits[2], 10) > 1) {
            digits = digits.slice(0, 2) + '0' + digits.slice(2);
        }

        digits = digits.slice(0, 8);

        let jour  = digits.slice(0, 2);
        let mois  = digits.slice(2, 4);
        const annee = digits.slice(4, 8);

        if (jour.length === 2) {
            const n = parseInt(jour, 10);
            if (n > 31)     jour = '31';
            else if (n === 0) jour = '01';
        }
        if (mois.length === 2) {
            const n = parseInt(mois, 10);
            if (n > 12)     mois = '12';
            else if (n === 0) mois = '01';
        }

        let out = jour;
        if (digits.length > 2) out += '/' + mois;
        if (digits.length > 4) out += '/' + annee;
        return out;
    }

    /**
     * Analyse une saisie JJ/MM/AAAA.
     *
     * @param {string} texte Contenu du champ visible.
     * @returns {{iso: string, erreur: string}} iso vide dès que la date
     *          n'est pas exploitable, avec le motif exact en clair.
     */
    function analyserDateFr(texte) {
        const valeur = String(texte).trim();
        if (!valeur) return { iso: '', erreur: '' };

        const m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(valeur);
        if (!m) {
            return { iso: '', erreur: 'Date incomplète. Utilise le format JJ/MM/AAAA, par exemple 04/09/2005.' };
        }

        const jour  = parseInt(m[1], 10);
        const mois  = parseInt(m[2], 10);
        const annee = parseInt(m[3], 10);

        if (mois < 1 || mois > 12) {
            return { iso: '', erreur: 'Le mois doit être compris entre 01 et 12.' };
        }

        // Le jour 0 du mois suivant est le dernier du mois courant : gère
        // les 30/31 jours comme le 29 février des années bissextiles.
        const joursDuMois = new Date(annee, mois, 0).getDate();
        if (jour < 1 || jour > joursDuMois) {
            return {
                iso: '',
                erreur: MOIS_FR[mois - 1] + ' ' + annee + ' compte ' + joursDuMois +
                        ' jours : le ' + m[1] + ' n\'existe pas.'
            };
        }

        if (annee < DATE_MIN_ANNEE) {
            return { iso: '', erreur: 'L\'année doit être postérieure à ' + DATE_MIN_ANNEE + '.' };
        }

        const iso = m[3] + '-' + m[2] + '-' + m[1];
        if (iso > AUJOURDHUI_ISO) {
            return { iso: '', erreur: 'La date de naissance ne peut pas être dans le futur.' };
        }

        return { iso: iso, erreur: '' };
    }

    /** "12/03/2005" -> "2005-03-12", '' si la date n'existe pas. */
    function dateFrVersIso(texte) {
        return analyserDateFr(texte).iso;
    }

    function dateIsoVersFr(iso) {
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso).trim());
        return m ? m[3] + '/' + m[2] + '/' + m[1] : '';
    }

    function messageErreurDate(msg) {
        if (!dateSaisie) return;
        const groupe = dateSaisie.closest('.form-group');
        if (!groupe) return;

        groupe.querySelectorAll('.field-error').forEach(function (e) { e.remove(); });

        if (!msg) {
            dateSaisie.classList.remove('error');
            dateSaisie.removeAttribute('aria-invalid');
            return;
        }

        dateSaisie.classList.add('error');
        dateSaisie.setAttribute('aria-invalid', 'true');

        const span = document.createElement('span');
        span.className   = 'field-error';
        span.textContent = msg;
        groupe.appendChild(span);
    }

    /**
     * L'icône du calendrier ne sert qu'à ouvrir le sélecteur : dès que le
     * champ contient quelque chose, elle laisse la place à la saisie (et
     * le champ date superposé cesse de capter les clics, sans quoi une
     * zone invisible resterait cliquable).
     */
    function majIconeCalendrier() {
        if (!dateField || !dateSaisie) return;
        dateField.classList.toggle('date-field--saisie', dateSaisie.value.trim() !== '');
    }

    /** Pose la date des deux côtés à partir d'une valeur ISO. */
    function setDateNaissance(iso) {
        if (!dateSaisie || !dateNative) return;
        const fr = dateIsoVersFr(iso);
        dateSaisie.value = fr;
        dateNative.value = fr ? iso : '';
        majIconeCalendrier();
        messageErreurDate('');
    }

    if (dateSaisie && dateNative) {
        dateSaisie.addEventListener('input', function () {
            const avant = this.value.length;
            const pos   = this.selectionStart;
            this.value  = formatDateFr(this.value);
            const diff  = this.value.length - avant;
            this.setSelectionRange(pos + diff, pos + diff);

            // Le champ soumis ne reçoit que les dates complètes et réelles.
            const analyse = analyserDateFr(this.value);
            dateNative.value = analyse.iso;

            majIconeCalendrier();

            // Pendant la frappe on n'affiche rien de neuf, mais une erreur
            // déjà posée disparaît dès que la saisie redevient correcte.
            if (analyse.iso || !this.value.trim()) messageErreurDate('');
        });

        // Le verdict tombe quand le candidat quitte le champ, pas à chaque
        // touche : signaler "date incomplète" au deuxième chiffre serait
        // du bruit.
        dateSaisie.addEventListener('blur', function () {
            messageErreurDate(analyserDateFr(this.value).erreur);
        });

        dateSaisie.addEventListener('keydown', function (e) {
            const autorises = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Home', 'End', 'Enter'];
            if (autorises.includes(e.key) || e.ctrlKey || e.metaKey) return;
            if (!/^[0-9]$/.test(e.key)) e.preventDefault();
        });

        // Un collage passe par le même masque que la frappe.
        dateSaisie.addEventListener('paste', function (e) {
            e.preventDefault();
            const colle = (e.clipboardData || window.clipboardData).getData('text');
            this.value = formatDateFr(this.value + colle);
            dateNative.value = dateFrVersIso(this.value);
            majIconeCalendrier();
        });

        // Calendrier natif : le champ date superposé à l'icône reçoit le
        // clic. showPicker() ouvre le sélecteur même là où cliquer un
        // champ date transparent ne suffirait pas (Firefox desktop).
        dateNative.addEventListener('click', function () {
            if (typeof this.showPicker === 'function') {
                try { this.showPicker(); } catch (err) { /* geste utilisateur refusé */ }
            }
        });

        // Le calendrier ne produit que des dates réelles, mais il reste
        // borné par min/max : on repasse par la même analyse.
        dateNative.addEventListener('change', function () {
            const analyse = analyserDateFr(dateIsoVersFr(this.value));
            this.value = analyse.iso;
            dateSaisie.value = dateIsoVersFr(analyse.iso);
            majIconeCalendrier();
            messageErreurDate(analyse.erreur);
        });

        majIconeCalendrier();
    }

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

        // Date de naissance : le champ texte peut être rempli mais
        // incomplet ou impossible — dans ce cas le champ soumis est vide
        // et analyserDateFr() dit précisément ce qui cloche.
        if (dateSaisie && fieldset.contains(dateSaisie) && dateSaisie.value.trim()) {
            const analyseDate = analyserDateFr(dateSaisie.value);
            if (analyseDate.erreur) {
                dateSaisie.classList.add('error');
                dateSaisie.setAttribute('aria-invalid', 'true');
                addError(dateSaisie.closest('.form-group'), analyseDate.erreur);
            }
        }

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
        telephone             : 'Téléphone',
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
        nom_urgence           : 'Personne à contacter en cas d\'urgence',
        numero_urgence        : 'Numéro à contacter en cas d\'urgence',
        adresse_urgence       : 'Adresse de la personne à contacter',
        sport_prefere         : 'Sport préféré',
        art_pratique          : 'Art pratiqué',
        numero_certificat_medical : 'N° certificat médical',
        lieu_obtention_certificat : "Lieu d'obtention du certificat",
    };

    // Traductions anglaises des mêmes libellés, affichées en petit texte
    // gris semi-transparent sous le label français dans le récap (étape 5).
    // Reprend les mêmes traductions que les <span class="field-trans">
    // déjà présentes dans page-preinscription.php, pour rester cohérent
    // avec le reste du formulaire.
    const LABELS_EN = {
        faculte               : 'Faculty / School',
        serie_diplome         : 'Diploma series / specialty',
        diplome_admission     : 'Admission diploma',
        niveau_lmd            : 'LMD level',
        type_formation        : 'Training type',
        filiere_1             : '1st choice of program',
        filiere_2             : '2nd choice of program',
        filiere_3             : '3rd choice of program',
        annee_obtention       : 'Year diploma obtained',
        moyenne_diplome       : 'Average obtained',
        mention               : 'Mention / Honors',
        statut_etudiant       : 'Student status',
        nom                   : 'Last name',
        prenom                : 'First name(s)',
        sexe                  : 'Gender',
        date_naissance        : 'Date of birth',
        lieu_naissance        : 'Place of birth',
        nationalite           : 'Nationality',
        premiere_langue       : 'First language',
        situation_matrimoniale: 'Marital status',
        statut_socio_professionnel: 'Socio-professional status',
        handicap              : 'Disability status',
        email                 : 'Email address',
        telephone             : 'Phone number',
        adresse               : 'Current address',
        region_origine        : 'Region of origin',
        departement_origine   : 'Department of origin',
        commune_origine       : 'Municipality of origin',
        nom_pere              : "Father's name",
        numero_pere           : "Father's phone number",
        profession_pere       : "Father's occupation",
        nom_mere              : "Mother's name",
        numero_mere           : "Mother's phone number",
        profession_mere       : "Mother's occupation",
        nom_tuteur            : "Guardian's name",
        numero_tuteur         : "Guardian's phone number",
        nom_urgence           : 'Emergency contact name',
        numero_urgence        : 'Emergency contact phone',
        adresse_urgence       : 'Emergency contact address',
        sport_prefere         : 'Favorite sport',
        art_pratique          : 'Art practiced',
        numero_certificat_medical : 'Medical certificate number',
        lieu_obtention_certificat : 'Place certificate obtained',
    };

    const SECTIONS = {
        formation : ['faculte','diplome_admission','type_formation','serie_diplome','filiere_1','moyenne_diplome','filiere_2','mention','filiere_3','annee_obtention','niveau_lmd','statut_etudiant'],
        etatCivil : ['nom','nationalite','prenom','premiere_langue','lieu_naissance','situation_matrimoniale','date_naissance','statut_socio_professionnel','sexe','handicap'],
        contact   : ['telephone','nom_pere','email','numero_pere','adresse','profession_pere','departement_origine','nom_mere','commune_origine','numero_mere','region_origine','profession_mere','nom_tuteur','numero_tuteur','nom_urgence','numero_urgence','adresse_urgence'],
        divers    : ['sport_prefere','numero_certificat_medical','art_pratique','lieu_obtention_certificat'],
    };

    const SECTION_TITLES = {
        formation : 'Formation choisie',
        etatCivil : 'État civil',
        contact   : 'Contact & origine',
        divers    : 'Informations diverses',
    };

    // Correspondance section → numéro d'étape du formulaire
    const SECTION_ETAPE = {
        formation : 1,
        etatCivil : 2,
        contact   : 3,
        divers    : 4,
    };

    function getFieldValue(fieldName) {
        if (fieldName === 'date_naissance') {
            return dateSaisie ? dateSaisie.value.trim() : '';
        }
        if (fieldName === 'telephone') {
            const input = document.querySelector('input[name="telephone[]"]');
            return input ? input.value.trim() : '';
        }
        if (fieldName === 'serie_diplome') {
            const sel = document.getElementById('serie_diplome_select');
            if (!sel) return '';
            const opt = sel.options[sel.selectedIndex];
            return opt ? opt.text : '';
        }
        if (fieldName === 'niveau_lmd') {
            const sel = document.getElementById('niveau_lmd_select');
            if (!sel) return '';
            const opt = sel.options[sel.selectedIndex];
            return opt ? opt.text : '';
        }
        if (fieldName === 'mention') {
            if (!mentionSelect) return '';
            const opt = mentionSelect.options[mentionSelect.selectedIndex];
            const txt = opt ? opt.text : '';
            return txt.startsWith('—') ? '' : txt;
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

            // En-tête : titre + bouton Modifier
            const header = document.createElement('div');
            header.style.cssText = [
                'display:flex',
                'align-items:center',
                'justify-content:space-between',
                'margin-bottom:10px',
                'padding-bottom:7px',
                'border-bottom:1.5px solid var(--ueb-bordure)',
            ].join(';');

            const title = document.createElement('div');
            title.className = 'recap-section-title';
            // Retire la bordure-bottom du titre (déjà sur le header)
            title.style.cssText = 'margin:0;padding:0;border:none';
            title.textContent = SECTION_TITLES[key];

            const btnModifier = document.createElement('button');
            btnModifier.type = 'button';
            btnModifier.textContent = '✏ Modifier';
            btnModifier.setAttribute('aria-label', 'Modifier ' + SECTION_TITLES[key]);
            btnModifier.style.cssText = [
                'background:transparent',
                'border:1.5px solid var(--ueb-vert)',
                'color:var(--ueb-vert)',
                'border-radius:6px',
                'padding:4px 14px',
                'font-size:.76rem',
                'font-weight:700',
                'cursor:pointer',
                'font-family:inherit',
                'flex-shrink:0',
                'transition:background .15s ease,color .15s ease',
            ].join(';');

            btnModifier.addEventListener('mouseenter', function () {
                this.style.background = 'var(--ueb-vert)';
                this.style.color = '#fff';
            });
            btnModifier.addEventListener('mouseleave', function () {
                this.style.background = 'transparent';
                this.style.color = 'var(--ueb-vert)';
            });
            btnModifier.addEventListener('click', function () {
                showStep(SECTION_ETAPE[key]);
            });

            header.appendChild(title);
            header.appendChild(btnModifier);
            section.appendChild(header);

            // Grille des champs
            const grid = document.createElement('div');
            grid.style.cssText = 'display:grid;grid-template-columns:1fr 1fr;gap:14px;';
            SECTIONS[key].forEach(function (fieldName) {
                if (!fieldName) return;
                const value    = getFieldValue(fieldName);
                const labelFr  = LABELS[fieldName] || fieldName;
                const labelEn  = LABELS_EN[fieldName] || '';
                const item     = document.createElement('div');
                item.className = 'recap-item';
                item.innerHTML =
                    '<span class="recap-label">' + labelFr +
                        (labelEn ? '<span class="recap-label-en">' + labelEn + '</span>' : '') +
                    '</span>' +
                    '<span class="recap-value">' + (value || '') + '</span>';
                grid.appendChild(item);
            });
            section.appendChild(grid);
            container.appendChild(section);
        });
    }

    /* ================================================================
       COLLECTE DES DONNÉES DU FORMULAIRE
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
                return;
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
       SAUVEGARDE DE LA PROGRESSION
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
    }

    /* ================================================================
       SAUVEGARDE AUTOMATIQUE SUR PERTE DE FOCUS (blur)
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
        const tag = el.tagName;
        if (tag !== 'INPUT' && tag !== 'SELECT' && tag !== 'TEXTAREA') return;
        scheduleAutoSave();
    }, true);

    /* ================================================================
       ÉVÉNEMENTS DE NAVIGATION
       ================================================================ */
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
            'nom_urgence', 'numero_urgence', 'adresse_urgence',
            'annee_obtention', 'moyenne_diplome',
            'numero_certificat_medical', 'lieu_obtention_certificat'
        ];
        simpleFields.forEach(function (name) {
            if (donnees[name] === undefined) return;
            const el = form.querySelector('[name="' + name + '"]');
            if (el) el.value = donnees[name];
        });

        // La date arrive en ISO : le champ texte visible doit suivre.
        setDateNaissance(donnees.date_naissance || '');

        if (donnees.sexe) {
            const radio = form.querySelector('input[name="sexe"][value="' + donnees.sexe + '"]');
            if (radio) radio.checked = true;
        }

        if (donnees.handicap) {
            const radio = form.querySelector('input[name="handicap"][value="' + donnees.handicap + '"]');
            if (radio) radio.checked = true;
        }

        setTelephone(donnees.telephone);

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

        // La mention n'est pas restaurée telle quelle : elle se déduit de
        // la moyenne, elle-même remise en place par simpleFields.
        appliquerMention();

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

        updateNiveauxDisponibles();
        if (donnees.niveau_lmd) {
            niveauSelect.value = donnees.niveau_lmd;
            syncNiveauHidden();
        }

        const type = donnees.type_formation || 'classique';
        const faculteCode = getFaculteCode(donnees.faculte);
        typeGroup.style.display = (faculteCode === 'FS') ? '' : 'none';
        selectType.value = type;

        const diplomeCodeResume = getDiplomeCode(donnees.diplome_admission);

        if (donnees.faculte && donnees.diplome_admission && DIPLOMES_AVEC_SERIE.indexOf(diplomeCodeResume) !== -1) {
            if (serieContainer) serieContainer.style.display = '';
            serieSelect.required = true;
            const series = await uebFetch('ueb_get_specialites', {
                faculte_id: donnees.faculte,
                diplome_id: donnees.diplome_admission
            });
            fillSelect(serieSelect, series, '— Choisir la série —', true);
            if (donnees.serie_diplome) {
                serieSelect.value = donnees.serie_diplome;
                if (serieHidden) serieHidden.value = donnees.serie_diplome;
            }
        } else if (serieContainer) {
            serieContainer.style.display = 'none';
            serieSelect.required = false;
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