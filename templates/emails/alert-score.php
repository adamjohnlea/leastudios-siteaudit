<?php
/**
 * Threshold-breach alert email body.
 *
 * Inline CSS only — many email clients strip `<head><style>` blocks.
 *
 * @package LEAStudios\SiteAudit
 *
 * @var string   $leastudios_siteaudit_url_name
 * @var string   $leastudios_siteaudit_url_address
 * @var int      $leastudios_siteaudit_current_score
 * @var int|null $leastudios_siteaudit_previous_score
 * @var int|null $leastudios_siteaudit_score_drop
 * @var int|null $leastudios_siteaudit_threshold_score
 * @var int|null $leastudios_siteaudit_threshold_drop
 * @var bool     $leastudios_siteaudit_score_threshold_breached
 * @var bool     $leastudios_siteaudit_drop_threshold_breached
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif; color: #1a1a1a; max-width: 560px; margin: 0 auto; padding: 24px;">

<h1 style="font-size: 18px; color: #b32d2e; margin: 0 0 16px;">
	<?php esc_html_e( 'Accessibility score alert', 'leastudios-siteaudit' ); ?>
</h1>

<p style="font-size: 14px; line-height: 1.5; margin: 0 0 16px;">
	<strong><?php echo esc_html( $leastudios_siteaudit_url_name ); ?></strong><br />
	<a href="<?php echo esc_url( $leastudios_siteaudit_url_address ); ?>" style="color: #2563eb; text-decoration: none; font-size: 13px;">
		<?php echo esc_html( $leastudios_siteaudit_url_address ); ?>
	</a>
</p>

<div style="background: #fae0e0; border-left: 4px solid #b32d2e; padding: 14px 18px; margin: 0 0 20px; border-radius: 3px;">
	<p style="font-size: 14px; line-height: 1.5; margin: 0;">
		<?php
		printf(
			/* translators: %d: latest accessibility score (0-100). */
			esc_html__( 'Latest score: %d', 'leastudios-siteaudit' ),
			(int) $leastudios_siteaudit_current_score
		);
		?>
		<?php if ( null !== $leastudios_siteaudit_previous_score ) : ?>
			<br />
			<?php
			printf(
				/* translators: %d: previous accessibility score. */
				esc_html__( 'Previous score: %d', 'leastudios-siteaudit' ),
				(int) $leastudios_siteaudit_previous_score
			);
			?>
		<?php endif; ?>
	</p>
</div>

<?php if ( $leastudios_siteaudit_score_threshold_breached && null !== $leastudios_siteaudit_threshold_score ) : ?>
	<p style="font-size: 14px; line-height: 1.5; margin: 0 0 12px;">
		<?php
		printf(
			/* translators: 1: current score, 2: configured below-threshold. */
			esc_html__( 'The latest score (%1$d) is at or below the configured alert threshold (%2$d).', 'leastudios-siteaudit' ),
			(int) $leastudios_siteaudit_current_score,
			(int) $leastudios_siteaudit_threshold_score
		);
		?>
	</p>
<?php endif; ?>

<?php if ( $leastudios_siteaudit_drop_threshold_breached && null !== $leastudios_siteaudit_threshold_drop && null !== $leastudios_siteaudit_score_drop ) : ?>
	<p style="font-size: 14px; line-height: 1.5; margin: 0 0 12px;">
		<?php
		printf(
			/* translators: 1: drop in points, 2: drop threshold. */
			esc_html__( 'The score dropped by %1$d points, exceeding the configured drop threshold of %2$d.', 'leastudios-siteaudit' ),
			(int) $leastudios_siteaudit_score_drop,
			(int) $leastudios_siteaudit_threshold_drop
		);
		?>
	</p>
<?php endif; ?>

<p style="font-size: 12px; color: #6c757d; margin: 24px 0 0;">
	<?php esc_html_e( 'You are receiving this because you are subscribed to alerts for this project. Visit the Site Audit dashboard to manage your subscriptions.', 'leastudios-siteaudit' ); ?>
</p>

</body>
</html>
