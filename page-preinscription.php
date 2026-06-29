<?php
/**
 * Template Name: Préinscription
 *
 * @package preinscriptions-ueb
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<div class="preinscription-page">

    <div class="preinscription-hero">
        <span class="preinscription-eyebrow">● Candidatures ouvertes · 2025–2027</span>
        <h1 class="preinscription-title">Ta préinscription à<br><span class="accent">l'UEB</span> commence ici.</h1>
        <p class="preinscription-subtitle">Remplis ce formulaire en quelques minutes. Nous t'accompagnons à chaque étape.</p>
    </div>

    <div class="preinscription-container">

        <!-- Barre de progression -->
        <div class="steps-nav" role="navigation" aria-label="Étapes du formulaire">
            <div class="step-item active" data-step="1">
                <div class="step-circle">1</div>
                <span class="step-label">Formation</span>
            </div>
            <div class="step-separator"></div>
            <div class="step-item" data-step="2">
                <div class="step-circle">2</div>
                <span class="step-label">État civil</span>
            </div>
            <div class="step-separator"></div>
            <div class="step-item" data-step="3">
                <div class="step-circle">3</div>
                <span class="step-label">Contact</span>
            </div>
            <div class="step-separator"></div>
            <div class="step-item" data-step="4">
                <div class="step-circle">4</div>
                <span class="step-label">Confirmation</span>
            </div>
        </div>

        <!-- Formulaire -->
        <div class="form-card">
            <?php
            $nonce_field = wp_nonce_field( 'preinscription_submit', 'preinscription_nonce', true, false );
            ?>
            <form id="form-preinscription" method="post" action="" novalidate>
                <?php echo $nonce_field; ?>
                <input type="hidden" name="action" value="preinscription_submit">

                <!-- ===== ÉTAPE 1 : CHOIX DE FORMATION ===== -->
                <fieldset class="form-step active" data-step="1">
                    <legend class="step-heading">
                        <span class="step-num">Étape 1 / 4</span>
                        Choix de ta formation
                    </legend>

                    <div class="form-grid">

                        <!-- Faculté -->
                        <div class="form-group full">
                            <label for="faculte">Faculté / École <span class="required">*</span></label>
                            <select id="faculte" name="faculte" required>
                                <option value="">— Choisir —</option>
                                <option value="FS">Faculté des Sciences (FS)</option>
                                <option value="FASEG">Faculté des Sciences Économiques et de Gestion (FASEG)</option>
                                <option value="FSJP">Faculté des Sciences Juridiques et Politiques (FSJP)</option>
                                <option value="FALSH">Faculté des Lettres et Sciences Humaines (FALSH)</option>
                            </select>
                        </div>

                        <!-- Diplôme d'admission -->
                        <div class="form-group full">
                            <label for="diplome_admission">Diplôme d'admission <span class="required">*</span></label>
                            <select id="diplome_admission" name="diplome_admission" required>
                                <option value="">— Choisir —</option>
                                <option value="bac">Baccalauréat</option>
                                <option value="gce_ol">GCE O-Level</option>
                            </select>
                        </div>

                        <!-- Série / Spécialité — préfixe "Série " prérempli -->
                        <div class="form-group full">
                            <label for="serie_diplome">Série / Spécialité du diplôme <span class="required">*</span></label>
                            <input
                                type="text"
                                id="serie_diplome"
                                name="serie_diplome"
                                placeholder="Ex : Série C, Série D, Sciences Expérimentales…"
                                value="Série "
                                required
                            >
                            <span class="field-hint">Le curseur se positionne après "Série " — tape directement ta lettre ou spécialité.</span>
                        </div>

                        <!-- Niveau LMD — figé sur L1 -->
                        <div class="form-group">
                            <label for="niveau_lmd">Niveau LMD</label>
                            <input
                                type="text"
                                id="niveau_lmd"
                                name="niveau_lmd"
                                value="Licence 1"
                                readonly
                                class="field-locked"
                            >
                            <span class="field-hint">Les préinscriptions sont ouvertes en Licence 1 uniquement.</span>
                        </div>

                        <!-- Type de formation — figé sur Initiale -->
                        <div class="form-group">
                            <label for="type_formation">Type de formation</label>
                            <input
                                type="text"
                                id="type_formation"
                                name="type_formation"
                                value="Formation Initiale"
                                readonly
                                class="field-locked"
                            >
                        </div>

                        <!-- 1er choix de filière -->
                        <div class="form-group full">
                            <label for="filiere_1">1er choix de filière <span class="required">*</span></label>
                            <select id="filiere_1" name="filiere_1" required disabled>
                                <option value="">— Choisir d'abord une faculté —</option>
                            </select>
                        </div>

                        <!-- 2e choix de filière -->
                        <div class="form-group full">
                            <label for="filiere_2">2e choix de filière</label>
                            <select id="filiere_2" name="filiere_2" disabled>
                                <option value="">— Choisir d'abord une faculté —</option>
                            </select>
                        </div>

                        <!-- Année d'obtention -->
                        <div class="form-group full">
                            <label for="annee_obtention">Année d'obtention du diplôme <span class="required">*</span></label>
                            <input type="number" id="annee_obtention" name="annee_obtention" min="1990" max="2025" placeholder="Ex : 2024" required>
                        </div>

                    </div><!-- .form-grid -->

                    <div class="form-nav">
                        <span></span>
                        <button type="button" class="btn-next btn-primary" data-next="2">
                            Suivant <span class="btn-arrow">→</span>
                        </button>
                    </div>
                </fieldset>

                <!-- ===== ÉTAPE 2 : ÉTAT CIVIL ===== -->
                <fieldset class="form-step" data-step="2">
                    <legend class="step-heading">
                        <span class="step-num">Étape 2 / 4</span>
                        Ton état civil
                    </legend>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nom">Nom <span class="required">*</span></label>
                            <input type="text" id="nom" name="nom" placeholder="Ton nom de famille" required autocomplete="family-name">
                        </div>

                        <div class="form-group">
                            <label for="prenom">Prénom(s) <span class="required">*</span></label>
                            <input type="text" id="prenom" name="prenom" placeholder="Ton ou tes prénoms" required autocomplete="given-name">
                        </div>

                        <div class="form-group">
                            <label>Sexe <span class="required">*</span></label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="sexe" value="M" required>
                                    <span>Masculin</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="sexe" value="F">
                                    <span>Féminin</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="date_naissance">Date de naissance <span class="required">*</span></label>
                            <input type="date" id="date_naissance" name="date_naissance" required>
                        </div>

                        <div class="form-group">
                            <label for="lieu_naissance">Lieu de naissance <span class="required">*</span></label>
                            <input type="text" id="lieu_naissance" name="lieu_naissance" placeholder="Ville / Village" required>
                        </div>

                        <div class="form-group">
                            <label for="nationalite">Nationalité <span class="required">*</span></label>
                            <input type="text" id="nationalite" name="nationalite" placeholder="Ex : Camerounaise" value="Camerounaise" required>
                        </div>

                        <div class="form-group full">
                            <label for="situation_matrimoniale">Situation matrimoniale</label>
                            <select id="situation_matrimoniale" name="situation_matrimoniale">
                                <option value="">— Choisir —</option>
                                <option value="celibataire">Célibataire</option>
                                <option value="marie">Marié(e)</option>
                                <option value="divorce">Divorcé(e)</option>
                                <option value="veuf">Veuf / Veuve</option>
                            </select>
                        </div>

                        <div class="form-group full">
                            <label for="handicap">Situation de handicap éventuelle</label>
                            <input type="text" id="handicap" name="handicap" placeholder="Laisser vide si non concerné(e)">
                        </div>
                    </div>

                    <div class="form-nav">
                        <button type="button" class="btn-prev btn-secondary" data-prev="1">
                            <span class="btn-arrow">←</span> Précédent
                        </button>
                        <button type="button" class="btn-next btn-primary" data-next="3">
                            Suivant <span class="btn-arrow">→</span>
                        </button>
                    </div>
                </fieldset>

                <!-- ===== ÉTAPE 3 : CONTACT & ORIGINE ===== -->
                <fieldset class="form-step" data-step="3">
                    <legend class="step-heading">
                        <span class="step-num">Étape 3 / 4</span>
                        Contact & origine
                    </legend>

                    <p class="form-notice">Si tu n'es pas camerounais(e), ignore les champs Région, Département et Arrondissement d'origine.</p>

                    <div class="form-grid">

                        <div class="form-group full">
                            <label for="email">Adresse e-mail <span class="required">*</span></label>
                            <input type="email" id="email" name="email" placeholder="ton@email.com" required autocomplete="email">
                        </div>

                        <!-- Téléphones multiples -->
                        <div class="form-group full">
                            <label>Téléphone(s) <span class="required">*</span></label>
                            <div id="telephones-container">
                                <div class="tel-row">
                                    <input
                                        type="tel"
                                        name="telephone[]"
                                        placeholder="6X XX XX XX XX"
                                        required
                                        autocomplete="tel"
                                        class="tel-input"
                                    >
                                </div>
                            </div>
                            <button type="button" id="btn-add-tel" class="btn-add-field">
                                <span>+</span> Ajouter un numéro
                            </button>
                        </div>

                        <div class="form-group full">
                            <label for="adresse">Adresse actuelle <span class="required">*</span></label>
                            <input type="text" id="adresse" name="adresse" placeholder="Quartier, ville" required>
                        </div>

                        <div class="form-group">
                            <label for="region_origine">Région d'origine</label>
                            <select id="region_origine" name="region_origine">
                                <option value="">— Choisir —</option>
                                <option value="adamaoua">Adamaoua</option>
                                <option value="centre">Centre</option>
                                <option value="est">Est</option>
                                <option value="extreme_nord">Extrême-Nord</option>
                                <option value="littoral">Littoral</option>
                                <option value="nord">Nord</option>
                                <option value="nord_ouest">Nord-Ouest</option>
                                <option value="ouest">Ouest</option>
                                <option value="sud">Sud</option>
                                <option value="sud_ouest">Sud-Ouest</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="departement_origine">Département d'origine</label>
                            <input type="text" id="departement_origine" name="departement_origine" placeholder="Ex : Mfoundi, Wouri…">
                        </div>

                        <div class="form-group full">
                            <label for="arrondissement_origine">Arrondissement d'origine</label>
                            <input type="text" id="arrondissement_origine" name="arrondissement_origine" placeholder="Ex : Yaoundé 1er">
                        </div>

                        <div class="form-group">
                            <label for="nom_pere">Nom du père</label>
                            <input type="text" id="nom_pere" name="nom_pere" placeholder="Nom complet">
                        </div>

                        <div class="form-group">
                            <label for="nom_mere">Nom de la mère</label>
                            <input type="text" id="nom_mere" name="nom_mere" placeholder="Nom complet">
                        </div>

                        <!-- Téléphone tuteur — multiples aussi -->
                        <div class="form-group full">
                            <label>Téléphone tuteur / parent <span class="required">*</span></label>
                            <div id="tel-tuteur-container">
                                <div class="tel-row">
                                    <input
                                        type="tel"
                                        name="tel_tuteur[]"
                                        placeholder="6X XX XX XX XX"
                                        required
                                        class="tel-input"
                                    >
                                </div>
                            </div>
                            <button type="button" class="btn-add-field" data-target="tel-tuteur-container" data-name="tel_tuteur[]">
                                <span>+</span> Ajouter un numéro
                            </button>
                        </div>

                        <div class="form-group">
                            <label for="profession_pere">Profession du père</label>
                            <input type="text" id="profession_pere" name="profession_pere" placeholder="Ex : Enseignant">
                        </div>

                    </div>

                    <div class="form-nav">
                        <button type="button" class="btn-prev btn-secondary" data-prev="2">
                            <span class="btn-arrow">←</span> Précédent
                        </button>
                        <button type="button" class="btn-next btn-primary" data-next="4">
                            Vérifier <span class="btn-arrow">→</span>
                        </button>
                    </div>
                </fieldset>

                <!-- ===== ÉTAPE 4 : RÉCAPITULATIF ===== -->
                <fieldset class="form-step" data-step="4">
                    <legend class="step-heading">
                        <span class="step-num">Étape 4 / 4</span>
                        Vérifie ta demande
                    </legend>

                    <div id="recap-content" class="recap-grid">
                        <!-- Rempli dynamiquement en JS -->
                    </div>

                    <div class="form-group full consent-group">
                        <label class="checkbox-option">
                            <input type="checkbox" name="consent" id="consent" required>
                            <span>Je certifie que les informations renseignées sont exactes et complètes. Je comprends qu'une fausse déclaration entraîne l'annulation de ma préinscription.</span>
                        </label>
                    </div>

                    <div class="form-nav">
                        <button type="button" class="btn-prev btn-secondary" data-prev="3">
                            <span class="btn-arrow">←</span> Modifier
                        </button>
<button type="button" class="btn-submit btn-primary" onclick="window.location.href='#'">
                            Générer ma fiche de préinscription →
                        </button>
                    </div>
                </fieldset>

            </form>
        </div><!-- .form-card -->

    </div><!-- .preinscription-container -->

</div><!-- .preinscription-page -->

<?php get_footer(); ?>