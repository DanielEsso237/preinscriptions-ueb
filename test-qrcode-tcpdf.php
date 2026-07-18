<?php
/**
 * Script de test isolé — TCPDF + QR code natif.
 *
 * But : vérifier que TCPDF est bien présent en local (lib/tcpdf/, non
 * versionné, cf. .gitignore) et que la génération de QR code fonctionne
 * nativement (TCPDF2DBarcode), sans aucune librairie externe.
 *
 * Ticket : "Nouveau format PDF (fiche + fiche médicale) + contact
 * d'urgence" — block Setup.
 *
 * À PLACER : à la racine du thème, au même niveau que functions.php
 * (à côté du dossier lib/). Ce fichier est destiné à être ajouté au
 * .gitignore une fois le test terminé (même principe que
 * test-numero-dossier.php déjà ignoré).
 *
 * USAGE : ouvrir directement dans le navigateur, ex. :
 *   http://localhost/wp-content/themes/preinscriptions-ueb/test-qrcode-tcpdf.php
 *
 * Résultat attendu : un PDF s'affiche dans le navigateur avec un QR code
 * lisible (scanne-le avec ton téléphone pour vérifier qu'il contient bien
 * le texte "UEB-2026-000123").
 *
 * NE PAS committer une fois le test validé — supprimer ce fichier ou
 * l'ajouter au .gitignore.
 */

$tcpdf_path   = __DIR__ . '/lib/tcpdf/tcpdf.php';
$barcode_path = __DIR__ . '/lib/tcpdf/tcpdf_barcodes_2d.php';

if ( ! file_exists( $tcpdf_path ) ) {
    die(
        "ERREUR : TCPDF introuvable à :\n" . $tcpdf_path . "\n\n" .
        "Le dossier lib/ est gitignoré (voir .gitignore) : il doit être " .
        "installé manuellement en local. Vérifie que /lib/tcpdf/tcpdf.php existe."
    );
}
require_once $tcpdf_path;

if ( ! file_exists( $barcode_path ) ) {
    die(
        "ERREUR : tcpdf_barcodes_2d.php introuvable à :\n" . $barcode_path . "\n\n" .
        "TCPDF est présent mais incomplet — vérifie que le dossier lib/tcpdf/ " .
        "contient bien tous les fichiers (installation via composer ou zip complet)."
    );
}
require_once $barcode_path;

/* ------------------------------------------------------------------ */
/* Génération du QR code (méthode qui sera reprise dans               */
/* inc/pdf-functions.php pour le numéro de dossier réel)               */
/* ------------------------------------------------------------------ */
$numero_dossier_test = 'UEB-2026-000123';

$qrcode = new TCPDF2DBarcode( $numero_dossier_test, 'QRCODE,M' );
$png    = $qrcode->getBarcodePngData( 4, 4 ); // taille en pixels par module

/* ------------------------------------------------------------------ */
/* PDF de test minimal                                                 */
/* ------------------------------------------------------------------ */
$pdf = new TCPDF( 'P', 'mm', 'A4', true, 'UTF-8', false );
$pdf->SetCreator( 'Test QR — UEB Préinscriptions' );
$pdf->SetTitle( 'Test QR Code TCPDF' );
$pdf->setPrintHeader( false );
$pdf->setPrintFooter( false );
$pdf->SetMargins( 18, 18, 18 );
$pdf->AddPage();

$pdf->SetFont( 'dejavusans', '', 11 );
$pdf->Write( 0, 'Test de génération QR code natif TCPDF (sans librairie externe).', '', 0, 'L', true );
$pdf->Ln( 6 );
$pdf->Write( 0, 'Code testé : ' . $numero_dossier_test, '', 0, 'L', true );
$pdf->Ln( 10 );

// Insertion du QR en position absolue (x, y, largeur, hauteur en mm).
$pdf->Image( '@' . $png, 18, 50, 30, 30, 'PNG' );

$pdf->Ln( 70 );
$pdf->Write( 0, "Si ce PDF s'affiche avec un QR code lisible ci-dessus, TCPDF + QR natif sont OK.", '', 0, 'L', true );

$pdf->Output( 'test-qrcode.pdf', 'I' ); // 'I' = affichage direct dans le navigateur
exit;
