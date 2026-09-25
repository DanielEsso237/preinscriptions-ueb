<?php
/** Commandes intégrées à la liste existante ; aucune route supplémentaire. */
if ( ! defined( 'ABSPATH' ) ) exit;
$duplicate_view = ueb_access_has( array( 'ueb_view_students', 'ueb_view_duplicates' ) );
?>
<div class="dup-toolbar" aria-label="Filtres de la liste des dossiers">
    <?php if ( $duplicate_view ) : ?>
    <div class="dup-field"><label for="list-duplicates">Doublons</label><select id="list-duplicates"><option value="">Tous les dossiers</option><option value="1">Doublons uniquement</option></select></div>
    <?php endif; ?>
    <div class="dup-field"><label for="list-duplicate-status">Statut des dossiers</label><select id="list-duplicate-status"><option value="">Dossiers actifs</option><option value="disabled">Doublons désactivés</option><option value="all">Tous les statuts</option></select></div>
    <div class="dup-field"><label for="list-date-from">Créés à partir du</label><input type="date" id="list-date-from"></div>
    <div class="dup-field"><label for="list-date-to">Jusqu’au</label><input type="date" id="list-date-to"></div>
    <?php if ( $duplicate_view ) : ?>
    <button type="button" class="admin-tbtn" id="dup-rescan"><?php echo ueb_icon( 'refresh', 'admin-icon--sm' ); ?>Actualiser l’analyse</button>
    <button type="button" class="admin-tbtn" id="dup-audit-toggle" aria-expanded="false" aria-controls="dup-audit"><?php echo ueb_icon( 'list', 'admin-icon--sm' ); ?>Journal</button>
    <?php endif; ?>
</div>
<?php if ( $duplicate_view ) : ?>
<div class="dup-analysis" id="dup-analysis" role="status" aria-live="polite"></div>
<?php if ( ueb_access_has( 'ueb_configure_duplicates' ) && null === ueb_access_scope( 'ueb_configure_duplicates' ) ) : ?>
<details class="dup-settings"><summary>Critères de détection</summary>
    <form id="dup-settings-form">
        <p>Un critère commun suffit. Le dossier le plus récemment créé reste toujours la référence du groupe.</p>
        <div class="dup-settings-options">
            <label><input type="checkbox" name="email"> Même email <span>Certain</span></label>
            <label><input type="checkbox" name="phone"> Même téléphone du candidat <span>Certain</span></label>
            <label><input type="checkbox" name="identity"> Nom, prénom et date de naissance <span>Probable</span></label>
        </div>
        <div class="dup-settings-bottom"><label>Indicatif des numéros locaux <input name="country" inputmode="numeric" pattern="[1-9][0-9]{0,2}" maxlength="3" required></label><button class="admin-tbtn admin-tbtn--primary" type="submit">Enregistrer et analyser</button></div>
        <p class="dup-help">Les coordonnées partagées peuvent produire un faux positif. Vérifiez les différences avant de désactiver. Les groupes inter-établissements se traitent manuellement, avec une portée globale.</p>
    </form>
</details>
<?php endif; ?>
<div id="dup-bulk" class="dup-bulk" hidden>
    <div><strong id="dup-summary" aria-live="polite"></strong><p>Les filtres sélectionnent des groupes complets. Le repère de groupe reste visible à chaque page.</p></div>
    <?php if ( ueb_access_has( 'ueb_manage_duplicates' ) ) : ?>
    <div class="dup-bulk-actions"><button type="button" class="admin-tbtn" id="dup-selected" disabled>Désactiver la sélection</button><button type="button" class="admin-tbtn admin-tbtn--primary" id="dup-all">Désactiver tous les anciens doublons</button><small>Groupes « Certain » du même établissement uniquement</small></div>
    <?php endif; ?>
</div>
<section id="dup-audit" class="dup-audit" aria-label="Journal des doublons" hidden><h3>Historique des décisions</h3><div id="dup-audit-content"></div><button type="button" class="admin-tbtn" id="dup-audit-more" hidden>Voir les événements précédents</button></section>
<dialog id="dup-confirm" class="dup-dialog" aria-labelledby="dup-confirm-title" aria-describedby="dup-confirm-description">
    <form id="dup-confirm-form"><h2 id="dup-confirm-title">Confirmer l’action</h2><p id="dup-confirm-description"></p><p class="dup-confirm-note">Aucune donnée ne sera supprimée. Le dossier le plus récent reste protégé lors des désactivations.</p><label for="dup-reason">Raison <span>(facultatif)</span></label><textarea id="dup-reason" rows="3" maxlength="2000" placeholder="Précisez le résultat de votre vérification…"></textarea><p id="dup-confirm-error" role="alert"></p><div class="dup-dialog-actions"><button type="button" class="admin-tbtn" id="dup-cancel">Retour à la liste</button><button type="submit" class="admin-tbtn admin-tbtn--primary" id="dup-confirm-submit">Confirmer</button></div></form>
</dialog>
<div id="dup-notice" class="dup-notice" role="status" aria-live="polite" hidden><span></span><button type="button" class="admin-tbtn" id="dup-undo" hidden>Annuler</button><button type="button" class="dup-close" aria-label="Fermer la notification"><?php echo ueb_icon( 'close', 'admin-icon--sm' ); ?></button></div>
<?php endif; ?>
<div id="dup-hidden" class="dup-hidden" hidden></div>
