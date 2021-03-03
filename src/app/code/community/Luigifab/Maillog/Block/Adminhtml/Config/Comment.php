<?php
/**
 * Created S/26/10/2019
 * Updated V/19/02/2021
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

		if (mb_stripos($element->getHtmlId(), 'sync') !== false)
			return implode("\n", ['<div class="comment maillogcomment">', $element->getComment(), '</div>']);

		$vars = ['var1' => [['numb' => -2, 'text' => 'hello'], ['numb' => 0, 'text' => 'hello'], ['numb' => 2, 'text' => 'hello']]];
		foreach ($vars['var1'] as $i => $n) {
			$t = $n['text'];
			$n = $n['numb'];
			$vars['var1'][$i]['a'] = ($n >  0) ? 'true' : 'false';
			$vars['var1'][$i]['b'] = ($n >= 0) ? 'true' : 'false';
			$vars['var1'][$i]['c'] = ($n <  0) ? 'true' : 'false';
			$vars['var1'][$i]['d'] = ($n <= 0) ? 'true' : 'false';
			$vars['var1'][$i]['e'] = ($n == 0) ? 'true' : 'false';
			$vars['var1'][$i]['f'] = ($n != 0) ? 'true' : 'false';
			$vars['var1'][$i]['g'] = ($n >  $n) ? 'true' : 'false';
			$vars['var1'][$i]['h'] = ($n >= $n) ? 'true' : 'false';
			$vars['var1'][$i]['i'] = ($n <  $n) ? 'true' : 'false';
			$vars['var1'][$i]['j'] = ($n <= $n) ? 'true' : 'false';
			$vars['var1'][$i]['k'] = ($n == $n) ? 'true' : 'false';
			$vars['var1'][$i]['l'] = ($n != $n) ? 'true' : 'false';
			// == et != avec empty
			$vars['var1'][$i]['m'] =  empty($n) ? 'true' : 'false';
			$vars['var1'][$i]['n'] = !empty($n) ? 'true' : 'false';
			// == et != // en PHP, tout string vaut 0 (voir aussi _getVariable)
			$vars['var1'][$i]['o'] = (is_numeric($n) && ($n == 0) && !is_numeric('abcde')) ? 'false' : (($n == 'abcde') ? 'true' : 'false');
			$vars['var1'][$i]['p'] = (is_numeric($n) && ($n == 0) && !is_numeric('abcde')) ? 'true'  : (($n != 'abcde') ? 'true' : 'false');
			// in_array
			$vars['var1'][$i]['q'] =  in_array($n, [0,1,2]) ? 'true' : 'false';
			$vars['var1'][$i]['r'] = !in_array($n, [0,1,2]) ? 'true' : 'false';
			// contains
			$vars['var1'][$i]['s'] = (mb_stripos($t, 'truc') !== false) ? 'true' : 'false';
			$vars['var1'][$i]['t'] = (mb_stripos($t, 'truc') === false) ? 'true' : 'false';
			$vars['var1'][$i]['u'] = (mb_stripos($t, '777') !== false) ? 'true' : 'false';
			$vars['var1'][$i]['v'] = (mb_stripos($t, '777') === false) ? 'true' : 'false';
			$vars['var1'][$i]['w'] = (mb_stripos($t, $t) !== false) ? 'true' : 'false';
			$vars['var1'][$i]['x'] = (mb_stripos($t, $t) === false) ? 'true' : 'false';
		}

		return implode("\n", [
			'<div class="comment maillogcomment">',
				'<p>'.$element->getComment().'</p>',
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
'<pre lang="en">'.trim(str_replace("<span>\n", '<span>', Mage::getModel('varien/filter_template')->resetVariables($vars)->filter('
<b>foreach</b>
{foreach var1} = [["numb" => -2, "text" => "hello"], ["numb" => 0, "text" => "hello"], ["numb" => 2, "text" => "hello"]]
 Z / ... / {if var1.numb operator value} true {else} false {/if} (result calculated by _getVariable()) = {var var1.z} (result calculated by PHP)
{/foreach}
<span>{{foreach var1}}<span>
 A / {if {{var var1.numb}} gt  0} / {{if var1.numb gt  0}} true  {{else}} false {{/if}} = {{var var1.a}}
 B / {if {{var var1.numb}} gte 0} / {{if var1.numb gte 0}} true  {{else}} false {{/if}} = {{var var1.b}}
 C / {if {{var var1.numb}} lt  0} / {{if var1.numb lt  0}} true  {{else}} false {{/if}} = {{var var1.c}}
 D / {if {{var var1.numb}} lte 0} / {{if var1.numb lte 0}} true  {{else}} false {{/if}} = {{var var1.d}}
 E / {if {{var var1.numb}} eq  0} / {{if var1.numb eq  0}} true  {{else}} false {{/if}} = {{var var1.e}}
 F / {if {{var var1.numb}} neq 0} / {{if var1.numb neq 0}} true  {{else}} false {{/if}} = {{var var1.f}}
 G / {if {{var var1.numb}} gt  {{var var1.numb}}} / {{if var1.numb gt  var1.numb}} true  {{else}} false {{/if}} = {{var var1.g}}
 H / {if {{var var1.numb}} gte {{var var1.numb}}} / {{if var1.numb gte var1.numb}} true  {{else}} false {{/if}} = {{var var1.h}}
 I / {if {{var var1.numb}} lt  {{var var1.numb}}} / {{if var1.numb lt  var1.numb}} true  {{else}} false {{/if}} = {{var var1.i}}
 J / {if {{var var1.numb}} lte {{var var1.numb}}} / {{if var1.numb lte var1.numb}} true  {{else}} false {{/if}} = {{var var1.j}}
 K / {if {{var var1.numb}} eq  {{var var1.numb}}} / {{if var1.numb eq  var1.numb}} true  {{else}} false {{/if}} = {{var var1.k}}
 L / {if {{var var1.numb}} neq {{var var1.numb}}} / {{if var1.numb neq var1.numb}} true  {{else}} false {{/if}} = {{var var1.l}}
 M / {if {{var var1.numb}} eq  empty} / {{if var1.numb eq  empty}} true  {{else}} false {{/if}} = {{var var1.m}}
 N / {if {{var var1.numb}} neq empty} / {{if var1.numb neq empty}} true  {{else}} false {{/if}} = {{var var1.n}}
 O / {if {{var var1.numb}} eq  abcde} / {{if var1.numb eq  abcde}} true  {{else}} false {{/if}} = {{var var1.o}}
 P / {if {{var var1.numb}} neq abcde} / {{if var1.numb neq abcde}} true  {{else}} false {{/if}} = {{var var1.p}}
 Q / {if {{var var1.numb}} in  0,1,2} / {{if var1.numb in  0,1,2}} true  {{else}} false {{/if}} = {{var var1.q}}
 R / {if {{var var1.numb}} nin 0,1,2} / {{if var1.numb nin 0,1,2}} true  {{else}} false {{/if}} = {{var var1.r}}
 S / {if {{var var1.text}} ct  truc} / {{if var1.text ct  truc}} true  {{else}} false {{/if}} = {{var var1.s}}
 T / {if {{var var1.text}} nct truc} / {{if var1.text nct truc}} true  {{else}} false {{/if}} = {{var var1.t}}
 U / {if {{var var1.text}} ct  777} / {{if var1.text ct  777}} true  {{else}} false {{/if}} = {{var var1.u}}
 V / {if {{var var1.text}} nct 777} / {{if var1.text nct 777}} true  {{else}} false {{/if}} = {{var var1.v}}
 W / {if {{var var1.text}} ct  {{var var1.text}}} / {{if var1.text ct  var1.text}} true  {{else}} false {{/if}} = {{var var1.w}}
 X / {if {{var var1.text}} nct {{var var1.text}}} / {{if var1.text nct var1.text}} true  {{else}} false {{/if}} = {{var var1.x}}
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
 B / {price 15 nodecimal="true"}   / <em>{{price 15 nodecimal="true"}}</em>
 C / {price 15.4 nodecimal="true"} / <em>{{price 15.4 nodecimal="true"}}</em>
 D / {price path="maillog/general/number"} / <em>{{price path="maillog/general/number"}}</em>
 E / {price path="maillog/general/number" nodecimal="true"} / <em>{{price path="maillog/general/number" nodecimal="true"}}</em>
 F / {price 15.4 currency="cad"} / <em>{{price 15.4 currency="cad"}}</em>
<b>currency</b>
 A / {currency code="gbp"} / <em>{{currency code="gbp"}}</em>
 B / {currency path="currency/options/base"} / <em>{{currency path="currency/options/base"}}</em>
'))).'</pre>',
'</div>',
		'</div>' // div class comment
		]);
	}
}