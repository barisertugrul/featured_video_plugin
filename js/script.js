jQuery(document).ready(function($) {
    // YouTube API'sini yükle
    var tag = document.createElement('script');
    tag.src = "https://www.youtube.com/iframe_api";
    var firstScriptTag = document.getElementsByTagName('script')[0];
    firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

    // Video önizleme işlemleri
    function initializeVideos() {
        $('.featured-video-preview').each(function() {
            var $container = $(this);
            var $video = $container.find('video');
            var $youtube = $container.find('iframe');

            // MP4 video kontrolü
            if ($video.length) {
                $container.on('mouseenter touchstart', function() {
                    $video[0].play();
                }).on('mouseleave touchend', function() {
                    $video[0].pause();
                    $video[0].currentTime = 0;
                });

                // Mobil için IntersectionObserver
                if ('IntersectionObserver' in window) {
                    var observer = new IntersectionObserver(function(entries) {
                        entries.forEach(function(entry) {
                            if (entry.isIntersecting) {
                                $video[0].play();
                            } else {
                                $video[0].pause();
                                $video[0].currentTime = 0;
                            }
                        });
                    });

                    observer.observe($video[0]);
                }
            }

            // YouTube video kontrolü
            if ($youtube.length) {
                var player;
                $container.on('mouseenter touchstart', function() {
                    if (player && typeof player.playVideo === 'function') {
                        player.playVideo();
                    }
                }).on('mouseleave touchend', function() {
                    if (player && typeof player.pauseVideo === 'function') {
                        player.pauseVideo();
                    }
                });

                // YouTube player'ı oluştur
                if (typeof YT !== 'undefined' && YT.Player) {
                    player = new YT.Player($youtube[0], {
                        events: {
                            'onReady': function(event) {
                                event.target.mute();
                            }
                        }
                    });
                }
            }
        });
    }

    // Sayfa yüklendiğinde ve AJAX sonrası videoları başlat
    initializeVideos();
    $(document).on('ajaxComplete', initializeVideos);
});
