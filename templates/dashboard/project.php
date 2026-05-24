<?php
/**
 * Project detail (or "Unassigned URLs") view.
 *
 * @package LEAStudios\SiteAudit
 *
 * @var \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project|null $leastudios_siteaudit_project
 * @var \LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects\Dashboard_Summary $leastudios_siteaudit_summary
 * @var array<int, \LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects\Url_Summary> $leastudios_siteaudit_url_summaries
 * @var bool $leastudios_siteaudit_is_subscribed
 * @var string $leastudios_siteaudit_detail_base_url
 * @var string $leastudios_siteaudit_overview_url
 */

defined( 'ABSPATH' ) || exit;

\LEAStudios\SiteAudit\Admin\Notice_Service::render();

$leastudios_siteaudit_score_class = static function ( ?int $score ): string {
	if ( null === $score ) {
		return 'lsa-score-none';
	}
	return match ( true ) {
		$score >= 90 => 'lsa-score-excellent',
		$score >= 70 => 'lsa-score-good',
		$score >= 50 => 'lsa-score-needs-work',
		default      => 'lsa-score-poor',
	};
};

$leastudios_siteaudit_page_title = null !== $leastudios_siteaudit_project
	? $leastudios_siteaudit_project->name()->value()
	: __( 'Unassigned URLs', 'leastudios-siteaudit' );
?>
<div class="wrap">
	<p class="lsa-breadcrumb">
		<a href="<?php echo esc_url( $leastudios_siteaudit_overview_url ); ?>"><?php esc_html_e( 'Dashboard', 'leastudios-siteaudit' ); ?></a>
		<span>&rsaquo;</span>
		<span><?php echo esc_html( $leastudios_siteaudit_page_title ); ?></span>
	</p>

	<h1 class="wp-heading-inline"><?php echo esc_html( $leastudios_siteaudit_page_title ); ?></h1>
	<?php
	$leastudios_siteaudit_summary_id = null !== $leastudios_siteaudit_project ? (int) $leastudios_siteaudit_project->id() : 0;
	$leastudios_siteaudit_csv_url    = wp_nonce_url(
		add_query_arg(
			[
				'action'     => \LEAStudios\SiteAudit\Modules\Reporting\Admin\Reporting_Controller::ACTION_EXPORT_SUMMARY,
				'project_id' => $leastudios_siteaudit_summary_id,
			],
			admin_url( 'admin-post.php' )
		),
		\LEAStudios\SiteAudit\Modules\Reporting\Admin\Reporting_Controller::ACTION_EXPORT_SUMMARY
	);
	?>
	<a href="<?php echo esc_url( $leastudios_siteaudit_csv_url ); ?>" class="page-title-action">
		<?php esc_html_e( 'Download URL list (CSV)', 'leastudios-siteaudit' ); ?>
	</a>
	<?php if ( null !== $leastudios_siteaudit_project ) : ?>
		<?php
		$leastudios_siteaudit_pdf_url = wp_nonce_url(
			add_query_arg(
				[
					'action'     => \LEAStudios\SiteAudit\Modules\Reporting\Admin\Reporting_Controller::ACTION_EXPORT_PDF,
					'project_id' => (int) $leastudios_siteaudit_project->id(),
				],
				admin_url( 'admin-post.php' )
			),
			\LEAStudios\SiteAudit\Modules\Reporting\Admin\Reporting_Controller::ACTION_EXPORT_PDF
		);
		?>
		<a href="<?php echo esc_url( $leastudios_siteaudit_pdf_url ); ?>" class="page-title-action">
			<?php esc_html_e( 'Download report (PDF)', 'leastudios-siteaudit' ); ?>
		</a>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
			<?php wp_nonce_field( \LEAStudios\SiteAudit\Modules\Notification\Admin\Subscription_Controller::ACTION_TOGGLE ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( \LEAStudios\SiteAudit\Modules\Notification\Admin\Subscription_Controller::ACTION_TOGGLE ); ?>" />
			<input type="hidden" name="project_id" value="<?php echo esc_attr( (string) (int) $leastudios_siteaudit_project->id() ); ?>" />
			<button type="submit" class="page-title-action">
				<?php
				echo $leastudios_siteaudit_is_subscribed
					? esc_html__( 'Unsubscribe from emails', 'leastudios-siteaudit' )
					: esc_html__( 'Subscribe to emails', 'leastudios-siteaudit' );
				?>
			</button>
		</form>
	<?php endif; ?>
	<hr class="wp-header-end" />

	<?php if ( null !== $leastudios_siteaudit_project ) : ?>
		<?php $leastudios_siteaudit_description = (string) ( $leastudios_siteaudit_project->description() ?? '' ); ?>
		<?php if ( '' !== $leastudios_siteaudit_description ) : ?>
			<p class="description"><?php echo esc_html( $leastudios_siteaudit_description ); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<div class="lsa-stat-row">
		<div class="lsa-stat">
			<div class="lsa-stat__value"><?php echo esc_html( (string) $leastudios_siteaudit_summary->total_urls() ); ?></div>
			<div class="lsa-stat__label"><?php esc_html_e( 'Monitored URLs', 'leastudios-siteaudit' ); ?></div>
		</div>
		<div class="lsa-stat">
			<div class="lsa-stat__value"><?php echo esc_html( (string) $leastudios_siteaudit_summary->total_audits() ); ?></div>
			<div class="lsa-stat__label"><?php esc_html_e( 'Total Audits', 'leastudios-siteaudit' ); ?></div>
		</div>
		<div class="lsa-stat">
			<?php
			$leastudios_siteaudit_avg       = $leastudios_siteaudit_summary->average_score();
			$leastudios_siteaudit_avg_class = $leastudios_siteaudit_score_class( $leastudios_siteaudit_summary->total_audits() > 0 ? $leastudios_siteaudit_avg : null );
			?>
			<div class="lsa-stat__value <?php echo esc_attr( $leastudios_siteaudit_avg_class ); ?>">
				<?php echo $leastudios_siteaudit_summary->total_audits() > 0 ? esc_html( (string) $leastudios_siteaudit_avg ) : '&mdash;'; ?>
			</div>
			<div class="lsa-stat__label"><?php esc_html_e( 'Avg Score', 'leastudios-siteaudit' ); ?></div>
		</div>
		<div class="lsa-stat">
			<div class="lsa-stat__value"><?php echo esc_html( (string) $leastudios_siteaudit_summary->urls_needing_attention() ); ?></div>
			<div class="lsa-stat__label"><?php esc_html_e( 'Need Attention', 'leastudios-siteaudit' ); ?></div>
		</div>
	</div>

	<?php if ( [] === $leastudios_siteaudit_url_summaries ) : ?>
		<p><?php esc_html_e( 'No URLs to display yet.', 'leastudios-siteaudit' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'URL', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Desktop', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Mobile', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Audits', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Frequency', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'leastudios-siteaudit' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $leastudios_siteaudit_url_summaries as $leastudios_siteaudit_row ) : ?>
					<?php
					$leastudios_siteaudit_detail_url = add_query_arg(
						[
							'action' => 'url',
							'id'     => $leastudios_siteaudit_row->url_id(),
						],
						$leastudios_siteaudit_detail_base_url
					);
					$leastudios_siteaudit_desktop    = $leastudios_siteaudit_row->latest_desktop_score();
					$leastudios_siteaudit_mobile     = $leastudios_siteaudit_row->latest_mobile_score();
					?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( $leastudios_siteaudit_detail_url ); ?>"><?php echo esc_html( $leastudios_siteaudit_row->name() ); ?></a></strong>
						</td>
						<td>
							<a href="<?php echo esc_url( $leastudios_siteaudit_row->address() ); ?>" target="_blank" rel="noreferrer noopener">
								<?php echo esc_html( $leastudios_siteaudit_row->address() ); ?>
							</a>
						</td>
						<td>
							<?php if ( null === $leastudios_siteaudit_desktop ) : ?>
								<span class="lsa-score-badge lsa-score-none">&mdash;</span>
							<?php else : ?>
								<span class="lsa-score-badge <?php echo esc_attr( $leastudios_siteaudit_score_class( $leastudios_siteaudit_desktop ) ); ?>"><?php echo esc_html( (string) $leastudios_siteaudit_desktop ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( null === $leastudios_siteaudit_mobile ) : ?>
								<span class="lsa-score-badge lsa-score-none">&mdash;</span>
							<?php else : ?>
								<span class="lsa-score-badge <?php echo esc_attr( $leastudios_siteaudit_score_class( $leastudios_siteaudit_mobile ) ); ?>"><?php echo esc_html( (string) $leastudios_siteaudit_mobile ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( (string) $leastudios_siteaudit_row->total_audits() ); ?></td>
						<td><?php echo esc_html( $leastudios_siteaudit_row->frequency() ); ?></td>
						<td>
							<?php if ( $leastudios_siteaudit_row->is_enabled() ) : ?>
								<span style="color:#1e7e34;font-weight:600;"><?php esc_html_e( 'Active', 'leastudios-siteaudit' ); ?></span>
							<?php else : ?>
								<span style="color:#6c757d;"><?php esc_html_e( 'Paused', 'leastudios-siteaudit' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( $leastudios_siteaudit_detail_url ); ?>"><?php esc_html_e( 'View', 'leastudios-siteaudit' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
