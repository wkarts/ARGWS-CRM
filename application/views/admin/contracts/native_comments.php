<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php foreach($comments as$comment){ ?><article class="contract-comment well" data-comment-id="<?= e($comment['id']); ?>"><p style="white-space:pre-wrap"><?= e($comment['body']); ?></p><small><?= e($comment['author_name']); ?> · <?= e($comment['created_at']); ?></small></article><?php } ?>
