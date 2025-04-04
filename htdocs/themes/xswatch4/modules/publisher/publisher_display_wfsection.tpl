<{include file='db:publisher_header.tpl'}>

<{if $indexpage || $category.subcats || ($category && $display_category_summary)}>

    <{if !empty($display_category_summary) && !empty($category)}>
        <div class="well well-sm">
            <{$lang_category_summary}>
        </div>
    <{/if}>

    <{include file='db:publisher_categories_table.tpl'}>
    <!-- End of if !$category || $category.subcats || ($category && $display_category_summary) //-->
<{/if}>
<h4 class="pub_last_articles_wf">
    <span class="fa-solid fa-newspaper"></span>&nbsp;<{$lang_items_title}>
</h4>
<div class="publisher_items_list_">
    <{if isset($items)}>
    <{foreach item=item from=$items|default:null}>
        <div class="article_wf">
            <div class="article_wf_title">
                <h3><{$item.titlelink}></h3>
                <span>
                    <span class="fa-solid fa-tag"></span>&nbsp;<{$item.category}>
                </span>
                <span>
                    <span class="fa-solid fa-user"></span>&nbsp;<{$item.who}>
                </span>
                <span>
                    <span class="fa-solid fa-calendar"></span>&nbsp;<{$item.when}>
                </span>
                <span>
                    <span class="fa-solid fa-comment"></span>&nbsp;<{$item.comments}>
                </span>
                <span>
                    <span class="fa-solid fa-circle-check"></span>&nbsp;<{$item.counter}> <{$smarty.const._MD_PUBLISHER_READS}>
                </span>
            </div>
            <{if $item.image_path}>
                <div class="article_wf_img">
                    <img class="img-fluid" src="<{$item.image_path}>" alt="<{$item.title}>"/>
                </div>
            <{/if}>
            <div class="article_wf_summary">
                <span style="font-weight: normal;">
                <{$item.summary}>
                    </span>
            </div>
            <div class="pull-right" style="margin-top: 15px;">
                <a href="<{$item.itemurl}>"
                   class="btn btn-primary btn-sm"> <{$smarty.const._MD_PUBLISHER_VIEW_MORE}></a>
            </div>
            <div class="clearfix"></div>
        </div>
    <{/foreach}>
</div>

    <div class="generic-pagination col text-right mt-2">
        <{$navbar|replace:'form':'div'|replace:'id="xo-pagenav"':''|replace:' //':'/'}>
    </div>

<{$press_room_footer|default:''}>


<{/if}>
<!-- end of if $items -->

<{include file='db:publisher_footer.tpl'}>
