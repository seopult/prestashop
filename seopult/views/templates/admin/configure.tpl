{**
* 2015 SeoPult
*
* @author    SeoPult RU <https://seopult.ru/>
* @copyright 2015 SeoPult RU
* @license   GNU General Public License, version 2
*}

<script type="text/javascript" src="http://i.seopult.pro/js/postmessage.js"></script>
<script type="text/javascript" src="http://i.seopult.pro/js/FrameManager.js"></script>
<a class="btn btn-default pull-right" href="{$html}">Восстановление доступа&nbsp;&nbsp;<i class="icon-external-link"></i></a>
{if isset($url)}
	<iframe onload="FrameManager.registerFrame(this)" id="seopult" name="seopult" frameborder="0" scrolling="no" width="100%" src="{$url}"></iframe>
{else}
	<div class="alert alert-warning">
		<h4>Внимание!</h4>
		<ul class="list-unstyled">
			<li>{$error}</li>
		</ul>
	</div>

{/if}

