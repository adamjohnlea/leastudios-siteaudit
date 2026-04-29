<?php
/**
 * Renders one issue table grouped by severity.
 *
 * Included from `dashboard/url.php`. Expects `$issues` to be a list of
 * `Issue` models in the scope where it's included.
 *
 * @package LEAStudios\SiteAudit
 *
 * @var array<int, \LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Issue> $issues
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $issues ) || ! is_array( $issues ) || [] === $issues ) {
	echo '<p>' . esc_html__( 'No issues recorded for this strategy.', 'leastudios-siteaudit' ) . '</p>';
	return;
}

$grouped = [
	'critical' => [],
	'serious'  => [],
	'moderate' => [],
	'minor'    => [],
];

foreach ( $issues as $issue ) {
	$grouped[ $issue->severity()->value ][] = $issue;
}
?>
<div class="lsa-issues">
	<?php foreach ( $grouped as $severity_key => $severity_issues ) : ?>
		<?php if ( [] === $severity_issues ) : ?>
			<?php continue; ?>
		<?php endif; ?>
		<?php $severity_label = $severity_issues[0]->severity()->label(); ?>
		<details class="lsa-issue-group" open>
			<summary>
				<span class="lsa-severity-badge lsa-severity-<?php echo esc_attr( $severity_key ); ?>">
					<?php echo esc_html( $severity_label ); ?>
				</span>
				<span class="lsa-issue-group__count">
					<?php echo esc_html( sprintf( /* translators: %d: number of issues */ _n( '%d issue', '%d issues', count( $severity_issues ), 'leastudios-siteaudit' ), count( $severity_issues ) ) ); ?>
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
					<?php foreach ( $severity_issues as $issue_row ) : ?>
						<tr>
							<td>
								<?php $issue_title = (string) ( $issue_row->title() ?? '' ); ?>
								<?php if ( '' !== $issue_title ) : ?>
									<strong><?php echo esc_html( $issue_title ); ?></strong><br />
								<?php endif; ?>
								<span class="lsa-issue-desc"><?php echo esc_html( $issue_row->description() ); ?></span>
							</td>
							<td><?php echo esc_html( $issue_row->category()->label() ); ?></td>
							<td>
								<?php $selector = $issue_row->element_selector(); ?>
								<?php if ( null !== $selector && '' !== $selector ) : ?>
									<code><?php echo esc_html( $selector ); ?></code>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							</td>
							<td>
								<?php $help_url = $issue_row->help_url(); ?>
								<?php if ( null !== $help_url && '' !== $help_url ) : ?>
									<a href="<?php echo esc_url( $help_url ); ?>" target="_blank" rel="noreferrer noopener">
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
