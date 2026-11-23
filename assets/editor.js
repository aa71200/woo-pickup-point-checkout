'use strict';

(function ({ blocks, element, i18n }) {
	const { createElement } = element;
	const { __ } = i18n;
	const description = __('The selector appears when Pickup Point is the selected shipping method.', 'woo-pickup-point-checkout');

	blocks.registerBlockType('codexawp/pickup-point', {
		edit: () => createElement('p', null, description),
		save: () => null,
	});
})(window.wp);
