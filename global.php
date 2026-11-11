<?php
/**
 * Vidéos MAF : habillage global du site (menu et pied de page), validé avec Axel le 11/11/2026.
 *
 * Menu : rubriques sur une ligne, « Vidéos » en pastille blanche (classe de menu mafx-menu-videos),
 * « Contact » en bouton jaune avec enveloppe (classe mafx-menu-contact), bouton Contact à côté du burger sur mobile.
 * Pied de page : bleu nuit #14213D sur tout le site, sans le crédit GeneratePress.
 *
 * @package MAF_Videos
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'mafv_global_assets' );
function mafv_global_assets() {
	wp_enqueue_style( 'maf-global', MAFV_URL . 'assets/maf-global.css', array(), MAFV_VERSION );
}

/* Bouton Contact affiché à côté du menu burger sur mobile. */
add_action( 'generate_inside_mobile_menu_control_wrapper', 'mafv_global_contact_mobile' );
function mafv_global_contact_mobile() {
	echo '<a class="mafx-nav-contact" href="' . esc_url( home_url( '/contact/' ) ) . '">Contact</a>';
}

/* Pied de page : copyright sans « Construit avec GeneratePress ». */
add_filter( 'generate_copyright', 'mafv_global_copyright' );
function mafv_global_copyright() {
	return '<span class="copyright">&copy; ' . esc_html( gmdate( 'Y' ) ) . ' Mon Appart Facile</span>';
}
