(function ($) {
	'use strict';

	const container = '#codexawp-pickup-classic';
	const controls = container + ' input[type=radio]';
	let selected = null;

	$(document.body).on('input', '#codexawp-classic-search', function () {
		const term = this.value.trim().toLowerCase();
		const panel = this.closest(container);
		const options = Array.from(panel.querySelectorAll('.codexawp-pickup-option'));
		options.forEach(option => { option.hidden = !option.textContent.toLowerCase().includes(term); });
		panel.querySelector('.codexawp-pickup-empty').hidden = options.some(option => !option.hidden);
	});

	$(document.body).on('change', controls, function () {
		selected = { rate: this.closest(container).dataset.rate, id: this.value };
	});

	$(document.body).on('updated_checkout', function () {
		if (!selected) return;
		const panel = document.querySelector(container);
		const option = panel?.dataset.rate === selected.rate
			? Array.from(panel.querySelectorAll('input[type=radio]')).find(input => input.value === selected.id)
			: null;
		if (option) option.checked = true;
		else selected = null;
	});
})(window.jQuery);
