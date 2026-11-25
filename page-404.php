<?php
/**
 * Vidéos MAF : page 404 personnalisée (maquette validée avec Axel le 25/11/2026).
 *
 * Le contenu vient d'une page WordPress (slug « page-introuvable », blocs natifs + codes courts ci-dessous).
 * - Brouillon : la page se relit en aperçu ; on peut aussi voir le vrai rendu 404 en ajoutant ?maf404=apercu
 *   à n'importe quelle adresse introuvable.
 * - Mise en ligne : passer la page en « Privée ». Toute adresse introuvable l'affiche alors, avec le code 404
 *   (Google ne l'indexe pas). Sans cette page, le 404 du thème reprend la main.
 *
 * Codes courts : [maf_404_recherche] [maf_404_scene] [maf_404_suggestions] [maf_404_besoins].
 *
 * @package MAF_Videos
 */

defined( 'ABSPATH' ) || exit;

define( 'MAFV_404_SLUG', 'page-introuvable' );
define( 'MAFV_404_EXEMPLE', '/meilleur-surmatelas-2025/' );

/** La page qui porte le contenu de la 404 (tous statuts), ou null. */
function mafv_404_page() {
	static $page = false;
	if ( false === $page ) {
		$res  = get_posts(
			array(
				'name'           => MAFV_404_SLUG,
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
				'posts_per_page' => 1,
			)
		);
		$page = $res ? $res[0] : null;
	}
	return $page;
}

/** Vrai quand la 404 personnalisée doit s'afficher sur cette requête. */
function mafv_404_actif() {
	if ( ! is_404() ) {
		return false;
	}
	$page = mafv_404_page();
	if ( ! $page ) {
		return false;
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$apercu = isset( $_GET['maf404'] ) && 'apercu' === $_GET['maf404'];
	return $apercu || in_array( $page->post_status, array( 'private', 'publish' ), true );
}

/* Gabarit : le contenu de la page dans la mise en page du thème, avec le code 404 conservé. */
add_filter( 'template_include', 'mafv_404_gabarit', 99 );
function mafv_404_gabarit( $template ) {
	return mafv_404_actif() ? __DIR__ . '/gabarit-404.php' : $template;
}

add_filter( 'generate_sidebar_layout', 'mafv_404_sans_barre' );
function mafv_404_sans_barre( $layout ) {
	return mafv_404_actif() ? 'no-sidebar' : $layout;
}

add_filter( 'body_class', 'mafv_404_body_class' );
function mafv_404_body_class( $classes ) {
	if ( mafv_404_actif() ) {
		$classes[] = 'mafx-page';
		$classes[] = 'maf404-page';
	}
	return $classes;
}

/* Styles : sur la 404 et sur la page qui en porte le contenu (aperçu). */
add_action( 'wp_enqueue_scripts', 'mafv_404_assets' );
function mafv_404_assets() {
	$p      = is_singular() ? get_post() : null;
	$editee = $p && false !== strpos( (string) $p->post_content, '[maf_404_' );
	if ( ! mafv_404_actif() && ! $editee ) {
		return;
	}
	wp_enqueue_style( 'maf-home', MAFV_URL . 'assets/maf-home.css', array(), MAFV_VERSION );
	wp_enqueue_script( 'maf-home', MAFV_URL . 'assets/maf-home.js', array(), MAFV_VERSION, true );
	mafv_enqueue();
	wp_enqueue_style( 'maf-404', MAFV_URL . 'assets/maf-404.css', array( 'maf-home', 'maf-videos' ), MAFV_VERSION );
}

/* -------------------------------------------------------------------------
 * Adresse demandée et mots à chercher
 * ---------------------------------------------------------------------- */

/** Chemin demandé (sur la 404), ou un exemple dans l'aperçu de la page. */
function mafv_404_adresse() {
	if ( is_404() ) {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
	} else {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$path = isset( $_GET['adresse'] ) ? (string) wp_unslash( $_GET['adresse'] ) : MAFV_404_EXEMPLE;
	}
	$path = '/' . ltrim( sanitize_text_field( rawurldecode( $path ) ), '/' );
	return mb_substr( $path, 0, 120 );
}

/** Mots utiles de l'adresse : sans chiffres, années, extensions ni mots vides. */
function mafv_404_mots( $path ) {
	$vides = array( 'les', 'des', 'une', 'pour', 'avec', 'sans', 'dans', 'sur', 'par', 'aux', 'est', 'que', 'qui', 'son', 'ses', 'vos', 'nos', 'votre', 'notre', 'html', 'htm', 'php', 'amp', 'page', 'category', 'categorie', 'tag', 'author', 'feed', 'www', 'content', 'uploads', 'index' );
	$bruts = preg_split( '/[\/\-_\.\s+,]+/u', mb_strtolower( (string) $path ) );
	$mots  = array();
	foreach ( $bruts as $m ) {
		$m = trim( $m );
		if ( '' === $m || preg_match( '/\d/', $m ) || mb_strlen( $m ) < 3 || in_array( $m, $vides, true ) ) {
			continue;
		}
		$mots[ $m ] = true;
	}
	return array_slice( array_keys( $mots ), 0, 5 );
}

/** Jusqu'à 3 articles pour ces mots (Relevanssi s'il est là), en retirant les derniers mots si besoin. */
function mafv_404_resultats( $mots ) {
	if ( ! $mots ) {
		return array();
	}
	$cle = 'mafv404_' . md5( implode( ' ', $mots ) );
	$ids = get_transient( $cle );
	if ( false === $ids ) {
		$ids = array();
		for ( $n = count( $mots ); $n >= 1 && ! $ids; $n-- ) {
			$args = array(
				's'              => implode( ' ', array_slice( $mots, 0, $n ) ),
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 3,
				'fields'         => 'ids',
			);
			if ( function_exists( 'relevanssi_do_query' ) ) {
				unset( $args['fields'] );
				$q = new WP_Query();
				$q->parse_query( $args );
				relevanssi_do_query( $q );
				$ids = array();
				foreach ( (array) $q->posts as $r ) {
					$ids[] = is_object( $r ) ? (int) $r->ID : (int) $r;
				}
			} else {
				$ids = get_posts( $args );
			}
			$ids = array_slice( array_values( array_filter( array_map( 'intval', (array) $ids ) ) ), 0, 3 );
		}
		set_transient( $cle, $ids, 12 * HOUR_IN_SECONDS );
	}
	return $ids;
}

/* -------------------------------------------------------------------------
 * Codes courts
 * ---------------------------------------------------------------------- */

/** [maf_404_recherche] : adresse demandée barrée, recherche pré-remplie, recherches fréquentes. */
add_shortcode( 'maf_404_recherche', 'mafv_404_sc_recherche' );
function mafv_404_sc_recherche() {
	$adresse = mafv_404_adresse();
	$mots    = implode( ' ', mafv_404_mots( $adresse ) );
	$chips   = array(
		'Matelas'        => add_query_arg( 's', 'matelas', home_url( '/' ) ),
		'Canapé'         => add_query_arg( 's', 'canapé', home_url( '/' ) ),
		'Premier appart' => add_query_arg( 's', 'premier appartement', home_url( '/' ) ),
		'Avis marques'   => home_url( '/categorie-avis/' ),
	);
	ob_start();
	?>
	<div class="maf404-rech">
		<?php if ( '/' !== $adresse ) : ?>
			<p class="maf404-asked">Adresse demandée : <code><?php echo esc_html( $adresse ); ?></code></p>
		<?php endif; ?>
		<form role="search" method="get" class="maf404-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="screen-reader-text" for="maf404-s">Rechercher sur le site</label>
			<input type="search" id="maf404-s" name="s" value="<?php echo esc_attr( $mots ); ?>" placeholder="Matelas, canapé, bail, voisin bruyant…">
			<button type="submit">Rechercher</button>
		</form>
		<div class="maf404-chips"><span>Souvent cherché :</span>
			<?php
			foreach ( $chips as $txt => $url ) {
				echo '<a href="' . esc_url( $url ) . '">' . esc_html( $txt ) . '</a>';
			}
			?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/** [maf_404_scene] : la porte de l'appartement 404 qui s'ouvre sur une pièce vide. */
add_shortcode( 'maf_404_scene', 'mafv_404_sc_scene' );
function mafv_404_sc_scene() {
	$ease = '.45 0 .25 1;.45 0 .25 1;.45 0 .25 1;.45 0 .25 1;.45 0 .25 1';
	$kt   = '0;.22;.34;.66;.78;1';
	$ferm = '160,96 290,96 290,346 160,346';
	$ouv  = '160,96 198,113 198,329 160,346';
	ob_start();
	?>
	<div class="maf404-art">
	<svg class="maf404-scene" viewBox="0 0 440 440" role="img" aria-label="La porte de l'appartement 404 s'ouvre sur une pièce vide : la page a déménagé">
		<defs>
			<clipPath id="maf404-clip"><rect x="160" y="96" width="130" height="250"/></clipPath>
			<radialGradient id="maf404-halo"><stop offset="0" stop-color="#FFE58A" stop-opacity=".7"/><stop offset=".4" stop-color="#E1B333" stop-opacity=".25"/><stop offset="1" stop-color="#E1B333" stop-opacity="0"/></radialGradient>
		</defs>
		<rect x="16" y="20" width="408" height="392" rx="30" fill="#4C76EA"/>
		<g opacity=".18" stroke="#fff" stroke-width="2"><path d="M60 20v320M120 20v320M330 20v320M390 20v320"/></g>
		<path d="M16 340h408v42a30 30 0 0 1-30 30H46a30 30 0 0 1-30-30z" fill="#2A4FC2"/>
		<path d="M16 340h408" stroke="#1F3FA6" stroke-width="4"/>
		<circle cx="92" cy="140" r="34" fill="#fff" stroke="#14213D" stroke-width="5"/>
		<g fill="#14213D"><circle cx="92" cy="114" r="2.6"/><circle cx="118" cy="140" r="2.6"/><circle cx="92" cy="166" r="2.6"/><circle cx="66" cy="140" r="2.6"/></g>
		<line class="maf404-hr" x1="92" y1="140" x2="92" y2="122" stroke="#14213D" stroke-width="5" stroke-linecap="round"/>
		<line class="maf404-min" x1="92" y1="140" x2="92" y2="113" stroke="#E1B333" stroke-width="3.5" stroke-linecap="round"/>
		<circle cx="92" cy="140" r="4" fill="#14213D"/>
		<g clip-path="url(#maf404-clip)">
			<rect x="160" y="96" width="130" height="250" fill="#14213D"/>
			<rect x="160" y="304" width="130" height="42" fill="#1D2D52"/>
			<ellipse class="maf404-pool" cx="225" cy="318" rx="58" ry="12" fill="#E1B333" opacity=".28"/>
			<g class="maf404-bulb">
				<circle cx="225" cy="168" r="78" fill="url(#maf404-halo)"/>
				<line x1="225" y1="96" x2="225" y2="150" stroke="#C9D3EA" stroke-width="2.5"/>
				<rect x="219" y="148" width="12" height="9" rx="2" fill="#8A95AE"/>
				<circle cx="225" cy="167" r="11" fill="#FFE58A"/>
			</g>
			<g transform="translate(232 278)">
				<path d="M0 14l22-10 22 10v34H0z" fill="#C9925A"/>
				<path d="M0 14l22 10 22-10" fill="none" stroke="#9C6A38" stroke-width="2"/>
				<path d="M22 24v24" stroke="#9C6A38" stroke-width="2"/>
				<rect x="5" y="30" width="13" height="9" rx="1.5" fill="#F3F6FC"/>
			</g>
			<polygon points="<?php echo esc_attr( $ferm ); ?>" fill="#F3F6FC" stroke="#C8D3EC" stroke-width="2">
				<animate attributeName="points" dur="8s" repeatCount="indefinite" calcMode="spline" keyTimes="<?php echo esc_attr( $kt ); ?>" keySplines="<?php echo esc_attr( $ease ); ?>" values="<?php echo esc_attr( "$ferm;$ferm;$ouv;$ouv;$ferm;$ferm" ); ?>"/>
				<animate attributeName="fill" dur="8s" repeatCount="indefinite" calcMode="spline" keyTimes="<?php echo esc_attr( $kt ); ?>" keySplines="<?php echo esc_attr( $ease ); ?>" values="#F3F6FC;#F3F6FC;#C8D3EC;#C8D3EC;#F3F6FC;#F3F6FC"/>
			</polygon>
			<g transform="translate(160 221)"><g>
				<animateTransform attributeName="transform" type="scale" dur="8s" repeatCount="indefinite" calcMode="spline" keyTimes="<?php echo esc_attr( $kt ); ?>" keySplines="<?php echo esc_attr( $ease ); ?>" values="1 1;1 1;.29 .86;.29 .86;1 1;1 1"/>
				<animate attributeName="opacity" dur="8s" repeatCount="indefinite" calcMode="spline" keyTimes="<?php echo esc_attr( $kt ); ?>" keySplines="<?php echo esc_attr( $ease ); ?>" values="1;1;.55;.55;1;1"/>
				<g transform="translate(-160 -221)">
					<rect x="176" y="114" width="98" height="92" rx="6" fill="none" stroke="#C8D3EC" stroke-width="3"/>
					<rect x="176" y="222" width="98" height="104" rx="6" fill="none" stroke="#C8D3EC" stroke-width="3"/>
					<rect x="196" y="132" width="58" height="30" rx="8" fill="#E1B333"/>
					<text x="225" y="154" text-anchor="middle" font-family="Poppins, system-ui, sans-serif" font-weight="800" font-size="20" fill="#14213D">404</text>
					<circle cx="274" cy="232" r="7" fill="#E1B333" stroke="#B88E1F" stroke-width="2"/>
					<g class="maf404-post">
						<rect x="200" y="176" width="52" height="40" rx="2" fill="#FFF1A8"/>
						<rect x="214" y="172" width="24" height="8" rx="1" fill="#DCE5FF" opacity=".9"/>
						<text x="226" y="201" text-anchor="middle" font-family="Poppins, system-ui, sans-serif" font-weight="700" font-size="12" fill="#14213D">Parti !</text>
					</g>
				</g>
			</g></g>
		</g>
		<path fill="#fff" fill-rule="evenodd" d="M148 92a8 8 0 0 1 8-8h138a8 8 0 0 1 8 8v254h-12V96H160v250h-12z"/>
		<path d="M150 352h150l12 26H138z" fill="#E1B333"/>
		<text x="225" y="371" text-anchor="middle" font-family="Poppins, system-ui, sans-serif" font-weight="800" font-size="12" letter-spacing="2" fill="#14213D">BIENVENUE</text>
		<path class="maf404-leaf" d="M352 300c-20-18-24-44-12-62 12 16 18 38 12 62z" fill="#2E8A57"/>
		<path class="maf404-leaf b" d="M356 300c6-28 24-48 44-50-6 22-22 40-44 50z" fill="#3FA36A"/>
		<path class="maf404-leaf" d="M352 302c-18-4-34-16-38-34 18 2 32 14 38 34z" fill="#3FA36A"/>
		<path d="M330 300h48l-6 46h-36z" fill="#fff"/>
		<path d="M333 316h42" stroke="#3361E1" stroke-width="5"/>
	</svg>
	</div>
	<script>(function(){var s=document.querySelector('.maf404-scene');if(s&&window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches&&s.pauseAnimations){s.pauseAnimations();}})();</script>
	<?php
	return ob_get_clean();
}

/** [maf_404_suggestions] : 3 articles trouvés d'après l'adresse, puis les catégories. */
add_shortcode( 'maf_404_suggestions', 'mafv_404_sc_suggestions' );
function mafv_404_sc_suggestions() {
	$mots = mafv_404_mots( mafv_404_adresse() );
	$ids  = mafv_404_resultats( $mots );
	$cats = array(
		'categorie-installation'    => 'Installation',
		'categorie-espace-mobilier' => 'Mobilier & espace',
		'categorie-decoration'      => 'Décoration',
		'categorie-quotidien'       => 'Vie quotidienne',
		'categorie-avis'            => 'Avis & comparatifs',
	);
	ob_start();
	echo '<div class="maf404-sugg">';
	if ( $ids ) {
		echo '<p class="maf404-sub">D\'après l\'adresse demandée : <b>« ' . esc_html( implode( ' ', $mots ) ) . ' »</b></p><ul class="maf404-cards">';
		foreach ( $ids as $id ) {
			$cat  = '';
			$slug = '';
			foreach ( get_the_category( $id ) as $c ) {
				if ( 'categorie-uncategorized' !== $c->slug ) {
					$cat  = $c->name;
					$slug = $c->slug;
					break;
				}
			}
			$img = get_the_post_thumbnail_url( $id, 'medium_large' );
			echo '<li><a class="maf404-card" href="' . esc_url( get_permalink( $id ) ) . '">';
			echo $img ? '<span class="maf404-card-img"><img src="' . esc_url( $img ) . '" alt="" loading="lazy" decoding="async"></span>' : '<span class="maf404-card-img is-vide"></span>';
			echo '<span class="maf404-card-b">';
			if ( $cat ) {
				echo '<span class="maf404-cat" data-cat="' . esc_attr( $slug ) . '">' . esc_html( $cat ) . '</span>';
			}
			echo '<span class="maf404-card-t">' . esc_html( get_the_title( $id ) ) . '</span><span class="maf404-card-l">Lire l\'article →</span></span></a></li>';
		}
		echo '</ul>';
	}
	echo '<div class="maf404-cats"><span>' . ( $ids ? 'Ou parcourez une thématique :' : 'Parcourez nos thématiques :' ) . '</span>';
	foreach ( $cats as $slug => $nom ) {
		echo '<a href="' . esc_url( home_url( '/' . $slug . '/' ) ) . '">' . esc_html( $nom ) . '</a>';
	}
	echo '</div></div>';
	return ob_get_clean();
}

/** [maf_404_besoins] : les cas les plus fréquents et un seul bouton vers la page Contact. */
add_shortcode( 'maf_404_besoins', 'mafv_404_sc_besoins' );
function mafv_404_sc_besoins() {
	$cas  = array( 'Une question sur un article', 'Un lien cassé à signaler', 'Un avis de marque ou une vidéo', 'Un article sponsorisé ou de l’affiliation' );
	$html = '<div class="maf404-contact"><div class="maf404-contact-t"><p class="maf404-contact-p">Écrivez-nous, on répond à tous les messages. Par exemple pour :</p><ul class="maf404-cas">';
	foreach ( $cas as $c ) {
		$html .= '<li>' . esc_html( $c ) . '</li>';
	}
	$html .= '</ul></div><a class="maf404-cta" href="' . esc_url( home_url( '/contact/' ) ) . '"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 7l9 6 9-6"></path></svg>Nous écrire</a></div>';
	return $html;
}

/* Formulaire de contact : l'objet se remplit depuis le lien (?your-subject=…). */
add_filter( 'wpcf7_form_tag', 'mafv_404_objet_prerempli' );
function mafv_404_objet_prerempli( $tag ) {
	$nom = is_object( $tag ) ? $tag->name : ( isset( $tag['name'] ) ? $tag['name'] : '' );
	if ( 'your-subject' !== $nom ) {
		return $tag;
	}
	if ( is_object( $tag ) ) {
		if ( ! in_array( 'default:get', $tag->options, true ) ) {
			$tag->options[] = 'default:get';
		}
	} elseif ( isset( $tag['options'] ) && is_array( $tag['options'] ) && ! in_array( 'default:get', $tag['options'], true ) ) {
		$tag['options'][] = 'default:get';
	}
	return $tag;
}
