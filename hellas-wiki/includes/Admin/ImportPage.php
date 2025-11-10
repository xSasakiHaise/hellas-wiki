<?php

namespace HellasWiki\Admin;

use HellasWiki\REST\ImportController;
use HellasWiki\TypeRegistry;

/**
 * Renders the importer interface.
 */
class ImportPage {
/**
 * Hook setup.
 */
public static function init(): void {
add_action( 'admin_post_hellaswiki_import_json', [ self::class, 'handle_import' ] );
}

/**
 * Render importer page.
 */
public static function render_page(): void {
if ( ! current_user_can( 'import_wiki_pages' ) ) {
wp_die( esc_html__( 'Insufficient permissions.', 'hellas-wiki' ) );
}

$types = TypeRegistry::get_post_type_slugs();
?>
<div class="wrap hellaswiki-admin hellaswiki-import">
<h1><?php esc_html_e( 'Import JSON', 'hellas-wiki' ); ?></h1>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
<?php wp_nonce_field( 'hellaswiki_import_json', '_hw_import_nonce' ); ?>
<input type="hidden" name="action" value="hellaswiki_import_json" />

<p>
<label><?php esc_html_e( 'Raw GitHub JSON URL', 'hellas-wiki' ); ?></label>
<input type="url" name="json_url" class="large-text" placeholder="https://raw.githubusercontent.com/..." />
</p>

<p><strong><?php esc_html_e( 'OR', 'hellas-wiki' ); ?></strong></p>

<p>
<label><?php esc_html_e( 'Upload JSON File', 'hellas-wiki' ); ?></label>
<input type="file" name="json_file" accept="application/json" />
</p>

<p>
<label><?php esc_html_e( 'Entry Type', 'hellas-wiki' ); ?></label>
<select name="post_type" required>
<option value="">--</option>
<?php foreach ( $types as $type ) :
$object = TypeRegistry::get( $type );
if ( ! $object ) {
continue;
}
?>
<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $object->get_label() ); ?></option>
<?php endforeach; ?>
</select>
</p>

<?php submit_button( __( 'Process Import', 'hellas-wiki' ) ); ?>
</form>
</div>
<?php
}

/**
 * Handle import request.
 */
public static function handle_import(): void {
if ( ! current_user_can( 'import_wiki_pages' ) ) {
wp_die( esc_html__( 'You do not have permission to import data.', 'hellas-wiki' ) );
}

check_admin_referer( 'hellaswiki_import_json', '_hw_import_nonce' );

$post_type = sanitize_key( $_POST['post_type'] ?? '' );
$json_url  = esc_url_raw( $_POST['json_url'] ?? '' );

$data = null;

if ( ! empty( $json_url ) ) {
$response = wp_remote_get( $json_url );
if ( ! is_wp_error( $response ) ) {
$data = wp_remote_retrieve_body( $response );
}
}

if ( empty( $data ) && ! empty( $_FILES['json_file']['tmp_name'] ?? '' ) ) {
$data = file_get_contents( $_FILES['json_file']['tmp_name'] );
}

if ( empty( $data ) ) {
wp_die( esc_html__( 'No JSON payload provided.', 'hellas-wiki' ) );
}

$payload = json_decode( $data, true );

if ( ! is_array( $payload ) ) {
wp_die( esc_html__( 'Invalid JSON payload.', 'hellas-wiki' ) );
}

$controller = new ImportController();
$result     = $controller->import_payload( $payload, $post_type );

if ( is_wp_error( $result ) ) {
wp_die( $result );
}

wp_safe_redirect( admin_url( 'edit.php?post_type=' . $post_type ) );
exit;
}
}
