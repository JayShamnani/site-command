<?php

namespace EE\Site\Type;

use function EE\Utils\mustache_render;
use function EE\Site\Utils\get_ssl_policy;
use function EE\Site\Utils\sysctl_parameters;

class Site_HTML_Docker {

	/**
	 * Generate docker-compose.yml according to requirement.
	 *
	 * @param array $filters Array to determine the docker-compose.yml generation.
	 ** @param array $volumes Array containing volume info passable to \EE_DOCKER::get_mounting_volume_array().
	 *
	 * @return String docker-compose.yml content string.
	 */
	public function generate_docker_compose_yml( array $filters = [], $volumes ) {
		$img_versions = \EE\Utils\get_image_versions();
		$base         = [];

		$restart_default = [ 'name' => 'always' ];

		// nginx configuration.
		$nginx['service_name'] = [ 'name' => 'nginx' ];
		$nginx['image']        = [ 'name' => 'easyengine/nginx:' . $img_versions['easyengine/nginx'] ];
		$nginx['restart']      = $restart_default;

		$v_host = 'VIRTUAL_HOST=${VIRTUAL_HOST}';

		if ( ! empty( $filters['alias_domains'] ) ) {
			$v_host .= ',' . $filters['alias_domains'];
		}

		$nginx['environment'] = [
			'env' => [
				[ 'name' => $v_host ],
				[ 'name' => 'VIRTUAL_PATH=/' ],
				[ 'name' => 'HSTS=off' ],
			],
		];

		if ( ! empty( $filters['alias_domains'] ) ) {
			$nginx['environment']['env'][] = [ 'name' => 'CERT_NAME=${VIRTUAL_HOST}' ];
		}

		$ssl_policy = get_ssl_policy();
		if ( ! empty( $filters['nohttps'] ) && $filters['nohttps'] ) {
			$nginx['environment']['env'][] = [ 'name' => 'HTTPS_METHOD=nohttps' ];
		} elseif ( 'Mozilla-Modern' !== $ssl_policy ) {
			$nginx['environment']['env'][] = [ 'name' => "SSL_POLICY=$ssl_policy" ];
		}

		$nginx['volumes']  = [
			'vol' => \EE_DOCKER::get_mounting_volume_array( $volumes ),
		];
		$nginx['labels']   = [
			'label' => [
				'name' => 'io.easyengine.site=${VIRTUAL_HOST}',
			],
		];
		$nginx['sysctls']  = sysctl_parameters();
		$nginx['networks'] = [
			'net' => [
				[ 'name' => 'global-frontend-network' ],
				[ 'name' => 'site-network' ],
			],
		];

		$external_volumes = [
			'external_vols' => [
				[ 'prefix' => $filters['site_prefix'], 'ext_vol_name' => 'htdocs' ],
				[ 'prefix' => $filters['site_prefix'], 'ext_vol_name' => 'config_nginx' ],
				[ 'prefix' => $filters['site_prefix'], 'ext_vol_name' => 'log_nginx' ],
			],
		];

		$base[] = $nginx;

		$binding = [
			'services' => $base,
			'network'  => [
				'networks_labels' => [
					'label' => [
						[ 'name' => 'org.label-schema.vendor=EasyEngine' ],
						[ 'name' => 'io.easyengine.site=${VIRTUAL_HOST}' ],
					],
				],
				'subnet_ip' => $filters['subnet_ip'],
			]
		];

		if ( ! IS_DARWIN ) {
			$binding['created_volumes'] = $external_volumes;
		}

		$docker_compose_yml = mustache_render( SITE_TEMPLATE_ROOT . '/docker-compose.mustache', $binding );

		return $docker_compose_yml;
	}
}
