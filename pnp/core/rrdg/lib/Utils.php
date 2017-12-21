<?php
\famzah\PNP\PNPApp::getInstance()
->add_class('\rrdg\lib\Utils')
	->append_method('config_assert_unique', function ($me, $data, $desc) {
		$non_unique = array_diff_assoc($data, array_unique($data));
		if (count($non_unique)) {
			throw new Exception(
				"Error in \"config.php\". All descriptions must be unique. ".
				"A list with the non-unique items for \"$desc\" follows: ".
				join(", ", $non_unique)
			);
		}
	})
	->append_method('x_check_config', function ($me, $config) {
		$all_ns = [];
		foreach ($config['namespaces'] as $rrd_dir => $ns) {
			foreach (array_keys($ns['mapping']) as $type) {
				if ($type == 'namespace') {
					$all_ns[] = $ns['mapping'][$type];
					continue;
				}
				$me->config_assert_unique(
					$ns['mapping'][$type], "mapping -> $type"
				);
			}
		}
		$me->config_assert_unique($all_ns, "namespace names");
	})
	->append_method('get_raw_http_post_query', function ($me) {
		return file_get_contents('php://input');
	})
	->append_method('get_json_http_post_query', function ($me) {
		$raw_post = $me->get_raw_http_post_query();

		$request = json_decode($raw_post, true);
		if (!$request) {
			throw new Exception("Unable to parse the POST request: $raw_post");
		}

		return $request;
	})
	->append_method('send_http_error_and_abort', function ($me, $header_message, $error_message) {
		header($_SERVER["SERVER_PROTOCOL"]." $header_message");
		echo json_encode($error_message);

		trigger_error($error_message, E_USER_ERROR);
		exit(1);
	})
	->append_method('regex_match_query', function ($me, $query_pattern, $subject) {
		return preg_match(
			sprintf('/^%s$/u', $query_pattern),
			$subject
		);
	})
	->append_method('parse_target_query', function ($me, $config, $target_query) {
		if (!preg_match(
			'/^\s*\[(.+?)\]\s*(?:->\s*\[(.+?)\]\s*)?(?:->\s*\[(.+?)\]\s*)?(?:->\s*\[(.+?)\]\s*)?$/u',
			$target_query, $m
		)) {
			$me->send_http_error_and_abort(
				"500 Bad Request", // HTTP 400 code is not handled well by Grafana
				"Unable to parse the request as grammar: $target_query"
			);
		}
		//file_put_contents('php://stderr', print_r($m, 1));

		$mcount = count($m) - 1;

		for ($i = 1; $i < 5; ++$i) {
			if ($i < count($m)) {
				$m[$i] = trim($m[$i]);
			} else {
				$m[$i] = null;
			}
		}
		$res = [
			'namespace' => $m[1],
			'rrd_file' => $m[2],
			'metric' => $m[3],
			'cfname' => $m[4],
			'_count' => $mcount,
		];

		//file_put_contents('php://stderr', print_r($res, 1));

		return $res;
	})
; // add_class
