<?php
\famzah\PNP\PNPApp::getInstance()
->add_class('\rrdg\lib\Search')
	->append_method('__construct', function ($me, $config, $Utils, $Mapping) {
		$me->config = $config;
		$me->Utils = $Utils;
		$me->Mapping = $Mapping;
	})
	->append_method('get_all_namespaces', function ($me, $target_filter = null) {
		$ret = [];

		foreach ($me->config['namespaces'] as $rrd_dir => $ns_config) {
			if (!is_null($target_filter)) {
				if (!$me->Utils->regex_match_query(
					$target_filter['namespace'],
					$ns_config['mapping']['namespace']
				)) {
					continue;
				}
			}

			$ret[$rrd_dir] = $ns_config;
		}

		return $ret;
	})
	->append_method('search_all_namespaces', function ($me, $target_filter = null) {
		$ret = [
			'results_human' => [],
			'results_raw' => [],
		];

		foreach ($me->get_all_namespaces($target_filter) as $rrd_dir => $ns_config) {
			if (!is_null($target_filter)) {
				if (!$me->Utils->regex_match_query(
					$target_filter['namespace'],
					$ns_config['mapping']['namespace']
				)) {
					continue;
				}
			}

			$r = $me->search_namespace($rrd_dir, $target_filter);

			foreach (array_keys($ret) as $k) {
				$ret[$k] = array_merge($ret[$k], $r[$k]);
			}
		}

		return $ret;
	})
	->append_method('get_all_rrd_files', function ($me, $rrd_dir) {
		$ret = [];

		$rrd_dir_with_sep = $rrd_dir.DIRECTORY_SEPARATOR;
		foreach (glob($rrd_dir_with_sep.'*.rrd') as $rrd_file) {
			if (!is_file($rrd_file)) continue;
			$rrd_file_nodir = substr($rrd_file, strlen($rrd_dir_with_sep));

			$ret[] = [
				'rrd_file_fullpath' => $rrd_file,
				'rrd_file_nodir' => $rrd_file_nodir,
			];
		}

		return $ret;
	})
	->append_method('search_namespace', function ($me, $rrd_dir, $target_filter = null) {
		$res = new stdClass(); // so that we can pass the arrays as reference
		$res->ds = [];
		$res->ds_raw = [];

		foreach ($me->get_all_rrd_files($rrd_dir) as $f) {
			\famzah\PNP\PNPApp::getInstance()
				->new_object('\rrdg\lib\Search\RRDFile',
					$rrd_dir, $f['rrd_file_fullpath'], $f['rrd_file_nodir'],
					$target_filter, $me->Utils, $me->Mapping, $res
				)
				->process_rrd_file();
		}

		return [
			'results_human' => $res->ds,
			'results_raw' => $res->ds_raw,
		];
	})
; // add_class

\famzah\PNP\PNPApp::getInstance()
->add_class('\rrdg\lib\Search\RRDFile')
	->append_method('__construct', function ($me,
		$rrd_dir, $rrd_file, $rrd_file_nodir, $target_filter,
		$Utils, $Mapping, $res
	) {
		$me->rrd_dir = $rrd_dir;
		$me->rrd_file = $rrd_file;
		$me->rrd_file_nodir = $rrd_file_nodir;
		$me->target_filter = $target_filter;

		$me->Utils = $Utils;
		$me->Mapping = $Mapping;

		$me->res = $res;

		$me->cf = [];
		$me->seen = [];
	})
	->append_method('add_result_rrd_file_only', function ($me) {
		$me->res->ds_raw[] = [
			'rrd_dir' => $me->rrd_dir,
			'rrd_file' => $me->rrd_file_nodir,
		];
	})
	->append_method('skip_rrd_file', function ($me) {
		if (is_null($me->target_filter)) {
			return false;
		}

		$rrd_file_value = $me->Mapping->map_plain(
			$me->rrd_dir, 'rrd_file', $me->rrd_file_nodir
		);
		if (!$me->Utils->regex_match_query($me->target_filter['rrd_file'], $rrd_file_value)) {
			return true;
		}

		if (is_null($me->target_filter['metric'])) {
			// used in the "search" API
			// don't read the RRD files, because we're interested only
			// in the RRD filenames
			$me->add_result_rrd_file_only();

			return true;
		}

		return false;
	})
	->append_method('get_rrd_info', function ($me) {
		$rrd_info = rrd_info($me->rrd_file);
		if (!$rrd_info) {
			throw new Exception("rrd_info($rrd_file): failed");
		}

		return $rrd_info;
	})
	->append_method('parse_cf_rrd_info', function ($me, $rrd_info) {
		foreach ($rrd_info as $key => $value) {
			if (preg_match('/^rra\[\d+\]\.cf$/', $key, $m)) {
				$me->cf[$value] = 1;
			}
		}
	})
	->append_method('process_rrd_file', function ($me) {
		if ($me->skip_rrd_file()) return;

		$rrd_info = $me->get_rrd_info();
		$me->parse_cf_rrd_info($rrd_info);
		$me->process_all_metrics($rrd_info);
	})
	->append_method('process_all_metrics', function ($me, $rrd_info) {
		foreach ($rrd_info as $key => $value) {
			if (!preg_match('/^ds\[(\S+)\]\.[a-z_]+$/', $key, $m)) {
				continue;
			}

			$metric = $m[1];
			if (isset($me->seen[$metric])) continue;

			$me->process_each_metric($metric);
		}
	})
	->append_method('add_result_metric_only', function ($me, $metric) {
		$me->res->ds_raw[] = [
			'rrd_dir' => $me->rrd_dir,
			'rrd_file' => $me->rrd_file_nodir,
			'metric' => $metric,
		];
	})
	->append_method('skip_metric', function ($me, $metric) {
		if (is_null($me->target_filter)) {
			return false;
		}

		$_metric_value = $me->Mapping->map_plain(
			$me->rrd_dir, 'metric', $metric
		);
		if (!$me->Utils->regex_match_query($me->target_filter['metric'], $_metric_value)) {
			return true;
		}

		if (is_null($me->target_filter['cfname'])) {
			// used in the "search" API
			// don't loop the CF's, because we're interested only
			// in the metrics
			$me->add_result_metric_only($metric);

			return true;
		}

		return false;
	})
	->append_method('process_each_metric', function ($me, $metric) {
		if ($me->skip_metric($metric)) return;

		$me->process_all_cf_names($metric);

		$me->seen[$metric] = true;
	})
	->append_method('skip_cf_name', function ($me, $cfname) {
		if (!is_null($me->target_filter)) {
			if (!$me->Utils->regex_match_query(
				$me->target_filter['cfname'], $cfname
			)) {
				return true;
			}
		}
		return false;
	})
	->append_method('process_all_cf_names', function ($me, $metric) {
		foreach (array_keys($me->cf) as $cfname) {
			if ($me->skip_cf_name($cfname)) continue;

			$me->add_result_full($metric, $cfname);
		}
	})
	->append_method('add_result_full', function ($me, $metric, $cfname) {
		$me->res->ds[] = [
			// what Grafana will display in the Metrics tab
			'text' => $me->Mapping->get_metric_text_desc(
				$me->rrd_dir, $me->rrd_file_nodir, $metric, $cfname
			),
			// raw value which Grafana will submit when doing queries
			'value' => $me->Mapping->get_metric_search_path(
				$me->rrd_dir, $me->rrd_file_nodir, $metric, $cfname
			)
		];
		$me->res->ds_raw[] = [
			'rrd_dir' => $me->rrd_dir,
			'rrd_file' => $me->rrd_file_nodir,
			'metric' => $metric,
			'cfname' => $cfname,
		];
	})
; // add_class
