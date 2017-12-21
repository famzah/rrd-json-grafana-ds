<?php
\famzah\PNP\PNPApp::getInstance()
->add_class('\rrdg\lib\Mapping')
	->append_method('__construct', function ($me, $config) {
		$me->config = $config;
	})
	->append_method('map_plain', function ($me, $rrd_dir, $type, $value) {
		$mapping = $me->config['namespaces'][$rrd_dir]['mapping'];

		if ($type == 'namespace') {
			if (isset($mapping[$type])) {
				return $mapping[$type];
			} else {
				return $rrd_dir;
			}
		} else {
			if (isset($mapping[$type][$value])) {
				return $mapping[$type][$value];
			} else {
				return $value;
			}
		}
	})
	->append_method('get_metric_text_desc', function ($me, $rrd_dir, $rrd_file, $metric, $cfname) {
		$cfdesc = '';
		if ($cfname != 'AVERAGE') {
			$cfdesc = sprintf(' (%s)', strtolower($cfname));
		}

		return sprintf('%s: %s%s',
			$me->map_plain($rrd_dir, 'rrd_file', $rrd_file),
			$me->map_plain($rrd_dir, 'metric', $metric),
			$cfdesc
		);
	})
	->append_method('get_metric_search_path', function ($me, $rrd_dir, $rrd_file, $metric, $cfname) {
		return sprintf('[%s]->[%s]->[%s]->[%s]',
			preg_quote($me->map_plain($rrd_dir, 'namespace', null), '/'),
			preg_quote($me->map_plain($rrd_dir, 'rrd_file', $rrd_file), '/'),
			preg_quote($me->map_plain($rrd_dir, 'metric', $metric), '/'),
			preg_quote($cfname, '/')
		);
	})
; // add_class
