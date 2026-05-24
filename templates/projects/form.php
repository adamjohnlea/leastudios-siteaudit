<?php
/**
 * Projects create/edit form.
 *
 * @package LEAStudios\SiteAudit
 *
 * @var \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project|null $leastudios_siteaudit_project
 * @var string $leastudios_siteaudit_create_url
 * @var string $leastudios_siteaudit_list_url
 * @var string $leastudios_siteaudit_create_action
 * @var string $leastudios_siteaudit_update_action
 */

defined( 'ABSPATH' ) || exit;

$leastudios_siteaudit_is_edit     = $leastudios_siteaudit_project instanceof \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project;
$leastudios_siteaudit_form_action = $leastudios_siteaudit_is_edit ? $leastudios_siteaudit_update_action : $leastudios_siteaudit_create_action;
$leastudios_siteaudit_form_title  = $leastudios_siteaudit_is_edit
	? __( 'Edit Project', 'leastudios-siteaudit' )
	: __( 'Add Project', 'leastudios-siteaudit' );

$leastudios_siteaudit_name_value        = $leastudios_siteaudit_is_edit ? $leastudios_siteaudit_project->name()->value() : '';
$leastudios_siteaudit_description_value = $leastudios_siteaudit_is_edit ? (string) ( $leastudios_siteaudit_project->description() ?? '' ) : '';

\LEAStudios\SiteAudit\Admin\Notice_Service::render();
?>
<div class="wrap">
	<h1><?php echo esc_html( $leastudios_siteaudit_form_title ); ?></h1>

	<form method="post" action="<?php echo esc_url( $leastudios_siteaudit_create_url ); ?>">
		<?php wp_nonce_field( $leastudios_siteaudit_form_action ); ?>
		<input type="hidden" name="action" value="<?php echo esc_attr( $leastudios_siteaudit_form_action ); ?>" />
		<?php if ( $leastudios_siteaudit_is_edit ) : ?>
			<input type="hidden" name="id" value="<?php echo esc_attr( (string) (int) $leastudios_siteaudit_project->id() ); ?>" />
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
							value="<?php echo esc_attr( $leastudios_siteaudit_name_value ); ?>"
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
						><?php echo esc_textarea( $leastudios_siteaudit_description_value ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Optional. Used for your own reference in lists and reports.', 'leastudios-siteaudit' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<?php submit_button( $leastudios_siteaudit_is_edit ? __( 'Save Changes', 'leastudios-siteaudit' ) : __( 'Create Project', 'leastudios-siteaudit' ), 'primary', 'submit', false ); ?>
			<a href="<?php echo esc_url( $leastudios_siteaudit_list_url ); ?>" class="button"><?php esc_html_e( 'Cancel', 'leastudios-siteaudit' ); ?></a>
		</p>
	</form>
</div>
