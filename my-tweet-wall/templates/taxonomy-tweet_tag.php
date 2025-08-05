<?php
/**
 * Архив tweet_tag — текст твита + кнопка «Поделиться» в ОДНОМ блоке
 */

get_header();

$term     = get_queried_object();
$tag_name = $term ? $term->name : '';

/* -- Форматируем твит --------------------------------------------------- */
function aliprofi_fmt_tweet( $txt, $skip ) {

    $txt = preg_replace( '/(^|\s)#' . preg_quote( $skip, '/' ) . '\b/iu', '$1', $txt );     // убрать текущий #тег
    $txt = preg_replace( '~(https?://[^\s<]+)~iu', '<a href="$1" target="_blank" rel="noopener">$1</a>', esc_html( $txt ) );
    $txt = preg_replace_callback( '/#([\p{L}][\p{L}0-9_]+)/u', function ( $m ) use ( $skip ) { // ост. хештеги
        if ( strcasecmp( $m[1], $skip ) === 0 ) return '';
        $term = get_term_by( 'name', $m[1], 'tweet_tag' );
        return $term && ! is_wp_error( $term )
            ? '<a href="' . esc_url( get_term_link( $term ) ) . '" class="tweet-hashtag">#' . esc_html( $m[1] ) . '</a>'
            : $m[0];
    }, $txt );

    return nl2br( trim( $txt ) ); // без <p>, только <br>
}

/* -- Облако тегов ------------------------------------------------------- */
function aliprofi_tag_cloud() {

    $tags = get_terms( [
        'taxonomy'   => 'tweet_tag',
        'hide_empty' => true,
        'number'     => 30,
        'orderby'    => 'count',
        'order'      => 'DESC',
    ] );

    if ( ! $tags ) return;

    echo '<div id="tweet-tags"><h3>Популярные теги:</h3>';
    foreach ( $tags as $tag ) {
        echo '<a class="tweet-tag-button" href="' . esc_url( get_term_link( $tag ) ) . '">#' .
             esc_html( $tag->name ) . ' (' . $tag->count . ')</a>';
    }
    echo '</div>';
}
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main tweet-tag-archive">

        <header class="page-header">
            <?php the_archive_title( '<h1 class="page-title">', '</h1>' ); ?>
        </header>

        <?php if ( have_posts() ) : ?>
            <?php while ( have_posts() ) : the_post(); ?>
                <?php
                $content = aliprofi_fmt_tweet( get_post_field( 'post_content', get_the_ID() ), $tag_name );
                $link    = get_permalink();
                ?>
                <div class="tweet-item">
                    <span class="tweet-text"><?php echo $content; ?></span>
                    <span class="tweet-share-btn" onclick="GoTo('<?php echo esc_url( $link ); ?>')">Поделиться</span>
                </div>
            <?php endwhile; ?>

            <?php the_posts_pagination(); ?>

        <?php else : ?>
            <p class="no-tweets">Твиты не найдены.</p>
        <?php endif; ?>

        <?php aliprofi_tag_cloud(); ?>

        <p class="back-link">
            <a href="javascript:history.back();" class="tweet-back">← Вернуться назад</a>
        </p>

    </main>
</div>

<?php get_footer(); ?>
