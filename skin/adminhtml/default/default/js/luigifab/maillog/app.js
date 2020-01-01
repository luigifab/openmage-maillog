/**
 * Created J/03/12/2015
 * Updated S/19/10/2019
 *
 * Copyright 2015-2020 | Fabrice Creuzot (luigifab) <code~luigifab~fr>
 * Copyright 2015-2016 | Fabrice Creuzot <fabrice.creuzot~label-park~com>
 * Copyright 2017-2018 | Fabrice Creuzot <fabrice~reactive-web~fr>
 * https://www.luigifab.fr/magento/maillog
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

if (window.NodeList && !NodeList.prototype.forEach) {
	NodeList.prototype.forEach = function (callback, that, i) {
		that = that || window;
		for (i = 0; i < this.length; i++)
			callback.call(that, this[i], i, this);
	};
}

var maillog = {

	start: function () {

		if (document.getElementById('maillog_sync_bounces_stats_customer')) {

			console.info('maillog.app - hello');
			var customer   = document.getElementById('maillog_sync_bounces_stats_customer'),
			    bounce     = document.getElementById('maillog_sync_bounces_stats_bounce'),
			    subscriber = document.getElementById('maillog_sync_unsubscribers_stats_subscriber'),
			    i, j;

			i = parseInt(customer.textContent.trim(), 10);
			j = parseInt(bounce.textContent.trim(), 10);
			bounce.textContent += ' (' + Math.floor(j * 100 / i) + ' %)';

			i = parseInt(customer.textContent.trim(), 10);
			j = parseInt(subscriber.textContent.trim(), 10);
			subscriber.textContent += ' (' + Math.floor(j * 100 / i) + ' %)';
		}
		else if (document.getElementById('pictureConfig')) {

			console.info('maillog.app - hello');
			var prev, nb = 0, max = 0, html = '';

			document.getElementById('pictureConfig').querySelectorAll('tbody > tr').forEach(function (elem) {
				if (elem.querySelector('td[rowspan]')) {
					nb  = parseInt(elem.querySelector('td[rowspan]').getAttribute('rowspan'), 10);
					max = (380 + 1) + 'px';
					html += '<div class="max" style="margin-left:' + max + ';">' +
						'<span class="s">a' + 'A' + '</span>' +
						'<span class="l">b' + max + '</span>' +
						'<span class="w">c' + elem.querySelector('.w').value + ' x ' + elem.querySelector('.h').value + '</span>' +
					'</div>';
				}
				else {
					html += '<div class="mid" style="margin-left:' + 10 + 'px; width:' + 10 + 'px;">' +
						'<span class="s">a' + 'B' + '</span>' +
						'<span class="l">b' + prev + '</span>' +
						'<span class="r">c' + elem.querySelector('.b').value + '</span>' +
						'<span class="w">d' + elem.querySelector('.w').value + ' x ' + elem.querySelector('.h').value + '</span>' +
					'</div>';
					if (--nb < 2) {
						html += '<div class="min" style="width:' + 10 + 'px;">' +
							'<span class="s">a' + 'C' + '</span>' +
							'<span class="l">b0</span>' +
							'<span class="r">c' + elem.querySelector('.b').value + '</span>' +
							'<span class="w">d' + elem.querySelector('.w').value + ' x ' + elem.querySelector('.h').value + '</span>' +
						'</div>';
					}
				}
			});

			document.getElementById('picturePreview').innerHTML += html;
		}
	},

	add: function () {

		var sys = document.getElementById('maillog_sync_mapping_system'),
		    mag = document.getElementById('maillog_sync_mapping_magento'),
		    cnf = document.getElementById('maillog_sync_mapping_config');

		if ((sys.value.length > 0) && (mag.value.length > 0)) {
			cnf.value = (cnf.value + '\n' + sys.value + ':' + mag.value).trim();
			cnf.scrollTop = cnf.scrollHeight;
			sys.selectedIndex = 0;
			mag.selectedIndex = 0;
			this.mark();
		}
	},

	mark: function () {

		var fields = document.getElementById('maillog_sync_mapping_config').value;

		document.getElementById('maillog_sync_mapping_system').querySelectorAll('option').forEach(function (elem) {
			if ((fields.indexOf('\n' + elem.value + ':') > -1) || (fields.indexOf(elem.value + ':') === 0))
				elem.setAttribute('class', 'has');
			else
				elem.removeAttribute('class');
		});

		document.getElementById('maillog_sync_mapping_magento').querySelectorAll('option').forEach(function (elem) {
			if (fields.indexOf(':' + elem.value) > -1)
				elem.setAttribute('class', 'has');
			else
				elem.removeAttribute('class');
		});
	},

	reset: function (elem, color) {
		var root = elem.parentNode;
		root.querySelectorAll('select')[0].selectedIndex = 0;
		root.querySelectorAll('select')[1].selectedIndex = 0;
		root.querySelectorAll('input')[0].value = color.toLowerCase();
		root.querySelectorAll('input')[1].value = '#000000';
	},

	remove: function (elem, txt) {
		if (confirm(txt))
			elem.parentNode.parentNode.removeChild(elem.parentNode);
	},

	iframe: function (elem) {
		try {
			elem.removeAttribute('onload');
			elem.contentDocument.body.parentNode.innerHTML = decodeURIComponent(escape(self.atob(elem.firstChild.nodeValue)));
			elem.style.height = elem.contentDocument.body.scrollHeight + 'px';
			self.setTimeout(function () {
				var elem = document.querySelector('div.content iframe');
				elem.style.height = elem.contentDocument.body.scrollHeight + 'px';
			}, 1000);
		}
		catch (e) {
			elem.contentDocument.body.textContent = e;
		}
	}
};

if (typeof self.addEventListener == 'function')
	self.addEventListener('load', maillog.start.bind(maillog));