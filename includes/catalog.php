<?php
/**
 * A synthetic catalogue shared by both WooCommerce checkout adapters.
 */

declare(strict_types=1);

namespace CodexaWP\PickupPoint;

function active_points( string $region ): array {
	$points = match ( $region ) {
		'north' => [
			[ 'id' => 'NTH-01', 'label' => 'Cedar Locker', 'address' => '14 Cedar Walk, North District' ],
			[ 'id' => 'NTH-02', 'label' => 'Market Counter', 'address' => '8 Market Lane, North District' ],
			[ 'id' => 'NTH-03', 'label' => 'Station Locker', 'address' => '3 Station Square, North District' ],
		],
		'south' => [
			[ 'id' => 'STH-01', 'label' => 'Harbor Locker', 'address' => '4 Harbor Way, South District' ],
			[ 'id' => 'STH-02', 'label' => 'Park Counter', 'address' => '19 Park Lane, South District' ],
			[ 'id' => 'STH-03', 'label' => 'Old Wharf Locker', 'address' => '2 Wharf Walk, South District', 'active' => false ],
		],
		default => [],
	};

	return array_values( array_filter( $points, static fn( array $point ): bool => $point['active'] ?? true ) );
}

function find_point( array $points, mixed $id ): ?array {
	if ( ! is_string( $id ) || ! preg_match( '/\A[A-Z]{3}-[0-9]{2}\z/', $id ) ) {
		return null;
	}
	foreach ( $points as $point ) {
		if ( $id === $point['id'] ) {
			return $point;
		}
	}

	return null;
}
