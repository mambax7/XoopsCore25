<{if $xoBlocks.canvas_right}>
    <div class="col-md-3 xoops-side-blocks">
        <{foreach item=block from=$xoBlocks.canvas_right|default:null}>
            <aside>
                <{if $block.title}><h4 class="block-title"><{$block.title}></h4><{/if}>
                <{$block.content}>
            </aside>
        <{/foreach}>
    </div>
<{/if}>
