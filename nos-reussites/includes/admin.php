<?php
/**
 * Administration : prénoms par parcours, résultats, écoute des lectures, export, effacement.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'nr_menu' );
function nr_menu() {
	add_menu_page( 'Nos réussites', 'Nos réussites', 'manage_options', 'nos-reussites', 'nr_page_resultats', 'dashicons-welcome-learn-more', 26 );
	add_submenu_page( 'nos-reussites', 'Résultats', 'Résultats', 'manage_options', 'nos-reussites', 'nr_page_resultats' );
	add_submenu_page( 'nos-reussites', 'Élèves', 'Élèves', 'manage_options', 'nos-reussites-eleves', 'nr_page_eleves' );
}

add_action( 'admin_head', 'nr_admin_css' );
function nr_admin_css() {
	$s = get_current_screen();
	if ( ! $s || false === strpos( $s->id, 'nos-reussites' ) ) {
		return;
	}
	echo '<style>
	.nr-wrap textarea{width:100%;max-width:420px;min-height:260px;font-size:15px}
	.nr-cols{display:flex;gap:30px;flex-wrap:wrap}
	.nr-ok{color:#0f7a36;font-weight:600}.nr-ko{color:#c01818;font-weight:600}.nr-aide{color:#b35c00;font-weight:600}.nr-vide{color:#999}
	.nr-det h3{margin:22px 0 6px;border-bottom:1px solid #ccd0d4;padding-bottom:4px}
	.nr-det table td{vertical-align:top}.nr-face{width:28px;vertical-align:middle}
	.nr-barre{display:inline-block;width:80px;height:8px;background:#e5e5e5;border-radius:4px;vertical-align:middle;margin-right:6px;overflow:hidden}
	.nr-barre i{display:block;height:100%;background:#0f7a36}
	.nr-texte{white-space:pre-wrap;background:#fff;border:1px solid #ddd;padding:8px;max-width:640px}
	</style>';
}

function nr_url_admin( $page, $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
}

function nr_lien_action( $action, $args = array() ) {
	return wp_nonce_url( add_query_arg( array_merge( array( 'action' => $action ), $args ), admin_url( 'admin-post.php' ) ), $action );
}

/* ------------------------------------------------------------------ page Élèves */

function nr_page_eleves() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$r = nr_reglages();
	$doublons = array();
	nr_eleves( $doublons );
	echo '<div class="wrap nr-wrap"><h1>Élèves</h1>';
	if ( isset( $_GET['enregistre'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>Enregistré.</p></div>';
	}
	$illisibles = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $r['p1'] . "\n" . $r['p2'] ) as $ligne ) {
		if ( '' !== trim( $ligne ) && '' === nr_cle( $ligne ) ) {
			$illisibles[] = $ligne;
		}
	}
	if ( $illisibles ) {
		echo '<div class="notice notice-error"><p>Prénoms impossibles à reconnaître : <strong>' . esc_html( implode( ', ', $illisibles ) ) . '</strong>. Les écrire en lettres latines.</p></div>';
	}
	if ( $doublons ) {
		echo '<div class="notice notice-warning"><p>Prénoms en double (seul le premier compte) : <strong>' . esc_html( implode( ', ', array_unique( $doublons ) ) ) . '</strong>. Pour deux élèves du même prénom, ajouter l\'initiale du nom (exemple : « Sara B. »).</p></div>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'nr_reglages' );
	echo '<input type="hidden" name="action" value="nr_reglages">';
	echo '<p>Un prénom par ligne. L\'élève tape son prénom : majuscules et accents ne comptent pas.</p><div class="nr-cols">';
	echo '<div><h2>Parcours 1</h2><p class="description">Lettres, syllabes, mots (apprenants 1 et 5).</p><textarea name="p1">' . esc_textarea( $r['p1'] ) . '</textarea></div>';
	echo '<div><h2>Parcours 2</h2><p class="description">Phrases et textes courts (apprenants 2, 3 et 4).</p><textarea name="p2">' . esc_textarea( $r['p2'] ) . '</textarea></div>';
	echo '</div><h2>Lecture à voix haute</h2><label><input type="checkbox" name="voix" value="1" ' . checked( ! empty( $r['voix'] ), true, false ) . '> Les élèves enregistrent leur lecture dans l\'application. Sinon, ils l\'envoient par message vocal.</label>';
	echo '<p class="description">Les fichiers audio sont rangés dans un dossier protégé et effacés avec les résultats de l\'élève. Informer les familles avant usage (données personnelles d\'élèves mineurs).</p>';
	submit_button( 'Enregistrer' );
	echo '</form><h2>Pages des activités</h2><table class="widefat striped" style="max-width:720px"><tbody>';
	foreach ( nr_activites_disponibles() as $act ) {
		$a  = nr_activite( $act );
		$id = (int) get_option( 'nr_page_' . $act );
		echo '<tr><td><strong>' . esc_html( isset( $a['titre'] ) ? $a['titre'] : $act ) . '</strong></td><td>';
		if ( $id && 'publish' === get_post_status( $id ) ) {
			$u = get_permalink( $id );
			echo '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener">' . esc_html( $u ) . '</a>';
		} else {
			echo '<a class="button" href="' . esc_url( nr_lien_action( 'nr_page', array( 'activite' => $act ) ) ) . '">Créer la page</a>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table><p class="description">Le lien de la page est celui à envoyer aux élèves. Une autre page peut aussi contenir le code court <code>[nos_reussites activite="10"]</code>.</p></div>';
}

add_action( 'admin_post_nr_reglages', 'nr_post_reglages' );
function nr_post_reglages() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Accès refusé.' );
	}
	check_admin_referer( 'nr_reglages' );
	$clean = function ( $t ) {
		$lignes = preg_split( '/\r\n|\r|\n/', sanitize_textarea_field( wp_unslash( (string) $t ) ) );
		return implode( "\n", array_filter( array_map( 'trim', $lignes ), 'strlen' ) );
	};
	update_option(
		NR_OPT,
		array(
			'p1'   => $clean( isset( $_POST['p1'] ) ? $_POST['p1'] : '' ), // phpcs:ignore
			'p2'   => $clean( isset( $_POST['p2'] ) ? $_POST['p2'] : '' ), // phpcs:ignore
			'voix' => empty( $_POST['voix'] ) ? 0 : 1,
		)
	);
	wp_safe_redirect( nr_url_admin( 'nos-reussites-eleves', array( 'enregistre' => 1 ) ) );
	exit;
}

add_action( 'admin_post_nr_page', 'nr_post_page' );
function nr_post_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Accès refusé.' );
	}
	check_admin_referer( 'nr_page' );
	$act = isset( $_GET['activite'] ) ? preg_replace( '/[^a-z0-9\-]/', '', sanitize_key( wp_unslash( $_GET['activite'] ) ) ) : '';
	if ( nr_activite( $act ) ) {
		nr_creer_page( $act );
	}
	wp_safe_redirect( nr_url_admin( 'nos-reussites-eleves' ) );
	exit;
}

/* ------------------------------------------------------------------ page Résultats */

function nr_activite_choisie() {
	$liste = nr_activites_disponibles();
	$act   = isset( $_GET['activite'] ) ? sanitize_key( wp_unslash( $_GET['activite'] ) ) : ''; // phpcs:ignore
	return in_array( $act, $liste, true ) ? $act : ( in_array( '10', $liste, true ) ? '10' : (string) reset( $liste ) );
}

function nr_page_resultats() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$act = nr_activite_choisie();
	$a   = nr_activite( $act );
	if ( isset( $_GET['eleve'] ) ) { // phpcs:ignore
		nr_detail( $act, nr_cle( wp_unslash( $_GET['eleve'] ) ) ); // phpcs:ignore
		return;
	}
	global $wpdb;
	$lignes = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . nr_table() . ' WHERE activite = %s ORDER BY parcours, prenom', $act ), ARRAY_A ); // phpcs:ignore
	$par    = array();
	foreach ( $lignes as $l ) {
		$par[ $l['cle'] ] = $l;
	}
	echo '<div class="wrap nr-wrap"><h1 class="wp-heading-inline">Résultats</h1> ';
	$acts = nr_activites_disponibles();
	if ( count( $acts ) > 1 ) {
		echo '<form method="get" style="display:inline"><input type="hidden" name="page" value="nos-reussites"><select name="activite" onchange="this.form.submit()">';
		foreach ( $acts as $x ) {
			$ax = nr_activite( $x );
			echo '<option value="' . esc_attr( $x ) . '" ' . selected( $x, $act, false ) . '>' . esc_html( $ax['titre'] ) . '</option>';
		}
		echo '</select></form>';
	} else {
		echo '<span class="title-count">' . esc_html( $a['titre'] ) . '</span>';
	}
	echo ' <a class="page-title-action" href="' . esc_url( nr_lien_action( 'nr_csv', array( 'activite' => $act ) ) ) . '">Exporter (CSV)</a><hr class="wp-header-end">';
	if ( isset( $_GET['efface'] ) ) { // phpcs:ignore
		echo '<div class="notice notice-success is-dismissible"><p>Effacé.</p></div>';
	}
	echo '<table class="widefat striped"><thead><tr><th>Prénom</th><th>Parcours</th><th>Avancée</th><th>Réussite</th><th>Fini</th><th>Ressenti</th><th>Voix</th><th>Dernière activité</th><th></th></tr></thead><tbody>';
	$eleves = nr_eleves();
	$vus    = array();
	foreach ( $eleves as $cle => $e ) {
		$vus[ $cle ] = 1;
		nr_ligne_resultat( $act, $a, $cle, $e['prenom'], $e['parcours'], isset( $par[ $cle ] ) ? $par[ $cle ] : null );
	}
	foreach ( $par as $cle => $l ) {
		if ( empty( $vus[ $cle ] ) ) { // élève retiré de la liste mais dont le travail existe encore
			nr_ligne_resultat( $act, $a, $cle, $l['prenom'] . ' (retiré)', $l['parcours'], $l );
		}
	}
	if ( ! $eleves && ! $par ) {
		echo '<tr><td colspan="9">Aucun élève. <a href="' . esc_url( nr_url_admin( 'nos-reussites-eleves' ) ) . '">Ajouter des prénoms</a>.</td></tr>';
	}
	echo '</tbody></table>';
	if ( $par ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:30px" onsubmit="return confirm(\'Effacer tous les résultats et toutes les lectures de cette activité ?\')">';
		wp_nonce_field( 'nr_effacer_tout' );
		echo '<input type="hidden" name="action" value="nr_effacer_tout"><input type="hidden" name="activite" value="' . esc_attr( $act ) . '">';
		submit_button( 'Effacer tous les résultats de cette activité', 'delete small', 'submit', false );
		echo '</form>';
	}
	echo '</div>';
}

function nr_nb_ecrans( $a, $parcours ) {
	return isset( $a['parcours'][ $parcours ]['ecrans'] ) ? count( $a['parcours'][ $parcours ]['ecrans'] ) : 0;
}

function nr_ligne_resultat( $act, $a, $cle, $prenom, $parcours, $l ) {
	$n = nr_nb_ecrans( $a, $parcours );
	echo '<tr><td><strong>' . esc_html( $prenom ) . '</strong></td><td>' . esc_html( $parcours ) . '</td>';
	if ( ! $l || ( '' === (string) $l['etat'] && '' === (string) $l['voix'] ) ) {
		echo '<td colspan="6" class="nr-vide">Pas commencé</td><td>';
		if ( $l ) {
			echo '<a href="' . esc_url( nr_lien_action( 'nr_effacer', array( 'activite' => $act, 'eleve' => $cle ) ) ) . '">Effacer</a>';
		}
		echo '</td></tr>';
		return;
	}
	$pct  = $l['score_total'] ? round( 100 * $l['score_ok'] / $l['score_total'] ) : 0;
	$voix = $l['voix'] ? count( (array) json_decode( $l['voix'], true ) ) : 0;
	echo '<td>' . esc_html( min( (int) $l['ecran'] + 1, $n ) . ' / ' . $n ) . '</td>';
	echo '<td><span class="nr-barre"><i style="width:' . (int) $pct . '%"></i></span>' . esc_html( $l['score_ok'] . ' / ' . $l['score_total'] ) . '</td>';
	echo '<td>' . ( $l['fini'] ? '<span class="nr-ok">oui</span>' : '' ) . '</td>';
	echo '<td>' . ( $l['autoeval'] ? '<img class="nr-face" alt="' . esc_attr( $l['autoeval'] ) . '" src="' . esc_url( NR_URL . 'assets/img/face_' . $l['autoeval'] . '.png' ) . '">' : '' ) . '</td>';
	echo '<td>' . ( $voix ? (int) $voix : '' ) . '</td>';
	echo '<td>' . esc_html( $l['maj'] ? mysql2date( 'd/m/Y H:i', $l['maj'] ) : '' ) . '</td>';
	echo '<td><a class="button button-small" href="' . esc_url( nr_url_admin( 'nos-reussites', array( 'activite' => $act, 'eleve' => $cle ) ) ) . '">Voir</a> ';
	echo '<a href="' . esc_url( nr_lien_action( 'nr_effacer', array( 'activite' => $act, 'eleve' => $cle ) ) ) . '" onclick="return confirm(\'Effacer le travail de cet élève ?\')" style="color:#b32d2e">Effacer</a></td></tr>';
}

/* ------------------------------------------------------------------ détail d'un élève */

function nr_statut( $s, $tente ) {
	if ( ! is_array( $s ) || ! array_key_exists( 'ok', $s ) || ( null === $s['ok'] && ! $tente ) ) {
		return '<span class="nr-vide">non fait</span>';
	}
	if ( ! empty( $s['a'] ) ) {
		return '<span class="nr-aide">réponse montrée</span>';
	}
	if ( true === $s['ok'] ) {
		$e = isset( $s['e'] ) ? (int) $s['e'] : 0;
		if ( ! empty( $s['p'] ) ) {
			return '<span class="nr-aide">presque (accent ou majuscule)</span>';
		}
		return '<span class="nr-ok">réussi' . ( $e > 1 ? ' après ' . $e . ' essais' : '' ) . '</span>';
	}
	return '<span class="nr-ko">à reprendre</span>';
}

function nr_detail( $act, $cle ) {
	$a = nr_activite( $act );
	$l = nr_ligne( $act, $cle );
	echo '<div class="wrap nr-wrap nr-det"><p><a href="' . esc_url( nr_url_admin( 'nos-reussites', array( 'activite' => $act ) ) ) . '">← Résultats</a></p>';
	if ( ! $l ) {
		echo '<p>Aucun travail.</p></div>';
		return;
	}
	$etat  = $l['etat'] ? json_decode( $l['etat'], true ) : array();
	$items = isset( $etat['items'] ) ? $etat['items'] : array();
	$champ = isset( $etat['champs'] ) ? $etat['champs'] : array();
	$oral  = isset( $etat['oral'] ) ? $etat['oral'] : array();
	$voix  = $l['voix'] ? (array) json_decode( $l['voix'], true ) : array();
	$p     = $a['parcours'][ $l['parcours'] ];
	echo '<h1>' . esc_html( $l['prenom'] ) . ' <small>parcours ' . esc_html( $l['parcours'] ) . ', ' . esc_html( $p['titre'] ) . '</small></h1>';
	echo '<p>Réussite : <strong>' . esc_html( $l['score_ok'] . ' / ' . $l['score_total'] ) . '</strong>. Début : ' . esc_html( $l['debut'] ? mysql2date( 'd/m/Y H:i', $l['debut'] ) : '' ) . '. Dernière activité : ' . esc_html( $l['maj'] ? mysql2date( 'd/m/Y H:i', $l['maj'] ) : '' ) . '.</p>';
	foreach ( $p['ecrans'] as $e ) {
		if ( 'fin' === $e['type'] ) {
			echo '<h3>Fin</h3><p>' . ( $l['fini'] ? 'Terminé.' : 'Pas terminé.' ) . ( $l['autoeval'] ? ' Ressenti : <img class="nr-face" alt="" src="' . esc_url( NR_URL . 'assets/img/face_' . $l['autoeval'] . '.png' ) . '">' : '' ) . '</p>';
			continue;
		}
		echo '<h3>' . esc_html( $e['num'] . '. ' . $e['consigne'] ) . '</h3>';
		if ( in_array( $e['type'], array( 'choix', 'multi', 'saisie' ), true ) ) {
			echo '<table class="widefat striped" style="max-width:820px"><tbody>';
			foreach ( $e['items'] as $k => $it ) {
				if ( ! empty( $it['exemple'] ) || isset( $it['bande'] ) ) {
					continue;
				}
				$s       = isset( $items[ $e['id'] . ':' . $k ] ) && is_array( $items[ $e['id'] . ':' . $k ] ) ? $items[ $e['id'] . ':' . $k ] : null;
				$sv      = $s && isset( $s['v'] ) ? $s['v'] : null;
				$invite  = isset( $it['modele'] ) ? $it['modele'] : ( isset( $it['texte'] ) ? $it['texte'] : trim( ( isset( $it['gauche'] ) ? $it['gauche'] . ' ' : '' ) . ( isset( $it['prefixe'] ) ? $it['prefixe'] : '' ) ) );
				$donne   = '';
				$attendu = '';
				if ( 'choix' === $e['type'] ) {
					$donne   = ( is_int( $sv ) && isset( $it['options'][ $sv ] ) ) ? $it['options'][ $sv ] : '';
					$attendu = $it['options'][ $it['bonne'] ];
				} elseif ( 'multi' === $e['type'] ) {
					$attendu = implode( ', ', array_map( function ( $i ) { return ( $i + 1 ) . 'e'; }, $it['bonnes'] ) );
					$donne   = is_array( $sv ) ? implode( ', ', array_map( function ( $i ) { return ( (int) $i + 1 ) . 'e'; }, $sv ) ) : '';
				} else {
					$donne   = is_string( $sv ) ? $sv : '';
					$attendu = $it['reponses'][0];
				}
				$essais = $s && isset( $s['e'] ) ? (int) $s['e'] : 0;
				echo '<tr><td style="width:40%">' . esc_html( $invite ) . '</td><td><strong>' . esc_html( $donne ) . '</strong></td><td class="nr-vide">' . esc_html( $attendu ) . '</td><td>' . nr_statut( $s, '' !== $donne || $essais > 0 ) . '</td></tr>'; // phpcs:ignore
			}
			echo '</tbody></table>';
		} elseif ( 'carte' === $e['type'] ) {
			echo '<p>';
			foreach ( $e['champs'] as $c ) {
				$v = isset( $champ[ $e['id'] . ':' . $c['cle'] ] ) ? $champ[ $e['id'] . ':' . $c['cle'] ] : '';
				echo esc_html( $c['label'] ) . ' : <strong>' . esc_html( $v ) . '</strong><br>';
			}
			echo '</p>';
		} elseif ( 'redaction' === $e['type'] ) {
			$v = isset( $champ[ $e['id'] . ':texte' ] ) ? $champ[ $e['id'] . ':texte' ] : '';
			echo $v ? '<div class="nr-texte">' . esc_html( $v ) . '</div>' : '<p class="nr-vide">non fait</p>';
		} elseif ( 'oral' === $e['type'] ) {
			foreach ( array( 'a' => 'Lecture', 'b' => 'EN PLUS' ) as $part => $lab ) {
				$k = $e['id'] . ':' . $part;
				if ( 'b' === $part && empty( $e['enplus'] ) && empty( $e['enplus_oral'] ) ) {
					continue;
				}
				echo '<p><strong>' . esc_html( $lab ) . '</strong> : ';
				if ( ! empty( $voix[ $k ] ) ) {
					echo '<audio controls preload="none" src="' . esc_url( nr_lien_action( 'nr_voix', array( 'f' => $voix[ $k ] ) ) ) . '"></audio>';
				} elseif ( ! empty( $oral[ $k ] ) ) {
					echo 'fait (message vocal envoyé à part)';
				} else {
					echo '<span class="nr-vide">non fait</span>';
				}
				echo '</p>';
			}
		}
	}
	echo '<p style="margin-top:30px"><a href="' . esc_url( nr_lien_action( 'nr_effacer', array( 'activite' => $act, 'eleve' => $cle ) ) ) . '" onclick="return confirm(\'Effacer le travail de cet élève ?\')" style="color:#b32d2e">Effacer le travail de cet élève</a> (il pourra recommencer).</p></div>';
}

/* ------------------------------------------------------------------ actions */

function nr_supprimer_voix( $json ) {
	foreach ( (array) json_decode( (string) $json, true ) as $f ) {
		if ( is_string( $f ) && preg_match( '/^[a-z0-9\-]+\.(webm|ogg|m4a)$/', $f ) && file_exists( nr_dossier_voix() . $f ) ) {
			wp_delete_file( nr_dossier_voix() . $f );
		}
	}
}

add_action( 'admin_post_nr_effacer', 'nr_post_effacer' );
function nr_post_effacer() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Accès refusé.' );
	}
	check_admin_referer( 'nr_effacer' );
	$act = isset( $_GET['activite'] ) ? sanitize_key( wp_unslash( $_GET['activite'] ) ) : '';
	$cle = isset( $_GET['eleve'] ) ? nr_cle( wp_unslash( $_GET['eleve'] ) ) : ''; // phpcs:ignore
	$l   = nr_ligne( $act, $cle );
	if ( $l ) {
		global $wpdb;
		nr_supprimer_voix( $l['voix'] );
		$wpdb->delete( nr_table(), array( 'id' => (int) $l['id'] ) );
	}
	wp_safe_redirect( nr_url_admin( 'nos-reussites', array( 'activite' => $act, 'efface' => 1 ) ) );
	exit;
}

add_action( 'admin_post_nr_effacer_tout', 'nr_post_effacer_tout' );
function nr_post_effacer_tout() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Accès refusé.' );
	}
	check_admin_referer( 'nr_effacer_tout' );
	$act = isset( $_POST['activite'] ) ? sanitize_key( wp_unslash( $_POST['activite'] ) ) : '';
	global $wpdb;
	foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT voix FROM ' . nr_table() . ' WHERE activite = %s', $act ) ) as $v ) { // phpcs:ignore
		nr_supprimer_voix( $v );
	}
	$wpdb->delete( nr_table(), array( 'activite' => $act ) );
	wp_safe_redirect( nr_url_admin( 'nos-reussites', array( 'activite' => $act, 'efface' => 1 ) ) );
	exit;
}

add_action( 'admin_post_nr_voix', 'nr_post_voix' );
function nr_post_voix() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Accès refusé.' );
	}
	check_admin_referer( 'nr_voix' );
	$f = isset( $_GET['f'] ) ? sanitize_file_name( wp_unslash( $_GET['f'] ) ) : '';
	if ( ! preg_match( '/^[a-z0-9\-]+\.(webm|ogg|m4a)$/', $f, $m ) || ! file_exists( nr_dossier_voix() . $f ) ) {
		status_header( 404 );
		exit;
	}
	$types = array( 'webm' => 'audio/webm', 'ogg' => 'audio/ogg', 'm4a' => 'audio/mp4' );
	nocache_headers();
	header( 'Content-Type: ' . $types[ $m[1] ] );
	header( 'Content-Length: ' . filesize( nr_dossier_voix() . $f ) );
	header( 'X-Content-Type-Options: nosniff' );
	readfile( nr_dossier_voix() . $f ); // phpcs:ignore
	exit;
}

add_action( 'admin_post_nr_csv', 'nr_post_csv' );
function nr_post_csv() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Accès refusé.' );
	}
	check_admin_referer( 'nr_csv' );
	$act = isset( $_GET['activite'] ) ? sanitize_key( wp_unslash( $_GET['activite'] ) ) : '10';
	$a   = nr_activite( $act );
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . nr_table() . ' WHERE activite = %s ORDER BY parcours, prenom', $act ), ARRAY_A ); // phpcs:ignore
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="activite-' . $act . '-resultats.csv"' );
	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" );
	fputcsv( $out, array( 'Prénom', 'Parcours', 'Écran atteint', 'Écrans', 'Réussis', 'Items', 'Réussite (%)', 'Fini', 'Ressenti', 'Début', 'Dernière activité' ), ';' );
	$ress = array( 'ok' => 'je sais faire', 'moy' => 'un peu', 'pas' => 'pas encore' );
	foreach ( $rows as $l ) {
		fputcsv(
			$out,
			array(
				$l['prenom'], $l['parcours'], (int) $l['ecran'] + 1, nr_nb_ecrans( $a, $l['parcours'] ), $l['score_ok'], $l['score_total'],
				$l['score_total'] ? round( 100 * $l['score_ok'] / $l['score_total'] ) : 0, $l['fini'] ? 'oui' : 'non',
				isset( $ress[ $l['autoeval'] ] ) ? $ress[ $l['autoeval'] ] : '', $l['debut'], $l['maj'],
			),
			';'
		);
	}
	fclose( $out );
	exit;
}

add_filter( 'plugin_action_links_' . plugin_basename( NR_DIR . 'nos-reussites.php' ), 'nr_liens_extension' );
function nr_liens_extension( $liens ) {
	array_unshift( $liens, '<a href="' . esc_url( nr_url_admin( 'nos-reussites-eleves' ) ) . '">Élèves</a>' );
	return $liens;
}
