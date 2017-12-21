<?php
$config = [
	'namespaces' => [
		'/var/lib/html/iot/temphumi' => [ // where all structure-identical RRDs are stored
			'mapping'=> [ // human-readable descriptions
				'namespace' => 'HomeEnv',
				'rrd_file' => [
					'60:01:94:43:36:b8.rrd' => 'Room A',
					'testcopy.rrd' => 'Room Копие',
				],
				'metric' => [
					'humi' => 'Влажност',
					'temp' => 'Температура',
				],
			],
		],
	],
	'max_results_per_query' => 10,
	'plugins' => [
		'core'
	],
];
