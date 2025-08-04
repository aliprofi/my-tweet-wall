<?php
/**
 * Шаблон для отдельного твита AliProfi
 */

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        
        <?php while (have_posts()) : the_post(); ?>
            
            <article id="post-<?php the_ID(); ?>" <?php post_class('single-tweet-container'); ?>>
                
                <header class="entry-header">
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                    
                    <div class="entry-meta">
                        <span class="posted-on">
                            <time class="entry-date published" datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                                <?php echo get_the_date('d F Y в H:i'); ?>
                            </time>
                        </span>
                        
                        <span class="byline">
                            Автор: <span class="author vcard">
                                <a class="url fn n" href="<?php echo esc_url(get_author_posts_url(get_the_author_meta('ID'))); ?>">
                                    <?php echo get_the_author(); ?>
                                </a>
                            </span>
                        </span>
                    </div>
                </header>

                <div class="entry-content single-tweet-content">
                    <?php
                    $content = get_the_content();
                    
                    // Обработка хештегов для SEO
                    $content = preg_replace_callback('/#(\w+)/u', function($matches) {
                        $tag = $matches[1];
                        $term = get_term_by('name', $tag, 'tweet_tag');
                        if ($term) {
                            $link = get_term_link($term);
                            return '<a href="' . esc_url($link) . '" class="tweet-hashtag" rel="tag">#' . esc_html($tag) . '</a>';
                        }
                        return $matches[0];
                    }, $content);
                    
                    // Обработка ссылок
                    $content = preg_replace('/(https?:\/\/[^\s]+)/', '<a href="$1" target="_blank" rel="noopener nofollow">$1</a>', $content);
                    
                    echo wpautop($content);
                    ?>
                </div>

                <footer class="entry-footer">
                    <?php
                    $tags = get_the_terms(get_the_ID(), 'tweet_tag');
                    if ($tags && !is_wp_error($tags)) :
                    ?>
                        <div class="tweet-tags-list">
                            <span class="tags-label">Теги:</span>
                            <?php foreach ($tags as $tag) : ?>
                                <a href="<?php echo esc_url(get_term_link($tag)); ?>" 
                                   class="tweet-tag-link" rel="tag">
                                    #<?php echo esc_html($tag->name); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="tweet-actions-single">
                        <?php
                        $likes = get_post_meta(get_the_ID(), '_tweet_likes', true) ?: [];
                        $like_count = count($likes);
                        $user_liked = in_array(get_current_user_id(), $likes);
                        ?>
                        
                        <button class="like-button<?php echo $user_liked ? ' liked' : ''; ?>" 
                                data-post-id="<?php the_ID(); ?>" 
                                title="<?php echo $user_liked ? 'Убрать лайк' : 'Лайкнуть'; ?>">
                            <span class="like-icon">♥</span>
                            <span class="like-count"><?php echo $like_count; ?></span>
                        </button>
                        
                        <div class="share-buttons">
                            <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode(get_the_title()); ?>&url=<?php echo urlencode(get_permalink()); ?>" 
                               target="_blank" rel="noopener" class="share-twitter" title="Поделиться в Twitter">
                                📤 Twitter
                            </a>
                            
                            <a href="https://vk.com/share.php?url=<?php echo urlencode(get_permalink()); ?>&title=<?php echo urlencode(get_the_title()); ?>" 
                               target="_blank" rel="noopener" class="share-vk" title="Поделиться ВКонтакте">
                                📤 ВК
                            </a>
                        </div>
                    </div>
                </footer>

            </article>

            <nav class="post-navigation" role="navigation">
                <h2 class="screen-reader-text">Навигация по записям</h2>
                <div class="nav-links">
                    <?php
                    $prev_post = get_previous_post(false, '', 'tweet_tag');
                    $next_post = get_next_post(false, '', 'tweet_tag');
                    
                    if ($prev_post) :
                    ?>
                        <div class="nav-previous">
                            <a href="<?php echo get_permalink($prev_post); ?>" rel="prev">
                                <span class="meta-nav">← Предыдущий твит</span>
                                <span class="post-title"><?php echo get_the_title($prev_post); ?></span>
                            </a>
                        </div>
                    <?php endif;
                    
                    if ($next_post) :
                    ?>
                        <div class="nav-next">
                            <a href="<?php echo get_permalink($next_post); ?>" rel="next">
                                <span class="meta-nav">Следующий твит →</span>
                                <span class="post-title"><?php echo get_the_title($next_post); ?></span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </nav>

            <?php
            // Похожие твиты по тегам
            $related_tweets = get_posts([
                'post_type' => 'mytweet',
                'posts_per_page' => 5,
                'post__not_in' => [get_the_ID()],
                'tax_query' => [
                    [
                        'taxonomy' => 'tweet_tag',
                        'field' => 'term_id',
                        'terms' => wp_list_pluck($tags ?: [], 'term_id'),
                    ],
                ],
            ]);
            
            if ($related_tweets) :
            ?>
                <aside class="related-tweets">
                    <h3>Похожие твиты:</h3>
                    <div class="related-tweets-list">
                        <?php foreach ($related_tweets as $related_tweet) : ?>
                            <div class="related-tweet-item">
                                <a href="<?php echo get_permalink($related_tweet); ?>">
                                    <?php echo get_the_title($related_tweet); ?>
                                </a>
                                <time datetime="<?php echo get_the_date('c', $related_tweet); ?>">
                                    <?php echo get_the_date('d.m.Y', $related_tweet); ?>
                                </time>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </aside>
            <?php 
            endif;
            wp_reset_postdata();
            ?>

        <?php endwhile; ?>

    </main>
</div>

<style>
.single-tweet-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    background: white;
    border-radius: 15px;
    box-shadow: 0 2px 20px rgba(0,0,0,0.1);
}

.entry-header {
    border-bottom: 2px solid #f1f3f4;
    padding-bottom: 20px;
    margin-bottom: 30px;
}

.entry-title {
    font-size: 28px;
    line-height: 1.3;
    margin-bottom: 15px;
    color: #14171a;
}

.entry-meta {
    color: #657786;
    font-size: 14px;
}

.entry-meta span {
    margin-right: 20px;
}

.single-tweet-content {
    font-size: 18px;
    line-height: 1.6;
    margin-bottom: 30px;
}

.tweet-hashtag {
    color: #1da1f2;
    text-decoration: none;
    font-weight: 600;
}

.tweet-hashtag:hover {
    text-decoration: underline;
}

.entry-footer {
    border-top: 2px solid #f1f3f4;
    padding-top: 20px;
}

.tweet-tags-list {
    margin-bottom: 20px;
}

.tags-label {
    font-weight: 600;
    margin-right: 10px;
}

.tweet-tag-link {
    display: inline-block;
    margin: 5px 10px 5px 0;
    padding: 5px 12px;
    background: #f8f9fa;
    border-radius: 15px;
    text-decoration: none;
    color: #1da1f2;
    font-size: 14px;
}

.tweet-actions-single {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.share-buttons a {
    margin-left: 15px;
    text-decoration: none;
    color: #657786;
    font-size: 14px;
}

.post-navigation {
    margin: 40px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 15px;
}

.nav-links {
    display: flex;
    justify-content: space-between;
}

.nav-previous, .nav-next {
    flex: 1;
}

.nav-next {
    text-align: right;
}

.related-tweets {
    margin-top: 40px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 15px;
}

.related-tweets h3 {
    margin-bottom: 15px;
    color: #14171a;
}

.related-tweet-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #e1e8ed;
}

.related-tweet-item:last-child {
    border-bottom: none;
}

.related-tweet-item a {
    text-decoration: none;
    color: #1da1f2;
    font-weight: 600;
}

.related-tweet-item time {
    color: #657786;
    font-size: 12px;
}

@media (max-width: 768px) {
    .single-tweet-container {
        margin: 10px;
        padding: 15px;
    }
    
    .entry-title {
        font-size: 24px;
    }
    
    .nav-links {
        flex-direction: column;
    }
    
    .nav-next {
        text-align: left;
        margin-top: 20px;
    }
    
    .tweet-actions-single {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .share-buttons {
        margin-top: 15px;
    }
    
    .share-buttons a {
        margin-left: 0;
        margin-right: 15px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Обработчик лайков для отдельной страницы твита
    $('.like-button').on('click', function(e) {
        e.preventDefault();
        
        let button = $(this);
        let post_id = button.data('post-id');
        
        if (button.hasClass('processing')) return;
        button.addClass('processing');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'toggle_like',
                post_id: post_id,
                nonce: '<?php echo wp_create_nonce('aliprofi_tweet_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    button.find('.like-count').text(response.data.like_count);
                    
                    if (response.data.is_liked) {
                        button.addClass('liked').attr('title', 'Убрать лайк');
                    } else {
                        button.removeClass('liked').attr('title', 'Лайкнуть');
                    }
                }
            },
            complete: function() {
                button.removeClass('processing');
            }
        });
    });
});
</script>

<?php get_footer(); ?>
