<?php
/**
 * Template Name: Préinscription
 *
 * @package preinscriptions-ueb
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
 * Numéro de dossier : priorité session > cookie persistant > génération.
 * - Session : évite une régénération à chaque refresh pendant la même visite.
 * - Cookie (30 jours) : survit à la fermeture du navigateur, sur le même appareil.
 * - Changer d'appareil (ex. téléphone -> PC) n'est PAS couvert par session/cookie :
 *   c'est volontaire, le candidat utilise alors le panneau "Continuer ma
 *   préinscription" (numéro saisi manuellement), qui fonctionne sur
 *   n'importe quel appareil puisque le numéro est stocké côté serveur.
 */
$ueb_numero_dossier = null;

if ( ! empty( $_SESSION['ueb_numero_dossier_en_cours'] ) ) {
    $ueb_numero_dossier = $_SESSION['ueb_numero_dossier_en_cours'];
} elseif ( ! empty( $_COOKIE['ueb_numero_dossier'] ) ) {
    $numero_candidat = sanitize_text_field( wp_unslash( $_COOKIE['ueb_numero_dossier'] ) );
    // On ne réutilise le numéro que s'il existe VRAIMENT en base (brouillon en cours).
    // Un dossier déjà soumis n'a plus de ligne de progression (supprimée à la
    // soumission finale, voir ueb_handle_db_save), donc il ne sera jamais réutilisé ici.
    if ( null !== ueb_recuperer_progression( $numero_candidat ) ) {
        $ueb_numero_dossier = $numero_candidat;
    }
}

if ( null === $ueb_numero_dossier ) {
    $ueb_numero_dossier = ueb_initialiser_dossier();
}

if ( false === $ueb_numero_dossier ) {
    error_log( '[UEB Préinscription] Échec d\'initialisation du dossier sur page-preinscription.php' );
} else {
    $_SESSION['ueb_numero_dossier_en_cours'] = $ueb_numero_dossier;
    // NOTE sécurité : le cookie n'est pas une preuve d'identité, un candidat
    // pourrait en théorie le modifier pour tomber sur le numéro de quelqu'un
    // d'autre. Risque accepté ici (dossier "brouillon" non sensible avant
    // soumission) ; à documenter comme limite connue du projet.
    setcookie(
        'ueb_numero_dossier',
        $ueb_numero_dossier,
        time() + 30 * DAY_IN_SECONDS,
        COOKIEPATH,
        COOKIE_DOMAIN,
        is_ssl(),
        true
    );
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

        <?php if ( $ueb_numero_dossier ) : ?>
        <div class="dossier-banner" role="status">
            <div class="dossier-banner-text">
                <span class="dossier-banner-label">Ton numéro de dossier :</span>
                <strong class="dossier-banner-numero"><?php echo esc_html( $ueb_numero_dossier ); ?></strong>
            </div>
            <p class="dossier-banner-hint">Note-le bien : il te permettra de reprendre ta préinscription si tu es interrompu(e).</p>
        </div>
        <?php else : ?>
        <div class="dossier-banner dossier-banner--error" role="alert">
            <p>Une erreur est survenue lors de la génération de ton numéro de dossier. Merci de recharger la page ou de réessayer plus tard.</p>
        </div>
        <?php endif; ?>

        <div class="reprise-dossier">
            <button type="button" id="btn-toggle-reprise" class="reprise-toggle">Tu as déjà un dossier ? Continuer ma préinscription</button>
            <div id="reprise-panel" class="reprise-panel" style="display:none;">
                <label for="reprise-numero">Numéro de dossier</label>
                <div class="reprise-panel-row">
                    <input type="text" id="reprise-numero" placeholder="Ex : UEB-2026-000123">
                    <button type="button" id="btn-reprise-valider" class="btn-secondary">Reprendre</button>
                </div>
                <p id="reprise-message" class="reprise-message" style="display:none;"></p>
            </div>
        </div>

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

        <div class="form-card">
            <?php
            $nonce_field = wp_nonce_field( 'preinscription_submit', 'preinscription_nonce', true, false );
            ?>
            <form id="form-preinscription" method="post" action="" novalidate>
                <?php echo $nonce_field; ?>
                <!--
                    NOTE : un seul champ "action" doit exister dans tout le formulaire
                    (sa valeur "generate_pdf" est ajoutée juste avant le bouton de
                    soumission, à l'étape 4). Avoir deux champs hidden avec le même
                    name="action" causait un bug : seule la dernière valeur du DOM
                    était envoyée dans $_POST, donc ueb_handle_db_save() (Oriol) et
                    ueb_handle_pdf_generation() (Yann) réagissent tous les deux à
                    "generate_pdf", via template_redirect avec des priorités
                    différentes (5 puis 10).
                -->
                <input type="hidden" id="numero_dossier" name="numero_dossier" value="<?php echo esc_attr( $ueb_numero_dossier ?: '' ); ?>">
                <input type="hidden" id="serie_diplome" name="serie_diplome">

                <!-- ===== ÉTAPE 1 : FORMATION ===== -->
                <fieldset class="form-step active" data-step="1">
                    <legend class="step-heading">
                        <span class="step-num">Étape 1 / 4</span>
                        Choix de ta formation
                    </legend>

                    <div class="form-grid">

                        <!-- Faculté — peuplé via AJAX (ueb_get_facultes) -->
                        <div class="form-group full">
                            <label for="faculte">Faculté / École <span class="required">*</span></label>
                            <select id="faculte" name="faculte" required disabled>
                                <option value="">— Chargement... —</option>
                            </select>
                        </div>

                        <!-- Diplôme d'admission — peuplé via AJAX (ueb_get_diplomes) -->
                        <div class="form-group full">
                            <label for="diplome_admission">Diplôme d'admission <span class="required">*</span></label>
                            <select id="diplome_admission" name="diplome_admission" required disabled>
                                <option value="">— Chargement... —</option>
                            </select>
                        </div>

                        <!-- Série / Spécialité — select dynamique par faculté + diplôme -->
                        <div class="form-group full" id="serie-container">
                            <label for="serie_diplome_select">Série / Spécialité du diplôme <span class="required">*</span></label>
                            <select id="serie_diplome_select" required disabled>
                                <option value="">— Choisir d'abord la faculté et le diplôme —</option>
                            </select>
                            <span class="field-hint">La liste des séries s'adapte selon la faculté et le diplôme choisis.</span>
                        </div>

                        <!-- Type de formation — visible uniquement pour FS -->
                        <div class="form-group full" id="type-formation-group" style="display:none;">
                            <label for="type_formation">Type de formation <span class="required">*</span></label>
                            <select id="type_formation" name="type_formation">
                                <option value="classique">Formation Classique (étude de dossier)</option>
                                <option value="pro">Formation Professionnelle — Licence Pro (LP)</option>
                            </select>
                            <span class="field-hint">La formation classique se fait sur étude de dossier. La Licence Pro est une filière professionnalisante.</span>
                        </div>

                        <!-- Notice filières pro -->
                        <div class="form-group full" id="pro-filiere-notice" style="display:none;">
                            <p class="form-notice form-notice--info">
                                En formation professionnelle, ton <strong>1er choix</strong> est une filière LP.
                                Tu peux indiquer en <strong>2e choix</strong> une filière classique que tu souhaites intégrer en parallèle par dossier, en attendant les résultats du concours LP.
                            </p>
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
                            <label for="filiere_2">2e choix de filière <span class="field-optional">(optionnel)</span></label>
                            <select id="filiere_2" name="filiere_2" disabled>
                                <option value="">— Aucun deuxième choix —</option>
                            </select>
                        </div>

                        <!-- Niveau LMD — figé L1 -->
                        <div class="form-group full">
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

                        <!-- Année d'obtention -->
                        <div class="form-group full">
                            <label for="annee_obtention">Année d'obtention du diplôme <span class="required">*</span></label>
                            <input type="number" id="annee_obtention" name="annee_obtention" min="1990" max="2025" placeholder="Ex : 2024" required>
                        </div>

                    </div>

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

                        <!-- Nationalité — peuplé via AJAX (ueb_get_nationalites) -->
                        <div class="form-group">
                            <label for="nationalite">Nationalité <span class="required">*</span></label>
                            <select id="nationalite" name="nationalite" required disabled>
                                <option value="">— Choisir ta nationalité —</option>
                            </select>
                        </div>

                        <!-- Situation matrimoniale — peuplé via AJAX (ueb_get_situations_matrimoniales) -->
                        <div class="form-group full">
                            <label for="situation_matrimoniale">Situation matrimoniale <span class="required">*</span></label>
                            <select id="situation_matrimoniale" name="situation_matrimoniale" required disabled>
                                <option value="">— Choisir ta situation —</option>
                            </select>
                        </div>

                        <!-- Statut socio-professionnel — peuplé via AJAX (ueb_get_statuts_socio_pro) -->
                        <div class="form-group full">
                            <label for="statut_socio_professionnel">Statut socio-professionnel <span class="required">*</span></label>
                            <select id="statut_socio_professionnel" name="statut_socio_professionnel" required disabled>
                                <option value="">— Chargement... —</option>
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

                    <div class="form-grid">

                        <div class="form-group full">
                            <label for="email">Adresse e-mail <span class="required">*</span></label>
                            <input type="email" id="email" name="email" placeholder="ton@email.com" required autocomplete="email">
                        </div>

                        <div class="form-group full">
                            <label>Téléphone(s) <span class="required">*</span></label>
                            <div id="telephones-container">
                                <div class="tel-row">
                                    <input type="tel" name="telephone[]" placeholder="6X XX XX XX XX" required class="tel-input">
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

                        <!-- Région d'origine — peuplé via AJAX (ueb_get_regions) -->
                        <div class="form-group">
                            <label for="region_origine">Région d'origine <span class="required">*</span></label>
                            <select id="region_origine" name="region_origine" required disabled>
                                <option value="">— Chargement... —</option>
                            </select>
                        </div>

                        <!-- Département d'origine — dépend de la région -->
                        <div class="form-group">
                            <label for="departement_origine">Département d'origine <span class="required">*</span></label>
                            <select id="departement_origine" name="departement_origine" required disabled>
                                <option value="">— Choisir d'abord une région —</option>
                            </select>
                        </div>

                        <!-- Commune d'origine — dépend du département (remplace l'ancien champ "arrondissement") -->
                        <div class="form-group full">
                            <label for="commune_origine">Commune d'origine <span class="required">*</span></label>
                            <select id="commune_origine" name="commune_origine" required disabled>
                                <option value="">— Choisir d'abord un département —</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="nom_pere">Nom du père <span class="required">*</span></label>
                            <input type="text" id="nom_pere" name="nom_pere" placeholder="Nom complet" required>
                        </div>

                        <div class="form-group">
                            <label for="nom_mere">Nom de la mère <span class="required">*</span></label>
                            <input type="text" id="nom_mere" name="nom_mere" placeholder="Nom complet" required>
                        </div>

                        <div class="form-group full">
                            <label>Téléphone tuteur / parent <span class="required">*</span></label>
                            <div id="tel-tuteur-container">
                                <div class="tel-row">
                                    <input type="tel" name="tel_tuteur[]" placeholder="6X XX XX XX XX" required class="tel-input">
                                </div>
                            </div>
                            <button type="button" class="btn-add-field" data-target="tel-tuteur-container" data-name="tel_tuteur[]">
                                <span>+</span> Ajouter un numéro
                            </button>
                        </div>

                        <div class="form-group full">
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

                    <div id="recap-content" class="recap-grid"></div>

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
                        <input type="hidden" name="action" value="generate_pdf">
                        <button type="submit" class="btn-submit btn-primary">
                            Générer ma fiche de préinscription
                        </button>
                    </div>
                </fieldset>

            </form>
        </div><!-- .form-card -->

    </div><!-- .preinscription-container -->

</div><!-- .preinscription-page -->

<?php get_footer(); ?>
