<?php
/**
 * Шаблон одиночного твита  (post type: mytweet)
 * - без заголовка, только сам текст (с кликабельными URL и хэштегами)
 * - блок кнопок «Поделиться»
 */

get_header();

/* ----------------------------------------------------------------------------
 *  Хелпер: превращаем URL-ы и хэштеги в ссылки
 * ------------------------------------------------------------------------- */
function aliprofi_format_single_tweet( $text ) {

    /* URL → ссылка */
    $text = preg_replace(
        '~(https?://[^\s<]+)~iu',
        '<a href="$1" target="_blank" rel="noopener">$1</a>',
        esc_html( $text )
    );

    /* #тег → ссылка на архив */
    $text = preg_replace_callback(
        '/#([\p{L}][\p{L}0-9_]+)/u',
        function ( $m ) {
            $term = get_term_by( 'name', $m[1], 'tweet_tag' );
            if ( $term && ! is_wp_error( $term ) ) {
                $url = get_term_link( $term );
                return '<a href="' . esc_url( $url ) . '" class="tweet-hashtag">#' .
                       esc_html( $m[1] ) . '</a>';
            }
            return $m[0];
        },
        $text
    );

    return wpautop( $text );
}

/* ----------------------------------------------------------------------------
 *  Вывод контента твита
 * ------------------------------------------------------------------------- */
if ( have_posts() ) :
    while ( have_posts() ) : the_post();

        $content = get_post_field( 'post_content', get_the_ID() );
        $content = aliprofi_format_single_tweet( $content );

        echo '<div class="tweet-single">' . $content . '</div>';

        /* ------------------------------------------------------------------
         *  Кнопки «Поделиться»
         * ---------------------------------------------------------------- */
        $url   = rawurlencode( get_permalink() );
        $txt   = rawurlencode( wp_strip_all_tags( $content ) );

        $share = [
            'vk'   => 'https://vk.com/share.php?url=' . $url . '&title=' . $txt,
            'tw'   => 'https://twitter.com/intent/tweet?url=' . $url . '&text=' . $txt,
            'tg'   => 'https://t.me/share/url?url=' . $url . '&text=' . $txt,
            'ok'   => 'https://connect.ok.ru/offer?url=' . $url . '&title=' . $txt,
           
        ];
        ?>
        <div class="uSocial-Share">
            <span>Поделиться:</span>
            <?php foreach ( $share as $key => $link ) : ?>
                <a class="share-btn share-<?php echo esc_attr( $key ); ?>"
                   href="<?php echo esc_url( $link ); ?>"
                   target="_blank" rel="noopener">
                    <?php echo strtoupper( $key ); ?>
                </a>
            <?php endforeach; ?>
        </div>

    <?php endwhile;
endif;

get_footer();
