<?php
\famzah\PNP\PNPApp::getInstance()
->add_class('\rrdg\controller\Query')
	->append_method('__construct', function ($me, $config, $Search, $Mapping, $Utils, $CachingRRD) {
		$me->config = $config;
		$me->Search = $Search;
		$me->Mapping = $Mapping;
		$me->Utils = $Utils;
		$me->CachingRRD = $CachingRRD;
	})
	->append_method('table_query', function ($me, $req_targets_data) {
		$grafserie = [
			'columns' => [
				[
					'text' => 'Time',
					'type' => 'time',
				],
			],
			'rows' => [],
			'type' => 'table',
		];

		foreach ($req_targets_data as $data) {
			$grafserie['columns'][] = [
				'text' => $me->Mapping->get_metric_text_desc(
					$data['meta']['rrd_dir'],
					$data['meta']['rrd_file'],
					$data['meta']['metric'],
					$data['meta']['cfname']
				),
				'type' => 'number',
			];
		}

		foreach ($req_targets_data as $data) {
			$i = -1;
			foreach ($data['data'] as $ts => $value) {
				$i++;
				if (is_nan($value)) $value = null;
				if (!isset($grafserie['rows'][$i])) {
					$grafserie['rows'][$i] = [$ts * 1000]; // add new array row
				}
				$grafserie['rows'][$i][] = $value; // append a "column" to the array row
			}
		}

		//file_put_contents('php://stderr', print_r($grafserie, 1));

		return $grafserie;
	})
	->append_method('timeserie_query', function ($me, $data) {
		$grafserie = [
			'target' => $me->Mapping->get_metric_text_desc(
				$data['meta']['rrd_dir'],
				$data['meta']['rrd_file'],
				$data['meta']['metric'],
				$data['meta']['cfname']
			),
			'datapoints' => [],
		];
		foreach ($data['data'] as $ts => $value) {
			if (is_nan($value)) $value = null;
			$grafserie['datapoints'][] = array($value, $ts * 1000);
		}

		return $grafserie;
	})
	->append_method('date2str', function ($me, $s) {
		$ts = strtotime($s);
		if (!$ts) {
			throw new Exception("Unable to parse datetime: $s");
		}
		return $ts;
	})
	->append_method('parse_grafana_post_query', function ($me) {
		$request = $me->Utils->get_json_http_post_query();

		$query = [
			'range' => [
				'from' => $me->date2str($request['range']['from']),
				'to'   => $me->date2str($request['range']['to']),
			],
			'grafana' => $request,
		];

		//file_put_contents('php://stderr', print_r($query, 1));
		//file_put_contents('php://stderr', print_r($query['grafana']['targets'], 1));
		//file_put_contents('php://stderr', print_r($query['grafana'], 1));

		return $query;
	})
	->append_method('sanity_check_grafana_query', function ($me, $requested_targets) {
		if (count($requested_targets) == 0) {
			$me->Utils->send_http_error_and_abort("400 Bad Request",
				"Grafana provided no target type (timeserie or table). ".
				"Did you configured anything in the \"Metrics\" tab?"
			);
		}
		if (count($requested_targets) > 1) {
			$me->Utils->send_http_error_and_abort("400 Bad Request",
				"Grafana requested more than one target type at once but Grafana ".
				"does not support mixed types well. Configure in the \"Metrics\" tab ".
				"either only \"table\" or \"timeserie\" types for your query."
			);
		}
	})
	->append_method('fetch_data_for_targets', function ($me, $query, $max_rows) {
		$requested_targets = [];

		foreach ($query['grafana']['targets'] as $grafana_target) {
			if (!isset($grafana_target['target'])) {
				continue;
			}

			if (!isset($requested_targets[$grafana_target['type']])) {
				$requested_targets[$grafana_target['type']] = [];
			}

			$target_query = $me->Utils->parse_target_query(
				$me->config, $grafana_target['target']
			);
			if ($target_query['_count'] != 4) {
				$me->Utils->send_http_error_and_abort("500 Bad Request",
					"Unable to parse the request - you need to provide all four ".
					"query filters: ".$grafana_target['target']
				);
			}
			$rrd_search = $me->Search->search_all_namespaces($target_query);
			foreach ($rrd_search['results_raw'] as $rrd_entry) {
				$target_info = [
					'rrd_dir' => $rrd_entry['rrd_dir'],
					'rrd_file' => $rrd_entry['rrd_file'],
					'metric' => $rrd_entry['metric'],
					'cfname' => $rrd_entry['cfname']
				];

				if (count($requested_targets[$grafana_target['type']]) > $max_rows) {
					trigger_error("Query returned too many results. Truncating to $max_rows");
					break;
				}

				$requested_targets[$grafana_target['type']][] = [
					'meta' => $target_info,
					'data' => $me->CachingRRD->fetch_for_metric(
						$query, $target_info
					),
				];
			}
		}

		return $requested_targets;
	})
	->append_method('main', function ($me) {
		$query = $me->parse_grafana_post_query();

		if (isset($me->config['max_results_per_query'])) {
			$max_rows = $me->config['max_results_per_query'];
		} else {
			$max_rows = 10;
		}

		$requested_targets = $me->fetch_data_for_targets($query, $max_rows);
		$me->sanity_check_grafana_query($requested_targets);

		// For now this is always 1 loop, because Grafana does not support mixed results.
		// See $me->sanity_check_grafana_query().
		$res = [];
		foreach ($requested_targets as $grafana_target_type => $req_targets_data) {
			if ($grafana_target_type == 'table') {
				// this consumes all "table" targets and returns one table with many columns
				$res[] = $me->table_query($req_targets_data);
			} elseif ($grafana_target_type == 'timeserie') {
				foreach ($req_targets_data as $data) {
					$res[] = $me->timeserie_query($data);
				}
			} else {
				throw new Exception("Unknown request type: $grafana_target_type");
			}
		}

		//file_put_contents('php://stderr', print_r($res, 1));

		echo json_encode($res);
	})
; // add_class
