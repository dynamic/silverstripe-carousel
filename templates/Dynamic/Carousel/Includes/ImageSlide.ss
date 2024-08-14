<img src="$Image.FocusFill(1200,400).URL" class="d-block w-100 image-fluid" alt="$Image.Title.XML">
<div class="carousel-caption d-none d-md-block">
    <% if $Title && $ShowTitle %><h3>$Title</h3><% end_if %>
    <% if $Content %>$Content<% end_if %>
    <% if $ElementLink %><p><a href="$ElementLink.URL" class="btn btn-primary" title="$ElementLink.Title"<% if $ElementLink.OpenInNew %> target="_blank" rel="noopener noreferrer"<% end_if %>>$ElementLink.Title</a></p><% end_if %>
</div>
