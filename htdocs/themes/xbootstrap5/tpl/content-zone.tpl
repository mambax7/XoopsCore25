<{if $xoBlocks.canvas_left && $xoBlocks.canvas_right}>
<div class="col-md-6">
    <{elseif $xoBlocks.canvas_left}>
    <div class="col-md-9">
        <{elseif $xoBlocks.canvas_right}>
        <div class="col-md-9">
            <{else}>
            <div class="col-md-12">
                <{/if}>
                <{include file="$theme_name/tpl/contents.tpl"}>

                <div class="row">
                    <{include file="$theme_name/tpl/centerBlock.tpl"}>
                    <{include file="$theme_name/tpl/centerLeft.tpl"}>
                    <{include file="$theme_name/tpl/centerRight.tpl"}>
                </div>
            </div>
