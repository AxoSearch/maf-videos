<?php
/**
 * Plugin Name: Vidéos MAF
 * Description: Gère toutes les vidéos Mon Appart Facile (formats courts et vidéos détaillées) à un seul endroit, et les affiche sur la home et la page Vidéos.
 * Version: 1.3.3
 * Author: Mon Appart Facile
 * Text Domain: maf-videos
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MAFV_VERSION', '1.3.3' );
define( 'MAFV_URL', plugin_dir_url( __FILE__ ) );
define( 'MAFV_FILE', __FILE__ );
require_once __DIR__ . '/maj-github.php';
require_once __DIR__ . '/recherche.php';
require_once __DIR__ . '/global.php';

/* -------------------------------------------------------------------------
 * 1. Réglages simples (thèmes et emplacements)
 * ---------------------------------------------------------------------- */

function mafv_themes() {
	return array(
		'sommeil'   => 'Sommeil & literie',
		'quotidien' => 'Vie quotidienne',
		'animaux'   => 'Animaux',
		'logement'  => 'Premier logement',
		'deco'      => 'Déco & mobilier',
		'bonsplans' => 'Bons plans',
		'avis'      => 'Avis marque',
		'comparatif'=> 'Comparatif',
	);
}

function mafv_theme_couleurs() {
	return array(
		'sommeil'    => '#3361E1',
		'quotidien'  => '#8733E1',
		'animaux'    => '#2E8A57',
		'logement'   => '#9A6412',
		'deco'       => '#B4316E',
		'bonsplans'  => '#C2410C',
		'avis'       => '#3361E1',
		'comparatif' => '#3361E1',
	);
}

/** Emplacements : clé de méta => libellé. */
function mafv_zones() {
	return array(
		'maf_hero_tel' => 'Home : téléphones du haut de page (3 formats courts)',
		'maf_hero_yt'  => 'Home : carte YouTube du haut de page (1 vidéo détaillée)',
		'maf_home'     => 'Home : section « Nos conseils maison, aussi en vidéo »',
	);
}

/* -------------------------------------------------------------------------
 * 2. Type de contenu « Vidéo » et champs
 * ---------------------------------------------------------------------- */

add_action( 'init', 'mafv_register' );
function mafv_register() {
	register_post_type(
		'maf_video',
		array(
			'labels'        => array(
				'name'               => 'Vidéos',
				'singular_name'      => 'Vidéo',
				'menu_name'          => 'Vidéos',
				'add_new'            => 'Ajouter une vidéo',
				'add_new_item'       => 'Ajouter une vidéo',
				'edit_item'          => 'Modifier la vidéo',
				'new_item'           => 'Nouvelle vidéo',
				'all_items'          => 'Toutes les vidéos',
				'search_items'       => 'Rechercher une vidéo',
				'not_found'          => 'Aucune vidéo',
				'not_found_in_trash' => 'Aucune vidéo dans la corbeille',
			),
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'show_in_rest'  => true,
			'rest_base'     => 'maf-videos',
			'menu_position' => 21,
			'menu_icon'     => 'dashicons-video-alt3',
			'supports'      => array( 'title', 'custom-fields' ),
			'has_archive'   => false,
			'rewrite'       => false,
		)
	);

	$auth = function () {
		return current_user_can( 'edit_posts' );
	};

	$metas = array(
		'maf_url'         => array( 'string', 'esc_url_raw' ),
		'maf_format'      => array( 'string', 'sanitize_key' ),
		'maf_titre_court' => array( 'string', 'sanitize_text_field' ),
		'maf_theme'       => array( 'string', 'sanitize_key' ),
		'maf_article'     => array( 'integer', 'absint' ),
		'maf_ordre'       => array( 'integer', 'mafv_san_int' ),
		'maf_hero_tel'    => array( 'boolean', 'rest_sanitize_boolean' ),
		'maf_hero_yt'     => array( 'boolean', 'rest_sanitize_boolean' ),
		'maf_home'        => array( 'boolean', 'rest_sanitize_boolean' ),
	);
	foreach ( $metas as $key => $def ) {
		register_post_meta(
			'maf_video',
			$key,
			array(
				'type'              => $def[0],
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => $def[1],
				'auth_callback'     => $auth,
			)
		);
	}
}

/** Nettoyage d'un entier (les fonctions PHP natives refusent les arguments en trop). */
function mafv_san_int( $v ) {
	return (int) $v;
}

/* Classe « mafx-page » sur les pages refaites (sert à la mise en page). */
add_filter( 'body_class', 'mafv_body_class' );
function mafv_body_class( $classes ) {
	if ( is_singular() ) {
		$p = get_post();
		if ( $p && false !== strpos( (string) $p->post_content, 'mafx-first' ) ) {
			$classes[] = 'mafx-page';
		}
	}
	return $classes;
}

/** Extrait l'identifiant YouTube d'un lien (watch, shorts, youtu.be, embed). */
function mafv_youtube_id( $url ) {
	if ( preg_match( '~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|shorts/|embed/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', (string) $url, $m ) ) {
		return $m[1];
	}
	return '';
}

/** Format : celui choisi, sinon déduit du lien (/shorts/ = format court). */
function mafv_format( $post_id ) {
	$f = get_post_meta( $post_id, 'maf_format', true );
	if ( in_array( $f, array( 'court', 'long' ), true ) ) {
		return $f;
	}
	return ( false !== strpos( (string) get_post_meta( $post_id, 'maf_url', true ), '/shorts/' ) ) ? 'court' : 'long';
}

/* Titre automatique : si le titre est vide, on le récupère chez YouTube. */
add_filter( 'wp_insert_post_data', 'mafv_titre_auto', 10, 2 );
function mafv_titre_auto( $data, $postarr ) {
	if ( 'maf_video' !== $data['post_type'] || '' !== trim( (string) $data['post_title'] ) ) {
		return $data;
	}
	$url = '';
	if ( isset( $_POST['maf_url'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$url = esc_url_raw( wp_unslash( $_POST['maf_url'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	} elseif ( ! empty( $postarr['ID'] ) ) {
		$url = get_post_meta( $postarr['ID'], 'maf_url', true );
	}
	$titre = mafv_oembed_titre( $url );
	if ( $titre ) {
		$data['post_title'] = $titre;
	}
	return $data;
}

function mafv_oembed_titre( $url ) {
	$id = mafv_youtube_id( $url );
	if ( ! $id ) {
		return '';
	}
	$rep = wp_remote_get( 'https://www.youtube.com/oembed?format=json&url=' . rawurlencode( 'https://www.youtube.com/watch?v=' . $id ), array( 'timeout' => 6 ) );
	if ( is_wp_error( $rep ) || 200 !== wp_remote_retrieve_response_code( $rep ) ) {
		return '';
	}
	$json = json_decode( wp_remote_retrieve_body( $rep ), true );
	return isset( $json['title'] ) ? sanitize_text_field( $json['title'] ) : '';
}

/* -------------------------------------------------------------------------
 * 3. Écran d'édition
 * ---------------------------------------------------------------------- */

add_action( 'add_meta_boxes', 'mafv_metabox' );
function mafv_metabox() {
	add_meta_box( 'mafv_box', 'La vidéo', 'mafv_metabox_html', 'maf_video', 'normal', 'high' );
}

function mafv_metabox_html( $post ) {
	wp_nonce_field( 'mafv_save', 'mafv_nonce' );
	$url    = get_post_meta( $post->ID, 'maf_url', true );
	$format = get_post_meta( $post->ID, 'maf_format', true );
	$court  = get_post_meta( $post->ID, 'maf_titre_court', true );
	$theme  = get_post_meta( $post->ID, 'maf_theme', true );
	$art    = (int) get_post_meta( $post->ID, 'maf_article', true );
	$ordre  = get_post_meta( $post->ID, 'maf_ordre', true );
	$id     = mafv_youtube_id( $url );
	$articles = get_posts(
		array(
			'post_type'   => array( 'post', 'page' ),
			'post_status' => 'publish',
			'numberposts' => 400,
			'orderby'     => 'title',
			'order'       => 'ASC',
		)
	);
	?>
	<style>
		.mafv-f{margin:0 0 18px}.mafv-f label.t{display:block;font-weight:600;margin-bottom:6px}
		.mafv-f input[type=url],.mafv-f input[type=text],.mafv-f select{width:100%;max-width:640px}
		.mafv-f .d{color:#646970;font-size:12px;margin-top:4px}
		.mafv-prev{display:flex;gap:16px;align-items:center;margin-bottom:18px}
		.mafv-prev img{height:120px;border-radius:8px}
	</style>
	<?php if ( $id ) : ?>
		<div class="mafv-prev">
			<img src="<?php echo esc_url( 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg' ); ?>" alt="">
			<div><strong><?php echo 'court' === mafv_format( $post->ID ) ? 'Format court' : 'Vidéo détaillée'; ?></strong><br>
			<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">Voir sur YouTube</a></div>
		</div>
	<?php endif; ?>
	<div class="mafv-f">
		<label class="t" for="maf_url">Lien YouTube (obligatoire)</label>
		<input type="url" id="maf_url" name="maf_url" value="<?php echo esc_attr( $url ); ?>" placeholder="https://www.youtube.com/shorts/... ou https://www.youtube.com/watch?v=...">
		<div class="d">Le titre (si vous laissez le champ du haut vide), la miniature et le format se remplissent tout seuls.</div>
	</div>
	<div class="mafv-f">
		<label class="t">Format</label>
		<label><input type="radio" name="maf_format" value="" <?php checked( '', $format ); ?>> Automatique</label>&nbsp;&nbsp;
		<label><input type="radio" name="maf_format" value="court" <?php checked( 'court', $format ); ?>> Format court</label>&nbsp;&nbsp;
		<label><input type="radio" name="maf_format" value="long" <?php checked( 'long', $format ); ?>> Vidéo détaillée</label>
	</div>
	<div class="mafv-f">
		<label class="t" for="maf_titre_court">Titre affiché sur le site (facultatif)</label>
		<input type="text" id="maf_titre_court" name="maf_titre_court" value="<?php echo esc_attr( $court ); ?>">
		<div class="d">Pour raccourcir le titre YouTube. Vide = titre de la vidéo, sans les emojis.</div>
	</div>
	<div class="mafv-f">
		<label class="t" for="maf_theme">Thème</label>
		<select id="maf_theme" name="maf_theme">
			<option value="">Aucun</option>
			<?php foreach ( mafv_themes() as $k => $l ) : ?>
				<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $k, $theme ); ?>><?php echo esc_html( $l ); ?></option>
			<?php endforeach; ?>
		</select>
		<div class="d">Sert aux filtres de la page Vidéos.</div>
	</div>
	<div class="mafv-f">
		<label class="t" for="maf_article">Article lié (facultatif)</label>
		<select id="maf_article" name="maf_article">
			<option value="0">Aucun</option>
			<?php foreach ( $articles as $a ) : ?>
				<option value="<?php echo (int) $a->ID; ?>" <?php selected( $a->ID, $art ); ?>><?php echo esc_html( get_the_title( $a ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<div class="d">Affiche un bouton « Lire l'article » sous la vidéo.</div>
	</div>
	<div class="mafv-f">
		<label class="t">Mettre en avant sur la home</label>
		<?php foreach ( mafv_zones() as $k => $l ) : ?>
			<label style="display:block;margin:4px 0"><input type="checkbox" name="<?php echo esc_attr( $k ); ?>" value="1" <?php checked( (bool) get_post_meta( $post->ID, $k, true ) ); ?>> <?php echo esc_html( $l ); ?></label>
		<?php endforeach; ?>
		<div class="d">Toutes les vidéos apparaissent sur la page Vidéos. Ces cases choisissent celles de la home. Si vous en cochez trop, les premières dans l'ordre s'affichent.</div>
	</div>
	<div class="mafv-f">
		<label class="t" for="maf_ordre">Ordre (facultatif)</label>
		<input type="text" id="maf_ordre" name="maf_ordre" value="<?php echo esc_attr( $ordre ); ?>" style="max-width:100px">
		<div class="d">1 = en premier. Vide = les plus récentes d'abord.</div>
	</div>
	<?php
}

add_action( 'save_post_maf_video', 'mafv_save', 10, 2 );
function mafv_save( $post_id, $post ) {
	if ( ! isset( $_POST['mafv_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mafv_nonce'] ) ), 'mafv_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, 'maf_url', esc_url_raw( wp_unslash( $_POST['maf_url'] ?? '' ) ) );
	$f = sanitize_key( wp_unslash( $_POST['maf_format'] ?? '' ) );
	update_post_meta( $post_id, 'maf_format', in_array( $f, array( 'court', 'long' ), true ) ? $f : '' );
	update_post_meta( $post_id, 'maf_titre_court', sanitize_text_field( wp_unslash( $_POST['maf_titre_court'] ?? '' ) ) );
	$t = sanitize_key( wp_unslash( $_POST['maf_theme'] ?? '' ) );
	update_post_meta( $post_id, 'maf_theme', array_key_exists( $t, mafv_themes() ) ? $t : '' );
	update_post_meta( $post_id, 'maf_article', absint( $_POST['maf_article'] ?? 0 ) );
	$o = trim( (string) wp_unslash( $_POST['maf_ordre'] ?? '' ) );
	if ( '' === $o ) {
		delete_post_meta( $post_id, 'maf_ordre' );
	} else {
		update_post_meta( $post_id, 'maf_ordre', intval( $o ) );
	}
	foreach ( array_keys( mafv_zones() ) as $k ) {
		update_post_meta( $post_id, $k, ! empty( $_POST[ $k ] ) );
	}
}

/* Colonnes de la liste des vidéos. */
add_filter( 'manage_maf_video_posts_columns', 'mafv_cols' );
function mafv_cols( $cols ) {
	return array(
		'cb'         => $cols['cb'],
		'mafv_img'   => '',
		'title'      => 'Vidéo',
		'mafv_fmt'   => 'Format',
		'mafv_theme' => 'Thème',
		'mafv_zones' => 'Sur la home',
		'mafv_ordre' => 'Ordre',
		'date'       => $cols['date'],
	);
}
add_action( 'manage_maf_video_posts_custom_column', 'mafv_col', 10, 2 );
function mafv_col( $col, $post_id ) {
	$id = mafv_youtube_id( get_post_meta( $post_id, 'maf_url', true ) );
	switch ( $col ) {
		case 'mafv_img':
			if ( $id ) {
				echo '<img src="' . esc_url( 'https://i.ytimg.com/vi/' . $id . '/mqdefault.jpg' ) . '" alt="" style="width:96px;border-radius:6px">';
			}
			break;
		case 'mafv_fmt':
			echo 'court' === mafv_format( $post_id ) ? 'Court' : 'Détaillée';
			break;
		case 'mafv_theme':
			$t = get_post_meta( $post_id, 'maf_theme', true );
			$all = mafv_themes();
			echo esc_html( $all[ $t ] ?? '—' );
			break;
		case 'mafv_zones':
			$out = array();
			if ( get_post_meta( $post_id, 'maf_hero_tel', true ) ) {
				$out[] = 'Téléphones';
			}
			if ( get_post_meta( $post_id, 'maf_hero_yt', true ) ) {
				$out[] = 'Carte YouTube';
			}
			if ( get_post_meta( $post_id, 'maf_home', true ) ) {
				$out[] = 'Section vidéo';
			}
			echo esc_html( $out ? implode( ', ', $out ) : '—' );
			break;
		case 'mafv_ordre':
			$o = get_post_meta( $post_id, 'maf_ordre', true );
			echo '' === $o ? '—' : (int) $o;
			break;
	}
}

/* -------------------------------------------------------------------------
 * 4. Lecture des vidéos
 * ---------------------------------------------------------------------- */

/**
 * @param array $args format (court|long|''), zone (clé de méta|''), limit (int).
 */
function mafv_get( $args = array() ) {
	$args = wp_parse_args( $args, array( 'format' => '', 'zone' => '', 'limit' => -1 ) );
	$q    = new WP_Query(
		array(
			'post_type'      => 'maf_video',
			'post_status'    => 'publish',
			'posts_per_page' => 300,
			'no_found_rows'  => true,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	$items = array();
	foreach ( $q->posts as $p ) {
		$url = get_post_meta( $p->ID, 'maf_url', true );
		$id  = mafv_youtube_id( $url );
		if ( ! $id ) {
			continue;
		}
		$fmt = mafv_format( $p->ID );
		if ( $args['format'] && $fmt !== $args['format'] ) {
			continue;
		}
		if ( $args['zone'] && ! get_post_meta( $p->ID, $args['zone'], true ) ) {
			continue;
		}
		$titre = get_post_meta( $p->ID, 'maf_titre_court', true );
		if ( ! $titre ) {
			$titre = get_the_title( $p );
		}
		$titre   = trim( preg_replace( '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', html_entity_decode( $titre, ENT_QUOTES, 'UTF-8' ) ) );
		$ordre   = get_post_meta( $p->ID, 'maf_ordre', true );
		$theme   = get_post_meta( $p->ID, 'maf_theme', true );
		$art     = (int) get_post_meta( $p->ID, 'maf_article', true );
		$items[] = array(
			'id'      => $id,
			'format'  => $fmt,
			'titre'   => $titre,
			'theme'   => $theme,
			'article' => ( $art && 'publish' === get_post_status( $art ) ) ? get_permalink( $art ) : '',
			'ordre'   => ( '' === $ordre ) ? PHP_INT_MAX : (int) $ordre,
			'date'    => strtotime( $p->post_date ),
		);
	}
	usort(
		$items,
		function ( $a, $b ) {
			if ( $a['ordre'] !== $b['ordre'] ) {
				return $a['ordre'] <=> $b['ordre'];
			}
			return $b['date'] <=> $a['date'];
		}
	);
	if ( $args['limit'] > 0 ) {
		$items = array_slice( $items, 0, (int) $args['limit'] );
	}
	return $items;
}

function mafv_img( $v ) {
	$base = 'https://i.ytimg.com/vi/' . $v['id'] . '/';
	if ( 'court' === $v['format'] ) {
		// Image verticale HD des Shorts (1080 x 1920), repli sur la miniature standard.
		return array( $base . 'oar2.jpg', $base . 'hqdefault.jpg' );
	}
	return array( $base . 'maxresdefault.jpg', $base . 'hqdefault.jpg' );
}

/** Attributs communs d'un déclencheur de lecture. */
function mafv_play_attrs( $v ) {
	return sprintf(
		' data-mafv-id="%s" data-mafv-format="%s" data-mafv-titre="%s" data-mafv-article="%s" aria-label="%s"',
		esc_attr( $v['id'] ),
		esc_attr( $v['format'] ),
		esc_attr( $v['titre'] ),
		esc_url( $v['article'] ),
		esc_attr( 'Lire la vidéo : ' . $v['titre'] )
	);
}

function mafv_thumb( $v, $class = '' ) {
	$src = mafv_img( $v );
	// YouTube renvoie une image grise de 120 px quand une taille n'existe pas : on bascule alors sur le repli.
	$swap = "if(this.dataset.f!=='1'){this.dataset.f='1';this.src='" . esc_url( $src[1] ) . "';}";
	return sprintf(
		'<img class="%s" src="%s" alt="" loading="lazy" decoding="async" onload="%s" onerror="%s">',
		esc_attr( $class ),
		esc_url( $src[0] ),
		esc_attr( 'if(this.naturalWidth<=120){' . $swap . '}' ),
		esc_attr( $swap )
	);
}

function mafv_playicon( $size = 20 ) {
	return '<span class="mafv-pi" aria-hidden="true"><svg width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" fill="#3361E1"/></svg></span>';
}

function mafv_enqueue() {
	wp_enqueue_style( 'maf-videos', MAFV_URL . 'assets/maf-videos.css', array(), MAFV_VERSION );
	wp_enqueue_script( 'maf-videos', MAFV_URL . 'assets/maf-videos.js', array(), MAFV_VERSION, true );
}

/* Chargement dans l'en-tête quand la page contient un bloc vidéo (évite tout clignement). */
add_action( 'wp_enqueue_scripts', 'mafv_enqueue_si_besoin' );
function mafv_enqueue_si_besoin() {
	if ( is_singular() ) {
		$p = get_post();
		if ( $p && false !== strpos( (string) $p->post_content, '[maf_videos' ) ) {
			mafv_enqueue();
		}
		// Mise en page de la home et de la page Vidéos (sections « mafx- »).
		if ( $p && false !== strpos( (string) $p->post_content, 'mafx-' ) ) {
			wp_enqueue_style( 'maf-home', MAFV_URL . 'assets/maf-home.css', array(), MAFV_VERSION );
			wp_enqueue_script( 'maf-home', MAFV_URL . 'assets/maf-home.js', array(), MAFV_VERSION, true );
		}
	}
}

/* Pages construites avec les sections « mafx- » : le titre de page est masqué (le H1 est dans le haut de page). */
add_filter( 'generate_show_title', 'mafv_masquer_titre' );
function mafv_masquer_titre( $show ) {
	if ( is_singular() ) {
		$p = get_post();
		if ( $p && false !== strpos( (string) $p->post_content, 'mafx-first' ) ) {
			return false;
		}
	}
	return $show;
}

/* Catégories des listes d'articles (classe « mafx-cat ») : affichées sans lien, seul l'article est cliquable. */
add_filter( 'render_block_core/post-terms', 'mafv_categories_sans_lien', 10, 2 );
function mafv_categories_sans_lien( $html, $block ) {
	$cls = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';
	if ( false === strpos( $cls, 'mafx-cat' ) ) {
		return $html;
	}
	return preg_replace( '#<a\s+href="([^"]*)"[^>]*>(.*?)</a>#s', '<span class="mafx-term" data-term="$1">$2</span>', $html );
}

/* -------------------------------------------------------------------------
 * Formulaire de contact (Contact Form 7) : protection anti-robots sans case à cocher.
 * Le formulaire contient un champ piège « maf-site » (invisible, doit rester vide)
 * et un jeton « maf-js » rempli par le navigateur quelques secondes après l'affichage.
 * Un envoi sans jeton, trop rapide ou avec le piège rempli est classé en indésirable.
 * ---------------------------------------------------------------------- */
add_filter( 'wpcf7_spam', 'mafv_cf7_antispam', 9, 2 );
function mafv_cf7_antispam( $spam, $submission = null ) {
	if ( $spam ) {
		return $spam;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	$form = ( $submission && method_exists( $submission, 'get_contact_form' ) ) ? $submission->get_contact_form() : null;
	if ( ! $form || MAFV_CF7_TITRE !== $form->title() ) {
		return $spam; // Autre formulaire du site : on ne touche à rien.
	}
	$piege = isset( $_POST['maf-site'] ) ? trim( (string) wp_unslash( $_POST['maf-site'] ) ) : '';
	$jeton = isset( $_POST['maf-js'] ) ? (string) wp_unslash( $_POST['maf-js'] ) : '';
	// phpcs:enable
	if ( '' !== $piege ) {
		return mafv_cf7_marquer( $submission, 'champ piège rempli' );
	}
	// Jeton « mafjs-<affichage>-<maintenant> » en millisecondes, deux heures du même navigateur (aucun souci de décalage d'horloge).
	if ( ! preg_match( '/^mafjs-(\d{10,13})-(\d{10,13})$/', $jeton, $m ) ) {
		return mafv_cf7_marquer( $submission, 'jeton JavaScript absent' );
	}
	$ecart = ( (float) $m[2] - (float) $m[1] ) / 1000;
	if ( $ecart < 3 || $ecart > 86400 ) {
		return mafv_cf7_marquer( $submission, 'envoi trop rapide ou jeton périmé' );
	}
	return $spam;
}
/* Création automatique du formulaire « Contact (refonte 2026) » (une seule fois, dans l'administration). */
define( 'MAFV_CF7_TITRE', 'Contact (refonte 2026)' );
add_action( 'admin_init', 'mafv_cf7_creer_formulaire' );
function mafv_cf7_creer_formulaire() {
	if ( ! class_exists( 'WPCF7_ContactForm' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$id = (int) get_option( 'mafv_cf7_id' );
	if ( $id && 'wpcf7_contact_form' === get_post_type( $id ) ) {
		return;
	}
	$existants = WPCF7_ContactForm::find( array( 'title' => MAFV_CF7_TITRE, 'post_status' => 'any' ) );
	if ( $existants ) {
		update_option( 'mafv_cf7_id', $existants[0]->id(), false );
		return;
	}
	$cf = WPCF7_ContactForm::get_template( array( 'title' => MAFV_CF7_TITRE, 'locale' => 'fr_FR' ) );
	$form = <<<'MAFFORM'
<div class="mafx-cf">
<div class="mafx-cf-row2">
<div class="mafx-cf-field"><label for="c-nom">Votre nom</label>[text* your-name id:c-nom autocomplete:name akismet:author maxlength:80 placeholder "Camille Martin"]</div>
<div class="mafx-cf-field"><label for="c-mail">Votre e-mail</label>[email* your-email id:c-mail autocomplete:email akismet:author_email maxlength:120 placeholder "camille@exemple.fr"]</div>
</div>
<div class="mafx-cf-field"><label for="c-sujet">Objet</label>[text* your-subject id:c-sujet maxlength:150 placeholder "Question sur un article, partenariat…"]</div>
<div class="mafx-cf-field"><label for="c-msg">Votre message</label>[textarea* your-message id:c-msg maxlength:5000 placeholder "Bonjour, je voulais savoir si…"]</div>
<div class="mafx-cf-ok">[acceptance rgpd] J'accepte que Mon Appart Facile utilise ces informations pour me répondre (<a href="https://www.mon-appart-facile.fr/politique-de-confidentialite/" target="_blank" rel="noopener">politique de confidentialité</a>). [/acceptance]</div>
<div class="mafx-cf-shield"><span>Protégé contre les robots, sans case à cocher.</span></div>
<div class="mafx-cf-send">[submit class:mafx-cf-btn "Envoyer le message"]</div>
</div>
MAFFORM;
	$corps = "Vous avez reçu un nouveau message via le formulaire de contact du site Mon Appart Facile.<br><br>\n🔹 Nom : [your-name]<br>\n🔹 E-mail : [your-email]<br>\n🔹 Objet : [your-subject]<br><br>\n✉️ Message :<br>\n[your-message]<br><br>\n---<br>\n🖥️ Envoyé depuis : [_url]";
	$mail = array_merge(
		(array) $cf->prop( 'mail' ),
		array(
			'active'             => true,
			'subject'            => '[Mon Appart Facile] Nouvelle demande de contact – "[your-subject]"',
			'sender'             => 'Mon Appart Facile <wordpress@mon-appart-facile.fr>',
			'recipient'          => 'contact@mon-appart-facile.fr',
			'body'               => $corps,
			'additional_headers' => 'Reply-To: [your-email]',
			'use_html'           => true,
		)
	);
	$mail2 = array_merge( (array) $cf->prop( 'mail_2' ), array( 'active' => false ) );
	$msgs  = array_merge(
		(array) $cf->prop( 'messages' ),
		array(
			'mail_sent_ok'      => 'Merci, votre message est bien parti ! On vous répond dès que possible.',
			'mail_sent_ng'      => 'Le message n’a pas pu partir. Réessayez dans un instant, ou écrivez-nous à contact@mon-appart-facile.fr.',
			'validation_error'  => 'Un champ est à compléter ou à corriger.',
			'spam'              => 'Le message n’a pas pu partir. Réessayez dans un instant, ou écrivez-nous à contact@mon-appart-facile.fr.',
			'accept_terms'      => 'Merci de cocher la case pour que nous puissions vous répondre.',
			'invalid_required'  => 'Ce champ est à remplir.',
			'invalid_too_long'  => 'Ce texte est un peu trop long.',
			'invalid_too_short' => 'Ce texte est trop court.',
			'invalid_email'     => 'Cette adresse e-mail ne semble pas valide.',
		)
	);
	$cf->set_properties( array( 'form' => $form, 'mail' => $mail, 'mail_2' => $mail2, 'messages' => $msgs ) );
	$nouvel_id = $cf->save();
	if ( $nouvel_id ) {
		update_option( 'mafv_cf7_id', (int) $nouvel_id, false );
	}
}

/* Champs anti-robots ajoutés au rendu du formulaire MAF (jamais filtrés par CF7). */
function mafv_cf7_est_maf() {
	if ( ! class_exists( 'WPCF7_ContactForm' ) ) {
		return false;
	}
	$cf = WPCF7_ContactForm::get_current();
	return $cf && MAFV_CF7_TITRE === $cf->title();
}
add_filter( 'wpcf7_form_hidden_fields', 'mafv_cf7_champs_caches' );
function mafv_cf7_champs_caches( $champs ) {
	if ( mafv_cf7_est_maf() ) {
		$champs['maf-js'] = '';
	}
	return $champs;
}
add_filter( 'wpcf7_form_elements', 'mafv_cf7_piege' );
function mafv_cf7_piege( $html ) {
	if ( mafv_cf7_est_maf() ) {
		$html = '<div class="mafx-cf-hp" aria-hidden="true"><label for="c-site">Ne pas remplir ce champ</label><input type="text" id="c-site" name="maf-site" value="" tabindex="-1" autocomplete="off"></div>' . $html;
	}
	return $html;
}

function mafv_cf7_marquer( $submission, $raison ) {
	if ( $submission && method_exists( $submission, 'add_spam_log' ) ) {
		$submission->add_spam_log( array( 'agent' => 'maf-videos', 'reason' => $raison ) );
	}
	return true;
}

/* Les mêmes styles dans l'éditeur, pour voir la page telle qu'en ligne. */
add_action( 'enqueue_block_editor_assets', 'mafv_editor_styles' );
function mafv_editor_styles() {
	wp_enqueue_style( 'maf-home-editor', MAFV_URL . 'assets/maf-home.css', array(), MAFV_VERSION );
	wp_enqueue_style( 'maf-videos-editor', MAFV_URL . 'assets/maf-videos.css', array(), MAFV_VERSION );
}

/* -------------------------------------------------------------------------
 * 5. Blocs d'affichage (codes courts)
 * ---------------------------------------------------------------------- */

/** [maf_videos_hero] : 3 téléphones + carte YouTube + badge, pour le haut de la home. */
add_shortcode( 'maf_videos_hero', 'mafv_sc_hero' );
function mafv_sc_hero( $atts ) {
	$atts   = shortcode_atts( array( 'badge' => 'Nouvelles vidéos chaque semaine' ), $atts );
	$courts = mafv_get( array( 'format' => 'court', 'zone' => 'maf_hero_tel', 'limit' => 3 ) );
	$longs  = mafv_get( array( 'format' => 'long', 'zone' => 'maf_hero_yt', 'limit' => 1 ) );
	if ( ! $courts && ! $longs ) {
		return '';
	}
	mafv_enqueue();
	ob_start();
	echo '<div class="mafv-hero" aria-label="Nos vidéos">';
	$i = 0;
	foreach ( $courts as $v ) {
		++$i;
		echo '<button type="button" class="mafv-play mafv-ph mafv-ph' . (int) $i . '"' . mafv_play_attrs( $v ) . '>' . mafv_thumb( $v ) . mafv_playicon( 18 ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	foreach ( $longs as $v ) {
		echo '<button type="button" class="mafv-play mafv-ytmini"' . mafv_play_attrs( $v ) . '><span class="mafv-ytmini-img">' . mafv_thumb( $v ) . mafv_playicon( 16 ) . '</span><span class="mafv-ytmini-txt"><span class="mafv-kicker">Sur YouTube</span><strong>' . esc_html( $v['titre'] ) . '</strong><span>Vidéo détaillée</span></span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	if ( $atts['badge'] ) {
		echo '<span class="mafv-badge">' . esc_html( $atts['badge'] ) . '</span>';
	}
	echo '</div>';
	return ob_get_clean();
}

/** Grille de formats courts. */
function mafv_grid_courts( $items, $with_theme = false ) {
	$themes = mafv_themes();
	$colors = mafv_theme_couleurs();
	$html   = '<div class="mafv-shorts">';
	foreach ( $items as $v ) {
		$t     = $v['theme'];
		$html .= '<div class="mafv-short" data-theme="' . esc_attr( $t ) . '"><button type="button" class="mafv-play mafv-short-img"' . mafv_play_attrs( $v ) . '>' . mafv_thumb( $v ) . mafv_playicon( 18 ) . '</button>';
		if ( $with_theme && $t && isset( $themes[ $t ] ) ) {
			$html .= '<span class="mafv-kicker" style="color:' . esc_attr( $colors[ $t ] ?? '#3361E1' ) . '">' . esc_html( $themes[ $t ] ) . '</span>';
		}
		$html .= '<span class="mafv-short-t">' . esc_html( $v['titre'] ) . '</span></div>';
	}
	return $html . '</div>';
}

/** Grille de vidéos détaillées. */
function mafv_grid_longs( $items, $cols = 4 ) {
	$themes = mafv_themes();
	$html   = '<div class="mafv-longs mafv-cols' . (int) $cols . '">';
	foreach ( $items as $v ) {
		$type  = ( $v['theme'] && isset( $themes[ $v['theme'] ] ) ) ? $themes[ $v['theme'] ] : 'Vidéo détaillée';
		$html .= '<div class="mafv-long"><button type="button" class="mafv-play mafv-long-img"' . mafv_play_attrs( $v ) . '>' . mafv_thumb( $v ) . mafv_playicon( 22 ) . '</button><div class="mafv-long-b"><span class="mafv-kicker">' . esc_html( $type ) . '</span><span class="mafv-long-t">' . esc_html( $v['titre'] ) . '</span>';
		if ( $v['article'] ) {
			$html .= '<a class="mafv-link" href="' . esc_url( $v['article'] ) . '">Lire l\'article →</a>';
		}
		$html .= '</div></div>';
	}
	return $html . '</div>';
}

/** [maf_videos_home] : onglets Formats courts / Vidéos détaillées de la home. */
add_shortcode( 'maf_videos_home', 'mafv_sc_home' );
function mafv_sc_home( $atts ) {
	// « lien » et « lien_texte » : bouton affiché à droite des onglets (ex. vers la page Vidéos).
	$atts   = shortcode_atts( array( 'courts' => 5, 'longs' => 4, 'lien' => '', 'lien_texte' => 'Voir toutes nos vidéos →' ), $atts );
	$courts = mafv_get( array( 'format' => 'court', 'zone' => 'maf_home', 'limit' => (int) $atts['courts'] ) );
	$longs  = mafv_get( array( 'format' => 'long', 'zone' => 'maf_home', 'limit' => (int) $atts['longs'] ) );
	if ( ! $courts && ! $longs ) {
		return '';
	}
	mafv_enqueue();
	$uid  = 'mafv' . wp_rand( 1000, 9999 );
	$html = '<div class="mafv-tabs" data-mafv-tabs><div class="mafv-bar">';
	// Vidéos détaillées en premier et affichées par défaut (retour d'Axel du 12/11/2026).
	if ( $courts && $longs ) {
		$html .= '<div class="mafv-tablist" role="tablist" aria-label="Type de vidéos">'
			. '<button type="button" role="tab" id="' . $uid . '-t2" aria-controls="' . $uid . '-p2" aria-selected="true" class="mafv-tab is-on">Vidéos détaillées</button>'
			. '<button type="button" role="tab" id="' . $uid . '-t1" aria-controls="' . $uid . '-p1" aria-selected="false" class="mafv-tab">Formats courts</button></div>';
	}
	if ( $atts['lien'] ) {
		$html .= '<a class="mafv-all" href="' . esc_url( $atts['lien'] ) . '">' . esc_html( $atts['lien_texte'] ) . '</a>';
	}
	$html .= '</div>';
	if ( $longs ) {
		$html .= '<div role="tabpanel" id="' . $uid . '-p2" aria-labelledby="' . $uid . '-t2" class="mafv-panel">' . mafv_grid_longs( $longs, 4 ) . '</div>';
	}
	if ( $courts ) {
		$html .= '<div role="tabpanel" id="' . $uid . '-p1" aria-labelledby="' . $uid . '-t1" class="mafv-panel"' . ( $longs ? ' hidden' : '' ) . '>' . mafv_grid_courts( $courts ) . '</div>';
	}
	return $html . '</div>';
}

/** [maf_videos_page type="long|court"] : listes complètes de la page Vidéos. */
add_shortcode( 'maf_videos_page', 'mafv_sc_page' );
function mafv_sc_page( $atts ) {
	$atts = shortcode_atts( array( 'type' => 'court', 'par_page' => 8 ), $atts );
	mafv_enqueue();
	if ( 'long' === $atts['type'] ) {
		$items = mafv_get( array( 'format' => 'long' ) );
		return $items ? mafv_grid_longs( $items, 3 ) : '';
	}
	$items = mafv_get( array( 'format' => 'court' ) );
	if ( ! $items ) {
		return '';
	}
	$present = array_unique( array_filter( wp_list_pluck( $items, 'theme' ) ) );
	$html    = '<div class="mafv-filtrable" data-mafv-filtre data-par-page="' . (int) $atts['par_page'] . '">';
	if ( count( $present ) > 1 ) {
		$html .= '<div class="mafv-filters" role="group" aria-label="Filtrer par thème"><button type="button" class="mafv-chip is-on" aria-pressed="true" data-theme="">Tous</button>';
		foreach ( mafv_themes() as $k => $l ) {
			if ( in_array( $k, $present, true ) ) {
				$html .= '<button type="button" class="mafv-chip" aria-pressed="false" data-theme="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</button>';
			}
		}
		$html .= '</div>';
	}
	$html .= mafv_grid_courts( $items, true );
	$html .= '<div class="mafv-more-w"><button type="button" class="mafv-more">Voir plus de vidéos</button></div></div>';
	return $html;
}

/** [maf_videos_nombre] : nombre de vidéos enregistrées (pour un compteur). */
add_shortcode( 'maf_videos_nombre', 'mafv_sc_nombre' );
function mafv_sc_nombre( $atts ) {
	$atts = shortcode_atts( array( 'format' => '' ), $atts );
	return (string) count( mafv_get( array( 'format' => $atts['format'] ) ) );
}
