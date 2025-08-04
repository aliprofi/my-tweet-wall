<?php
/**
 * Шаблон архива tweet-tag
 * – h1 с названием тега (the_archive_title)
 * – Каждый твит выводится как один <p class="tweet-text">
 *   без повторяющегося #тега текущего архива
 *   с активными URL и оставшимися хештегами-ссылками.
 */

get_header();

/* текущий термин */
$term      = get_queried_object();
$tag_name  = $term ? $term->name : '';

/* вспом-функция: ссылки + хештеги */
function aliprofi_format_archive_tweet( $text, $skip_tag ) {

    // Удаляем #текущийТег
    $pattern_skip = '/(^|\s)#' . preg_quote( $skip_tag, '/' ) . '\b/iu';
    $text         = preg_replace( $pattern_skip, '$1', $text );

    // Делаем кликабельные URL
    $text = preg_replace(
        '~(https?://[^\s<]+)~iu',
        '<a href="$1" target="_blank" rel="noopener">$1</a>',
        esc_html( $text )
    );

    // Хештеги-ссылки (кроме пропущенного)
    $text = preg_replace_callback(
        '/#([\p{L}][\p{L}0-9_]+)/u',
        function ( $m ) use ( $skip_tag ) {

            if ( strcasecmp( $m[1], $skip_tag ) === 0 ) {
                return ''; // пропускаем текущий тег
            }

            $term = get_term_by( 'name', $m[1], 'tweet_tag' );
            if ( $term && ! is_wp_error( $term ) ) {
                $url = get_term_link( $term );
                return '<a href="' . esc_url( $url ) . '" class="tweet-hashtag">#' . esc_html( $m[1] ) . '</a>';
            }

            return $m[0];
        },
        $text
    );

    return wpautop( trim( $text ) );
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
                $content = get_post_field( 'post_content', get_the_ID() );
                $content = aliprofi_format_archive_tweet( $content, $tag_name );
                ?>
                <p class="tweet-text" style="font-size:1.4em;line-height:1.4;">
                    <?php echo $content; ?>
                </p>
            <?php endwhile; ?>

            <?php the_posts_pagination(); ?>

        <?php else : ?>
            <p class="no-tweets">Твиты не найдены.</p>
        <?php endif; ?>

    </main>
</div>

<?php get_footer(); ?>
