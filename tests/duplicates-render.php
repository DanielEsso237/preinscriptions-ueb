<?php
/** Rendu isolé pour revue visuelle. Aucun compte, dossier ou option créé. */
if ( PHP_SAPI !== 'cli' ) exit;
require dirname( __DIR__, 4 ) . '/wp-load.php';
global $current_user, $wp_query, $post;
$current_user = new WP_User();
$current_user->ID = 999991;
$current_user->allcaps = array( 'read'=>true, 'manage_options'=>true );
$current_user->data = (object) array( 'ID'=>999991, 'display_name'=>'Revue des admissions', 'user_login'=>'visual-test', 'user_email'=>'visual@example.test' );
$current_user->roles = array();
$pages = get_pages( array( 'meta_key'=>'_wp_page_template', 'meta_value'=>'page-administration.php' ) );
if ( ! $pages ) exit( 'Page administration introuvable.' );
$post = $pages[0];
$_SERVER['SERVER_NAME'] = 'localhost';
$wp_query->queried_object = $post; $wp_query->queried_object_id=$post->ID; $wp_query->post=$post; $wp_query->posts=array($post); $wp_query->is_page=true; $wp_query->is_singular=true;
add_filter( 'get_post_metadata', function( $value,$id,$key ) { return $id===999991 && $key==='_wp_page_template' ? array('page-administration.php') : $value; },10,3 );
ob_start(); require get_template_directory().'/page-administration.php';
file_put_contents('/tmp/ueb-duplicates-preview.html',ob_get_clean());
echo "Rendu visuel : /tmp/ueb-duplicates-preview.html\n";
