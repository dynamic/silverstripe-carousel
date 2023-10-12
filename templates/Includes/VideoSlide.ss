<div class="embed-responsive embed-responsive-16by9">
    <% if $VideoType == Embed %>
        $VideoEmbed.Raw
    <% else_if $VideoType == Native %>
        $Video
    <% end_if %>
</div>
<script>
    var player;

    window.onYouTubeIframeAPIReady = function() {
        var iframe = document.querySelector('#carousel-1 .embed-responsive-item');
        player = new YT.Player(iframe, {
            events: {
                'onStateChange': onPlayerStateChange
            }
        });
    }

    function onPlayerStateChange(event) {
        var carouselElement = document.getElementById('carousel-1');
        var carouselInstance = new bootstrap.Carousel(carouselElement);

        if (event.data == YT.PlayerState.PLAYING) {
            carouselInstance.pause();
        } else if (event.data == YT.PlayerState.ENDED) {
            carouselInstance.cycle();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        var tag = document.createElement('script');
        tag.src = "https://www.youtube.com/iframe_api";
        var firstScriptTag = document.getElementsByTagName('script')[0];
        firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);

        var carouselElement = document.getElementById('carousel-1');
        carouselElement.addEventListener('slid.bs.carousel', function(e) {
            if (e.relatedTarget.querySelector('iframe') && player.getPlayerState() !== YT.PlayerState.PLAYING) {
                player.playVideo();
            }
        });
    });
</script>


