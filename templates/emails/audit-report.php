<?php
/**
 * Per-project audit report email body. Sent alongside the PDF attachment.
 *
 * @package LEAStudios\SiteAudit
 *
 * @var string $project_name
 * @var string $date
 * @var int    $average_score
 * @var int    $total_urls
 * @var int    $total_issues
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; color: #1a1a1a; max-width: 560px; margin: 0 auto; padding: 24px;">

<h1 style="font-size: 18px; color: #1d2327; margin: 0 0 16px;">
	<?php
	printf(
		/* translators: %s: project name. */
		esc_html__( 'Accessibility audit report: %s', 'leastudios-siteaudit' ),
		esc_html( $project_name )
	);
	?>
</h1>

<p style="font-size: 13px; color: #6c757d; margin: 0 0 20px;">
	<?php
	printf(
		/* translators: %s: timestamp. */
		esc_html__( 'Generated %s', 'leastudios-siteaudit' ),
		esc_html( $date )
	);
	?>
</p>

<table cellspacing="0" cellpadding="0" style="width: 100%; margin: 0 0 24px;">
	<tr>
		<td style="padding: 12px 14px; background: #f1f7e3; border-radius: 4px; width: 33%;">
			<div style="font-size: 11px; color: #50575e; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px;">
				<?php esc_html_e( 'Average score', 'leastudios-siteaudit' ); ?>
			</div>
			<div style="font-size: 20px; font-weight: 600; color: #1e7e34;">
				<?php echo esc_html( (string) $average_score ); ?>
			</div>
		</td>
		<td style="padding: 12px 14px; background: #f0f0f1; border-radius: 4px; width: 33%;">
			<div style="font-size: 11px; color: #50575e; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px;">
				<?php esc_html_e( 'URLs', 'leastudios-siteaudit' ); ?>
			</div>
			<div style="font-size: 20px; font-weight: 600; color: #1d2327;">
				<?php echo esc_html( (string) $total_urls ); ?>
			</div>
		</td>
		<td style="padding: 12px 14px; background: #fcf2d9; border-radius: 4px; width: 33%;">
			<div style="font-size: 11px; color: #50575e; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px;">
				<?php esc_html_e( 'Total issues', 'leastudios-siteaudit' ); ?>
			</div>
			<div style="font-size: 20px; font-weight: 600; color: #8a5b00;">
				<?php echo esc_html( (string) $total_issues ); ?>
			</div>
		</td>
	</tr>
</table>

<p style="font-size: 14px; line-height: 1.5; margin: 0 0 12px;">
	<?php esc_html_e( 'The full report is attached as a PDF. Open it for the per-URL score breakdown and category-grouped issue list.', 'leastudios-siteaudit' ); ?>
</p>

<p style="font-size: 12px; color: #6c757d; margin: 24px 0 0;">
	<?php esc_html_e( 'You are receiving this because you are subscribed to reports for this project. Visit the Site Audit dashboard to manage your subscriptions.', 'leastudios-siteaudit' ); ?>
</p>

</body>
</html>
