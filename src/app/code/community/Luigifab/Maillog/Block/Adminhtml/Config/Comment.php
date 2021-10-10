<?php
/**
 * Created S/26/10/2019
 * Updated M/28/09/2021
 *
 * Copyright 2015-2021 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * Copyright 2020-2021 | Fabrice Creuzot <fabrice~cellublue~com>
 * https://www.luigifab.fr/openmage/maillog
 *
 * This program is free software, you can redistribute it or modify
 * it under the terms of the GNU General Public License (GPL) as published
 * by the free software foundation, either version 2 of the license, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but without any warranty, without even the implied warranty of
 * merchantability or fitness for a particular purpose. See the
 * GNU General Public License (GPL) for more details.
 */

class Luigifab_Maillog_Block_Adminhtml_Config_Comment extends Mage_Adminhtml_Block_System_Config_Form_Fieldset {

	protected function _getHeaderCommentHtml($element) {

		$comment = $element->getComment();
		if (empty($comment))
			return '<img src="'.$this->getSkinUrl('images/luigifab/maillog/logo-'.str_replace('maillog_sync_', '', $element->getId()).'.svg').'" alt="" class="maillog logo" />';

		if (stripos($element->getHtmlId(), 'sync') !== false)
			return implode("\n", ['<div class="comment maillog">', $comment, '</div>']);

		$vars = [
			'myvar1' => [['numb' => -2, 'text' => 'hello'], ['numb' => 0, 'text' => 'hello'], ['numb' => 2, 'text' => 'hello']],
			'myvar2' => ['str1', 'str2']
		];
		foreach ($vars['myvar1'] as $i => $n) {
			$t = $n['text'];
			$n = $n['numb'];
			$vars['myvar1'][$i]['a'] = ($n >  0) ? 'true' : 'false';
			$vars['myvar1'][$i]['b'] = ($n >= 0) ? 'true' : 'false';
			$vars['myvar1'][$i]['c'] = ($n <  0) ? 'true' : 'false';
			$vars['myvar1'][$i]['d'] = ($n <= 0) ? 'true' : 'false';
			$vars['myvar1'][$i]['e'] = ($n == 0) ? 'true' : 'false';
			$vars['myvar1'][$i]['f'] = ($n != 0) ? 'true' : 'false';
			$vars['myvar1'][$i]['g'] = ($n >  $n) ? 'true' : 'false';
			$vars['myvar1'][$i]['h'] = ($n >= $n) ? 'true' : 'false';
			$vars['myvar1'][$i]['i'] = ($n <  $n) ? 'true' : 'false';
			$vars['myvar1'][$i]['j'] = ($n <= $n) ? 'true' : 'false';
			$vars['myvar1'][$i]['k'] = ($n == $n) ? 'true' : 'false';
			$vars['myvar1'][$i]['l'] = ($n != $n) ? 'true' : 'false';
			// == et != avec empty
			$vars['myvar1'][$i]['m'] =  empty($n) ? 'true' : 'false';
			$vars['myvar1'][$i]['n'] = !empty($n) ? 'true' : 'false';
			// == et != // en PHP, tout string vaut 0 (voir aussi _getVariable)
			$vars['myvar1'][$i]['o'] = (is_numeric($n) && ($n == 0) && !is_numeric('abcde')) ? 'false' : (($n == 'abcde') ? 'true' : 'false');
			$vars['myvar1'][$i]['p'] = (is_numeric($n) && ($n == 0) && !is_numeric('abcde')) ? 'true'  : (($n != 'abcde') ? 'true' : 'false');
			// in_array
			$vars['myvar1'][$i]['q'] =  in_array($n, [0,1,2]) ? 'true' : 'false';
			$vars['myvar1'][$i]['r'] = !in_array($n, [0,1,2]) ? 'true' : 'false';
			// contains
			$vars['myvar1'][$i]['s'] = (mb_stripos($t, 'truc') !== false) ? 'true' : 'false';
			$vars['myvar1'][$i]['t'] = (mb_stripos($t, 'truc') === false) ? 'true' : 'false';
			$vars['myvar1'][$i]['u'] = (mb_stripos($t, '777') !== false) ? 'true' : 'false';
			$vars['myvar1'][$i]['v'] = (mb_stripos($t, '777') === false) ? 'true' : 'false';
			$vars['myvar1'][$i]['w'] = (mb_stripos($t, $t) !== false) ? 'true' : 'false';
			$vars['myvar1'][$i]['x'] = (mb_stripos($t, $t) === false) ? 'true' : 'false';
		}

		return implode("\n", [
			'<div class="comment maillog">',
				'<p>'.$comment.'</p>',
				'<ul lang="en">',
					'<li>{{foreach something}} ... {{forelse}} ... {{/foreach}}</li>',
					'<li>{{if something gt/gte/gteq/lt/lte/lteq/eq/neq something/empty}} ... {{elseif ...}} ... {{else}} ... {{/if}}</li>',
					'<li>{{if something in/nin a,b,c,1,2,3}} ... {{elseif ...}} ... {{else}} ... {{/if}}  (in_array)</li>',
					'<li>{{if something ct/nct something}} ... {{elseif ...}} ... {{else}} ... {{/if}}  (contains)</li>',
					'<li>{{ifconfig path="a/b/c"}} ... {{elseconfig}} ... {{/ifconfig}}</li>',
					'<li>{{helper action="xx/yy::zz"}} | {{helper action="xx/yy::zz" param="abc" marap=$something ifconfig="a/b/c"}}</li>',
					'<li>{{number something}} | {{number path="a/b/c" nodecimal="true" ifconfig="a/b/c"}}</li>',
					'<li>{{price something}} | {{price path="a/b/c" nodecimal="true" currency="xyz" store="i" product="i" ifconfig="a/b/c"}}</li>',
					'<li>{{currency code="xyz"}} | {{currency path="a/b/c" store="i" ifconfig="a/b/c"}}</li>',
					'<li>{{include template="xyz.html"}} | {{include template="xyz.html" ifconfig="a/b/c"}}</li>',
					'<li>{{dump something}} | {{dump}}</li>',
					'<li>{{picture ...}}</li>',
				'</ul>',
// foreach
'<div class="maillogexamples" onclick="this.innerHTML = this.innerHTML.replace(/\s+true\s+=\s+true/g, \' <b>true</b>\').replace(/\s+false\s+=\s+false/g, \' <b>false</b>\'); this.removeAttribute(\'onclick\');">',
'<pre lang="en">'.trim(str_replace("<span>\n", '<span>', str_replace(['{', '}'], ['{{', '}}'], Mage::getModel('varien/filter_template')->resetVariables($vars)->filter('
<b>foreach</b>
{foreach myvar1 = [["numb" => -2, "text" => "hello"], ["numb" => 0, "text" => "hello"], ["numb" => 2, "text" => "hello"]]}
 Z / ... / {if myvar1.numb operator value} true {else} false {/if} (result calculated by _getVariable()) = {var myvar1.z} (result calculated by PHP)
{/foreach}
<span>{{foreach myvar1}}<span>
 A / {if {{var myvar1.numb}} gt  0} / {{if myvar1.numb gt  0}} true  {{else}} false {{/if}} = {{var myvar1.a}}
 B / {if {{var myvar1.numb}} gte 0} / {{if myvar1.numb gte 0}} true  {{else}} false {{/if}} = {{var myvar1.b}}
 C / {if {{var myvar1.numb}} lt  0} / {{if myvar1.numb lt  0}} true  {{else}} false {{/if}} = {{var myvar1.c}}
 D / {if {{var myvar1.numb}} lte 0} / {{if myvar1.numb lte 0}} true  {{else}} false {{/if}} = {{var myvar1.d}}
 E / {if {{var myvar1.numb}} eq  0} / {{if myvar1.numb eq  0}} true  {{else}} false {{/if}} = {{var myvar1.e}}
 F / {if {{var myvar1.numb}} neq 0} / {{if myvar1.numb neq 0}} true  {{else}} false {{/if}} = {{var myvar1.f}}
 G / {if {{var myvar1.numb}} gt  {{var myvar1.numb}}} / {{if myvar1.numb gt  myvar1.numb}} true  {{else}} false {{/if}} = {{var myvar1.g}}
 H / {if {{var myvar1.numb}} gte {{var myvar1.numb}}} / {{if myvar1.numb gte myvar1.numb}} true  {{else}} false {{/if}} = {{var myvar1.h}}
 I / {if {{var myvar1.numb}} lt  {{var myvar1.numb}}} / {{if myvar1.numb lt  myvar1.numb}} true  {{else}} false {{/if}} = {{var myvar1.i}}
 J / {if {{var myvar1.numb}} lte {{var myvar1.numb}}} / {{if myvar1.numb lte myvar1.numb}} true  {{else}} false {{/if}} = {{var myvar1.j}}
 K / {if {{var myvar1.numb}} eq  {{var myvar1.numb}}} / {{if myvar1.numb eq  myvar1.numb}} true  {{else}} false {{/if}} = {{var myvar1.k}}
 L / {if {{var myvar1.numb}} neq {{var myvar1.numb}}} / {{if myvar1.numb neq myvar1.numb}} true  {{else}} false {{/if}} = {{var myvar1.l}}
 M / {if {{var myvar1.numb}} eq  empty} / {{if myvar1.numb eq  empty}} true  {{else}} false {{/if}} = {{var myvar1.m}}
 N / {if {{var myvar1.numb}} neq empty} / {{if myvar1.numb neq empty}} true  {{else}} false {{/if}} = {{var myvar1.n}}
 O / {if {{var myvar1.numb}} eq  abcde} / {{if myvar1.numb eq  abcde}} true  {{else}} false {{/if}} = {{var myvar1.o}}
 P / {if {{var myvar1.numb}} neq abcde} / {{if myvar1.numb neq abcde}} true  {{else}} false {{/if}} = {{var myvar1.p}}
 Q / {if {{var myvar1.numb}} in  0,1,2} / {{if myvar1.numb in  0,1,2}} true  {{else}} false {{/if}} = {{var myvar1.q}}
 R / {if {{var myvar1.numb}} nin 0,1,2} / {{if myvar1.numb nin 0,1,2}} true  {{else}} false {{/if}} = {{var myvar1.r}}
 S / {if {{var myvar1.text}} ct  truc} / {{if myvar1.text ct  truc}} true  {{else}} false {{/if}} = {{var myvar1.s}}
 T / {if {{var myvar1.text}} nct truc} / {{if myvar1.text nct truc}} true  {{else}} false {{/if}} = {{var myvar1.t}}
 U / {if {{var myvar1.text}} ct  777} / {{if myvar1.text ct  777}} true  {{else}} false {{/if}} = {{var myvar1.u}}
 V / {if {{var myvar1.text}} nct 777} / {{if myvar1.text nct 777}} true  {{else}} false {{/if}} = {{var myvar1.v}}
 W / {if {{var myvar1.text}} ct  {{var myvar1.text}}} / {{if myvar1.text ct  myvar1.text}} true  {{else}} false {{/if}} = {{var myvar1.w}}
 X / {if {{var myvar1.text}} nct {{var myvar1.text}}} / {{if myvar1.text nct myvar1.text}} true  {{else}} false {{/if}} = {{var myvar1.x}}
</span>{{/foreach}}</span>{foreach myvar2 = ["str1", "str2"]} Y / {var myvar2} {/foreach}
<span>{{foreach myvar2}}<span>
 Y / {{var myvar2}}
</span>{{/foreach}}</span><!--
--><b>ifconfig</b>
 A / {ifconfig path="maillog/general/enabled"} true {elseconfig} false {/ifconfig} / {{ifconfig path="maillog/general/enabled"}} <em>true</em> {{elseconfig}} <em>false</em> {{/ifconfig}}
 B / {ifconfig path="maillog/general/unknown"} true {elseconfig} false {/ifconfig} / {{ifconfig path="maillog/general/unknown"}} <em>true</em> {{elseconfig}} <em>false</em> {{/ifconfig}}
<b>helper</b>
 A / {helper action="maillog::demoHelper"} / <em>{{helper action="maillog::demoHelper"}}</em>
 B / {helper action="maillog::demoHelper" param="abc"} / <em>{{helper action="maillog::demoHelper" param="abc"}}</em>
<b>number</b>
 A / {number 1700200} / <em>{{number 1700200}}</em>
 B / {number 15.4 nodecimal="true"} / <em>{{number 15.4 nodecimal="true"}}</em>
 C / {number 15.4 precision="2"}    / <em>{{number 15.4 precision="2"}}</em>
 D / {number path="maillog/general/number"} / <em>{{number path="maillog/general/number"}}</em>
 E / {number path="mallog/general/number" precision="2"} / <em>{{number path="maillog/general/number" precision="2"}}</em>
<b>price</b>
 A / {price 99.99 / <em>{{price 99.99}}</em>
 B / {price 15000 nodecimal="true"}   / <em>{{price 15000 nodecimal="true"}}</em>
 C / {price 15000.4 nodecimal="true"} / <em>{{price 15000.4 nodecimal="true"}}</em>
 D / {price path="maillog/general/number"} / <em>{{price path="maillog/general/number"}}</em>
 E / {price path="maillog/general/number" nodecimal="true"} / <em>{{price path="maillog/general/number" nodecimal="true"}}</em>
 F / {price 15.4 currency="cad"} / <em>{{price 15.4 currency="cad"}}</em>
<b>currency</b>
 A / {currency code="gbp"} / <em>{{currency code="gbp"}}</em>
 B / {currency path="currency/options/base"} / <em>{{currency path="currency/options/base"}}</em>
')))).'</pre>',
'</div>',
		'</div>' // div class comment
		]);
	}
}