<?php
/**
 * URLs list page.
 *
 * @package LEAStudios\SiteAudit
 *
 * @var array<int, \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url> $urls
 * @var array<int, \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project> $projects_by_id
 * @var array<int, array{desktop?: int, mobile?: int}> $latest_scores
 * @var int $total
 * @var int $page
 * @var int $total_pages
 * @var int $per_page
 * @var string $search
 * @var string $list_url
 * @var string $create_url
 * @var string $bulk_import_url
 * @var string $edit_base_url
 * @var string $delete_url
 * @var string $delete_action
 * @var string $run_audit_url
 * @var string $run_audit_action
 */

defined( 'ABSPATH' ) || exit;

\LEAStudios\SiteAudit\Admin\Notice_Service::render();
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'URLs', 'leastudios-siteaudit' ); ?></h1>
	<a href="<?php echo esc_url( $create_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add URL', 'leastudios-siteaudit' ); ?></a>
	<a href="<?php echo esc_url( $bulk_import_url ); ?>" class="page-title-action"><?php esc_html_e( 'Bulk Import', 'leastudios-siteaudit' ); ?></a>
	<hr class="wp-header-end" />

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="<?php echo esc_attr( \LEAStudios\SiteAudit\Modules\Url\Admin\Url_Controller::PAGE_SLUG ); ?>" />
		<p class="search-box">
			<label class="screen-reader-text" for="leastudios-siteaudit-url-search"><?php esc_html_e( 'Search URLs', 'leastudios-siteaudit' ); ?></label>
			<input type="search" id="leastudios-siteaudit-url-search" name="s" value="<?php echo esc_attr( $search ); ?>" />
			<?php submit_button( __( 'Search URLs', 'leastudios-siteaudit' ), '', '', false ); ?>
		</p>
	</form>

	<?php if ( empty( $urls ) ) : ?>
		<p><?php esc_html_e( 'No URLs match your filters.', 'leastudios-siteaudit' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'URL', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Project', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Frequency', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Strategy', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Desktop', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Mobile', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Last Audited', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'leastudios-siteaudit' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $urls as $url_model ) : ?>
					<?php
					$url_id        = (int) $url_model->id();
					$edit_url      = add_query_arg(
						[
							'action' => 'edit',
							'id'     => $url_id,
						],
						$edit_base_url
					);
					$project_id    = $url_model->project_id();
					$project_label = '&mdash;';
					if ( null !== $project_id && isset( $projects_by_id[ $project_id ] ) ) {
						$project_label = esc_html( $projects_by_id[ $project_id ]->name()->value() );
					}
					$last_audited  = $url_model->last_audited_at();
					$row_scores    = $latest_scores[ $url_id ] ?? [];
					$desktop_score = $row_scores['desktop'] ?? null;
					$mobile_score  = $row_scores['mobile'] ?? null;
					?>
					<tr>
						<td>
							<strong>
								<a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( (string) ( $url_model->name() ?? '' ) ); ?></a>
							</strong>
						</td>
						<td>
							<a href="<?php echo esc_url( $url_model->url()->value() ); ?>" target="_blank" rel="noreferrer noopener">
								<?php echo esc_html( $url_model->url()->value() ); ?>
							</a>
						</td>
						<td>
							<?php
							// Project label is pre-escaped where set; default literal is safe HTML.
							echo wp_kses_post( $project_label );
							?>
						</td>
						<td><?php echo esc_html( $url_model->audit_frequency()->label() ); ?></td>
						<td><?php echo esc_html( $url_model->audit_strategy()->label() ); ?></td>
						<td>
							<?php if ( $url_model->is_enabled() ) : ?>
								<span style="color:#1e7e34;font-weight:600;"><?php esc_html_e( 'Enabled', 'leastudios-siteaudit' ); ?></span>
							<?php else : ?>
								<span style="color:#6c757d;"><?php esc_html_e( 'Disabled', 'leastudios-siteaudit' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php echo null === $desktop_score ? '&mdash;' : esc_html( (string) $desktop_score ); ?>
						</td>
						<td>
							<?php echo null === $mobile_score ? '&mdash;' : esc_html( (string) $mobile_score ); ?>
						</td>
						<td>
							<?php
							if ( null !== $last_audited ) {
								echo esc_html( \LEAStudios\SiteAudit\Shared\Datetime_Util::format_for_display( $last_audited, get_option( 'date_format', 'Y-m-d' ) ) );
							} else {
								echo '&mdash;';
							}
							?>
						</td>
						<td>
							<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'leastudios-siteaudit' ); ?></a>
							<?php if ( current_user_can( \LEAStudios\SiteAudit\Capabilities::MANAGE ) ) : ?>
								| <form method="post" action="<?php echo esc_url( $run_audit_url ); ?>" style="display:inline">
									<?php wp_nonce_field( $run_audit_action ); ?>
									<input type="hidden" name="action" value="<?php echo esc_attr( $run_audit_action ); ?>" />
									<input type="hidden" name="id" value="<?php echo esc_attr( (string) $url_id ); ?>" />
									<button type="submit" class="button-link"><?php esc_html_e( 'Run audit now', 'leastudios-siteaudit' ); ?></button>
								</form>
								| <form method="post" action="<?php echo esc_url( $delete_url ); ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this URL? Audit history will be removed too.', 'leastudios-siteaudit' ) ); ?>');">
									<?php wp_nonce_field( $delete_action ); ?>
									<input type="hidden" name="action" value="<?php echo esc_attr( $delete_action ); ?>" />
									<input type="hidden" name="id" value="<?php echo esc_attr( (string) $url_id ); ?>" />
									<button type="submit" class="button-link delete"><?php esc_html_e( 'Delete', 'leastudios-siteaudit' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<span class="displaying-num">
						<?php
						printf(
							/* translators: %s: number of URLs. */
							esc_html( _n( '%s item', '%s items', $total, 'leastudios-siteaudit' ) ),
							esc_html( number_format_i18n( $total ) )
						);
						?>
					</span>
					<?php
					$base_url = $list_url;
					if ( '' !== $search ) {
						$base_url = add_query_arg( 's', $search, $base_url );
					}

					$pagination_links = paginate_links(
						[
							'base'      => add_query_arg( 'paged', '%#%', $base_url ),
							'format'    => '',
							'total'     => $total_pages,
							'current'   => $page,
							'type'      => 'plain',
							'prev_text' => __( '&laquo;', 'leastudios-siteaudit' ),
							'next_text' => __( '&raquo;', 'leastudios-siteaudit' ),
						]
					);

					if ( is_string( $pagination_links ) && '' !== $pagination_links ) {
						echo wp_kses_post( $pagination_links );
					}
					?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
