<% if $Slides %>
    <div id="carousel-{$ID}" class="carousel slide mb-5">
        <% if $Slides.Count > 1 %>
            <div class="carousel-indicators">
                <% loop $Slides.Sort('SortOrder') %>
                    <button type="button" data-bs-target="#carousel-{$Up.ID}" data-bs-slide-to="{$Pos(0)}"  <% if $IsFirst %>class="active" aria-current="true"<% end_if %> aria-label="{$Title.XML}"></button>
                <% end_loop %>
            </div>
        <% end_if %>
        <div class="carousel-inner">
            <% loop $Slides.Sort('SortOrder') %>
                <div class="carousel-item<% if $IsFirst %> active<% end_if %>">
                    <% if $ClassName.ShortName == ImageSlide %>
                        <% include ImageSlide %>
                    <% else_if $ClassName.ShortName == VideoSlide %>
                        <% include VideoSlide UpID=$Up.ID %>
                    <% end_if %>
                </div>
            <% end_loop %>
        </div>
        <% if $Slides.Count > 1 %>
            <button class="carousel-control-prev" type="button" data-bs-target="#carousel-{$ID}" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#carousel-{$ID}" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        <% end_if %>
    </div>
<% end_if %>
