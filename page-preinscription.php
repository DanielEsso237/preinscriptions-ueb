<?php
/**
 * Template Name: Préinscription
 *
 * @package preinscriptions-ueb
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* Mode maintenance : personne (hors équipe via preinscriptions_acces_anticipe())
   ne doit pouvoir remplir le formulaire tant que le site est en chantier,
   même en tapant l'URL directement. */
if ( preinscriptions_maintenance_active() && ! preinscriptions_acces_anticipe() ) {
    $ueb_url_maintenance = preinscriptions_maintenance_url();
    wp_safe_redirect( '#' === $ueb_url_maintenance ? home_url( '/' ) : $ueb_url_maintenance );
    exit;
}

/* Ouverture pas encore atteinte : le formulaire ne doit recevoir aucun
   dossier, même en tapant son adresse directement. On renvoie vers le compte
   à rebours — sauf pour l'équipe, reconnue à sa session WordPress
   (voir preinscriptions_acces_anticipe()), qui doit pouvoir continuer à tester. */
if ( ! preinscriptions_ouverture_atteinte() && ! preinscriptions_acces_anticipe() ) {
    $ueb_url_attente = preinscriptions_compte_rebours_url();
    wp_safe_redirect( '#' === $ueb_url_attente ? home_url( '/' ) : $ueb_url_attente );
    exit;
}

/* Période de préinscription dépassée : on n'initialise même pas de
   numéro de dossier (inutile de consommer la séquence), et on affiche
   un message de clôture au lieu du formulaire. */
if ( preinscriptions_cloture_atteinte() ) {
    get_header();
    ?>
    <div class="cd">
        <div class="cd-decor" aria-hidden="true">
            <span class="cd-halo cd-halo-1"></span>
            <span class="cd-halo cd-halo-2"></span>
        </div>
        <div class="cd-wrap">
            <header class="cd-hero">
                <p class="cd-eyebrow">
                    <span class="cd-dot" aria-hidden="true"></span>
                    Campagne 2026&#8202;–&#8202;2027 · Session terminée
                </p>
                <h1 class="cd-title">Les préinscriptions sont <span class="cd-title-accent">closes</span>.</h1>
                <p class="cd-lead">
                    La période de préinscription (1er septembre – 31 octobre 2026) est terminée.
                    Aucune nouvelle demande n'est acceptée au-delà de cette date.
                </p>
                <div class="cd-actions">
                    <a class="cd-btn cd-btn-primary" href="<?php echo esc_url( get_template_directory_uri() . '/assets/pdf/communique-recteur.pdf' ); ?>" target="_blank" rel="noopener noreferrer">
                        <svg class="cd-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <path d="M14 2v6h6"></path><path d="M9 13h6"></path><path d="M9 17h4"></path>
                        </svg>
                        Communiqué du recteur
                        <span class="cd-sr-only"> (PDF, nouvel onglet)</span>
                    </a>
                    <a class="cd-btn cd-btn-ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                        <svg class="cd-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                            <path d="M19 12H5"></path><path d="m12 19-7-7 7-7"></path>
                        </svg>
                        Retour à l'accueil
                    </a>
                </div>
            </header>
        </div>
    </div>
    <?php
    get_footer();
    return;
}

/*
 * Numéro de dossier : priorité session > cookie persistant > génération.
 */
$ueb_numero_dossier = null;

if ( ! empty( $_SESSION['ueb_numero_dossier_en_cours'] ) ) {
    $ueb_numero_dossier = $_SESSION['ueb_numero_dossier_en_cours'];
} elseif ( ! empty( $_COOKIE['ueb_numero_dossier'] ) ) {
    $numero_candidat = sanitize_text_field( wp_unslash( $_COOKIE['ueb_numero_dossier'] ) );
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
        <span class="preinscription-eyebrow">● Candidatures ouvertes · 2026–2027</span>
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

        <?php
        $ueb_contacts_aide = array(
            array( 'affichage' => '+237 6 76 29 54 88', 'whatsapp' => '237676295488' ),
            array( 'affichage' => '+237 6 73 41 43 81', 'whatsapp' => '237673414381' ),
            array( 'affichage' => '+237 6 59 49 02 21', 'whatsapp' => '237659490221' ),
            array( 'affichage' => '+237 6 93 89 91 50', 'whatsapp' => '237693899150' ),
        );

        $ueb_message_aide = rawurlencode(
            'Bonjour, j\'ai besoin d\'aide pour ma préinscription à l\'UEB'
            . ( $ueb_numero_dossier ? ' (dossier ' . $ueb_numero_dossier . ').' : '.' )
        );
        ?>
        <div class="aide-contact">
            <p class="aide-contact-intro">Besoin d'aide ou un problème pendant ta préinscription ? Contacte-nous au :</p>
            <ul class="aide-contact-liste">
                <?php foreach ( $ueb_contacts_aide as $ueb_contact ) : ?>
                <li>
                    <a class="aide-contact-lien"
                       href="https://wa.me/<?php echo esc_attr( $ueb_contact['whatsapp'] ); ?>?text=<?php echo esc_attr( $ueb_message_aide ); ?>"
                       target="_blank"
                       rel="noopener noreferrer"
                       aria-label="Écrire au <?php echo esc_attr( $ueb_contact['affichage'] ); ?> sur WhatsApp">
                        <svg class="aide-contact-icone" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
                        <span><?php echo esc_html( $ueb_contact['affichage'] ); ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

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

        <div class="assistance-notice">
            <span class="assistance-notice-icon" aria-hidden="true">☎</span>
            <p>
                Besoin d'aide ou un problème pendant ta préinscription&nbsp;?
                Contacte-nous au
                <a href="tel:+237676295488">+237 6 76 29 54 88</a>,
                <a href="tel:+237673414381">+237 6 73 41 43 81</a>,
                <a href="tel:+237659490221">+237 6 59 49 02 21</a>
                ou
                <a href="tel:+237693899150">+237 6 93 89 91 50</a>.
            </p>
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
                <span class="step-label">Divers</span>
            </div>
            <div class="step-separator"></div>
            <div class="step-item" data-step="5">
                <div class="step-circle">5</div>
                <span class="step-label">Confirmation</span>
            </div>
        </div>

        <div class="form-card">
            <?php
            $nonce_field = wp_nonce_field( 'preinscription_submit', 'preinscription_nonce', true, false );
            ?>
            <form id="form-preinscription" method="post" action="" novalidate>
                <?php echo $nonce_field; ?>
                <input type="hidden" id="numero_dossier" name="numero_dossier" value="<?php echo esc_attr( $ueb_numero_dossier ?: '' ); ?>">
                <input type="hidden" id="serie_diplome" name="serie_diplome">
                <input type="hidden" id="niveau_lmd" name="niveau_lmd">

                <!-- ===== ÉTAPE 1 : FORMATION ===== -->
                <fieldset class="form-step active" data-step="1">
                    <legend class="step-heading">
                        <span class="step-num">Étape 1 / 5</span>
                        Choix de ta formation
                    </legend>

                    <div class="form-grid">

                        <!-- Faculté -->
                        <div class="form-group full">
                            <label for="faculte">Faculté / École <span class="required">*</span><span class="field-trans">Faculty / School</span></label>
                            <select id="faculte" name="faculte" required disabled>
                                <option value="">— Chargement... —</option>
                            </select>
                        </div>

                        <!-- Diplôme d'admission -->
                        <div class="form-group full">
                            <label for="diplome_admission">Diplôme d'admission <span class="required">*</span><span class="field-trans">Admission diploma</span></label>
                            <select id="diplome_admission" name="diplome_admission" required disabled>
                                <option value="">— Chargement... —</option>
                            </select>
                        </div>

                        <!-- Série / Spécialité -->
                        <div class="form-group full" id="serie-container">
                            <label for="serie_diplome_select">Série / Spécialité du diplôme <span class="required">*</span><span class="field-trans">Diploma series / specialty</span></label>
                            <select id="serie_diplome_select" required disabled>
                                <option value="">— Choisir d'abord la faculté et le diplôme —</option>
                            </select>
                            <span class="field-hint">La liste des séries s'adapte selon la faculté et le diplôme choisis.</span>
                        </div>

                        <!-- Type de formation -->
                        <div class="form-group full" id="type-formation-group" style="display:none;">
                            <label for="type_formation">Type de formation <span class="required">*</span><span class="field-trans">Training type</span></label>
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
                            <label for="filiere_1">1er choix de filière <span class="required">*</span><span class="field-trans">1st choice of program</span></label>
                            <select id="filiere_1" name="filiere_1" required disabled>
                                <option value="">— Choisir d'abord une faculté —</option>
                            </select>
                        </div>

                        <!-- 2e choix de filière -->
                        <div class="form-group full">
                            <label for="filiere_2">2e choix de filière <span class="required">*</span><span class="field-trans">2nd choice of program</span></label>
                            <select id="filiere_2" name="filiere_2" required disabled>
                                <option value="">— Aucun deuxième choix —</option>
                            </select>
                        </div>

                        <!-- 3e choix de filière -->
                        <div class="form-group full">
                            <label for="filiere_3">3e choix de filière <span class="field-optional">(optionnel)</span><span class="field-trans">3rd choice of program</span></label>
                            <select id="filiere_3" name="filiere_3" disabled>
                                <option value="">— Aucun troisième choix —</option>
                            </select>
                        </div>

                        <!-- Niveau LMD -->
                        <div class="form-group full">
                            <label for="niveau_lmd_select">Niveau LMD <span class="required">*</span><span class="field-trans">LMD level</span></label>
                            <select id="niveau_lmd_select" required disabled>
                                <option value="">— Choisir d'abord le diplôme d'admission —</option>
                            </select>
                            <span class="field-hint">Ton diplôme d'admission détermine les niveaux auxquels tu peux postuler.</span>
                        </div>

                        <!-- Moyenne obtenue — réels dans [10, 20] -->
                        <div class="form-group align-top">
                            <label for="moyenne_diplome">Moyenne obtenue au diplôme <span class="required">*</span><span class="field-trans">Average obtained</span></label>
                            <input
                                type="number"
                                id="moyenne_diplome"
                                name="moyenne_diplome"
                                step="0.01"
                                min="10"
                                max="20"
                                placeholder="Ex : 13.50"
                                required
                            >
                            <span class="field-hint">Valeur comprise entre 10 et 20.</span>
                        </div>

                        <!-- Mention -->
                        <div class="form-group align-top">
                            <label for="mention">Mention <span class="required">*</span><span class="field-trans">Mention / Honors</span></label>
                            <select id="mention" name="mention" required disabled>
                                <option value="">— Chargement... —</option>
                            </select>
                        </div>

                        <!-- Année d'obtention — SELECT 1980..2026 -->
                        <div class="form-group full">
                            <label for="annee_obtention">Année d'obtention du diplôme <span class="required">*</span><span class="field-trans">Year diploma obtained</span></label>
                            <select id="annee_obtention" name="annee_obtention" required>
                                <option value="">— Choisir une année —</option>
                                <?php for ( $y = 2026; $y >= 1980; $y-- ) : ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <!-- Statut de l'étudiant -->
                        <div class="form-group full">
                            <label for="statut_etudiant">Statut <span class="required">*</span><span class="field-trans">Student status</span></label>
                            <select id="statut_etudiant" name="statut_etudiant" required disabled>
                                <option value="">— Chargement... —</option>
                            </select>
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
                        <span class="step-num">Étape 2 / 5</span>
                        Ton état civil
                    </legend>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nom">Nom <span class="required">*</span><span class="field-trans">Last name</span></label>
                            <input type="text" id="nom" name="nom" placeholder="Ton nom de famille" required autocomplete="family-name">
                        </div>

                        <div class="form-group">
                            <label for="prenom">Prénom(s) <span class="required">*</span><span class="field-trans">First name(s)</span></label>
                            <input type="text" id="prenom" name="prenom" placeholder="Ton ou tes prénoms" required autocomplete="given-name">
                        </div>

                        <div class="form-group">
                            <label>Sexe <span class="required">*</span><span class="field-trans">Gender</span></label>
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
                            <label for="date_naissance_saisie">Date de naissance <span class="required">*</span><span class="field-trans">Date of birth</span></label>
                            <div class="date-field">
                                <input
                                    type="text"
                                    id="date_naissance_saisie"
                                    class="date-field-input"
                                    inputmode="numeric"
                                    autocomplete="bday"
                                    placeholder="JJ/MM/AAAA"
                                    maxlength="10"
                                    aria-describedby="date-naissance-hint"
                                    required
                                >
                                <span class="date-field-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                        <rect x="3" y="5" width="18" height="16" rx="2"></rect>
                                        <path d="M3 10h18M8 3v4M16 3v4"></path>
                                    </svg>
                                </span>
                                <!-- Champ réellement soumis (format ISO attendu par la colonne
                                     DATE). Superposé à l'icône : le toucher ouvre le calendrier
                                     natif, sans empêcher la saisie au clavier dans le champ texte. -->
                                <input type="date" id="date_naissance" name="date_naissance" class="date-field-native" tabindex="-1" aria-label="Choisir la date de naissance dans le calendrier">
                            </div>
                            <span class="field-hint" id="date-naissance-hint">Tape ta date au format JJ/MM/AAAA, ou touche l'icône pour ouvrir le calendrier.</span>
                        </div>

                        <div class="form-group">
                            <label for="lieu_naissance">Lieu de naissance <span class="required">*</span><span class="field-trans">Place of birth</span></label>
                            <input type="text" id="lieu_naissance" name="lieu_naissance" placeholder="Ville / Village" required>
                        </div>

                        <div class="form-group">
                            <label for="nationalite">Nationalité <span class="required">*</span><span class="field-trans">Nationality</span></label>
                            <select id="nationalite" name="nationalite" required disabled>
                                <option value="">— Choisir ta nationalité —</option>
                            </select>
                        </div>

                        <div class="form-group full">
                            <label for="premiere_langue">Première langue <span class="required">*</span><span class="field-trans">First language</span></label>
                            <select id="premiere_langue" name="premiere_langue" required disabled>
                                <option value="">— Chargement... —</option>
                            </select>
                        </div>

                        <div class="form-group full">
                            <label for="situation_matrimoniale">Situation matrimoniale <span class="required">*</span><span class="field-trans">Marital status</span></label>
                            <select id="situation_matrimoniale" name="situation_matrimoniale" required disabled>
                                <option value="">— Choisir ta situation —</option>
                            </select>
                        </div>

                        <div class="form-group full">
                            <label for="statut_socio_professionnel">Statut socio-professionnel <span class="required">*</span><span class="field-trans">Socio-professional status</span></label>
                            <select id="statut_socio_professionnel" name="statut_socio_professionnel" required disabled>
                                <option value="">— Chargement... —</option>
                            </select>
                        </div>

                        <div class="form-group full">
                            <label>Situation de handicap <span class="required">*</span><span class="field-trans">Disability status</span></label>
                            <div class="radio-group">
                                <label class="radio-option"><input type="radio" name="handicap" value="non" required checked><span>Non</span></label>
                                <label class="radio-option"><input type="radio" name="handicap" value="oui"><span>Oui</span></label>
                            </div>
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
                        <span class="step-num">Étape 3 / 5</span>
                        Contact & origine
                    </legend>

                    <div class="form-grid">

                        <div class="form-group full">
                            <label for="email">Adresse e-mail <span class="required">*</span><span class="field-trans">Email address</span></label>
                            <input type="email" id="email" name="email" placeholder="ton@email.com" required autocomplete="email">
                        </div>

                        <div class="form-group full">
                            <label for="telephone">Téléphone <span class="required">*</span><span class="field-trans">Phone number</span></label>
                            <div id="telephones-container">
                                <div class="tel-row">
                                    <input type="tel" id="telephone" name="telephone[]" placeholder="6X XX XX XX XX" required class="tel-input">
                                </div>
                            </div>
                        </div>

                        <div class="form-group full">
                            <label for="adresse">Adresse actuelle <span class="required">*</span><span class="field-trans">Current address</span></label>
                            <input type="text" id="adresse" name="adresse" placeholder="Quartier, ville" required>
                        </div>

                        <div class="form-group">
                            <label for="region_origine">Région d'origine <span class="required">*</span><span class="field-trans">Region of origin</span></label>
                            <select id="region_origine" name="region_origine" required disabled>
                                <option value="">— Chargement... —</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="departement_origine">Département d'origine <span class="required">*</span><span class="field-trans">Department of origin</span></label>
                            <select id="departement_origine" name="departement_origine" required disabled>
                                <option value="">— Choisir d'abord une région —</option>
                            </select>
                        </div>

                        <div class="form-group full">
                            <label for="commune_origine">Commune d'origine <span class="required">*</span><span class="field-trans">Municipality of origin</span></label>
                            <select id="commune_origine" name="commune_origine" required disabled>
                                <option value="">— Choisir d'abord un département —</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="nom_pere">Nom du père <span class="required">*</span><span class="field-trans">Father's name</span></label>
                            <input type="text" id="nom_pere" name="nom_pere" placeholder="Nom complet" required>
                        </div>
                        <div class="form-group">
                            <label for="numero_pere">Numéro du père <span class="field-trans">Father's phone number</span></label>
                            <input type="tel" id="numero_pere" name="numero_pere" placeholder="6X XX XX XX XX">
                        </div>

                        <div class="form-group">
                            <label for="nom_mere">Nom de la mère <span class="required">*</span><span class="field-trans">Mother's name</span></label>
                            <input type="text" id="nom_mere" name="nom_mere" placeholder="Nom complet" required>
                        </div>
                        <div class="form-group">
                            <label for="numero_mere">Numéro de la mère <span class="field-trans">Mother's phone number</span></label>
                            <input type="tel" id="numero_mere" name="numero_mere" placeholder="6X XX XX XX XX">
                        </div>

                        <div class="form-group">
                            <label for="nom_tuteur">Nom du tuteur <span class="field-trans">Guardian's name</span></label>
                            <input type="text" id="nom_tuteur" name="nom_tuteur" placeholder="Nom complet (si applicable)">
                        </div>
                        <div class="form-group">
                            <label for="numero_tuteur">Numéro du tuteur <span class="field-trans">Guardian's phone number</span></label>
                            <input type="tel" id="numero_tuteur" name="numero_tuteur" placeholder="6X XX XX XX XX">
                        </div>

                        <div class="form-group">
                            <label for="profession_pere">Profession du père <span class="field-trans">Father's occupation</span></label>
                            <input type="text" id="profession_pere" name="profession_pere" placeholder="Ex : Enseignant">
                        </div>
                        <div class="form-group">
                            <label for="profession_mere">Profession de la mère <span class="field-trans">Mother's occupation</span></label>
                            <input type="text" id="profession_mere" name="profession_mere" placeholder="Ex : Commerçante">
                        </div>

                        <!-- Personne à contacter en cas d'urgence -->
                        <div class="form-group">
                            <label for="nom_urgence">Personne à contacter en cas d'urgence <span class="required">*</span><span class="field-trans">Emergency contact name</span></label>
                            <input type="text" id="nom_urgence" name="nom_urgence" placeholder="Nom complet" required>
                        </div>
                        <div class="form-group">
                            <label for="numero_urgence">Numéro à contacter en cas d'urgence <span class="required">*</span><span class="field-trans">Emergency contact phone</span></label>
                            <input type="tel" id="numero_urgence" name="numero_urgence" placeholder="6X XX XX XX XX" required>
                        </div>
                        <div class="form-group full">
                            <label for="adresse_urgence">Adresse de la personne à contacter <span class="field-optional">(optionnel)</span><span class="field-trans">Emergency contact address</span></label>
                            <input type="text" id="adresse_urgence" name="adresse_urgence" placeholder="Quartier, ville">
                        </div>

                    </div>

                    <div class="form-nav">
                        <button type="button" class="btn-prev btn-secondary" data-prev="2">
                            <span class="btn-arrow">←</span> Précédent
                        </button>
                        <button type="button" class="btn-next btn-primary" data-next="4">
                            Suivant <span class="btn-arrow">→</span>
                        </button>
                    </div>
                </fieldset>

                <!-- ===== ÉTAPE 4 : INFORMATIONS DIVERSES ===== -->
                <fieldset class="form-step" data-step="4">
                    <legend class="step-heading">
                        <span class="step-num">Étape 4 / 5</span>
                        Informations diverses
                    </legend>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="sport_prefere">Sport préféré <span class="field-trans">Favorite sport</span></label>
                            <select id="sport_prefere" name="sport_prefere" disabled>
                                <option value="">— Chargement... —</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="numero_certificat_medical">N° certificat médical <span class="field-trans">Medical certificate number</span></label>
                            <input type="text" id="numero_certificat_medical" name="numero_certificat_medical" placeholder="Laisser vide si non disponible">
                        </div>

                        <div class="form-group">
                            <label for="art_pratique">Art pratiqué <span class="field-trans">Art practiced</span></label>
                            <select id="art_pratique" name="art_pratique" disabled>
                                <option value="">— Chargement... —</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="lieu_obtention_certificat">Lieu d'obtention du certificat <span class="field-trans">Place certificate obtained</span></label>
                            <input type="text" id="lieu_obtention_certificat" name="lieu_obtention_certificat" placeholder="Ex : Centre médico-social d'Ébolowa">
                        </div>
                    </div>

                    <div class="form-nav">
                        <button type="button" class="btn-prev btn-secondary" data-prev="3">
                            <span class="btn-arrow">←</span> Précédent
                        </button>
                        <button type="button" class="btn-next btn-primary" data-next="5">
                            Suivant <span class="btn-arrow">→</span>
                        </button>
                    </div>
                </fieldset>

                <!-- ===== ÉTAPE 5 : RÉCAPITULATIF ===== -->
                <fieldset class="form-step" data-step="5">
                    <legend class="step-heading">
                        <span class="step-num">Étape 5 / 5</span>
                        Vérifie tes informations
                    </legend>

                    <div id="recap-content" class="recap-grid"></div>

                    <div class="form-group full consent-group">
                        <label class="checkbox-option">
                            <input type="checkbox" name="consent" id="consent" required>
                            <span>Je certifie que les informations renseignées sont exactes et complètes. Je comprends qu'une fausse déclaration entraîne l'annulation de ma préinscription.</span>
                        </label>
                    </div>

                    <div class="form-nav">
                        <button type="button" class="btn-prev btn-secondary" data-prev="4">
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