{if $header}
{php}
header($header);
{/php}
{/if}
{include file=header.tpl title=$title}
{include file=components/errormessage.tpl message=$error}
{include file=footer.tpl}
