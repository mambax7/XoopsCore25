<script>
    jQuery(document).ready(function ($) {
        $('#extgallery-carousel2 div.item:first-child').addClass('active');
        $('#ext-ind2 > li:first-child').addClass('active');
        var extgallery2 = $('#ext-ind2 > li');
        extgallery2.each(
            function (index) {
                $(this).attr('data-bs-slide-to', index);
            }
        )
    });
</script>

<div id="extgallery-carousel2" class="carousel slide" data-bs-ride="carousel">
    <ol id="ext-ind2" class="carousel-indicators">
        <{foreach item=photo from=$block.photos|default:null}>
            <li data-bs-target="#extgallery-carousel2"></li>
        <{/foreach}>
    </ol>
    <div class="carousel-inner" role="listbox">
        <{foreach item=photo from=$block.photos|default:null}>
            <div class="item" id="<{$photo.photo_id}>">
                <img src="<{$xoops_url}>/uploads/extgallery/public-photo/medium/<{$photo.photo_name}>" alt="<{$photo.photo_title}>">

                <div class="carousel-caption">
                    <h3><{$photo.photo_title}></h3>
                    <p><{$photo.photo_desc}></p>
                    <a class="btn btn-sm btn-primary" title="<{$photo.photo_title}>" href="<{$xoops_url}>/modules/extgallery/public-photo.php?photoId=<{$photo.photo_id}>">
                        <{$smarty.const._MB_EXTGALLERY_MOREINFO}>
                    </a>
                </div>
            </div>
        <{/foreach}>
        <a class="left carousel-control" href="#extgallery-carousel2" data-bs-slide="prev"><span class="icon-prev"></span></a>
        <a data-bs-slide="next" href="#extgallery-carousel2" class="right carousel-control"><span class="icon-next"></span></a>
    </div>
</div>
