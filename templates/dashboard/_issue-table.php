<?php
/**
 * Renders one issue table grouped by severity.
 *
 * Included from `dashboard/url.php`. Expects `$leastudios_siteaudit_issues`
 * to be a list of `Issue` models in the scope where it's included.
 *
 * @package LEAStudios\SiteAudit
 *
 * @var array<int, \LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Issue> $leastudios_siteaudit_issues
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $leastudios_siteaudit_issues ) || ! is_array( $leastudios_siteaudit_issues ) || [] === $leastudios_siteaudit_issues ) {
	echo '<p>' . esc_html__( 'No issues recorded for this strategy.', 'leastudios-siteaudit' ) . '</p>';
	return;
}

$leastudios_siteaudit_grouped = [
	'critical' => [],
	'serious'  => [],
	'moderate' => [],
	'minor'    => [],
];

foreach ( $leastudios_siteaudit_issues as $leastudios_siteaudit_issue ) {
	$leastudios_siteaudit_grouped[ $leastudios_siteaudit_issue->severity()->value ][] = $leastudios_siteaudit_issue;
}
?>
<div class="lsa-issues">
	<?php foreach ( $leastudios_siteaudit_grouped as $leastudios_siteaudit_severity_key => $leastudios_siteaudit_severity_issues ) : ?>
		<?php if ( [] === $leastudios_siteaudit_severity_issues ) : ?>
			<?php continue; ?>
		<?php endif; ?>
		<?php $leastudios_siteaudit_severity_label = $leastudios_siteaudit_severity_issues[0]->severity()->label(); ?>
		<details class="lsa-issue-group" open>
			<summary>
				<span class="lsa-severity-badge lsa-severity-<?php echo esc_attr( $leastudios_siteaudit_severity_key ); ?>">
					<?php echo esc_html( $leastudios_siteaudit_severity_label ); ?>
				</span>
				<span class="lsa-issue-group__count">
					<?php echo esc_html( sprintf( /* translators: %d: number of issues */ _n( '%d issue', '%d issues', count( $leastudios_siteaudit_severity_issues ), 'leastudios-siteaudit' ), count( $leastudios_siteaudit_severity_issues ) ) ); ?>
				</span>
			</summary>
			<table class="wp-list-table widefat">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Issue', 'leastudios-siteaudit' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Category', 'leastudios-siteaudit' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Element', 'leastudios-siteaudit' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Reference', 'leastudios-siteaudit' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $leastudios_siteaudit_severity_issues as $leastudios_siteaudit_issue_row ) : ?>
						<tr>
							<td>
								<?php $leastudios_siteaudit_issue_title = (string) ( $leastudios_siteaudit_issue_row->title() ?? '' ); ?>
								<?php if ( '' !== $leastudios_siteaudit_issue_title ) : ?>
									<strong><?php echo esc_html( $leastudios_siteaudit_issue_title ); ?></strong><br />
								<?php endif; ?>
								<span class="lsa-issue-desc"><?php echo esc_html( $leastudios_siteaudit_issue_row->description() ); ?></span>
							</td>
							<td><?php echo esc_html( $leastudios_siteaudit_issue_row->category()->label() ); ?></td>
							<td>
								<?php $leastudios_siteaudit_selector = $leastudios_siteaudit_issue_row->element_selector(); ?>
								<?php if ( null !== $leastudios_siteaudit_selector && '' !== $leastudios_siteaudit_selector ) : ?>
									<code><?php echo esc_html( $leastudios_siteaudit_selector ); ?></code>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td>
								<?php $leastudios_siteaudit_help_url = $leastudios_siteaudit_issue_row->help_url(); ?>
								<?php if ( null !== $leastudios_siteaudit_help_url && '' !== $leastudios_siteaudit_help_url ) : ?>
									<a href="<?php echo esc_url( $leastudios_siteaudit_help_url ); ?>" target="_blank" rel="noreferrer noopener">
										<?php esc_html_e( 'Learn more', 'leastudios-siteaudit' ); ?>
									</a>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</details>
	<?php endforeach; ?>
</div>
