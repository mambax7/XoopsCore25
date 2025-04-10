<div class="xoops-comment-template" id="comment<{$comment.id}>">
    <div class="row hidden-xs">
        <div class="col-2 col-md-2 aligncenter"><{$comment.poster.uname}></div><!-- .col-md-3 -->
        <div class="col-4 col-md-4">
            <small class="label label-info"><strong><{$lang_posted}></strong> <{$comment.date_posted}></small>
        </div><!-- .col-md-3 -->
        <{if $comment.date_posted != $comment.date_modified}>
            <div class="col-5 col-md-5">
                <small class="label label-info"><strong><{$lang_updated}></strong> <{$comment.date_modified}></small>
            </div>
            <!-- .col-md-3 -->
        <{/if}>
    </div><!-- row -->

    <div class="row">
        <div class="col-2 col-md-2 xoops-comment-author aligncenter">
            <{if $comment.poster.id|default:0 != 0}>
                <img src="<{$xoops_upload_url}>/<{$comment.poster.avatar}>" class="img-fluid rounded-circle image-avatar" alt="">
                <ul class="list-unstyled">
                    <li><strong class="poster-rank hidden-xs"><{$comment.poster.rank_title}></strong></li>
                    <li><img src="<{$xoops_upload_url}>/<{$comment.poster.rank_image}>" alt="<{$comment.poster.rank_title}>" class="poster-rank img-fluid"></li>
                </ul>
                <ul class="list-unstyled poster-info hidden">
                    <li><{$lang_joined}> <{$comment.poster.regdate}></li>
                    <li><{$lang_from}> <{$comment.poster.from}></li>
                    <li><{$lang_posts}> <{$comment.poster.postnum}></li>
                    <li><{$comment.poster.status}></li>
                </ul>
            <{else}>
                &nbsp; <!-- ? -->
            <{/if}>
        </div><!-- .col-md-3 .xoops-comment-author -->

        <div class="col-10 col-md-10 xoops-comment-text">
            <h4><{$comment.image}><{$comment.title}></h4>

            <p class="message-text text-muted"><{$comment.text}></p>
        </div><!-- .col-md-3 -->
    </div><!-- row -->

    <div class="row">
        <div class="col-12 col-md-12 alignright">
            <{if $xoops_iscommentadmin == true}>
                <a href="<{$editcomment_link}>&com_id=<{$comment.id}>" title="<{$lang_edit}>" class="btn btn-primary btn-sm">
                    <span class="fa-solid fa-pen-to-square"></span>
                </a>
                <a href="<{$replycomment_link}>&com_id=<{$comment.id}>" title="<{$lang_reply}>" class="btn btn-info btn-sm">
                    <span class="fa-solid fa-comment"></span>
                </a>
                <a href="<{$deletecomment_link}>&com_id=<{$comment.id}>" title="<{$lang_delete}>" class="btn btn-danger btn-sm">
                    <span class="fa-solid fa-trash-can"></span>
                </a>
            <{elseif $xoops_isuser == true && $xoops_userid == $comment.poster.id}>
                <a href="<{$editcomment_link}>&com_id=<{$comment.id}>" title="<{$lang_edit}>" class="btn btn-primary btn-sm">
                    <span class="fa-solid fa-pen-to-square"></span>
                </a>
                <a href="<{$replycomment_link}>&com_id=<{$comment.id}>" title="<{$lang_reply}>" class="btn btn-info btn-sm">
                    <span class="fa-solid fa-comment"></span>
                </a>
            <{elseif $xoops_isuser == true || $anon_canpost == true}>
                <a href="<{$replycomment_link}>&com_id=<{$comment.id}>" class="btn btn-info btn-sm">
                    <span class="fa-solid fa-comment"></span>
                </a>
            <{else}>
                &nbsp;        <!-- ? -->
            <{/if}>
        </div><!-- .col-md-12 -->
    </div><!-- row -->
</div><!-- .xoops-comment-template -->
