<?php
/**
 * Projects list page.
 *
 * @package LEAStudios\SiteAudit
 *
 * @var array<int, \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project> $projects
 * @var string $list_url
 * @var string $create_url
 * @var string $edit_base_url
 * @var string $delete_url
 * @var string $delete_action
 */

defined( 'ABSPATH' ) || exit;

\LEAStudios\SiteAudit\Admin\Notice_Service::render();
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Projects', 'leastudios-siteaudit' ); ?></h1>
	<a href="<?php echo esc_url( $create_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add Project', 'leastudios-siteaudit' ); ?></a>
	<hr class="wp-header-end" />

	<?php if ( empty( $projects ) ) : ?>
		<p><?php esc_html_e( 'No projects yet. Add your first project to start grouping URLs.', 'leastudios-siteaudit' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Description', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Created', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'leastudios-siteaudit' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $projects as $project ) : ?>
					<?php
					$project_id  = (int) $project->id();
					$edit_url    = add_query_arg(
						[
							'action' => 'edit',
							'id'     => $project_id,
						],
						$edit_base_url
					);
					$description = $project->description();
					?>
					<tr>
						<td>
							<strong>
								<a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $project->name()->value() ); ?></a>
							</strong>
						</td>
						<td>
							<?php
							if ( null !== $description && '' !== $description ) {
								echo esc_html( wp_trim_words( $description, 30, '…' ) );
							} else {
								echo '&mdash;';
							}
							?>
						</td>
						<td><?php echo esc_html( \LEAStudios\SiteAudit\Shared\Datetime_Util::format_immutable_for_display( $project->created_at(), get_option( 'date_format', 'Y-m-d' ) ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'leastudios-siteaudit' ); ?></a>
							<?php if ( current_user_can( \LEAStudios\SiteAudit\Capabilities::MANAGE ) ) : ?>
								| <form method="post" action="<?php echo esc_url( $delete_url ); ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this project? URLs in it will become unassigned.', 'leastudios-siteaudit' ) ); ?>');">
									<?php wp_nonce_field( $delete_action ); ?>
									<input type="hidden" name="action" value="<?php echo esc_attr( $delete_action ); ?>" />
									<input type="hidden" name="id" value="<?php echo esc_attr( (string) $project_id ); ?>" />
									<button type="submit" class="button-link delete"><?php esc_html_e( 'Delete', 'leastudios-siteaudit' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
