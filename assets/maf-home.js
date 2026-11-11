document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('.mafx-profils').forEach(function (box) {
		var profils = box.querySelectorAll('.mafx-profil');
		if (!profils.length) { return; }
		var chips = document.createElement('div');
		chips.className = 'mafx-pchips';
		chips.setAttribute('role', 'group');
		chips.setAttribute('aria-label', 'Votre projet');
		profils.forEach(function (pr, i) {
			var label = pr.querySelector('.mafx-profil-label');
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'mafx-pchip' + (i === 0 ? ' is-on' : '');
			b.setAttribute('aria-pressed', i === 0 ? 'true' : 'false');
			b.textContent = label ? label.textContent : 'Profil ' + (i + 1);
			b.addEventListener('click', function () {
				chips.querySelectorAll('.mafx-pchip').forEach(function (c, j) {
					c.classList.toggle('is-on', j === i);
					c.setAttribute('aria-pressed', j === i ? 'true' : 'false');
				});
				profils.forEach(function (x, j) { x.hidden = j !== i; });
			});
			chips.appendChild(b);
			pr.hidden = i !== 0;
		});
		var txt = box.parentNode.querySelector('.mafx-parcours-txt');
		(txt || box.parentNode).appendChild(chips);
		box.classList.add('is-js');
	});
});

/* Formulaire de contact : jeton anti-robots (affichage → maintenant), vérifié par l'extension */
(function () {
	var champs = document.querySelectorAll('input[name="maf-js"]');
	if (!champs.length) { return; }
	var t0 = Date.now();
	function maj() { champs.forEach(function (c) { c.value = 'mafjs-' + t0 + '-' + Date.now(); }); }
	maj();
	setInterval(maj, 1000);
	document.addEventListener('input', maj, true);
	document.addEventListener('submit', maj, true);
})();
