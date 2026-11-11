<?php
/**
 * Vidéos MAF : habillage de la page de résultats de recherche (Relevanssi + GeneratePress).
 *
 * Bandeau bleu avec la recherche relancée et le nombre de résultats, cartes en grille,
 * images légères, page « aucun résultat » avec raccourcis.
 *
 * @package MAF_Videos
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'mafv_recherche_assets' );
function mafv_recherche_assets() {
	if ( is_search() ) {
		wp_enqueue_style( 'maf-recherche', MAFV_URL . 'assets/maf-recherche.css', array(), MAFV_VERSION );
	}
}

/* Images des cartes : taille moyenne au lieu de l'image pleine résolution. */
add_filter( 'post_thumbnail_size', 'mafv_recherche_taille_image' );
function mafv_recherche_taille_image( $size ) {
	return ( is_search() && in_the_loop() ) ? 'medium_large' : $size;
}

/* Sur la recherche, pas d'auteur dans les cartes (date seule). */
add_filter( 'generate_post_author', 'mafv_recherche_sans_auteur' );
function mafv_recherche_sans_auteur( $show ) {
	return is_search() ? false : $show;
}

/* Bandeau de haut de page. */
add_action( 'generate_before_main_content', 'mafv_recherche_bandeau' );
function mafv_recherche_bandeau() {
	if ( ! is_search() ) {
		return;
	}
	global $wp_query;
	$terme = get_search_query();
	$nb    = (int) $wp_query->found_posts;
	if ( $nb > 1 ) {
		$compte = sprintf( '%d articles trouvés', $nb );
	} elseif ( 1 === $nb ) {
		$compte = '1 article trouvé';
	} else {
		$compte = 'Aucun article trouvé';
	}
	?>
	<div class="mafr-bandeau">
		<p class="mafr-kicker">Recherche</p>
		<h1 class="mafr-titre"><?php echo $terme ? 'Résultats pour <mark>' . esc_html( $terme ) . '</mark>' : 'Rechercher sur le site'; ?></h1>
		<p class="mafr-compte"><?php echo esc_html( $compte ); ?></p>
		<form role="search" method="get" class="mafr-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="screen-reader-text" for="mafr-s">Rechercher</label>
			<input type="search" id="mafr-s" name="s" value="<?php echo esc_attr( $terme ); ?>" placeholder="Matelas, canapé, bail, rangement…">
			<button type="submit">Rechercher</button>
		</form>
	</div>
	<?php
	if ( ! $nb ) {
		$liens = array(
			'Meilleures marques de matelas' => home_url( '/meilleure-marque-matelas/' ),
			'Meilleures marques de canapés' => home_url( '/meilleure-marque-canapes/' ),
			'Budget pour meubler un appartement' => home_url( '/budget-pour-meubler-appartement/' ),
			'Toutes nos vidéos'             => home_url( '/videos/' ),
		);
		echo '<div class="mafr-vide"><p class="mafr-vide-t">Essayez un mot plus simple (« matelas », « canapé », « chauffage »…), ou partez de nos guides les plus lus :</p><ul>';
		foreach ( $liens as $txt => $url ) {
			echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $txt ) . ' →</a></li>';
		}
		echo '</ul></div>';
	}
}
