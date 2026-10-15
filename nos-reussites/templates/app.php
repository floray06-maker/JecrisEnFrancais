<?php
/**
 * Page élève plein écran : aucun élément du thème (menus, publicités, pied de page).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$nr_act = isset( $GLOBALS['nr_activite_courante'] ) ? $GLOBALS['nr_activite_courante'] : '10';
$nr_a   = nr_activite( $nr_act );
$nr_v   = NR_VERSION . '-' . substr( md5( (string) filemtime( NR_DIR . 'assets/app.js' ) . filemtime( NR_DIR . 'assets/app.css' ) ), 0, 6 );
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#ffffff">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="format-detection" content="telephone=no">
<title><?php echo esc_html( isset( $nr_a['titre'] ) ? $nr_a['titre'] : 'Activité' ); ?></title>
<link rel="icon" href="<?php echo esc_url( NR_URL . 'assets/img/reussi.png' ); ?>">
<link rel="apple-touch-icon" href="<?php echo esc_url( NR_URL . 'assets/img/reussi.png' ); ?>">
<link rel="preload" href="<?php echo esc_url( NR_URL . 'assets/fonts/andika-latin-400-normal.woff2' ); ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?php echo esc_url( NR_URL . 'assets/app.css?ver=' . $nr_v ); ?>">
</head>
<body class="nr-page">
<div id="nr-app"></div>
<noscript><p style="font:20px sans-serif;text-align:center;padding:40px">JavaScript</p></noscript>
<script>window.NR_CFG = <?php echo wp_json_encode( nr_config_js( $nr_act ) ); ?>;</script>
<script src="<?php echo esc_url( NR_URL . 'assets/app.js?ver=' . $nr_v ); ?>"></script>
</body>
</html>
