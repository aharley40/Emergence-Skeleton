{extends content/contentEdit.tpl}

{block title}{if $data->isPhantom}Create blog post{else}Edit &ldquo;{$data->Title|escape}&rdquo;{/if} &mdash; {$dwoo.parent}{/block}