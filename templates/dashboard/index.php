<?php
/**
 * Dashboard overview page.
 *
 * @package LEAStudios\SiteAudit
 *
 * @var array<int, array{project: \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project, summary: \LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects\Dashboard_Summary}> $leastudios_siteaudit_project_cards
 * @var \LEAStudios\SiteAudit\Modules\Dashboard\Domain\ValueObjects\Dashboard_Summary $leastudios_siteaudit_unassigned_summary
 * @var bool $leastudios_siteaudit_has_any_urls
 * @var string $leastudios_siteaudit_detail_base_url
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
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Site Audit Dashboard', 'leastudios-siteaudit' ); ?></h1>
	<hr class="wp-header-end" />

	<?php if ( ! $leastudios_siteaudit_has_any_urls ) : ?>
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
			<?php foreach ( $leastudios_siteaudit_project_cards as $leastudios_siteaudit_card ) : ?>
				<?php
				$leastudios_siteaudit_project     = $leastudios_siteaudit_card['project'];
				$leastudios_siteaudit_summary     = $leastudios_siteaudit_card['summary'];
				$leastudios_siteaudit_project_id  = (int) $leastudios_siteaudit_project->id();
				$leastudios_siteaudit_project_url = add_query_arg(
					[
						'action' => 'project',
						'id'     => $leastudios_siteaudit_project_id,
					],
					$leastudios_siteaudit_detail_base_url
				);
				$leastudios_siteaudit_avg         = $leastudios_siteaudit_summary->average_score();
				$leastudios_siteaudit_avg_class   = $leastudios_siteaudit_score_class( $leastudios_siteaudit_summary->total_audits() > 0 ? $leastudios_siteaudit_avg : null );
				$leastudios_siteaudit_description = (string) ( $leastudios_siteaudit_project->description() ?? '' );
				?>
				<a class="lsa-card" href="<?php echo esc_url( $leastudios_siteaudit_project_url ); ?>">
					<h2 class="lsa-card__title"><?php echo esc_html( $leastudios_siteaudit_project->name()->value() ); ?></h2>
					<?php if ( '' !== $leastudios_siteaudit_description ) : ?>
						<p class="lsa-card__desc"><?php echo esc_html( $leastudios_siteaudit_description ); ?></p>
					<?php endif; ?>
					<div class="lsa-card__stats">
						<div class="lsa-stat">
							<div class="lsa-stat__value"><?php echo esc_html( (string) $leastudios_siteaudit_summary->total_urls() ); ?></div>
							<div class="lsa-stat__label"><?php esc_html_e( 'URLs', 'leastudios-siteaudit' ); ?></div>
						</div>
						<div class="lsa-stat">
							<div class="lsa-stat__value <?php echo esc_attr( $leastudios_siteaudit_avg_class ); ?>">
								<?php echo $leastudios_siteaudit_summary->total_audits() > 0 ? esc_html( (string) $leastudios_siteaudit_avg ) : '&mdash;'; ?>
							</div>
							<div class="lsa-stat__label"><?php esc_html_e( 'Avg Score', 'leastudios-siteaudit' ); ?></div>
						</div>
						<div class="lsa-stat">
							<div class="lsa-stat__value"><?php echo esc_html( (string) $leastudios_siteaudit_summary->urls_needing_attention() ); ?></div>
							<div class="lsa-stat__label"><?php esc_html_e( 'Need Work', 'leastudios-siteaudit' ); ?></div>
						</div>
					</div>
				</a>
			<?php endforeach; ?>

			<?php if ( $leastudios_siteaudit_unassigned_summary->total_urls() > 0 ) : ?>
				<?php
				$leastudios_siteaudit_unassigned_url = add_query_arg(
					[
						'action' => 'project',
						'id'     => 0,
					],
					$leastudios_siteaudit_detail_base_url
				);
				$leastudios_siteaudit_avg_class      = $leastudios_siteaudit_score_class( $leastudios_siteaudit_unassigned_summary->total_audits() > 0 ? $leastudios_siteaudit_unassigned_summary->average_score() : null );
				?>
				<a class="lsa-card lsa-card--dashed" href="<?php echo esc_url( $leastudios_siteaudit_unassigned_url ); ?>">
					<h2 class="lsa-card__title"><?php esc_html_e( 'Unassigned URLs', 'leastudios-siteaudit' ); ?></h2>
					<p class="lsa-card__desc"><?php esc_html_e( 'URLs that are not part of any project.', 'leastudios-siteaudit' ); ?></p>
					<div class="lsa-card__stats">
						<div class="lsa-stat">
							<div class="lsa-stat__value"><?php echo esc_html( (string) $leastudios_siteaudit_unassigned_summary->total_urls() ); ?></div>
							<div class="lsa-stat__label"><?php esc_html_e( 'URLs', 'leastudios-siteaudit' ); ?></div>
						</div>
						<div class="lsa-stat">
							<div class="lsa-stat__value <?php echo esc_attr( $leastudios_siteaudit_avg_class ); ?>">
								<?php echo $leastudios_siteaudit_unassigned_summary->total_audits() > 0 ? esc_html( (string) $leastudios_siteaudit_unassigned_summary->average_score() ) : '&mdash;'; ?>
							</div>
							<div class="lsa-stat__label"><?php esc_html_e( 'Avg Score', 'leastudios-siteaudit' ); ?></div>
						</div>
						<div class="lsa-stat">
							<div class="lsa-stat__value"><?php echo esc_html( (string) $leastudios_siteaudit_unassigned_summary->urls_needing_attention() ); ?></div>
							<div class="lsa-stat__label"><?php esc_html_e( 'Need Work', 'leastudios-siteaudit' ); ?></div>
						</div>
					</div>
				</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
