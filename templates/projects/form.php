<?php
/**
 * Projects create/edit form.
 *
 * @package LEAStudios\SiteAudit
 *
 * @var \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project|null $project
 * @var string $create_url
 * @var string $list_url
 * @var string $create_action
 * @var string $update_action
 */

defined( 'ABSPATH' ) || exit;

$is_edit     = $project instanceof \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project;
$form_action = $is_edit ? $update_action : $create_action;
$form_title  = $is_edit
	? __( 'Edit Project', 'leastudios-siteaudit' )
	: __( 'Add Project', 'leastudios-siteaudit' );

$name_value        = $is_edit ? $project->name()->value() : '';
$description_value = $is_edit ? (string) ( $project->description() ?? '' ) : '';

\LEAStudios\SiteAudit\Admin\Notice_Service::render();
?>
<div class="wrap">
	<h1><?php echo esc_html( $form_title ); ?></h1>

	<form method="post" action="<?php echo esc_url( $create_url ); ?>">
		<?php wp_nonce_field( $form_action ); ?>
		<input type="hidden" name="action" value="<?php echo esc_attr( $form_action ); ?>" />
		<?php if ( $is_edit ) : ?>
			<input type="hidden" name="id" value="<?php echo esc_attr( (string) (int) $project->id() ); ?>" />
		<?php endif; ?>

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">
						<label for="leastudios-siteaudit-project-name"><?php esc_html_e( 'Name', 'leastudios-siteaudit' ); ?></label>
					</th>
					<td>
						<input
							type="text"
							id="leastudios-siteaudit-project-name"
							name="name"
							value="<?php echo esc_attr( $name_value ); ?>"
							class="regular-text"
							required
							maxlength="255"
						/>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="leastudios-siteaudit-project-description"><?php esc_html_e( 'Description', 'leastudios-siteaudit' ); ?></label>
					</th>
					<td>
						<textarea
							id="leastudios-siteaudit-project-description"
							name="description"
							rows="4"
							cols="50"
							class="large-text"
						><?php echo esc_textarea( $description_value ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Optional. Used for your own reference in lists and reports.', 'leastudios-siteaudit' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<?php submit_button( $is_edit ? __( 'Save Changes', 'leastudios-siteaudit' ) : __( 'Create Project', 'leastudios-siteaudit' ), 'primary', 'submit', false ); ?>
			<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( 'Cancel', 'leastudios-siteaudit' ); ?></a>
		</p>
	</form>
</div>
