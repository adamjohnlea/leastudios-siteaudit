<?php
/**
 * Project detail (or "Unassigned URLs") view.
 *
 * @package LEAStudios\SiteAudit
 *
 * @var \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project|null $project
 * @var \LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects\Dashboard_Summary $summary
 * @var array<int, \LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects\Url_Summary> $url_summaries
 * @var string $detail_base_url
 * @var string $overview_url
 */

defined( 'ABSPATH' ) || exit;

\LEAStudios\SiteAudit\Admin\Notice_Service::render();

$score_class = static function ( ?int $score ): string {
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

$page_title = null !== $project
	? $project->name()->value()
	: __( 'Unassigned URLs', 'leastudios-siteaudit' );
?>
<div class="wrap">
	<p class="lsa-breadcrumb">
		<a href="<?php echo esc_url( $overview_url ); ?>"><?php esc_html_e( 'Dashboard', 'leastudios-siteaudit' ); ?></a>
		<span>&rsaquo;</span>
		<span><?php echo esc_html( $page_title ); ?></span>
	</p>

	<h1 class="wp-heading-inline"><?php echo esc_html( $page_title ); ?></h1>
	<hr class="wp-header-end" />

	<?php if ( null !== $project ) : ?>
		<?php $description = (string) ( $project->description() ?? '' ); ?>
		<?php if ( '' !== $description ) : ?>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<div class="lsa-stat-row">
		<div class="lsa-stat">
			<div class="lsa-stat__value"><?php echo esc_html( (string) $summary->total_urls() ); ?></div>
			<div class="lsa-stat__label"><?php esc_html_e( 'Monitored URLs', 'leastudios-siteaudit' ); ?></div>
		</div>
		<div class="lsa-stat">
			<div class="lsa-stat__value"><?php echo esc_html( (string) $summary->total_audits() ); ?></div>
			<div class="lsa-stat__label"><?php esc_html_e( 'Total Audits', 'leastudios-siteaudit' ); ?></div>
		</div>
		<div class="lsa-stat">
			<?php
			$avg       = $summary->average_score();
			$avg_class = $score_class( $summary->total_audits() > 0 ? $avg : null );
			?>
			<div class="lsa-stat__value <?php echo esc_attr( $avg_class ); ?>">
				<?php echo $summary->total_audits() > 0 ? esc_html( (string) $avg ) : '&mdash;'; ?>
			</div>
			<div class="lsa-stat__label"><?php esc_html_e( 'Avg Score', 'leastudios-siteaudit' ); ?></div>
		</div>
		<div class="lsa-stat">
			<div class="lsa-stat__value"><?php echo esc_html( (string) $summary->urls_needing_attention() ); ?></div>
			<div class="lsa-stat__label"><?php esc_html_e( 'Need Attention', 'leastudios-siteaudit' ); ?></div>
		</div>
	</div>

	<?php if ( [] === $url_summaries ) : ?>
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
				<?php foreach ( $url_summaries as $row ) : ?>
					<?php
					$detail_url = add_query_arg(
						[
							'action' => 'url',
							'id'     => $row->url_id(),
						],
						$detail_base_url
					);
					$desktop    = $row->latest_desktop_score();
					$mobile     = $row->latest_mobile_score();
					?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html( $row->name() ); ?></a></strong>
						</td>
						<td>
							<a href="<?php echo esc_url( $row->address() ); ?>" target="_blank" rel="noreferrer noopener">
								<?php echo esc_html( $row->address() ); ?>
							</a>
						</td>
						<td>
							<?php if ( null === $desktop ) : ?>
								<span class="lsa-score-badge lsa-score-none">&mdash;</span>
							<?php else : ?>
								<span class="lsa-score-badge <?php echo esc_attr( $score_class( $desktop ) ); ?>"><?php echo esc_html( (string) $desktop ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( null === $mobile ) : ?>
								<span class="lsa-score-badge lsa-score-none">&mdash;</span>
							<?php else : ?>
								<span class="lsa-score-badge <?php echo esc_attr( $score_class( $mobile ) ); ?>"><?php echo esc_html( (string) $mobile ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( (string) $row->total_audits() ); ?></td>
						<td><?php echo esc_html( $row->frequency() ); ?></td>
						<td>
							<?php if ( $row->is_enabled() ) : ?>
								<span style="color:#1e7e34;font-weight:600;"><?php esc_html_e( 'Active', 'leastudios-siteaudit' ); ?></span>
							<?php else : ?>
								<span style="color:#6c757d;"><?php esc_html_e( 'Paused', 'leastudios-siteaudit' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<a href="<?php echo esc_url( $detail_url ); ?>"><?php esc_html_e( 'View', 'leastudios-siteaudit' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
