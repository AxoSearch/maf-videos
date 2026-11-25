<?php
/**
 * Vidéos MAF : gabarit de la page 404 (même structure qu'une page GeneratePress, sans barre latérale).
 * Le contenu est celui de la page « page-introuvable ». Le code HTTP 404 est conservé.
 *
 * @package MAF_Videos
 */

defined( 'ABSPATH' ) || exit;

$mafv_page = mafv_404_page();

get_header();
?>
	<div <?php function_exists( 'generate_do_attr' ) ? generate_do_attr( 'content' ) : print( 'id="primary" class="content-area"' ); ?>>
		<main <?php function_exists( 'generate_do_attr' ) ? generate_do_attr( 'main' ) : print( 'id="main" class="site-main"' ); ?>>
			<?php do_action( 'generate_before_main_content' ); ?>
			<article id="post-<?php echo (int) $mafv_page->ID; ?>" class="page type-page maf404-article">
				<div class="inside-article">
					<div class="entry-content">
						<?php
						// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
						echo apply_filters( 'the_content', $mafv_page->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</div>
				</div>
			</article>
			<?php do_action( 'generate_after_main_content' ); ?>
		</main>
	</div>
	<?php
	do_action( 'generate_after_primary_content_area' );
	if ( function_exists( 'generate_construct_sidebars' ) ) {
		generate_construct_sidebars();
	}
get_footer();
