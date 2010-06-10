<markers>
{foreach from=$fields item=f}
<marker lat="{$f->latitude}" lng="{$f->longitude}" fid="{$f->fid}">
<balloon><![CDATA[<a href="{lr_url path="field/view/`$f->fid`}">{$f->name}</a>{$f->code}
{if $f->length}
<br/><a href="{lr_url path="gmaps/view/`$f->fid`}">Field map and layout</a>
{/if}
]]></balloon>
<tooltip>{$f->name|escape } ({$f->code})</tooltip>
<image>{lr_url path="image/pins/`$f->code`.png"}</image>
</marker>
{/foreach}
</markers>
