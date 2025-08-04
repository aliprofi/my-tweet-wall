<?php
/**
 * Plugin Name: AliProfi Tweet Wall
 * Plugin URI:  https://aliprofi.ru
 * Description: Микроблог с хештегами, лайками и защитой контента.
 * Version:     2.0.1
 * Author:      Ali Profi
 * Text Domain: aliprofi-tweet-wall
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class AliProfi_Tweet_Wall {

    public function __construct() {
        add_action( 'init',               [ $this, 'register_post_type_taxonomy' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        /* AJAX */
        add_action( 'wp_ajax_load_tweets',        [ $this, 'ajax_load_tweets' ] );
        add_action( 'wp_ajax_nopriv_load_tweets', [ $this, 'ajax_load_tweets' ] );
        add_action( 'wp_ajax_add_tweet',          [ $this, 'ajax_add_tweet' ] );
        add_action( 'wp_ajax_toggle_like',        [ $this, 'ajax_toggle_like' ] );
        add_action( 'wp_ajax_nopriv_toggle_like', [ $this, 'ajax_toggle_like' ] );

        add_action( 'save_post_mytweet', [ $this, 'save_tweet_tags' ], 10, 1 );
        add_action( 'template_redirect', [ $this, 'template_override' ] );

        add_shortcode( 'tweet_wall', [ $this, 'shortcode' ] );
    }

    /* --------------------------------------------------------------------- */
    /*  CPT + Taxonomy                                                        */
    /* --------------------------------------------------------------------- */

    public function register_post_type_taxonomy() {

        register_post_type( 'mytweet', [
            'label'               => 'Твиты',
            'public'              => true,
            'menu_icon'           => 'dashicons-twitter',
            'show_in_rest'        => true,
            'supports'            => [ 'title', 'editor', 'author' ],
            'rewrite'             => [ 'slug' => 'mytweet' ],
            'has_archive'         => true,
            'publicly_queryable'  => true,
            'exclude_from_search' => false,
        ] );

        register_taxonomy( 'tweet_tag', 'mytweet', [
            'label'        => 'Теги твитов',
            'public'       => true,
            'rewrite'      => [ 'slug' => 'tweet-tag' ],
            'show_in_rest' => true,
        ] );
    }

    /* --------------------------------------------------------------------- */
    /*  Assets                                                                */
    /* --------------------------------------------------------------------- */

    public function enqueue_assets() {

        wp_enqueue_style(
            'aliprofi-tweet-wall',
            plugin_dir_url( __FILE__ ) . 'style.css',
            [],
            '2.0.1'
        );

        wp_enqueue_script( 'jquery' );

        wp_enqueue_script(
            'aliprofi-tweet-wall',
            plugin_dir_url( __FILE__ ) . 'script.js',
            [ 'jquery' ],
            '2.0.1',
            true
        );

        wp_localize_script( 'aliprofi-tweet-wall', 'tweetWallSettings', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'aliprofi_tweet_nonce' ),
        ] );

        wp_enqueue_script(
            'aliprofi-antivor',
            plugin_dir_url( __FILE__ ) . 'antivor.js',
            [],
            '2.0.1',
            true
        );
    }

    /* --------------------------------------------------------------------- */
    /*  Shortcode                                                             */
    /* --------------------------------------------------------------------- */

    public function shortcode( $atts ) {

        $atts = shortcode_atts(
            [
                'posts_per_page' => 10,
                'show_form'      => true,
            ],
            $atts,
            'tweet_wall'
        );

        ob_start();
        ?>
        <div id="tweet-wall" data-per-page="<?php echo esc_attr( $atts['posts_per_page'] ); ?>">
            <?php if ( $atts['show_form'] && is_user_logged_in() ) : ?>
                <form id="tweet-form">
                    <textarea name="tweet_content" maxlength="280" placeholder="Что происходит?"></textarea>
                    <div class="form-buttons">
                        <button type="submit">Опубликовать</button>
                        <button type="button" id="add-hashtag"># Хештег</button>
                    </div>
                </form>
            <?php endif; ?>

            <div id="tweets"></div>
            <div id="pagination"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* --------------------------------------------------------------------- */
    /*  AJAX — загрузка твитов                                                */
    /* --------------------------------------------------------------------- */

    public function ajax_load_tweets() {

        $page  = max( 1, intval( $_POST['page'] ?? 1 ) );
        $per   = 10;

        $q = new WP_Query( [
            'post_type'      => 'mytweet',
            'post_status'    => 'publish',
            'paged'          => $page,
            'posts_per_page' => $per,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        $html = '';
        while ( $q->have_posts() ) {
            $q->the_post();
            $html .= $this->render_single_tweet( get_the_ID() );
        }
        wp_reset_postdata();

        wp_send_json_success( [
            'tweets'     => $html ?: '<div class="no-tweets">Твиты не найдены</div>',
            'pagination' => $this->pagination( $q->max_num_pages, $page ),
        ] );
    }

    /* --------------------------------------------------------------------- */
    /*  AJAX — публикация твита                                               */
    /* --------------------------------------------------------------------- */

    public function ajax_add_tweet() {

        check_ajax_referer( 'aliprofi_tweet_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( 'Требуется авторизация' );
        }

        $content = sanitize_textarea_field( $_POST['content'] ?? '' );
        if ( ! $content ) {
            wp_send_json_error( 'Пустой текст' );
        }
        if ( mb_strlen( $content, 'UTF-8' ) > 280 ) {
            wp_send_json_error( 'Максимум — 280 символов' );
        }

        $id = wp_insert_post( [
            'post_type'    => 'mytweet',
            'post_status'  => 'publish',
            'post_excerpt' => '',
            'post_title'   => mb_substr( $content, 0, 50, 'UTF-8' ),
            'post_content' => $content,
            'post_author'  => get_current_user_id(),
        ] );

        $id ? wp_send_json_success() : wp_send_json_error( 'Не удалось сохранить' );
    }

    /* --------------------------------------------------------------------- */
    /*  AJAX — лайки                                                          */
    /* --------------------------------------------------------------------- */

    public function ajax_toggle_like() {

        check_ajax_referer( 'aliprofi_tweet_nonce', 'nonce' );

        $post_id = absint( $_POST['post_id'] );
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            wp_send_json_error( 'Требуется авторизация' );
        }

        $likes = (array) get_post_meta( $post_id, '_tweet_likes', true );
        $liked = in_array( $user_id, $likes, true );

        $liked ? $likes = array_diff( $likes, [ $user_id ] ) : $likes[] = $user_id;

        update_post_meta( $post_id, '_tweet_likes', $likes );

        wp_send_json_success( [
            'like_count' => count( $likes ),
            'is_liked'   => ! $liked,
        ] );
    }

    /* --------------------------------------------------------------------- */
    /*  Отрисовка одного твита                                                */
    /* --------------------------------------------------------------------- */

    private function render_single_tweet( $id ) {

        $content = get_post_field( 'post_content', $id );
        $content = $this->format_tweet_content( $content );

        $author  = get_userdata( get_post_field( 'post_author', $id ) );
        $likes   = (array) get_post_meta( $id, '_tweet_likes', true );
        $liked   = is_user_logged_in() && in_array( get_current_user_id(), $likes, true );

        ob_start();
        ?>
        <div class="tweet" data-id="<?php echo $id; ?>">
            <div class="tweet-content"><?php echo $content; ?></div>

            <div class="tweet-actions">
                <button
                    class="like-button<?php echo $liked ? ' liked' : ''; ?>"
                    data-post-id="<?php echo $id; ?>"
                    title="<?php echo $liked ? 'Убрать лайк' : 'Лайкнуть'; ?>"
                >
                    <span class="like-icon">♥</span>
                    <span class="like-count"><?php echo count( $likes ); ?></span>
                </button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /* --------------------------------------------------------------------- */
    /*  Форматирование контента твита                                         */
    /* --------------------------------------------------------------------- */

    private function format_tweet_content( $text ) {

        // 1. Делаем ссылки кликабельными
        $text = preg_replace(
            '~(https?://[^\s<]+)~iu',
            '<a href="$1" target="_blank" rel="noopener">$1</a>',
            esc_html( $text )
        );

        // 2. Обрабатываем хештеги (только в текстовых узлах)
        $text = $this->link_hashtags( $text );

        // 3. Параграфы
        return wpautop( $text );
    }

    /**
     * Оборачивает #тег ссылкой, не затрагивая уже существующие HTML-теги.
     */
    private function link_hashtags( $text ) {

        $chunks = wp_html_split( $text );

        foreach ( $chunks as &$c ) {
            if ( $c === '' || $c[0] === '<' ) {
                continue; // пропускаем HTML
            }

            $c = preg_replace_callback(
                '/(?<![\p{L}0-9_])#([\p{L}][\p{L}0-9_]+)/u',
                function ( $m ) {
                    $tag  = $m[1];
                    $term = get_term_by( 'name', $tag, 'tweet_tag' );

                    if ( $term && ! is_wp_error( $term ) ) {
                        $url = get_term_link( $term );
                        return '<a href="' . esc_url( $url ) . '" class="tweet-hashtag">#' . esc_html( $tag ) . '</a>';
                    }
                    return $m[0];
                },
                $c
            );
        }

        return implode( '', $chunks );
    }

    /* --------------------------------------------------------------------- */
    /*  Сохранение тегов при публикации                                       */
    /* --------------------------------------------------------------------- */

    public function save_tweet_tags( $post_id ) {

        $content = get_post_field( 'post_content', $post_id );
        preg_match_all( '/#([\p{L}][\p{L}0-9_]+)/u', $content, $matches );

        if ( empty( $matches[1] ) ) {
            return;
        }

        $terms = [];
        foreach ( $matches[1] as $tag ) {

            $tag = trim( $tag );
            $slug = $this->transliterate( $tag );

            $term = get_term_by( 'slug', $slug, 'tweet_tag' );
            if ( ! $term ) {
                $res = wp_insert_term( $tag, 'tweet_tag', [ 'slug' => $slug ] );
                if ( ! is_wp_error( $res ) ) {
                    $term = get_term( $res['term_id'] );
                }
            }

            if ( $term && ! is_wp_error( $term ) ) {
                $terms[] = (int) $term->term_id;
            }
        }

        if ( $terms ) {
            wp_set_object_terms( $post_id, $terms, 'tweet_tag' );
        }
    }

    /* --------------------------------------------------------------------- */
    /*  Транслитерация                                                        */
    /* --------------------------------------------------------------------- */

    private function transliterate( $str ) {

        static $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh',
            'з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o',
            'п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts',
            'ч'=>'ch','ш'=>'sh','щ'=>'shch','ы'=>'y','э'=>'e','ю'=>'yu','я'=>'ya',
            'ь'=>'','ъ'=>''
        ];

        $str = mb_strtolower( $str, 'UTF-8' );
        $str = strtr( $str, $map );
        $str = preg_replace( '/[^a-z0-9]+/u', '-', $str );
        return trim( $str, '-' );
    }

    /* --------------------------------------------------------------------- */
    /*  Pagination                                                            */
    /* --------------------------------------------------------------------- */

    private function pagination( $max, $current ) {

        if ( $max <= 1 ) {
            return '';
        }

        $out = '<div id="pagination">';
        if ( $current > 1 ) {
            $out .= '<a href="#" class="page-numbers prev">«</a>';
        }

        for ( $i = 1; $i <= $max; $i++ ) {
            $cls = $i === $current ? ' class="page-numbers current"' : ' class="page-numbers"';
            $out .= '<a href="#"' . $cls . '>' . $i . '</a>';
        }

        if ( $current < $max ) {
            $out .= '<a href="#" class="page-numbers next">»</a>';
        }
        return $out . '</div>';
    }

    /* --------------------------------------------------------------------- */
    /*  Шаблоны из папки /templates/                                          */
    /* --------------------------------------------------------------------- */

    public function template_override() {

        if ( is_singular( 'mytweet' ) ) {
            $tpl = plugin_dir_path( __FILE__ ) . 'templates/single-mytweet.php';
            if ( file_exists( $tpl ) ) {
                include $tpl;
                exit;
            }
        }

        if ( is_tax( 'tweet_tag' ) ) {
            $tpl = plugin_dir_path( __FILE__ ) . 'templates/taxonomy-tweet_tag.php';
            if ( file_exists( $tpl ) ) {
                include $tpl;
                exit;
            }
        }
    }
}

/*  Запуск плагина  */
new AliProfi_Tweet_Wall();
