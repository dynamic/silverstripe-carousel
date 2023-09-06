<%if $Slides %>
    <div id="carousel-{$ID}" class="carousel slide" data-ride="carousel">
        <ol class="carousel-indicators">
            <% loop $Slides.Sort('SortOrder') %>
                <li data-target="#carousel-{$Top.ID}" data-slide-to="{$Pos(0)}" <% if $IsFirst %>class="active" aria-current="true"<% end_if %> aria-label="{$Title.XML}"></li>
            <% end_loop %>
        </ol>
        <div class="carousel-inner">
            <% loop $Slides.Sort('SortOrder') %>
                <div class="carousel-item<% if $IsFirst %> active<% end_if %>">
                    <img src="$Image.Fill(1200,500).URL" class="d-block w-100" alt="$Image.Title.XML">
                    <div class="carousel-caption d-none d-md-block">
                        <h3>$Title</h3>
                        $Content
                    </div>
                </div>
            <% end_loop %>
        </div>
        <a class="carousel-control-prev" href="#carousel-{$ID}" role="button" data-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="sr-only">Previous</span>
        </a>
        <a class="carousel-control-next" href="#carousel-{$ID}" role="button" data-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="sr-only">Next</span>
        </a>
    </div>
<% end_if %>
