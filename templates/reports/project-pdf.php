<?php
/**
 * PDF project report template.
 *
 * Rendered into Dompdf — keep all CSS inline (no external stylesheets,
 * `setIsRemoteEnabled(false)` blocks them). No JavaScript (Dompdf doesn't
 * execute it). All scalar interpolations escape with `esc_html()` despite
 * the output going to a PDF — same defense-in-depth posture as the admin UI.
 *
 * @package LEAStudios\SiteAudit
 *
 * @var \LEAStudios\SiteAudit\Modules\Reporting\Domain\ValueObjects\Project_Report_Data $report
 */

defined( 'ABSPATH' ) || exit;

$summary            = $report->summary();
$score_distribution = $summary->score_distribution();
$severity_counts    = $report->severity_counts();
$issues_by_category = $report->issues_by_category();
$total_issues       = $report->total_issues();
$category_count     = count( $issues_by_category );

$score_color_class = static function ( ?int $score ): string {
	if ( null === $score ) {
		return '';
	}
	return match ( true ) {
		$score >= 90 => 'color-excellent',
		$score >= 70 => 'color-good',
		$score >= 50 => 'color-warn',
		default      => 'color-poor',
	};
};

$grade_class = static function ( string $grade ): string {
	return match ( $grade ) {
		'A'     => 'grade-a',
		'B'     => 'grade-b',
		'C'     => 'grade-c',
		'F'     => 'grade-f',
		default => '',
	};
};
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<style>
		* { margin: 0; padding: 0; box-sizing: border-box; }
		body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color: #1a1a1a; line-height: 1.4; }
		.page-break { page-break-before: always; }
		.report-header { border-bottom: 2px solid #2563eb; padding-bottom: 12px; margin-bottom: 20px; }
		.report-header h1 { font-size: 20px; color: #111827; margin-bottom: 4px; }
		.report-header .subtitle { font-size: 11px; color: #6b7280; }
		.summary-grid { width: 100%; margin-bottom: 20px; }
		.summary-grid td { width: 25%; padding: 8px 10px; vertical-align: top; }
		.stat-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; }
		.stat-label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
		.stat-value { font-size: 18px; font-weight: bold; color: #111827; }
		.section-title { font-size: 13px; font-weight: bold; color: #111827; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid #e5e7eb; }
		.distribution-grid { width: 100%; margin-bottom: 16px; }
		.distribution-grid td { padding: 4px 8px; }
		.dist-label { font-size: 9px; color: #6b7280; }
		.dist-value { font-size: 12px; font-weight: bold; }
		.color-excellent { color: #059669; }
		.color-good { color: #2563eb; }
		.color-warn { color: #d97706; }
		.color-poor { color: #dc2626; }
		.badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; text-transform: uppercase; }
		.badge-critical { background: #fef2f2; color: #dc2626; }
		.badge-serious { background: #fff7ed; color: #ea580c; }
		.badge-moderate { background: #fffbeb; color: #d97706; }
		.badge-minor { background: #f0fdf4; color: #16a34a; }
		.grade { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 9px; font-weight: bold; }
		.grade-a { background: #d1fae5; color: #059669; }
		.grade-b { background: #dbeafe; color: #2563eb; }
		.grade-c { background: #fef3c7; color: #d97706; }
		.grade-f { background: #fef2f2; color: #dc2626; }
		table.url-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
		table.url-table th { background: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 8px; text-align: left; font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.3px; }
		table.url-table td { border: 1px solid #e2e8f0; padding: 5px 8px; font-size: 9px; }
		table.url-table tr:nth-child(even) td { background: #f8fafc; }
		.category-header { font-size: 12px; font-weight: bold; color: #1e40af; margin-top: 16px; margin-bottom: 8px; padding: 6px 10px; background: #eff6ff; border-left: 3px solid #2563eb; }
		.issue-card { border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px 10px; margin-bottom: 6px; }
		.issue-title { font-size: 10px; font-weight: bold; color: #111827; margin-bottom: 2px; }
		.issue-description { font-size: 9px; color: #4b5563; margin-bottom: 4px; }
		.issue-meta { font-size: 8px; color: #6b7280; }
		.issue-meta a { color: #2563eb; text-decoration: none; }
		.affected-urls { font-size: 8px; color: #6b7280; margin-top: 3px; }
		.empty-state { text-align: center; padding: 30px; color: #9ca3af; font-size: 11px; }
		.report-footer { margin-top: 20px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af; text-align: center; }
	</style>
</head>
<body>

<div class="report-header">
	<h1><?php echo esc_html( $report->project_name() ); ?></h1>
	<div class="subtitle">
		<?php
		printf(
			/* translators: %s: generated-at timestamp. */
			esc_html__( 'Accessibility Audit Report &mdash; Generated %s', 'leastudios-siteaudit' ),
			esc_html( $report->generated_at() )
		);
		?>
	</div>
</div>

<table class="summary-grid" cellspacing="0">
	<tr>
		<td>
			<div class="stat-card">
				<div class="stat-label"><?php esc_html_e( 'Total URLs', 'leastudios-siteaudit' ); ?></div>
				<div class="stat-value"><?php echo esc_html( (string) $summary->total_urls() ); ?></div>
			</div>
		</td>
		<td>
			<div class="stat-card">
				<div class="stat-label"><?php esc_html_e( 'Average Score', 'leastudios-siteaudit' ); ?></div>
				<div class="stat-value <?php echo esc_attr( $score_color_class( $summary->average_score() ) ); ?>">
					<?php echo esc_html( (string) $summary->average_score() ); ?>
				</div>
			</div>
		</td>
		<td>
			<div class="stat-card">
				<div class="stat-label"><?php esc_html_e( 'Need Attention', 'leastudios-siteaudit' ); ?></div>
				<div class="stat-value <?php echo $summary->urls_needing_attention() > 0 ? 'color-poor' : ''; ?>">
					<?php echo esc_html( (string) $summary->urls_needing_attention() ); ?>
				</div>
			</div>
		</td>
		<td>
			<div class="stat-card">
				<div class="stat-label"><?php esc_html_e( 'Total Issues', 'leastudios-siteaudit' ); ?></div>
				<div class="stat-value"><?php echo esc_html( (string) $total_issues ); ?></div>
			</div>
		</td>
	</tr>
</table>

<table class="distribution-grid" cellspacing="0">
	<tr>
		<td style="width: 50%; vertical-align: top;">
			<div class="section-title"><?php esc_html_e( 'Score Distribution', 'leastudios-siteaudit' ); ?></div>
			<table cellspacing="0" style="width: 100%;">
				<tr>
					<td><span class="dist-label"><?php esc_html_e( 'Excellent (90-100)', 'leastudios-siteaudit' ); ?></span></td>
					<td style="text-align: right;"><span class="dist-value color-excellent"><?php echo esc_html( (string) $score_distribution['excellent'] ); ?></span></td>
				</tr>
				<tr>
					<td><span class="dist-label"><?php esc_html_e( 'Good (70-89)', 'leastudios-siteaudit' ); ?></span></td>
					<td style="text-align: right;"><span class="dist-value color-good"><?php echo esc_html( (string) $score_distribution['good'] ); ?></span></td>
				</tr>
				<tr>
					<td><span class="dist-label"><?php esc_html_e( 'Needs Work (50-69)', 'leastudios-siteaudit' ); ?></span></td>
					<td style="text-align: right;"><span class="dist-value color-warn"><?php echo esc_html( (string) $score_distribution['needs_work'] ); ?></span></td>
				</tr>
				<tr>
					<td><span class="dist-label"><?php esc_html_e( 'Poor (0-49)', 'leastudios-siteaudit' ); ?></span></td>
					<td style="text-align: right;"><span class="dist-value color-poor"><?php echo esc_html( (string) $score_distribution['poor'] ); ?></span></td>
				</tr>
			</table>
		</td>
		<td style="width: 50%; vertical-align: top; padding-left: 20px;">
			<div class="section-title"><?php esc_html_e( 'Severity Breakdown', 'leastudios-siteaudit' ); ?></div>
			<table cellspacing="0" style="width: 100%;">
				<tr>
					<td><span class="badge badge-critical"><?php esc_html_e( 'Critical', 'leastudios-siteaudit' ); ?></span></td>
					<td style="text-align: right;"><span class="dist-value"><?php echo esc_html( (string) $severity_counts['critical'] ); ?></span></td>
				</tr>
				<tr>
					<td><span class="badge badge-serious"><?php esc_html_e( 'Serious', 'leastudios-siteaudit' ); ?></span></td>
					<td style="text-align: right;"><span class="dist-value"><?php echo esc_html( (string) $severity_counts['serious'] ); ?></span></td>
				</tr>
				<tr>
					<td><span class="badge badge-moderate"><?php esc_html_e( 'Moderate', 'leastudios-siteaudit' ); ?></span></td>
					<td style="text-align: right;"><span class="dist-value"><?php echo esc_html( (string) $severity_counts['moderate'] ); ?></span></td>
				</tr>
				<tr>
					<td><span class="badge badge-minor"><?php esc_html_e( 'Minor', 'leastudios-siteaudit' ); ?></span></td>
					<td style="text-align: right;"><span class="dist-value"><?php echo esc_html( (string) $severity_counts['minor'] ); ?></span></td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<div class="section-title"><?php esc_html_e( 'URL Scores', 'leastudios-siteaudit' ); ?></div>
<?php if ( [] === $report->url_summaries() ) : ?>
	<div class="empty-state"><?php esc_html_e( 'No URLs have been audited yet.', 'leastudios-siteaudit' ); ?></div>
<?php else : ?>
	<table class="url-table" cellspacing="0">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'leastudios-siteaudit' ); ?></th>
				<th><?php esc_html_e( 'URL', 'leastudios-siteaudit' ); ?></th>
				<th><?php esc_html_e( 'Score', 'leastudios-siteaudit' ); ?></th>
				<th><?php esc_html_e( 'Grade', 'leastudios-siteaudit' ); ?></th>
				<th><?php esc_html_e( 'Audits', 'leastudios-siteaudit' ); ?></th>
				<th><?php esc_html_e( 'Frequency', 'leastudios-siteaudit' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $report->url_summaries() as $url_summary ) : ?>
				<?php $latest_score = $url_summary->latest_score(); ?>
				<tr>
					<td><strong><?php echo esc_html( $url_summary->name() ); ?></strong></td>
					<td><?php echo esc_html( $url_summary->address() ); ?></td>
					<td style="text-align: center;">
						<?php if ( null !== $latest_score ) : ?>
							<strong class="<?php echo esc_attr( $score_color_class( $latest_score ) ); ?>"><?php echo esc_html( (string) $latest_score ); ?></strong>
						<?php else : ?>
							&mdash;
						<?php endif; ?>
					</td>
					<td style="text-align: center;">
						<span class="grade <?php echo esc_attr( $grade_class( $url_summary->score_grade() ) ); ?>"><?php echo esc_html( $url_summary->score_grade() ); ?></span>
					</td>
					<td style="text-align: center;"><?php echo esc_html( (string) $url_summary->total_audits() ); ?></td>
					<td><?php echo esc_html( $url_summary->frequency() ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<?php if ( [] !== $issues_by_category ) : ?>
	<div class="page-break"></div>

	<div class="report-header">
		<h1><?php esc_html_e( 'Issue Breakdown', 'leastudios-siteaudit' ); ?></h1>
		<div class="subtitle">
			<?php
			$issue_label = 1 === $total_issues
				? __( '1 issue', 'leastudios-siteaudit' )
				/* translators: %d: number of issues. */
				: sprintf( __( '%d issues', 'leastudios-siteaudit' ), $total_issues );
			$category_label = 1 === $category_count
				? __( '1 category', 'leastudios-siteaudit' )
				/* translators: %d: number of categories. */
				: sprintf( __( '%d categories', 'leastudios-siteaudit' ), $category_count );

			printf(
				'%s &mdash; %s %s %s',
				esc_html( $report->project_name() ),
				esc_html( $issue_label ),
				esc_html__( 'across', 'leastudios-siteaudit' ),
				esc_html( $category_label )
			);
			?>
		</div>
	</div>

	<?php foreach ( $issues_by_category as $category => $issues ) : ?>
		<div class="category-header">
			<?php echo esc_html( $category ); ?> (<?php echo esc_html( (string) count( $issues ) ); ?>)
		</div>
		<?php foreach ( $issues as $issue_row ) : ?>
			<div class="issue-card">
				<div class="issue-title">
					<span class="badge badge-<?php echo esc_attr( strtolower( $issue_row['severity'] ) ); ?>">
						<?php echo esc_html( $issue_row['severity'] ); ?>
					</span>
					<?php echo esc_html( $issue_row['title'] ); ?>
				</div>
				<div class="issue-description"><?php echo esc_html( $issue_row['description'] ); ?></div>
				<?php if ( null !== $issue_row['help_url'] && '' !== $issue_row['help_url'] ) : ?>
					<div class="issue-meta">
						<?php esc_html_e( 'Learn more:', 'leastudios-siteaudit' ); ?>
						<a href="<?php echo esc_url( $issue_row['help_url'] ); ?>"><?php echo esc_html( $issue_row['help_url'] ); ?></a>
					</div>
				<?php endif; ?>
				<div class="affected-urls">
					<?php esc_html_e( 'Affected:', 'leastudios-siteaudit' ); ?>
					<?php echo esc_html( implode( ', ', $issue_row['affected_urls'] ) ); ?>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endforeach; ?>
<?php endif; ?>

<div class="report-footer">
	<?php
	printf(
		/* translators: %s: generated-at timestamp. */
		esc_html__( 'Generated by LEA Studios Site Audit &mdash; %s', 'leastudios-siteaudit' ),
		esc_html( $report->generated_at() )
	);
	?>
</div>

</body>
</html>
