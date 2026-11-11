<?php
/**
 * Vidéos MAF : mises à jour automatiques depuis GitHub.
 *
 * Chaque version publiée dans le dépôt (release « vX.Y.Z » avec le fichier maf-videos.zip)
 * apparaît dans Extensions comme une mise à jour classique, installable en un clic
 * (ou par Claude via le connecteur). Rien n'est installé sans action.
 *
 * @package MAF_Videos
 */

defined( 'ABSPATH' ) || exit;

/** Dépôt GitHub public « propriétaire/nom ». */
if ( ! defined( 'MAFV_GH_REPO' ) ) {
	define( 'MAFV_GH_REPO', 'AxoSearch/maf-videos' );
}

/** Dernière version publiée sur GitHub (mise en cache 1 h, vidée lors d'une vérification forcée). */
function mafv_gh_derniere( $forcer = false ) {
	$cache = get_site_transient( 'mafv_gh_release' );
	if ( ! $forcer && is_array( $cache ) ) {
		return $cache;
	}
	$rep = wp_remote_get(
		'https://api.github.com/repos/' . MAFV_GH_REPO . '/releases/latest',
		array(
			'timeout' => 10,
			'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'maf-videos-updater' ),
		)
	);
	if ( is_wp_error( $rep ) || 200 !== wp_remote_retrieve_response_code( $rep ) ) {
		set_site_transient( 'mafv_gh_release', array(), 15 * MINUTE_IN_SECONDS );
		return array();
	}
	$data    = json_decode( wp_remote_retrieve_body( $rep ), true );
	$version = isset( $data['tag_name'] ) ? ltrim( (string) $data['tag_name'], 'vV' ) : '';
	$zip     = '';
	foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
		if ( 'maf-videos.zip' === ( $asset['name'] ?? '' ) ) {
			$zip = (string) $asset['browser_download_url'];
		}
	}
	$infos = ( $version && $zip ) ? array(
		'version' => $version,
		'zip'     => $zip,
		'url'     => (string) ( $data['html_url'] ?? '' ),
		'notes'   => (string) ( $data['body'] ?? '' ),
	) : array();
	set_site_transient( 'mafv_gh_release', $infos, HOUR_IN_SECONDS );
	return $infos;
}

add_filter( 'pre_set_site_transient_update_plugins', 'mafv_gh_annoncer_maj' );
function mafv_gh_annoncer_maj( $transient ) {
	if ( ! is_object( $transient ) ) {
		return $transient;
	}
	$fichier = plugin_basename( MAFV_FILE );
	// Une vérification demandée par WordPress (bouton « Vérifier » ou connecteur) relit GitHub.
	$infos = mafv_gh_derniere( ! empty( $transient->last_checked ) && ( time() - (int) $transient->last_checked ) < 60 );
	$objet = (object) array(
		'id'          => 'github.com/' . MAFV_GH_REPO,
		'slug'        => 'maf-videos',
		'plugin'      => $fichier,
		'new_version' => $infos['version'] ?? MAFV_VERSION,
		'url'         => $infos['url'] ?? '',
		'package'     => $infos['zip'] ?? '',
	);
	if ( $infos && version_compare( $infos['version'], MAFV_VERSION, '>' ) ) {
		$transient->response[ $fichier ] = $objet;
		unset( $transient->no_update[ $fichier ] );
	} else {
		$transient->no_update[ $fichier ] = $objet;
		unset( $transient->response[ $fichier ] );
	}
	return $transient;
}

/* Fenêtre « Voir les détails » de la mise à jour. */
add_filter( 'plugins_api', 'mafv_gh_details', 10, 3 );
function mafv_gh_details( $res, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || 'maf-videos' !== $args->slug ) {
		return $res;
	}
	$infos = mafv_gh_derniere();
	return (object) array(
		'name'          => 'Vidéos MAF',
		'slug'          => 'maf-videos',
		'version'       => $infos['version'] ?? MAFV_VERSION,
		'author'        => 'Mon Appart Facile',
		'homepage'      => $infos['url'] ?? '',
		'download_link' => $infos['zip'] ?? '',
		'sections'      => array( 'changelog' => wpautop( esc_html( $infos['notes'] ?? '' ) ) ),
	);
}

/* Après une mise à jour, on oublie la version en cache. */
add_action(
	'upgrader_process_complete',
	function () {
		delete_site_transient( 'mafv_gh_release' );
	}
);
