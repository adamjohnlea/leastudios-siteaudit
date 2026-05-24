<?php
/**
 * URL detail view: latest score, trend, issues (with desktop/mobile tabs), audit history.
 *
 * @package LEAStudios\SiteAudit
 *
 * @var \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url $leastudios_siteaudit_url
 * @var \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project|null $leastudios_siteaudit_project
 * @var array<int, \LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit> $leastudios_siteaudit_audits
 * @var \LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Trend $leastudios_siteaudit_trend
 * @var array<int, array{score: int, date: string}> $leastudios_siteaudit_graph_data
 * @var int $leastudios_siteaudit_average_score
 * @var int|null $leastudios_siteaudit_latest_score
 * @var array<int, \LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Issue> $leastudios_siteaudit_desktop_issues
 * @var array<int, \LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Issue> $leastudios_siteaudit_mobile_issues
 * @var bool $leastudios_siteaudit_has_desktop
 * @var bool $leastudios_siteaudit_has_mobile
 * @var string $leastudios_siteaudit_active_tab
 * @var string $leastudios_siteaudit_overview_url
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

$leastudios_siteaudit_display_name = (string) ( $leastudios_siteaudit_url->name() ?? $leastudios_siteaudit_url->url()->value() );
$leastudios_siteaudit_show_tabs    = $leastudios_siteaudit_has_desktop && $leastudios_siteaudit_has_mobile;
$leastudios_siteaudit_base_url     = add_query_arg(
	[
		'action' => 'url',
		'id'     => (int) $leastudios_siteaudit_url->id(),
	],
	$leastudios_siteaudit_detail_base_url
);

$leastudios_siteaudit_trend_class = match ( $leastudios_siteaudit_trend->value ) {
	'improving' => 'lsa-trend-up',
	'degrading' => 'lsa-trend-down',
	default     => 'lsa-trend-flat',
};

$leastudios_siteaudit_trend_arrow = match ( $leastudios_siteaudit_trend->value ) {
	'improving' => '↑',
	'degrading' => '↓',
	default     => '→',
};
?>
<div class="wrap">
	<p class="lsa-breadcrumb">
		<a href="<?php echo esc_url( $leastudios_siteaudit_overview_url ); ?>"><?php esc_html_e( 'Dashboard', 'leastudios-siteaudit' ); ?></a>
		<span>&rsaquo;</span>
		<?php if ( null !== $leastudios_siteaudit_project ) : ?>
			<?php
			$leastudios_siteaudit_project_url = add_query_arg(
				[
					'action' => 'project',
					'id'     => (int) $leastudios_siteaudit_project->id(),
				],
				$leastudios_siteaudit_detail_base_url
			);
			?>
			<a href="<?php echo esc_url( $leastudios_siteaudit_project_url ); ?>"><?php echo esc_html( $leastudios_siteaudit_project->name()->value() ); ?></a>
			<span>&rsaquo;</span>
		<?php endif; ?>
		<span><?php echo esc_html( $leastudios_siteaudit_display_name ); ?></span>
	</p>

	<h1 class="wp-heading-inline"><?php echo esc_html( $leastudios_siteaudit_display_name ); ?></h1>
	<?php
	$leastudios_siteaudit_audits_csv_url = wp_nonce_url(
		add_query_arg(
			[
				'action' => \LEAStudios\SiteAudit\Modules\Reporting\Admin\Reporting_Controller::ACTION_EXPORT_AUDITS,
				'url_id' => (int) $leastudios_siteaudit_url->id(),
			],
			admin_url( 'admin-post.php' )
		),
		\LEAStudios\SiteAudit\Modules\Reporting\Admin\Reporting_Controller::ACTION_EXPORT_AUDITS
	);
	?>
	<a href="<?php echo esc_url( $leastudios_siteaudit_audits_csv_url ); ?>" class="page-title-action">
		<?php esc_html_e( 'Download audit history (CSV)', 'leastudios-siteaudit' ); ?>
	</a>
	<hr class="wp-header-end" />

	<p class="description">
		<a href="<?php echo esc_url( $leastudios_siteaudit_url->url()->value() ); ?>" target="_blank" rel="noreferrer noopener">
			<?php echo esc_html( $leastudios_siteaudit_url->url()->value() ); ?>
		</a>
	</p>

	<div class="lsa-stat-row">
		<div class="lsa-stat">
			<?php $leastudios_siteaudit_latest_class = $leastudios_siteaudit_score_class( $leastudios_siteaudit_latest_score ); ?>
			<div class="lsa-stat__value <?php echo esc_attr( $leastudios_siteaudit_latest_class ); ?>">
				<?php echo null === $leastudios_siteaudit_latest_score ? '&mdash;' : esc_html( (string) $leastudios_siteaudit_latest_score ); ?>
			</div>
			<div class="lsa-stat__label"><?php esc_html_e( 'Latest Score', 'leastudios-siteaudit' ); ?></div>
		</div>
		<div class="lsa-stat">
			<div class="lsa-stat__value <?php echo esc_attr( $leastudios_siteaudit_trend_class ); ?>">
				<span aria-hidden="true"><?php echo esc_html( $leastudios_siteaudit_trend_arrow ); ?></span> <?php echo esc_html( $leastudios_siteaudit_trend->label() ); ?>
			</div>
			<div class="lsa-stat__label"><?php esc_html_e( 'Trend', 'leastudios-siteaudit' ); ?></div>
		</div>
		<div class="lsa-stat">
			<div class="lsa-stat__value">
				<?php echo $leastudios_siteaudit_average_score > 0 ? esc_html( (string) $leastudios_siteaudit_average_score ) : '&mdash;'; ?>
			</div>
			<div class="lsa-stat__label"><?php esc_html_e( 'Average', 'leastudios-siteaudit' ); ?></div>
		</div>
		<div class="lsa-stat">
			<div class="lsa-stat__value"><?php echo esc_html( (string) count( $leastudios_siteaudit_audits ) ); ?></div>
			<div class="lsa-stat__label"><?php esc_html_e( 'Total Audits', 'leastudios-siteaudit' ); ?></div>
		</div>
	</div>

	<h2><?php esc_html_e( 'Accessibility issues', 'leastudios-siteaudit' ); ?></h2>

	<?php if ( ! $leastudios_siteaudit_has_desktop && ! $leastudios_siteaudit_has_mobile ) : ?>
		<p><?php esc_html_e( 'No completed audits yet. Run an audit from the URLs page to see issues.', 'leastudios-siteaudit' ); ?></p>
	<?php else : ?>

		<?php if ( $leastudios_siteaudit_show_tabs ) : ?>
			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'desktop', $leastudios_siteaudit_base_url ) ); ?>" class="nav-tab <?php echo 'desktop' === $leastudios_siteaudit_active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Desktop', 'leastudios-siteaudit' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'mobile', $leastudios_siteaudit_base_url ) ); ?>" class="nav-tab <?php echo 'mobile' === $leastudios_siteaudit_active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Mobile', 'leastudios-siteaudit' ); ?>
				</a>
			</h2>
		<?php endif; ?>

		<?php
		// Decide which issue list to render based on tab state and which strategies have audits.
		if ( $leastudios_siteaudit_show_tabs ) {
			$leastudios_siteaudit_issues = 'mobile' === $leastudios_siteaudit_active_tab ? $leastudios_siteaudit_mobile_issues : $leastudios_siteaudit_desktop_issues;
		} elseif ( $leastudios_siteaudit_has_desktop ) {
			$leastudios_siteaudit_issues = $leastudios_siteaudit_desktop_issues;
		} else {
			$leastudios_siteaudit_issues = $leastudios_siteaudit_mobile_issues;
		}

		include __DIR__ . '/_issue-table.php';
		?>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Audit history', 'leastudios-siteaudit' ); ?></h2>

	<?php if ( [] === $leastudios_siteaudit_audits ) : ?>
		<p><?php esc_html_e( 'No audits yet.', 'leastudios-siteaudit' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Date', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Strategy', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Score', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Error', 'leastudios-siteaudit' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $leastudios_siteaudit_audits as $leastudios_siteaudit_audit_row ) : ?>
					<?php $leastudios_siteaudit_row_score = $leastudios_siteaudit_audit_row->score()->value(); ?>
					<tr>
						<td><?php echo esc_html( \LEAStudios\SiteAudit\Shared\Datetime_Util::format_immutable_for_display( $leastudios_siteaudit_audit_row->audit_date(), get_option( 'date_format', 'Y-m-d' ) . ' H:i' ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $leastudios_siteaudit_audit_row->strategy()->value ) ); ?></td>
						<td>
							<span class="lsa-score-badge <?php echo esc_attr( $leastudios_siteaudit_score_class( $leastudios_siteaudit_row_score ) ); ?>">
								<?php echo esc_html( (string) $leastudios_siteaudit_row_score ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $leastudios_siteaudit_audit_row->status()->label() ); ?></td>
						<td>
							<?php $leastudios_siteaudit_err = (string) ( $leastudios_siteaudit_audit_row->error_message() ?? '' ); ?>
							<?php echo '' !== $leastudios_siteaudit_err ? esc_html( $leastudios_siteaudit_err ) : '&mdash;'; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
