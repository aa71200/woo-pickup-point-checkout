<?php
declare(strict_types=1);

use function CodexaWP\PickupPoint\active_points;
use function CodexaWP\PickupPoint\find_point;

require __DIR__ . '/../includes/catalog.php';

$north = active_points( 'north' );
$south = active_points( 'south' );
$cases = [
	'Find an active point by exact code' => 'Market Counter' === ( find_point( $north, 'NTH-02' )['label'] ?? null ),
	'Reject a point from another region' => null === find_point( $north, 'STH-01' ),
	'Reject a retired point' => null === find_point( $south, 'STH-03' ),
	'Reject an array submitted in place of an ID' => null === find_point( $north, [ 'NTH-02' ] ),
	'Reject a malformed ID' => null === find_point( $north, 'nTh-02' ),
	'Reject pickup on an unconfigured region' => [] === active_points( 'unset' ),
	'Recognize South locations in their own catalogue' => 'Harbor Locker' === ( find_point( $south, 'STH-01' )['label'] ?? null ),
];

foreach ( $cases as $case => $passed ) {
	if ( ! $passed ) {
		throw new RuntimeException( $case );
	}
	echo "PASS: $case\n";
}
