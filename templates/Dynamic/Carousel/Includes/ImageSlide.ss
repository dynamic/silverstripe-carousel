<figure class="figure d-block w-100">
    <img src="$Image.FocusFill(1200,400).URL" class="figure-img img-fluid d-block w-100" alt="$Image.Title.XML">
    <% if $HasImageCaption %>
    <figcaption class="figure-caption">
        <% if $Caption %>$Caption.XML<% end_if %>
        <% if $Credit %><cite class="image-credit">$Credit.XML</cite><% end_if %>
    </figcaption>
    <% end_if %>
</figure>

<% if $ShowContent %>
<div class="carousel-caption d-none d-md-block">
    <% if $TopTitle %><p class="carousel-slide-top-title">$TopTitle</p><% end_if %>
    <% if $Title && $ShowTitle %><h3>$Title</h3><% end_if %>
    <% if $Content %>$Content<% end_if %>
    <% if $ElementLink %><p><a href="$ElementLink.URL" class="btn btn-primary" title="$ElementLink.Title"<% if $ElementLink.OpenInNew %> target="_blank" rel="noopener noreferrer"<% end_if %>>$ElementLink.Title</a></p><% end_if %>
</div>
<% end_if %>
