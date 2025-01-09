<?php
/*
Plugin Name: Featured Video Plugin
Plugin URI: https://github.com/featured-video-plugin
Description: Öne çıkan görselleri otomatik olarak video ile değiştirir ve önizleme özelliği sunar.
Version: 1.1
Author: Barış ERTUĞRUL
Author URI: https://barisertugrul.com
License: GPL v2 or later
*/

// Güvenlik kontrolü
if (!defined('ABSPATH')) {
    exit;
}

// Eklenti sınıfı
class Featured_Video {
    
    public function __construct() {
        // Admin paneline özel alan ekleme
        add_action('add_meta_boxes', array($this, 'add_video_metabox'));
        add_action('save_post', array($this, 'save_video_metabox'));
        
        // Gerekli stil ve scriptleri yükleme
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

        // Öne çıkan görsel filtreleri
        add_filter('post_thumbnail_html', array($this, 'replace_thumbnail_with_video'), 10, 5);
        add_filter('the_content', array($this, 'maybe_replace_first_image'));
    }

    // Admin paneline video alanı ekleme
    public function add_video_metabox() {
        add_meta_box(
            'featured_video_metabox',
            'Öne Çıkan Video',
            array($this, 'render_video_metabox'),
            'post',
            'side',
            'high'
        );
    }

    // Video alanı HTML'i
    public function render_video_metabox($post) {
        wp_nonce_field('featured_video_nonce', 'featured_video_nonce');
        $video_url = get_post_meta($post->ID, '_featured_video_url', true);
        $video_type = get_post_meta($post->ID, '_featured_video_type', true);
        $enable_video = get_post_meta($post->ID, '_enable_featured_video', true);
        ?>
        <p>
            <label>
                <input type="checkbox" name="enable_featured_video" value="1" 
                       <?php checked($enable_video, '1'); ?>>
                Video'yu etkinleştir
            </label>
        </p>
        <p>
            <label for="featured_video_url">Video URL:</label>
            <input type="text" id="featured_video_url" name="featured_video_url" 
                   value="<?php echo esc_attr($video_url); ?>" style="width: 100%;">
        </p>
        <p>
            <label for="featured_video_type">Video Tipi:</label>
            <select id="featured_video_type" name="featured_video_type" style="width: 100%;">
                <option value="youtube" <?php selected($video_type, 'youtube'); ?>>YouTube</option>
                <option value="mp4" <?php selected($video_type, 'mp4'); ?>>MP4</option>
            </select>
        </p>
        <?php
    }

    // Video bilgilerini kaydetme
    public function save_video_metabox($post_id) {
        if (!isset($_POST['featured_video_nonce']) || 
            !wp_verify_nonce($_POST['featured_video_nonce'], 'featured_video_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        update_post_meta($post_id, '_enable_featured_video', 
            isset($_POST['enable_featured_video']) ? '1' : '');

        if (isset($_POST['featured_video_url'])) {
            update_post_meta($post_id, '_featured_video_url', 
                           sanitize_text_field($_POST['featured_video_url']));
        }

        if (isset($_POST['featured_video_type'])) {
            update_post_meta($post_id, '_featured_video_type', 
                           sanitize_text_field($_POST['featured_video_type']));
        }
    }

    // Stil ve scriptleri yükleme
    public function enqueue_scripts() {
        wp_enqueue_style('featured-video-preview', 
            plugins_url('css/style.css', __FILE__));
        wp_enqueue_script('featured-video-preview', 
            plugins_url('js/script.js', __FILE__), 
            array('jquery'), 
            '1.1', 
            true);
    }

    public function admin_enqueue_scripts() {
        wp_enqueue_style('featured-video-preview-admin', 
            plugins_url('css/admin-style.css', __FILE__));
    }

    // Video HTML'ini oluşturma
    public function get_video_html($post_id, $size = 'post-thumbnail') {
        $video_url = get_post_meta($post_id, '_featured_video_url', true);
        $video_type = get_post_meta($post_id, '_featured_video_type', true);
        $enable_video = get_post_meta($post_id, '_enable_featured_video', true);

        if (!$enable_video || !$video_url) {
            return '';
        }

        // Thumbnail boyutlarını al
        $dimensions = $this->get_thumbnail_dimensions($size);
        $width = $dimensions['width'];
        $height = $dimensions['height'];

        $output = '<div class="featured-video-preview" style="width: ' . esc_attr($width) . 'px; height: ' . esc_attr($height) . 'px;">';
        
        if ($video_type === 'youtube') {
            preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $video_url, $matches);
            if (isset($matches[1])) {
                $youtube_id = $matches[1];
                $output .= '<div class="youtube-preview" data-youtube-id="' . esc_attr($youtube_id) . '">';
                $output .= '<iframe src="https://www.youtube.com/embed/' . esc_attr($youtube_id) . '?enablejsapi=1&controls=0&showinfo=0&rel=0&autoplay=0&mute=1" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
                $output .= '</div>';
            }
        } else {
            $output .= '<div class="mp4-preview">';
            $output .= '<video muted loop preload="metadata" playsinline>';
            $output .= '<source src="' . esc_url($video_url) . '" type="video/mp4">';
            $output .= '</video>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }

    // Thumbnail boyutlarını alma
    private function get_thumbnail_dimensions($size) {
        global $_wp_additional_image_sizes;
        
        if (isset($_wp_additional_image_sizes[$size])) {
            return array(
                'width' => $_wp_additional_image_sizes[$size]['width'],
                'height' => $_wp_additional_image_sizes[$size]['height']
            );
        }

        $default_sizes = array('thumbnail', 'medium', 'large', 'full');
        if (in_array($size, $default_sizes)) {
            return array(
                'width' => get_option($size . '_size_w'),
                'height' => get_option($size . '_size_h')
            );
        }

        return array('width' => 600, 'height' => 400); // Varsayılan boyutlar
    }

    // Öne çıkan görseli video ile değiştirme
    public function replace_thumbnail_with_video($html, $post_id, $post_thumbnail_id, $size, $attr) {
        $enable_video = get_post_meta($post_id, '_enable_featured_video', true);
        $video_url = get_post_meta($post_id, '_featured_video_url', true);

        if ($enable_video && $video_url) {
            return $this->get_video_html($post_id, $size);
        }

        return $html;
    }

    // İçerikteki ilk görseli video ile değiştirme
    public function maybe_replace_first_image($content) {
        global $post;
        
        if (!is_singular() || !has_post_thumbnail($post->ID)) {
            return $content;
        }

        $enable_video = get_post_meta($post->ID, '_enable_featured_video', true);
        $video_url = get_post_meta($post->ID, '_featured_video_url', true);

        if ($enable_video && $video_url) {
            $video_html = $this->get_video_html($post->ID, 'large');
            
            // İlk görseli bul ve değiştir
            $pattern = '/<img[^>]+>/i';
            $content = preg_replace($pattern, $video_html, $content, 1);
        }

        return $content;
    }
}

// Eklentiyi başlat
$featured_video_plugin = new Featured_Video();