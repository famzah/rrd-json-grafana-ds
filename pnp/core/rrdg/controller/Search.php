<?php
\famzah\PNP\PNPApp::getInstance()
->add_class('\rrdg\controller\Search')
	->append_method('__construct', function ($me, $config, $Search, $Mapping, $Utils) {
		$me->config = $config;
		$me->Search = $Search;
		$me->Mapping = $Mapping;
		$me->Utils = $Utils;
	})
	->append_method('do_query_filter', function ($me, $target_query, $rkey) {
		$ret = [];

		$seen = [];
		$rrd_search = $me->Search->search_all_namespaces($target_query);
		foreach ($rrd_search['results_raw'] as $row) {
			$value = $row[$rkey];

			if (isset($seen[$value])) {
				continue;
			}
			$seen[$value] = 1;

			if ($rkey != 'cfname') {
				$desc = $me->Mapping->map_plain($row['rrd_dir'], $rkey, $value);
			} else {
				$desc = $value;
			}

			$ret[] = [
				'text' => $desc,
				'value' => $desc, // XXX: not the raw $value
			];
		}

		return $ret;
	})
	->append_method('filter_level_1', function ($me, $target_query) {
		$ret = [];

		$all_ns = $me->Search->get_all_namespaces($target_query);
		foreach ($all_ns as $rrd_dir => $ns_config) {
			$desc = $me->Mapping->map_plain($rrd_dir, 'namespace', null);
			$ret[] = [
				'text' => $desc,
				'value' => $desc, // XXX: not the raw value "$rrd_dir"
			];
		}

		return $ret;
	})
	->append_method('filter_level_2', function ($me, $target_query) {
		return $me->do_query_filter($target_query, 'rrd_file');
	})
	->append_method('filter_level_3', function ($me, $target_query) {
		return $me->do_query_filter($target_query, 'metric');
	})
	->append_method('filter_level_4', function ($me, $target_query) {
		return $me->do_query_filter($target_query, 'cfname');
	})
	->append_method('filter_no_target', function ($me) {
		$config = $me->config;
		if (0) {
			reset($config['namespaces']);
			$rrd_dir = key($config['namespaces']); // get the first one
			$res = $me->Search->search_namespace($rrd_dir);
		} else {
			$res = $me->Search->search_all_namespaces();
		}

		return $res['results_human'];
	})
	->append_method('filter_with_target', function ($me, $q) {
		$target_query = $me->Utils->parse_target_query($me->config, $q['target']);
		//file_put_contents('php://stderr', print_r($target_query, 1));

		switch ($target_query['_count']) {
			case 1:
				$ret = $me->filter_level_1($target_query);
				break;
			case 2:
				$ret = $me->filter_level_2($target_query);
				break;
			case 3:
				$ret = $me->filter_level_3($target_query);
				break;
			case 4:
				$ret = $me->filter_level_4($target_query);
				break;
			default:
				throw new Exception(
					"target_query count is unexpected: ".$target_query['_count']
				);
				break;
		}

		return $ret;
	})
	->append_method('main', function ($me) {
		$q = $me->Utils->get_json_http_post_query();
		//file_put_contents('php://stderr', print_r($q, 1));

		if (!isset($q['target']) || !strlen($q['target'])) {
			$ret = $me->filter_no_target();
		} else {
			$ret = $me->filter_with_target($q);
		}

		//file_put_contents('php://stderr', print_r($ret, 1));

		echo json_encode($ret);
	})
; // add_class
