<?php
/**
 * URLs list page.
 *
 * @package LEAStudios\SiteAudit
 *
 * @var array<int, \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url> $leastudios_siteaudit_urls
 * @var array<int, \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project> $leastudios_siteaudit_projects_by_id
 * @var array<int, array{desktop?: int, mobile?: int}> $leastudios_siteaudit_latest_scores
 * @var int $leastudios_siteaudit_total
 * @var int $leastudios_siteaudit_page
 * @var int $leastudios_siteaudit_total_pages
 * @var int $leastudios_siteaudit_per_page
 * @var string $leastudios_siteaudit_search
 * @var string $leastudios_siteaudit_list_url
 * @var string $leastudios_siteaudit_create_url
 * @var string $leastudios_siteaudit_bulk_import_url
 * @var string $leastudios_siteaudit_edit_base_url
 * @var string $leastudios_siteaudit_delete_url
 * @var string $leastudios_siteaudit_delete_action
 * @var string $leastudios_siteaudit_run_audit_url
 * @var string $leastudios_siteaudit_run_audit_action
 */

defined( 'ABSPATH' ) || exit;

\LEAStudios\SiteAudit\Admin\Notice_Service::render();
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'URLs', 'leastudios-siteaudit' ); ?></h1>
	<a href="<?php echo esc_url( $leastudios_siteaudit_create_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add URL', 'leastudios-siteaudit' ); ?></a>
	<a href="<?php echo esc_url( $leastudios_siteaudit_bulk_import_url ); ?>" class="page-title-action"><?php esc_html_e( 'Bulk Import', 'leastudios-siteaudit' ); ?></a>
	<hr class="wp-header-end" />

	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="<?php echo esc_attr( \LEAStudios\SiteAudit\Modules\Url\Admin\Url_Controller::PAGE_SLUG ); ?>" />
		<p class="search-box">
			<label class="screen-reader-text" for="leastudios-siteaudit-url-search"><?php esc_html_e( 'Search URLs', 'leastudios-siteaudit' ); ?></label>
			<input type="search" id="leastudios-siteaudit-url-search" name="s" value="<?php echo esc_attr( $leastudios_siteaudit_search ); ?>" />
			<?php submit_button( __( 'Search URLs', 'leastudios-siteaudit' ), '', '', false ); ?>
		</p>
	</form>

	<?php if ( empty( $leastudios_siteaudit_urls ) ) : ?>
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
				<?php foreach ( $leastudios_siteaudit_urls as $leastudios_siteaudit_url_model ) : ?>
					<?php
					$leastudios_siteaudit_url_id        = (int) $leastudios_siteaudit_url_model->id();
					$leastudios_siteaudit_edit_url      = add_query_arg(
						[
							'action' => 'edit',
							'id'     => $leastudios_siteaudit_url_id,
						],
						$leastudios_siteaudit_edit_base_url
					);
					$leastudios_siteaudit_project_id    = $leastudios_siteaudit_url_model->project_id();
					$leastudios_siteaudit_project_label = '&mdash;';
					if ( null !== $leastudios_siteaudit_project_id && isset( $leastudios_siteaudit_projects_by_id[ $leastudios_siteaudit_project_id ] ) ) {
						$leastudios_siteaudit_project_label = esc_html( $leastudios_siteaudit_projects_by_id[ $leastudios_siteaudit_project_id ]->name()->value() );
					}
					$leastudios_siteaudit_last_audited  = $leastudios_siteaudit_url_model->last_audited_at();
					$leastudios_siteaudit_row_scores    = $leastudios_siteaudit_latest_scores[ $leastudios_siteaudit_url_id ] ?? [];
					$leastudios_siteaudit_desktop_score = $leastudios_siteaudit_row_scores['desktop'] ?? null;
					$leastudios_siteaudit_mobile_score  = $leastudios_siteaudit_row_scores['mobile'] ?? null;
					?>
					<tr>
						<td>
							<strong>
								<a href="<?php echo esc_url( $leastudios_siteaudit_edit_url ); ?>"><?php echo esc_html( (string) ( $leastudios_siteaudit_url_model->name() ?? '' ) ); ?></a>
							</strong>
						</td>
						<td>
							<a href="<?php echo esc_url( $leastudios_siteaudit_url_model->url()->value() ); ?>" target="_blank" rel="noreferrer noopener">
								<?php echo esc_html( $leastudios_siteaudit_url_model->url()->value() ); ?>
							</a>
						</td>
						<td>
							<?php
							// Project label is pre-escaped where set; default literal is safe HTML.
							echo wp_kses_post( $leastudios_siteaudit_project_label );
							?>
						</td>
						<td><?php echo esc_html( $leastudios_siteaudit_url_model->audit_frequency()->label() ); ?></td>
						<td><?php echo esc_html( $leastudios_siteaudit_url_model->audit_strategy()->label() ); ?></td>
						<td>
							<?php if ( $leastudios_siteaudit_url_model->is_enabled() ) : ?>
								<span style="color:#1e7e34;font-weight:600;"><?php esc_html_e( 'Enabled', 'leastudios-siteaudit' ); ?></span>
							<?php else : ?>
								<span style="color:#6c757d;"><?php esc_html_e( 'Disabled', 'leastudios-siteaudit' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php echo null === $leastudios_siteaudit_desktop_score ? '&mdash;' : esc_html( (string) $leastudios_siteaudit_desktop_score ); ?>
						</td>
						<td>
							<?php echo null === $leastudios_siteaudit_mobile_score ? '&mdash;' : esc_html( (string) $leastudios_siteaudit_mobile_score ); ?>
						</td>
						<td>
							<?php
							if ( null !== $leastudios_siteaudit_last_audited ) {
								echo esc_html( \LEAStudios\SiteAudit\Shared\Datetime_Util::format_immutable_for_display( $leastudios_siteaudit_last_audited, get_option( 'date_format', 'Y-m-d' ) ) );
							} else {
								echo '&mdash;';
							}
							?>
						</td>
						<td>
							<a href="<?php echo esc_url( $leastudios_siteaudit_edit_url ); ?>"><?php esc_html_e( 'Edit', 'leastudios-siteaudit' ); ?></a>
							<?php if ( current_user_can( \LEAStudios\SiteAudit\Capabilities::MANAGE ) ) : ?>
								| <form method="post" action="<?php echo esc_url( $leastudios_siteaudit_run_audit_url ); ?>" style="display:inline">
									<?php wp_nonce_field( $leastudios_siteaudit_run_audit_action ); ?>
									<input type="hidden" name="action" value="<?php echo esc_attr( $leastudios_siteaudit_run_audit_action ); ?>" />
									<input type="hidden" name="id" value="<?php echo esc_attr( (string) $leastudios_siteaudit_url_id ); ?>" />
									<button type="submit" class="button-link"><?php esc_html_e( 'Run audit now', 'leastudios-siteaudit' ); ?></button>
								</form>
								| <form method="post" action="<?php echo esc_url( $leastudios_siteaudit_delete_url ); ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this URL? Audit history will be removed too.', 'leastudios-siteaudit' ) ); ?>');">
									<?php wp_nonce_field( $leastudios_siteaudit_delete_action ); ?>
									<input type="hidden" name="action" value="<?php echo esc_attr( $leastudios_siteaudit_delete_action ); ?>" />
									<input type="hidden" name="id" value="<?php echo esc_attr( (string) $leastudios_siteaudit_url_id ); ?>" />
									<button type="submit" class="button-link delete"><?php esc_html_e( 'Delete', 'leastudios-siteaudit' ); ?></button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $leastudios_siteaudit_total_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<span class="displaying-num">
						<?php
						printf(
							/* translators: %s: number of URLs. */
							esc_html( _n( '%s item', '%s items', $leastudios_siteaudit_total, 'leastudios-siteaudit' ) ),
							esc_html( number_format_i18n( $leastudios_siteaudit_total ) )
						);
						?>
					</span>
					<?php
					$leastudios_siteaudit_base_url = $leastudios_siteaudit_list_url;
					if ( '' !== $leastudios_siteaudit_search ) {
						$leastudios_siteaudit_base_url = add_query_arg( 's', $leastudios_siteaudit_search, $leastudios_siteaudit_base_url );
					}

					$leastudios_siteaudit_pagination_links = paginate_links(
						[
							'base'      => add_query_arg( 'paged', '%#%', $leastudios_siteaudit_base_url ),
							'format'    => '',
							'total'     => $leastudios_siteaudit_total_pages,
							'current'   => $leastudios_siteaudit_page,
							'type'      => 'plain',
							'prev_text' => __( '&laquo;', 'leastudios-siteaudit' ),
							'next_text' => __( '&raquo;', 'leastudios-siteaudit' ),
						]
					);

					if ( is_string( $leastudios_siteaudit_pagination_links ) && '' !== $leastudios_siteaudit_pagination_links ) {
						echo wp_kses_post( $leastudios_siteaudit_pagination_links );
					}
					?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
