<?php
/**
 * Désinstallation : efface les réglages, la table des résultats et les lectures enregistrées.
 * Les pages créées restent en place (contenu du site).
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
global $wpdb;
delete_option( 'nr_reglages' );
foreach ( (array) glob( __DIR__ . '/activites/activite-*.json' ) as $f ) {
	if ( preg_match( '/activite-([a-z0-9\-]+)\.json$/', $f, $m ) ) {
		delete_option( 'nr_page_' . $m[1] );
	}
}
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'nr_travaux' ); // phpcs:ignore
$d = (string) get_option( 'nr_dossier_voix' );
delete_option( 'nr_dossier_voix' );
if ( $d && is_dir( $d ) && false !== strpos( $d, 'nos-reussites-voix-' ) ) {
	$d = trailingslashit( $d );
	foreach ( (array) glob( $d . '*' ) as $f ) {
		wp_delete_file( $f );
	}
	@unlink( $d . '.htaccess' ); // phpcs:ignore
	@rmdir( $d ); // phpcs:ignore
}
