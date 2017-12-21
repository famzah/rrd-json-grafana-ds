<?php
\famzah\PNP\PNPApp::getInstance()
->add_class('\rrdg\lib\CachingRRD')
	->append_method('__construct', function ($me) {
		$me->data_cache = [];
	})
	->append_method('_raw_rrd_fetch', function ($me, $rrd_file, $cfname, $query) {
		$def = [
			$cfname,
			'--resolution' => floor($query['grafana']['intervalMs']/1000),
			'--align-start',
			'--start', $query['range']['from'],
			'--end', $query['range']['to'],
		];
		$data = rrd_fetch($rrd_file, $def);

		//file_put_contents('php://stderr', print_r($data, 1));

		if (!is_array($data)) {
			throw new Exception("rrd_fetch($rrd_file): failed");
		}

		/*if (count($target_filter)) { // if any filter was supplied by Grafana
			$filtered_data = [];
			foreach ($data['data'] as $metric => $tsvalues) {
				if (!in_array($metric, $target_filter)) {
					continue;
				}
				$filtered_data[$metric] = $tsvalues;
			}
			$data['data'] = $filtered_data;
		}*/

		//file_put_contents('php://stderr', print_r($data, 1));

		return $data;
	})
	->append_method('fetch_for_metric', function ($me, $query, $target_info) {
		$rrd_cache_key = sprintf('%s:%s',
			$target_info['rrd_file'], $target_info['cfname']
		);
		if (!isset($me->data_cache[$rrd_cache_key])) {
			$me->data_cache[$rrd_cache_key] = $me->_raw_rrd_fetch(
				$target_info['rrd_dir'].DIRECTORY_SEPARATOR.$target_info['rrd_file'],
				$target_info['cfname'],
				$query
			);
		}
		$rrd_raw_data = $me->data_cache[$rrd_cache_key]['data'];
		if (isset($rrd_raw_data[$target_info['metric']])) {
			return $rrd_raw_data[$target_info['metric']];
		} else {
			return [];
		}
	})
; // add_class
