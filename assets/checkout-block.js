'use strict';

(function ({ wp, wc }) {
	const { createElement: el, useEffect, useState } = wp.element;
	const { useSelect } = wp.data;
	const { __ } = wp.i18n;
	const namespace = 'codexawp-pickup';
	const noPoints = [];

	function Selector({ rate, points, setExtensionData }) {
		const [query, setQuery] = useState('');
		const [selection, select] = useState('');
		const current = points.some(point => point.id === selection) ? selection : '';

		useEffect(() => {
			if (selection !== current) select(current);
			setExtensionData(namespace, 'pickup_point_id', current);
		}, [current, selection, setExtensionData]);

		if (!rate) return null;

		const term = query.trim().toLowerCase();
		const visible = points.filter(point => (
			[point.label, point.address, point.id].some(value => value.toLowerCase().includes(term))
		));

		return el('fieldset', { className: 'codexawp-pickup' },
			el('legend', null, __('Choose a pickup point', 'woo-pickup-point-checkout')),
			el('label', { htmlFor: 'codexawp-block-search' }, __('Search by name, address or code', 'woo-pickup-point-checkout')),
			el('input', {
				id: 'codexawp-block-search',
				type: 'search',
				value: query,
				autoComplete: 'off',
				onChange: event => setQuery(event.target.value),
			}),
			el('div', { className: 'codexawp-pickup-options' }, points.map(point =>
				el('label', { className: 'codexawp-pickup-option', key: point.id, hidden: !visible.includes(point) },
					el('input', {
						type: 'radio',
						name: 'codexawp-pickup-block',
						value: point.id,
						checked: point.id === current,
						onChange: () => select(point.id),
					}),
					el('span', null,
						el('strong', null, point.label),
						el('small', null, point.address + ' · ' + point.id)
					)
				)
			)),
			visible.length ? null : el('p', { className: 'codexawp-pickup-empty', role: 'status' }, __('No matching pickup points.', 'woo-pickup-point-checkout'))
		);
	}

	function PickupBlock({ checkoutExtensionData }) {
		const context = useSelect(select => (
			select(wc.wcBlocksData.CART_STORE_KEY).getCartData()?.extensions?.[namespace]
		), []);
		const rate = context?.rate || '';

		return el(Selector, {
			key: rate,
			rate,
			points: context?.points || noPoints,
			setExtensionData: checkoutExtensionData.setExtensionData,
		});
	}

	wc.blocksCheckout.registerCheckoutBlock({
		metadata: {
			name: 'codexawp/pickup-point',
			parent: ['woocommerce/checkout-shipping-methods-block'],
		},
		component: PickupBlock,
	});
})(window);
