<?php

namespace HellasWiki\Admin;

use HellasWiki\TypeRegistry;
use HellasWiki\Types\AbstractType;

/**
 * Renders the creation wizard and other admin panels.
 */
class WizardPage {
/**
 * Hook setup.
 */
public static function init(): void {
add_action( 'admin_post_hellaswiki_wizard_create', [ self::class, 'handle_create' ] );
}

/**
 * Render the wizard main page.
 */
public static function render_page(): void {
self::render_wrapper( 'wizard', function () {
$types = TypeRegistry::get_post_type_slugs();
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hellaswiki-wizard">
<?php wp_nonce_field( 'hellaswiki_wizard_create', '_hw_nonce' ); ?>
<input type="hidden" name="action" value="hellaswiki_wizard_create" />

<p><?php esc_html_e( 'Select the entry type you would like to create.', 'hellas-wiki' ); ?></p>
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

<p>
<label><?php esc_html_e( 'Entry Title', 'hellas-wiki' ); ?></label>
<input type="text" name="post_title" required />
</p>

<p>
<label><?php esc_html_e( 'Pokédex Number / Identifier', 'hellas-wiki' ); ?></label>
<input type="text" name="identifier" />
</p>

<p class="submit">
<button type="submit" class="button button-primary"><?php esc_html_e( 'Create Draft', 'hellas-wiki' ); ?></button>
</p>
</form>
<?php
} );
}

/**
 * Render import queue page.
 */
public static function render_queue_page(): void {
self::render_wrapper( 'queue', function () {
$queue   = get_option( 'hellaswiki_import_queue', [] );
$counter = get_option( 'hellaswiki_import_counter', 0 );
?>
<div class="hellaswiki-queue">
<h2><?php esc_html_e( 'Import Queue', 'hellas-wiki' ); ?></h2>
<p><?php printf( esc_html__( 'There are %d items queued for import.', 'hellas-wiki' ), intval( $counter ) ); ?></p>
<table class="wp-list-table widefat">
<thead>
<tr>
<th><?php esc_html_e( 'Path', 'hellas-wiki' ); ?></th>
<th><?php esc_html_e( 'Detected Type', 'hellas-wiki' ); ?></th>
<th><?php esc_html_e( 'Actions', 'hellas-wiki' ); ?></th>
</tr>
</thead>
<tbody>
<?php if ( empty( $queue ) ) : ?>
<tr>
<td colspan="3"><?php esc_html_e( 'No queued entries. Trigger a GitHub sync to see new content.', 'hellas-wiki' ); ?></td>
</tr>
<?php else : ?>
<?php foreach ( $queue as $key => $item ) : ?>
<tr>
<td><?php echo esc_html( $item['path'] ?? $key ); ?></td>
<td><?php echo esc_html( $item['post_type'] ?? '' ); ?></td>
<td>
<button class="button button-primary" data-action="process" data-key="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Create', 'hellas-wiki' ); ?></button>
<button class="button" data-action="dismiss" data-key="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Dismiss', 'hellas-wiki' ); ?></button>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
<?php
}, 'import_wiki_pages' );
}

/**
 * Render settings page.
 */
public static function render_settings_page(): void {
self::render_wrapper( 'settings', function () {
$settings = get_option( 'hellaswiki_settings', [] );
?>
<form method="post" action="options.php" class="hellaswiki-settings">
<?php settings_fields( 'hellaswiki_settings' ); ?>
<table class="form-table">
<tr>
<th scope="row"><?php esc_html_e( 'GitHub Repository', 'hellas-wiki' ); ?></th>
<td><input type="text" name="hellaswiki_settings[github_repo]" value="<?php echo esc_attr( $settings['github_repo'] ?? '' ); ?>" placeholder="owner/repo" /></td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Personal Access Token', 'hellas-wiki' ); ?></th>
<td><input type="password" name="hellaswiki_settings[github_token]" value="<?php echo esc_attr( $settings['github_token'] ?? '' ); ?>" /></td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Webhook Secret', 'hellas-wiki' ); ?></th>
<td><input type="password" name="hellaswiki_settings[webhook_secret]" value="<?php echo esc_attr( $settings['webhook_secret'] ?? '' ); ?>" /></td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Enable Poller', 'hellas-wiki' ); ?></th>
<td><label><input type="checkbox" name="hellaswiki_settings[enable_poller]" value="1" <?php checked( ! empty( $settings['enable_poller'] ) ); ?> /> <?php esc_html_e( 'Run cron-based syncing every 10 minutes.', 'hellas-wiki' ); ?></label></td>
</tr>
</table>

<?php submit_button(); ?>
</form>
<?php
}, 'manage_options' );
}

/**
 * Handles wizard submission.
 */
public static function handle_create(): void {
if ( ! current_user_can( 'edit_wiki_pages' ) ) {
wp_die( esc_html__( 'You do not have permission to perform this action.', 'hellas-wiki' ) );
}

check_admin_referer( 'hellaswiki_wizard_create', '_hw_nonce' );

$post_type = sanitize_key( $_POST['post_type'] ?? '' );
$title     = sanitize_text_field( $_POST['post_title'] ?? '' );
$identifier = sanitize_text_field( $_POST['identifier'] ?? '' );

/** @var AbstractType|null $type */
$type = TypeRegistry::get( $post_type );

if ( ! $type ) {
wp_die( esc_html__( 'Unknown post type.', 'hellas-wiki' ) );
}

$post_id = wp_insert_post(
[
'post_type'   => $post_type,
'post_status' => 'draft',
'post_title'  => $title,
]
);

if ( is_wp_error( $post_id ) ) {
wp_die( $post_id );
}

$type->prefill_meta( $post_id, $identifier );

wp_safe_redirect( get_edit_post_link( $post_id, '' ) );
exit;
}

/**
 * Render wrapper container.
 *
 * @param string   $context Context string.
 * @param callable $callback Render callback.
 */
protected static function render_wrapper( string $context, callable $callback, string $capability = 'edit_wiki_pages' ): void {
if ( ! current_user_can( $capability ) ) {
wp_die( esc_html__( 'Insufficient permissions.', 'hellas-wiki' ) );
}

echo '<div class="wrap hellaswiki-admin hellaswiki-' . esc_attr( $context ) . '">';
echo '<h1>' . esc_html__( 'Hellas Wiki', 'hellas-wiki' ) . '</h1>';

call_user_func( $callback );

echo '</div>';
}
}
