<?php
/**
 * URL bulk-import result page.
 *
 * @package LEAStudios\SiteAudit
 *
 * @var \LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Bulk_Import_Result $leastudios_siteaudit_result
 * @var string $leastudios_siteaudit_list_url
 * @var string $leastudios_siteaudit_bulk_import_url
 */

defined( 'ABSPATH' ) || exit;

\LEAStudios\SiteAudit\Admin\Notice_Service::render();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Bulk Import Result', 'leastudios-siteaudit' ); ?></h1>

	<table class="widefat striped" style="max-width:480px;margin-bottom:1em;">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Imported', 'leastudios-siteaudit' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $leastudios_siteaudit_result->imported_count ) ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Skipped (duplicates)', 'leastudios-siteaudit' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( $leastudios_siteaudit_result->skipped_count ) ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Errors', 'leastudios-siteaudit' ); ?></th>
				<td><?php echo esc_html( number_format_i18n( count( $leastudios_siteaudit_result->errors ) ) ); ?></td>
			</tr>
		</tbody>
	</table>

	<?php if ( $leastudios_siteaudit_result->has_errors() ) : ?>
		<h2><?php esc_html_e( 'Rows with errors', 'leastudios-siteaudit' ); ?></h2>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Line', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'URL', 'leastudios-siteaudit' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Error', 'leastudios-siteaudit' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $leastudios_siteaudit_result->errors as $leastudios_siteaudit_error_row ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $leastudios_siteaudit_error_row['line'] ); ?></td>
						<td><code><?php echo esc_html( (string) $leastudios_siteaudit_error_row['url'] ); ?></code></td>
						<td><?php echo esc_html( (string) $leastudios_siteaudit_error_row['error'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<p class="submit">
		<a href="<?php echo esc_url( $leastudios_siteaudit_bulk_import_url ); ?>" class="button button-primary"><?php esc_html_e( 'Import More', 'leastudios-siteaudit' ); ?></a>
		<a href="<?php echo esc_url( $leastudios_siteaudit_list_url ); ?>" class="button"><?php esc_html_e( 'View All URLs', 'leastudios-siteaudit' ); ?></a>
	</p>
</div>
