<?php
namespace Newstler\Reports;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Report_Generator {
    public function generate_from_query( $query, $date_start = '', $date_end = '', $categories = '' ) {
        ob_start();
        ?>
        <!doctype html>
        <html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
        <head>
            <meta charset="utf-8">
            <title><?php echo esc_html( get_bloginfo( 'name' ) . ' — Boletín' ); ?></title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <style>
                body{ font-family: Arial, Helvetica, sans-serif; margin: 24px; color:#111 }
                header{ text-align:center; margin-bottom: 24px }
                h1{ margin:0; font-size:22px }
                .meta{ margin-top:6px; color:#666; font-size:14px }
                article{ margin-bottom:22px; border-bottom:1px solid #eee; padding-bottom:12px }
                .title{ font-size:18px; margin:0 0 6px }
                .excerpt{ color:#333; margin-top:6px }
                img.thumb{ max-width:200px; height:auto; float:right; margin-left:12px }
                footer{ margin-top:36px; font-size:12px; color:#666; text-align:center }
            </style>
        </head>
        <body>
            <header>
                <h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?> — Boletín</h1>
                <div class="meta">
                    <?php
                    if ( $date_start || $date_end ) {
                        echo esc_html( sprintf( 'Período: %s — %s', $date_start ?: '-', $date_end ?: '-' ) );
                    }
                    if ( $categories ) {
                        echo ' | Categorías: ' . esc_html( is_array( $categories ) ? implode( ',', $categories ) : (string) $categories );
                    }
                    ?>
                </div>
            </header>
            <main>
                <?php
                if ( $query->have_posts() ) :
                    while ( $query->have_posts() ) : $query->the_post(); ?>
                        <article id="post-<?php the_ID(); ?>">
                            <?php if ( has_post_thumbnail() ) : ?>
                                <img class="thumb" src="<?php echo esc_url( get_the_post_thumbnail_url( get_the_ID(), 'medium' ) ); ?>" alt="">
                            <?php endif; ?>
                            <h2 class="title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                            <div class="meta"><?php echo esc_html( get_the_date() ) . ' — ' . esc_html( get_the_author() ); ?></div>
                            <div class="excerpt"><?php echo wp_kses_post( wpautop( get_the_excerpt() ) ); ?></div>
                        </article>
                    <?php
                    endwhile;
                    wp_reset_postdata();
                else :
                    echo '<p>No se encontraron entradas para este filtro.</p>';
                endif;
                ?>
            </main>
            <footer>
                <p>Generado por Newstler • <?php echo esc_html( date( 'Y-m-d H:i' ) ); ?></p>
            </footer>
        </body>
        </html>
        <?php
        $html = ob_get_clean();
        return $html;
    }
}
