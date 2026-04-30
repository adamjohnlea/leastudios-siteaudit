<?php
/**
 * URL detail view: latest score, trend, issues (with desktop/mobile tabs), audit history.
 *
 * @package LEAStudios\SiteAudit
 *
 * @var \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url $url
 * @var \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project|null $project
 * @var array<int, \LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit> $audits
 * @var \LEAStudios\SiteAudit\Modules\Audit\Domain\ValueObjects\Trend $trend
 * @var array<int, array{score: int, date: string}> $graph_data
 * @var int $average_score
 * @var int|null $latest_score
 * @var array<int, \LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Issue> $desktop_issues
 * @var array<int, \LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Issue> $mobile_issues
 * @var bool $has_desktop
 * @var bool $has_mobile
 * @var string $active_tab
 * @var string $overview_url
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

$display_name = (string) ( $url->name() ?? $url->url()->value() );
$show_tabs    = $has_desktop && $has_mobile;
$base_url     = add_query_arg(
	[
		'action' => 'url',
		'id'     => (int) $url->id(),
	],
	$detail_base_url
);

$trend_class = match ( $trend->value ) {
	'improving' => 'lsa-trend-up',
	'degrading' => 'lsa-trend-down',
	default     => 'lsa-trend-flat',
};

$trend_arrow = match ( $trend->value ) {
	'improving' => '↑',
	'degrading' => '↓',
	default     => '→',
};
?>
<div class="wrap">
	<p class="lsa-breadcrumb">
		<a href="<?php echo esc_url( $overview_url ); ?>"><?php esc_html_e( 'Dashboard', 'leastudios-siteaudit' ); ?></a>
		<span>&rsaquo;</span>
		<?php if ( null !== $project ) : ?>
			<?php
			$project_url = add_query_arg(
				[
					'action' => 'project',
					'id'     => (int) $project->id(),
				],
				$detail_base_url
			);
			?>
			<a href="<?php echo esc_url( $project_url ); ?>"><?php echo esc_html( $project->name()->value() ); ?></a>
			<span>&rsaquo;</span>
		<?php endif; ?>
		<span><?php echo esc_html( $display_name ); ?></span>
	</p>

	<h1 class="wp-heading-inline"><?php echo esc_html( $display_name ); ?></h1>
	<?php
	$audits_csv_url = wp_nonce_url(
		add_query_arg(
			[
				'action' => \LEAStudios\SiteAudit\Modules\Reporting\Admin\Reporting_Controller::ACTION_EXPORT_AUDITS,
				'url_id' => (int) $url->id(),
			],
			admin_url( 'admin-post.php' )
		),
		\LEAStudios\SiteAudit\Modules\Reporting\Admin\Reporting_Controller::ACTION_EXPORT_AUDITS
	);
	?>
	<a href="<?php echo esc_url( $audits_csv_url ); ?>" class="page-title-action">
		<?php esc_html_e( 'Download audit history (CSV)', 'leastudios-siteaudit' ); ?>
	</a>
	<hr class="wp-header-end" />

	<p class="description">
		<a href="<?php echo esc_url( $url->url()->value() ); ?>" target="_blank" rel="noreferrer noopener">
			<?php echo esc_html( $url->url()->value() ); ?>
		</a>
	</p>

	<div class="lsa-stat-row">
		<div class="lsa-stat">
			<?php $latest_class = $score_class( $latest_score ); ?>
			<div class="lsa-stat__value <?php echo esc_attr( $latest_class ); ?>">
				<?php echo null === $latest_score ? '&mdash;' : esc_html( (string) $latest_score ); ?>
			</div>
			<div class="lsa-stat__label"><?php esc_html_e( 'Latest Score', 'leastudios-siteaudit' ); ?></div>
		</div>
		<div class="lsa-stat">
			<div class="lsa-stat__value <?php echo esc_attr( $trend_class ); ?>">
				<?php echo esc_html( $trend_arrow ); ?> <?php echo esc_html( $trend->label() ); ?>
			</div>
			<div class="lsa-stat__label"><?php esc_html_e( 'Trend', 'leastudios-siteaudit' ); ?></div>
		</div>
		<div class="lsa-stat">
			<div class="lsa-stat__value">
				<?php echo $average_score > 0 ? esc_html( (string) $average_score ) : '&mdash;'; ?>
			</div>
			<div class="lsa-stat__label"><?php esc_html_e( 'Average', 'leastudios-siteaudit' ); ?></div>
		</div>
		<div class="lsa-stat">
			<div class="lsa-stat__value"><?php echo esc_html( (string) count( $audits ) ); ?></div>
			<div class="lsa-stat__label"><?php esc_html_e( 'Total Audits', 'leastudios-siteaudit' ); ?></div>
		</div>
	</div>

	<h2><?php esc_html_e( 'Accessibility issues', 'leastudios-siteaudit' ); ?></h2>

	<?php if ( ! $has_desktop && ! $has_mobile ) : ?>
		<p><?php esc_html_e( 'No completed audits yet. Run an audit from the URLs page to see issues.', 'leastudios-siteaudit' ); ?></p>
	<?php else : ?>

		<?php if ( $show_tabs ) : ?>
			<h2 class="nav-tab-wrapper">
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'desktop', $base_url ) ); ?>" class="nav-tab <?php echo 'desktop' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Desktop', 'leastudios-siteaudit' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'mobile', $base_url ) ); ?>" class="nav-tab <?php echo 'mobile' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Mobile', 'leastudios-siteaudit' ); ?>
				</a>
			</h2>
		<?php endif; ?>

		<?php
		// Decide which issue list to render based on tab state and which strategies have audits.
		if ( $show_tabs ) {
			$issues = 'mobile' === $active_tab ? $mobile_issues : $desktop_issues;
		} elseif ( $has_desktop ) {
			$issues = $desktop_issues;
		} else {
			$issues = $mobile_issues;
		}

		include __DIR__ . '/_issue-table.php';
		?>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Audit history', 'leastudios-siteaudit' ); ?></h2>

	<?php if ( [] === $audits ) : ?>
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
				<?php foreach ( $audits as $audit_row ) : ?>
					<?php $row_score = $audit_row->score()->value(); ?>
					<tr>
						<td><?php echo esc_html( \LEAStudios\SiteAudit\Shared\Datetime_Util::format_for_display( $audit_row->audit_date(), get_option( 'date_format', 'Y-m-d' ) . ' H:i' ) ); ?></td>
						<td><?php echo esc_html( ucfirst( $audit_row->strategy()->value ) ); ?></td>
						<td>
							<span class="lsa-score-badge <?php echo esc_attr( $score_class( $row_score ) ); ?>">
								<?php echo esc_html( (string) $row_score ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $audit_row->status()->label() ); ?></td>
						<td>
							<?php $err = (string) ( $audit_row->error_message() ?? '' ); ?>
							<?php echo '' !== $err ? esc_html( $err ) : '&mdash;'; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
