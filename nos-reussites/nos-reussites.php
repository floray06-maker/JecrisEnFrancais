<?php
/**
 * Plugin Name:       Nos réussites. Activités UPE2A
 * Description:       Activités en autonomie sur téléphone pour les élèves allophones (UPE2A NSA). L'élève entre son prénom ; le prénom choisit le parcours. Réponses et lectures enregistrées pour l'enseignant.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            M. Kongolo
 * License:           GPL-2.0-or-later
 * Text Domain:       nos-reussites
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NR_VERSION', '1.0.0' );
define( 'NR_DIR', plugin_dir_path( __FILE__ ) );
define( 'NR_URL', plugin_dir_url( __FILE__ ) );
define( 'NR_OPT', 'nr_reglages' );
define( 'NR_NS', 'nosreussites/v1' );

/* ------------------------------------------------------------------ outils */

function nr_table() {
	global $wpdb;
	return $wpdb->prefix . 'nr_travaux';
}

function nr_reglages() {
	$r = get_option( NR_OPT, array() );
	return wp_parse_args( is_array( $r ) ? $r : array(), array( 'p1' => '', 'p2' => '', 'voix' => 1 ) );
}

/** Clé d'un prénom : sans accents, minuscules, espaces simples. « Ahmad », « AHMAD » et « ahmad » donnent la même clé. */
function nr_cle( $prenom ) {
	$p = str_replace( array( "\u{2019}", "\u{2018}", '`' ), "'", wp_strip_all_tags( (string) $prenom ) );
	$p = remove_accents( $p );
	$p = function_exists( 'mb_strtolower' ) ? mb_strtolower( $p, 'UTF-8' ) : strtolower( $p );
	$p = preg_replace( '/[^\p{L}\p{N} \'\-]/u', ' ', $p );
	$p = preg_replace( '/\s+/', ' ', $p );
	return trim( $p );
}

/** Liste des élèves : clé => array( prenom, parcours ). Un prénom présent dans les deux listes reste dans le parcours 1. */
function nr_eleves( &$doublons = null ) {
	$r       = nr_reglages();
	$eleves  = array();
	$doublons = array();
	foreach ( array( '1' => $r['p1'], '2' => $r['p2'] ) as $parcours => $liste ) {
		foreach ( preg_split( '/\r\n|\r|\n|,/', (string) $liste ) as $ligne ) {
			$prenom = trim( wp_strip_all_tags( $ligne ) );
			$cle    = nr_cle( $prenom );
			if ( '' === $cle ) {
				continue;
			}
			if ( isset( $eleves[ $cle ] ) ) {
				$doublons[] = $prenom;
				continue;
			}
			$eleves[ $cle ] = array( 'prenom' => $prenom, 'parcours' => (string) $parcours );
		}
	}
	return $eleves;
}

function nr_activites_disponibles() {
	$out = array();
	foreach ( (array) glob( NR_DIR . 'activites/activite-*.json' ) as $f ) {
		if ( preg_match( '/activite-([a-z0-9\-]+)\.json$/', $f, $m ) ) {
			$out[] = $m[1];
		}
	}
	return $out;
}

function nr_activite( $id ) {
	static $cache = array();
	$id = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) $id ) );
	if ( '' === $id ) {
		return null;
	}
	if ( ! array_key_exists( $id, $cache ) ) {
		$f            = NR_DIR . 'activites/activite-' . $id . '.json';
		$cache[ $id ] = file_exists( $f ) ? json_decode( (string) file_get_contents( $f ), true ) : null;
	}
	return $cache[ $id ];
}

function nr_jeton( $cle, $activite ) {
	return substr( hash_hmac( 'sha256', $cle . '|' . $activite, wp_salt( 'nonce' ) ), 0, 40 );
}

function nr_dossier_voix() {
	$d = get_option( 'nr_dossier_voix' );
	if ( $d && is_dir( $d ) ) {
		return trailingslashit( $d );
	}
	$hors = trailingslashit( dirname( untrailingslashit( ABSPATH ) ) ) . 'nos-reussites-voix-' . wp_generate_password( 16, false ) . '/';
	if ( is_writable( dirname( untrailingslashit( ABSPATH ) ) ) && wp_mkdir_p( $hors ) ) {
		$d = $hors; // hors de la racine du site : inaccessible depuis le web, même sous Nginx
	} else {
		$u = wp_upload_dir();
		$d = trailingslashit( $u['basedir'] ) . 'nos-reussites-voix-' . strtolower( wp_generate_password( 24, false ) ) . '/';
		wp_mkdir_p( $d );
	}
	update_option( 'nr_dossier_voix', $d, false );
	return $d;
}

function nr_proteger_dossier_voix() {
	$d = nr_dossier_voix();
	if ( ! file_exists( $d . 'index.php' ) ) {
		file_put_contents( $d . 'index.php', "<?php\n// Silence.\n" );
	}
	if ( ! file_exists( $d . '.htaccess' ) ) {
		file_put_contents( $d . '.htaccess', "Require all denied\nDeny from all\n" );
	}
}

/* ------------------------------------------------------------------ activation */

register_activation_hook( __FILE__, 'nr_activation' );
function nr_activation() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	$sql     = 'CREATE TABLE ' . nr_table() . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		activite varchar(20) NOT NULL,
		cle varchar(100) NOT NULL,
		prenom varchar(100) NOT NULL,
		parcours varchar(10) NOT NULL,
		etat longtext NULL,
		voix longtext NULL,
		ecran smallint(5) unsigned NOT NULL DEFAULT 0,
		fini tinyint(1) NOT NULL DEFAULT 0,
		autoeval varchar(10) NOT NULL DEFAULT '',
		score_ok smallint(5) unsigned NOT NULL DEFAULT 0,
		score_total smallint(5) unsigned NOT NULL DEFAULT 0,
		debut datetime NULL,
		maj datetime NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY activite_cle (activite, cle)
	) $charset;";
	dbDelta( $sql );
	if ( false === get_option( NR_OPT ) ) {
		add_option( NR_OPT, array( 'p1' => '', 'p2' => '', 'voix' => 1 ) );
	}
	nr_proteger_dossier_voix();
	nr_creer_page( '10' );
}

/** Crée la page de l'activité si elle n'existe pas encore. */
function nr_creer_page( $activite ) {
	$opt = 'nr_page_' . $activite;
	$id  = (int) get_option( $opt );
	if ( $id && get_post_status( $id ) && 'trash' !== get_post_status( $id ) ) {
		return $id;
	}
	$id = wp_insert_post(
		array(
			'post_title'   => 'Activité ' . $activite,
			'post_name'    => 'activite-' . $activite,
			'post_content' => '[nos_reussites activite="' . $activite . '"]',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'comment_status' => 'closed',
		)
	);
	if ( $id && ! is_wp_error( $id ) ) {
		update_option( $opt, (int) $id );
		return (int) $id;
	}
	return 0;
}

/* ------------------------------------------------------------------ page élève (plein écran, sans le thème) */

function nr_activite_de_la_page() {
	if ( ! is_singular() ) {
		return '';
	}
	$post = get_post();
	if ( ! $post || ! has_shortcode( $post->post_content, 'nos_reussites' ) ) {
		return '';
	}
	if ( preg_match( '/\[nos_reussites[^\]]*activite=["\']?([a-z0-9\-]+)/i', $post->post_content, $m ) ) {
		return $m[1];
	}
	return '10';
}

add_filter( 'template_include', 'nr_template', 99 );
function nr_template( $template ) {
	$act = nr_activite_de_la_page();
	if ( $act && nr_activite( $act ) ) {
		$GLOBALS['nr_activite_courante'] = $act;
		nocache_headers();
		return NR_DIR . 'templates/app.php';
	}
	return $template;
}

function nr_config_js( $act ) {
	return array(
		'rest'     => esc_url_raw( rest_url( NR_NS . '/' ) ),
		'activite' => $act,
		'img'      => NR_URL . 'assets/img/',
	);
}

/* Repli : si un constructeur de pages ignore le gabarit, le code court affiche l'application en plein écran. */
add_shortcode( 'nos_reussites', 'nr_shortcode' );
function nr_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'activite' => '10' ), $atts, 'nos_reussites' );
	$act  = preg_replace( '/[^a-z0-9\-]/', '', strtolower( $atts['activite'] ) );
	if ( ! nr_activite( $act ) ) {
		return '';
	}
	wp_enqueue_style( 'nr-app', NR_URL . 'assets/app.css', array(), NR_VERSION );
	wp_enqueue_script( 'nr-app', NR_URL . 'assets/app.js', array(), NR_VERSION, true );
	wp_add_inline_script( 'nr-app', 'window.NR_CFG = ' . wp_json_encode( nr_config_js( $act ) ) . ';', 'before' );
	return '<div id="nr-app" class="nr-plein"></div>';
}

/* ------------------------------------------------------------------ API REST */

add_action( 'rest_api_init', 'nr_routes' );
function nr_routes() {
	foreach ( array( 'login', 'save', 'audio' ) as $r ) {
		register_rest_route(
			NR_NS,
			'/' . $r,
			array(
				'methods'             => 'POST',
				'callback'            => 'nr_rest_' . $r,
				'permission_callback' => '__return_true',
			)
		);
	}
}

function nr_reponse( $data, $status = 200 ) {
	$r = new WP_REST_Response( $data, $status );
	$r->header( 'Cache-Control', 'no-store, max-age=0' );
	return $r;
}

/** Limite les prénoms inconnus : 30 erreurs par adresse et par quart d'heure (une classe partage souvent la même adresse). */
function nr_cle_limite() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return 'nr_rl_' . md5( $ip );
}
function nr_limite() {
	return (int) get_transient( nr_cle_limite() ) < 30;
}
function nr_echec() {
	$k = nr_cle_limite();
	set_transient( $k, (int) get_transient( $k ) + 1, 15 * MINUTE_IN_SECONDS );
}

function nr_ligne( $activite, $cle ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . nr_table() . ' WHERE activite = %s AND cle = %s', $activite, $cle ), ARRAY_A ); // phpcs:ignore
}

function nr_rest_login( WP_REST_Request $req ) {
	$act = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) $req->get_param( 'activite' ) ) );
	$a   = nr_activite( $act );
	$cle = nr_cle( $req->get_param( 'prenom' ) );
	$el  = nr_eleves();
	if ( ! $a || '' === $cle || ! isset( $el[ $cle ] ) || empty( $a['parcours'][ $el[ $cle ]['parcours'] ] ) ) {
		if ( ! nr_limite() ) {
			return nr_reponse( array( 'ok' => false, 'raison' => 'limite' ), 429 );
		}
		nr_echec();
		return nr_reponse( array( 'ok' => false ) );
	}
	$e = $el[ $cle ];
	$p = $a['parcours'][ $e['parcours'] ];
	global $wpdb;
	$ligne = nr_ligne( $act, $cle );
	$now   = current_time( 'mysql' );
	if ( ! $ligne ) {
		$wpdb->insert(
			nr_table(),
			array( 'activite' => $act, 'cle' => $cle, 'prenom' => $e['prenom'], 'parcours' => $e['parcours'], 'etat' => '', 'voix' => '', 'debut' => $now, 'maj' => $now )
		);
		$etat  = null;
		$ligne = nr_ligne( $act, $cle );
	} else {
		if ( $ligne['parcours'] !== $e['parcours'] || $ligne['prenom'] !== $e['prenom'] ) {
			$wpdb->update( nr_table(), array( 'parcours' => $e['parcours'], 'prenom' => $e['prenom'] ), array( 'id' => $ligne['id'] ) );
		}
		$etat = $ligne['etat'] ? json_decode( $ligne['etat'], true ) : null;
		if ( $ligne['parcours'] !== $e['parcours'] ) {
			$etat = null; // changement de parcours par l'enseignant : on repart de zéro
		}
	}
	$r = nr_reglages();
	return nr_reponse(
		array(
			'ok'       => true,
			'prenom'   => $e['prenom'],
			'cle'      => $cle,
			'jeton'    => nr_jeton( $cle, $act ),
			'parcours' => $e['parcours'],
			'voix'     => ! empty( $r['voix'] ),
			'contenu'  => array(
				'titre'  => $p['titre'],
				'duree'  => isset( $a['duree'] ) ? $a['duree'] : '',
				'ecrans' => $p['ecrans'],
			),
			'etat'     => $etat,
			'gen'      => $ligne ? $ligne['id'] . '-' . $e['parcours'] : '',
		)
	);
}

/** Vérifie l'élève et son jeton ; renvoie array( activite, cle, élève, activité ) ou null. */
function nr_verifier( WP_REST_Request $req ) {
	$act = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) $req->get_param( 'activite' ) ) );
	$cle = nr_cle( $req->get_param( 'cle' ) );
	$jet = (string) $req->get_param( 'jeton' );
	$a   = nr_activite( $act );
	$el  = nr_eleves();
	if ( ! $a || '' === $cle || ! isset( $el[ $cle ] ) || ! hash_equals( nr_jeton( $cle, $act ), $jet ) ) {
		return null;
	}
	return array( $act, $cle, $el[ $cle ], $a );
}

/** Score : items réussis sans aide / items à corriger (exemples exclus). */
function nr_score( $ecrans, $etat ) {
	$ok    = 0;
	$total = 0;
	$items = isset( $etat['items'] ) && is_array( $etat['items'] ) ? $etat['items'] : array();
	foreach ( $ecrans as $e ) {
		if ( ! in_array( $e['type'], array( 'choix', 'multi', 'saisie' ), true ) ) {
			continue;
		}
		foreach ( $e['items'] as $k => $it ) {
			if ( ! empty( $it['exemple'] ) || isset( $it['bande'] ) ) {
				continue;
			}
			$total++;
			$s = isset( $items[ $e['id'] . ':' . $k ] ) ? $items[ $e['id'] . ':' . $k ] : null;
			if ( $s && true === ( isset( $s['ok'] ) ? $s['ok'] : null ) && empty( $s['a'] ) ) {
				$ok++;
			}
		}
	}
	return array( $ok, $total );
}

/** Ne garde de l'état envoyé que ce que le contenu de l'activité prévoit, avec des types sûrs. */
function nr_nettoyer_etat( $etat, $ecrans ) {
	$propre = array( 'items' => array(), 'champs' => array(), 'oral' => array() );
	$items  = isset( $etat['items'] ) && is_array( $etat['items'] ) ? $etat['items'] : array();
	$champs = isset( $etat['champs'] ) && is_array( $etat['champs'] ) ? $etat['champs'] : array();
	$oral   = isset( $etat['oral'] ) && is_array( $etat['oral'] ) ? $etat['oral'] : array();
	foreach ( $ecrans as $e ) {
		if ( in_array( $e['type'], array( 'choix', 'multi', 'saisie' ), true ) ) {
			foreach ( $e['items'] as $k => $it ) {
				$c = $e['id'] . ':' . $k;
				if ( empty( $items[ $c ] ) || ! is_array( $items[ $c ] ) ) {
					continue;
				}
				$s = $items[ $c ];
				$v = isset( $s['v'] ) ? $s['v'] : null;
				if ( 'choix' === $e['type'] ) {
					$v = is_numeric( $v ) ? (int) $v : null;
				} elseif ( 'multi' === $e['type'] ) {
					$v = is_array( $v ) ? array_values( array_map( 'intval', array_filter( $v, 'is_numeric' ) ) ) : array();
				} else {
					$v = is_scalar( $v ) ? mb_substr( (string) $v, 0, 300 ) : '';
				}
				$propre['items'][ $c ] = array(
					'v'  => $v,
					'ok' => isset( $s['ok'] ) && is_bool( $s['ok'] ) ? $s['ok'] : null,
					'e'  => isset( $s['e'] ) ? max( 0, min( 999, (int) $s['e'] ) ) : 0,
					'a'  => ! empty( $s['a'] ),
					'p'  => ! empty( $s['p'] ),
				);
			}
		}
		if ( 'carte' === $e['type'] ) {
			foreach ( $e['champs'] as $ch ) {
				$c = $e['id'] . ':' . $ch['cle'];
				if ( isset( $champs[ $c ] ) && is_scalar( $champs[ $c ] ) ) {
					$propre['champs'][ $c ] = mb_substr( (string) $champs[ $c ], 0, 100 );
				}
			}
		}
		if ( 'redaction' === $e['type'] && isset( $champs[ $e['id'] . ':texte' ] ) && is_scalar( $champs[ $e['id'] . ':texte' ] ) ) {
			$propre['champs'][ $e['id'] . ':texte' ] = mb_substr( (string) $champs[ $e['id'] . ':texte' ], 0, 4000 );
		}
		if ( 'oral' === $e['type'] ) {
			foreach ( array( 'a', 'b' ) as $part ) {
				if ( ! empty( $oral[ $e['id'] . ':' . $part ] ) ) {
					$propre['oral'][ $e['id'] . ':' . $part ] = 1;
				}
			}
		}
	}
	$propre['ecran']    = isset( $etat['ecran'] ) ? max( 0, min( 999, (int) $etat['ecran'] ) ) : 0;
	$propre['fini']     = empty( $etat['fini'] ) ? 0 : 1;
	$propre['autoeval'] = isset( $etat['autoeval'] ) && in_array( $etat['autoeval'], array( 'ok', 'moy', 'pas' ), true ) ? $etat['autoeval'] : '';
	$propre['maj']      = isset( $etat['maj'] ) && is_numeric( $etat['maj'] ) ? (int) $etat['maj'] : 0;
	$propre['gen']      = isset( $etat['gen'] ) && is_scalar( $etat['gen'] ) ? substr( (string) $etat['gen'], 0, 40 ) : '';
	return $propre;
}

/** Parties d'enregistrement prévues par le contenu (lecture, puis EN PLUS). */
function nr_parties_orales( $ecrans ) {
	$p = array();
	foreach ( $ecrans as $e ) {
		if ( 'oral' === $e['type'] ) {
			$p[] = $e['id'] . ':a';
			if ( ! empty( $e['enplus'] ) || ! empty( $e['enplus_oral'] ) ) {
				$p[] = $e['id'] . ':b';
			}
		}
	}
	return $p;
}

function nr_rest_save( WP_REST_Request $req ) {
	$v = nr_verifier( $req );
	if ( ! $v ) {
		return nr_reponse( array( 'ok' => false ), 403 );
	}
	list( $act, $cle, $eleve, $a ) = $v;
	$etat = $req->get_param( 'etat' );
	if ( ! is_array( $etat ) ) {
		return nr_reponse( array( 'ok' => false ), 400 );
	}
	$ecrans = $a['parcours'][ $eleve['parcours'] ]['ecrans'];
	$etat   = nr_nettoyer_etat( $etat, $ecrans );
	$ancien = nr_ligne( $act, $cle );
	if ( $ancien && $ancien['etat'] ) {
		$prec = json_decode( $ancien['etat'], true );
		if ( isset( $prec['maj'] ) && $etat['maj'] && $etat['maj'] < (int) $prec['maj'] ) {
			return nr_reponse( array( 'ok' => true, 'ancien' => true ) ); // sauvegarde arrivée en retard : on garde la plus récente
		}
	}
	$json = wp_json_encode( $etat );
	if ( strlen( $json ) > 300000 ) {
		return nr_reponse( array( 'ok' => false ), 413 );
	}
	list( $ok, $total ) = nr_score( $ecrans, $etat );
	global $wpdb;
	if ( ! nr_ligne( $act, $cle ) ) {
		$wpdb->insert( nr_table(), array( 'activite' => $act, 'cle' => $cle, 'prenom' => $eleve['prenom'], 'parcours' => $eleve['parcours'], 'debut' => current_time( 'mysql' ) ) );
	}
	$auto = $etat['autoeval'];
	$wpdb->update(
		nr_table(),
		array(
			'etat'        => $json,
			'ecran'       => isset( $etat['ecran'] ) ? max( 0, min( 999, (int) $etat['ecran'] ) ) : 0,
			'fini'        => empty( $etat['fini'] ) ? 0 : 1,
			'autoeval'    => $auto,
			'score_ok'    => $ok,
			'score_total' => $total,
			'maj'         => current_time( 'mysql' ),
		),
		array( 'activite' => $act, 'cle' => $cle )
	);
	return nr_reponse( array( 'ok' => true ) );
}

function nr_rest_audio( WP_REST_Request $req ) {
	$v = nr_verifier( $req );
	$r = nr_reglages();
	if ( ! $v || empty( $r['voix'] ) ) {
		return nr_reponse( array( 'ok' => false ), 403 );
	}
	list( $act, $cle, $eleve, $a ) = $v;
	$partie = preg_replace( '/[^a-z0-9_:]/', '', strtolower( (string) $req->get_param( 'partie' ) ) );
	if ( ! in_array( $partie, nr_parties_orales( $a['parcours'][ $eleve['parcours'] ]['ecrans'] ), true ) ) {
		return nr_reponse( array( 'ok' => false ), 400 );
	}
	$files = $req->get_file_params();
	if ( empty( $files['fichier'] ) ) {
		return nr_reponse( array( 'ok' => false, 'raison' => 'taille' ), 413 ); // fichier refusé par PHP avant l'extension (post_max_size)
	}
	$f = $files['fichier'];
	if ( ! empty( $f['error'] ) ) {
		return nr_reponse( array( 'ok' => false, 'raison' => 'televersement-' . (int) $f['error'] ), in_array( (int) $f['error'], array( 1, 2 ), true ) ? 413 : 400 );
	}
	if ( empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
		return nr_reponse( array( 'ok' => false ), 400 );
	}
	if ( $f['size'] > 8 * MB_IN_BYTES || $f['size'] < 500 ) {
		return nr_reponse( array( 'ok' => false ), 413 );
	}
	$type = function_exists( 'mime_content_type' ) ? (string) mime_content_type( $f['tmp_name'] ) : '';
	$tete = (string) file_get_contents( $f['tmp_name'], false, null, 0, 12 );
	if ( "\x1A\x45\xDF\xA3" === substr( $tete, 0, 4 ) ) {
		$type = 'audio/webm';
	} elseif ( 'OggS' === substr( $tete, 0, 4 ) ) {
		$type = 'audio/ogg';
	} elseif ( 'ftyp' === substr( $tete, 4, 4 ) ) {
		$type = 'audio/mp4';
	} else {
		$type = 'inconnu'; // seuls les trois formats audio des téléphones sont acceptés, d'après leurs premiers octets
	}
	$ext  = array(
		'audio/webm' => 'webm', 'video/webm' => 'webm', 'audio/ogg' => 'ogg', 'application/ogg' => 'ogg',
		'audio/mp4'  => 'm4a', 'video/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/aac' => 'm4a',
	);
	if ( ! isset( $ext[ $type ] ) ) {
		return nr_reponse( array( 'ok' => false, 'type' => $type ), 415 );
	}
	nr_proteger_dossier_voix();
	$nom = substr( md5( $act . $cle ), 0, 8 ) . '-' . str_replace( ':', '-', $partie ) . '-' . bin2hex( random_bytes( 12 ) ) . '.' . $ext[ $type ];
	if ( ! move_uploaded_file( $f['tmp_name'], nr_dossier_voix() . $nom ) ) {
		return nr_reponse( array( 'ok' => false ), 500 );
	}
	global $wpdb;
	$ligne = nr_ligne( $act, $cle );
	$voix  = $ligne && $ligne['voix'] ? json_decode( $ligne['voix'], true ) : array();
	if ( ! is_array( $voix ) ) {
		$voix = array();
	}
	if ( ! empty( $voix[ $partie ] ) && preg_match( '/^[a-z0-9\-]+\.(webm|ogg|m4a)$/', $voix[ $partie ] ) ) {
		wp_delete_file( nr_dossier_voix() . $voix[ $partie ] ); // l'élève a recommencé : on garde la dernière lecture
	}
	$voix[ $partie ] = $nom;
	$wpdb->update( nr_table(), array( 'voix' => wp_json_encode( $voix ), 'maj' => current_time( 'mysql' ) ), array( 'activite' => $act, 'cle' => $cle ) );
	return nr_reponse( array( 'ok' => true ) );
}

/* ------------------------------------------------------------------ administration */

if ( is_admin() ) {
	require_once NR_DIR . 'includes/admin.php';
}
