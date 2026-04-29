<?php
/**
 * Dashboard overview page.
 *
 * @package LEAStudios\SiteAudit
 *
 * @var array<int, array{project: \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project, summary: \LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects\Dashboard_Summary}> $project_cards
 * @var \LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects\Dashboard_Summary $unassigned_summary
 * @var bool $has_any_urls
 * @var string $detail_base_url
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
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Site Audit Dashboard', 'leastudios-siteaudit' ); ?></h1>
	<hr class="wp-header-end" />

	<?php if ( ! $has_any_urls ) : ?>
		<div class="notice notice-info inline">
			<p>
				<?php
				printf(
					/* translators: %s: link to URLs page */
					esc_html__( 'No URLs are being monitored yet. %s to start auditing.', 'leastudios-siteaudit' ),
					'<a href="' . esc_url( add_query_arg( 'page', \LEAStudios\SiteAudit\Modules\Url\Admin\Url_Controller::PAGE_SLUG, admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Add a URL', 'leastudios-siteaudit' ) . '</a>'
				);
				?>
			</p>
		</div>
	<?php else : ?>
		<div class="lsa-card-grid">
			<?php foreach ( $project_cards as $card ) : ?>
				<?php
				$project     = $card['project'];
				$summary     = $card['summary'];
				$project_id  = (int) $project->id();
				$project_url = add_query_arg(
					[
						'action' => 'project',
						'id'     => $project_id,
					],
					$detail_base_url
				);
				$avg         = $summary->average_score();
				$avg_class   = $score_class( $summary->total_audits() > 0 ? $avg : null );
				$description = (string) ( $project->description() ?? '' );
				?>
				<a class="lsa-card" href="<?php echo esc_url( $project_url ); ?>">
					<h2 class="lsa-card__title"><?php echo esc_html( $project->name()->value() ); ?></h2>
					<?php if ( '' !== $description ) : ?>
						<p class="lsa-card__desc"><?php echo esc_html( $description ); ?></p>
					<?php endif; ?>
					<div class="lsa-card__stats">
						<div class="lsa-stat">
							<div class="lsa-stat__value"><?php echo esc_html( (string) $summary->total_urls() ); ?></div>
							<div class="lsa-stat__label"><?php esc_html_e( 'URLs', 'leastudios-siteaudit' ); ?></div>
						</div>
						<div class="lsa-stat">
							<div class="lsa-stat__value <?php echo esc_attr( $avg_class ); ?>">
								<?php echo $summary->total_audits() > 0 ? esc_html( (string) $avg ) : '&mdash;'; ?>
							</div>
							<div class="lsa-stat__label"><?php esc_html_e( 'Avg Score', 'leastudios-siteaudit' ); ?></div>
						</div>
						<div class="lsa-stat">
							<div class="lsa-stat__value"><?php echo esc_html( (string) $summary->urls_needing_attention() ); ?></div>
							<div class="lsa-stat__label"><?php esc_html_e( 'Need Work', 'leastudios-siteaudit' ); ?></div>
						</div>
					</div>
				</a>
			<?php endforeach; ?>

			<?php if ( $unassigned_summary->total_urls() > 0 ) : ?>
				<?php
				$unassigned_url = add_query_arg(
					[
						'action' => 'project',
						'id'     => 0,
					],
					$detail_base_url
				);
				$avg_class      = $score_class( $unassigned_summary->total_audits() > 0 ? $unassigned_summary->average_score() : null );
				?>
				<a class="lsa-card lsa-card--dashed" href="<?php echo esc_url( $unassigned_url ); ?>">
					<h2 class="lsa-card__title"><?php esc_html_e( 'Unassigned URLs', 'leastudios-siteaudit' ); ?></h2>
					<p class="lsa-card__desc"><?php esc_html_e( 'URLs that are not part of any project.', 'leastudios-siteaudit' ); ?></p>
					<div class="lsa-card__stats">
						<div class="lsa-stat">
							<div class="lsa-stat__value"><?php echo esc_html( (string) $unassigned_summary->total_urls() ); ?></div>
							<div class="lsa-stat__label"><?php esc_html_e( 'URLs', 'leastudios-siteaudit' ); ?></div>
						</div>
						<div class="lsa-stat">
							<div class="lsa-stat__value <?php echo esc_attr( $avg_class ); ?>">
								<?php echo $unassigned_summary->total_audits() > 0 ? esc_html( (string) $unassigned_summary->average_score() ) : '&mdash;'; ?>
							</div>
							<div class="lsa-stat__label"><?php esc_html_e( 'Avg Score', 'leastudios-siteaudit' ); ?></div>
						</div>
						<div class="lsa-stat">
							<div class="lsa-stat__value"><?php echo esc_html( (string) $unassigned_summary->urls_needing_attention() ); ?></div>
							<div class="lsa-stat__label"><?php esc_html_e( 'Need Work', 'leastudios-siteaudit' ); ?></div>
						</div>
					</div>
				</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
