/* Vidéos MAF : fenêtre de lecture, onglets, filtres et « voir plus » */
(function () {
	'use strict';

	var modal, frame, box, titleEl, artEl, lastFocus;

	function buildModal() {
		modal = document.createElement('div');
		modal.className = 'mafv-modal';
		modal.hidden = true;
		modal.setAttribute('role', 'dialog');
		modal.setAttribute('aria-modal', 'true');
		modal.setAttribute('aria-label', 'Lecture de la vidéo');
		modal.innerHTML =
			'<div class="mafv-box">' +
			'<button type="button" class="mafv-close" aria-label="Fermer la vidéo">×</button>' +
			'<div class="mafv-frame"></div>' +
			'<div class="mafv-mt"></div>' +
			'<div class="mafv-actions"><a class="mafv-a1" href="#">Lire l\'article lié</a>' +
			'<a class="mafv-a2" href="https://www.youtube.com/@MonAppartFacile?sub_confirmation=1" target="_blank" rel="noopener">S\'abonner</a></div>' +
			'</div>';
		document.body.appendChild(modal);
		box = modal.querySelector('.mafv-box');
		frame = modal.querySelector('.mafv-frame');
		titleEl = modal.querySelector('.mafv-mt');
		artEl = modal.querySelector('.mafv-a1');
		modal.querySelector('.mafv-close').addEventListener('click', close);
		modal.addEventListener('click', function (e) { if (e.target === modal) { close(); } });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) { close(); } });
	}

	function open(btn) {
		if (!modal) { buildModal(); }
		lastFocus = btn;
		var id = btn.getAttribute('data-mafv-id');
		var court = btn.getAttribute('data-mafv-format') === 'court';
		var art = btn.getAttribute('data-mafv-article');
		box.classList.toggle('is-court', court);
		titleEl.textContent = btn.getAttribute('data-mafv-titre') || '';
		if (art) { artEl.href = art; artEl.style.display = ''; } else { artEl.style.display = 'none'; }
		var ifr = document.createElement('iframe');
		ifr.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) + '?autoplay=1&rel=0&modestbranding=1&playsinline=1';
		ifr.title = titleEl.textContent || 'Vidéo YouTube';
		ifr.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
		ifr.allowFullscreen = true;
		frame.innerHTML = '';
		frame.appendChild(ifr);
		modal.hidden = false;
		document.body.classList.add('mafv-open');
		modal.querySelector('.mafv-close').focus();
	}

	function close() {
		modal.hidden = true;
		frame.innerHTML = '';
		document.body.classList.remove('mafv-open');
		if (lastFocus) { lastFocus.focus(); }
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('.mafv-play') : null;
		if (btn) { e.preventDefault(); open(btn); return; }

		var tab = e.target.closest ? e.target.closest('.mafv-tab') : null;
		if (tab) {
			var wrap = tab.closest('[data-mafv-tabs]');
			wrap.querySelectorAll('.mafv-tab').forEach(function (t) {
				var on = t === tab;
				t.classList.toggle('is-on', on);
				t.setAttribute('aria-selected', on ? 'true' : 'false');
				var p = document.getElementById(t.getAttribute('aria-controls'));
				if (p) { p.hidden = !on; }
			});
			return;
		}

		var chip = e.target.closest ? e.target.closest('.mafv-chip') : null;
		if (chip) {
			var f = chip.closest('[data-mafv-filtre]');
			f.querySelectorAll('.mafv-chip').forEach(function (c) {
				var on = c === chip;
				c.classList.toggle('is-on', on);
				c.setAttribute('aria-pressed', on ? 'true' : 'false');
			});
			f.setAttribute('data-theme', chip.getAttribute('data-theme'));
			f.setAttribute('data-shown', f.getAttribute('data-par-page'));
			refresh(f);
			return;
		}

		var more = e.target.closest ? e.target.closest('.mafv-more') : null;
		if (more) {
			var g = more.closest('[data-mafv-filtre]');
			g.setAttribute('data-shown', String(parseInt(g.getAttribute('data-shown'), 10) + parseInt(g.getAttribute('data-par-page'), 10)));
			refresh(g);
		}
	});

	function refresh(f) {
		var theme = f.getAttribute('data-theme') || '';
		var max = parseInt(f.getAttribute('data-shown') || f.getAttribute('data-par-page') || '12', 10);
		var n = 0, total = 0;
		f.querySelectorAll('.mafv-short').forEach(function (s) {
			var ok = !theme || s.getAttribute('data-theme') === theme;
			if (ok) { total++; }
			var show = ok && n < max;
			if (show) { n++; }
			s.hidden = !show;
		});
		var w = f.querySelector('.mafv-more-w');
		if (w) { w.hidden = total <= max; }
	}

	function init() {
		document.querySelectorAll('[data-mafv-filtre]').forEach(function (f) {
			f.setAttribute('data-shown', f.getAttribute('data-par-page') || '12');
			refresh(f);
		});
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
