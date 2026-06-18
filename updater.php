<?php

/**
 * Plugin updater handler function.
 * Fetches the latest release from GitHub to check for updates.
 */
function na8k_check_for_plugin_update( $transient ) {

	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$plugin_slug = 'nem-alderstjek/nem-alderstjek.php';
	$api_url     = 'https://api.github.com/repos/NemBestil/nemalderstjek-wp-plugin/releases/latest';

	$response = wp_remote_get( $api_url, [
		'headers' => [
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
		],
	] );

	if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
		return $transient;
	}

	$release = json_decode( wp_remote_retrieve_body( $response ) );

	if ( empty( $release->tag_name ) ) {
		return $transient;
	}

	$new_version = ltrim( $release->tag_name, 'v' );

	// Prefer an uploaded zip asset (correct folder name) over the source archive.
	$package = '';
	if ( ! empty( $release->assets ) ) {
		foreach ( $release->assets as $asset ) {
			if ( substr( $asset->name, -4 ) === '.zip' ) {
				$package = $asset->browser_download_url;
				break;
			}
		}
	}
	if ( empty( $package ) ) {
		$package = "https://github.com/NemBestil/nemalderstjek-wp-plugin/archive/refs/tags/{$release->tag_name}.zip";
	}

	if ( ! isset( $transient->checked[ $plugin_slug ] ) || version_compare( $transient->checked[ $plugin_slug ], $new_version, '<' ) ) {
		$transient->response[ $plugin_slug ] = (object) [
			'slug'        => 'nem-alderstjek',
			'plugin'      => $plugin_slug,
			'new_version' => $new_version,
			'url'         => $release->html_url,
			'package'     => $package,
		];
	}

	return $transient;
}
add_filter( 'pre_set_site_transient_update_plugins', 'na8k_check_for_plugin_update' );
