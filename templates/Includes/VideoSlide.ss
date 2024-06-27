<div class="embed-responsive embed-responsive-16by9">
    <% if $VideoType == 'Embed' %>
        $VideoEmbed
    <% else_if $VideoType == 'Native' %>
        $Video
    <% end_if %>
</div>
