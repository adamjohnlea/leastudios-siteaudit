<?php
/**
 * URL bulk-import form.
 *
 * @package LEAStudios\SiteAudit
 *
 * @var array<int, \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Project> $projects
 * @var array<int, \LEAStudios\SiteAudit\Modules\Url\Domain\ValueObjects\Audit_Frequency> $frequencies
 * @var string $post_url
 * @var string $list_url
 * @var string $action_name
 */

defined( 'ABSPATH' ) || exit;

\LEAStudios\SiteAudit\Admin\Notice_Service::render();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Bulk Import URLs', 'leastudios-siteaudit' ); ?></h1>
	<p><?php esc_html_e( 'Add many URLs at once by pasting a list or uploading a CSV.', 'leastudios-siteaudit' ); ?></p>

	<form method="post" action="<?php echo esc_url( $post_url ); ?>" enctype="multipart/form-data">
		<?php wp_nonce_field( $action_name ); ?>
		<input type="hidden" name="action" value="<?php echo esc_attr( $action_name ); ?>" />

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Source', 'leastudios-siteaudit' ); ?></th>
					<td>
						<fieldset>
							<legend class="screen-reader-text"><span><?php esc_html_e( 'Import source', 'leastudios-siteaudit' ); ?></span></legend>
							<label>
								<input type="radio" name="import_type" value="paste" checked />
								<?php esc_html_e( 'Paste URLs (one per line)', 'leastudios-siteaudit' ); ?>
							</label><br />
							<label>
								<input type="radio" name="import_type" value="csv" />
								<?php esc_html_e( 'Upload CSV', 'leastudios-siteaudit' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="leastudios-siteaudit-bulk-urls"><?php esc_html_e( 'URLs', 'leastudios-siteaudit' ); ?></label>
					</th>
					<td>
						<textarea
							id="leastudios-siteaudit-bulk-urls"
							name="urls"
							rows="10"
							class="large-text code"
							placeholder="https://example.com&#10;https://example.com/about&#10;https://example.com/contact"
						></textarea>
						<p class="description"><?php esc_html_e( 'One URL per line. Each URL becomes both the address and the display name; rename later from the URL detail page.', 'leastudios-siteaudit' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="leastudios-siteaudit-bulk-csv"><?php esc_html_e( 'CSV file', 'leastudios-siteaudit' ); ?></label>
					</th>
					<td>
						<input
							type="file"
							id="leastudios-siteaudit-bulk-csv"
							name="csv_file"
							accept=".csv,text/csv"
						/>
						<p class="description"><?php esc_html_e( 'CSV must have a header row containing a "url" column. Optional columns: "name", "frequency".', 'leastudios-siteaudit' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="leastudios-siteaudit-bulk-frequency"><?php esc_html_e( 'Default frequency', 'leastudios-siteaudit' ); ?></label>
					</th>
					<td>
						<select id="leastudios-siteaudit-bulk-frequency" name="frequency">
							<?php foreach ( $frequencies as $frequency_case ) : ?>
								<option value="<?php echo esc_attr( $frequency_case->value ); ?>" <?php selected( $frequency_case->value, 'weekly' ); ?>>
									<?php echo esc_html( $frequency_case->label() ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="leastudios-siteaudit-bulk-project"><?php esc_html_e( 'Project', 'leastudios-siteaudit' ); ?></label>
					</th>
					<td>
						<select id="leastudios-siteaudit-bulk-project" name="project_id">
							<option value=""><?php esc_html_e( '— None —', 'leastudios-siteaudit' ); ?></option>
							<?php foreach ( $projects as $project_option ) : ?>
								<?php $option_id = (int) $project_option->id(); ?>
								<option value="<?php echo esc_attr( (string) $option_id ); ?>">
									<?php echo esc_html( $project_option->name()->value() ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<?php submit_button( __( 'Import URLs', 'leastudios-siteaudit' ), 'primary', 'submit', false ); ?>
			<a href="<?php echo esc_url( $list_url ); ?>" class="button"><?php esc_html_e( 'Cancel', 'leastudios-siteaudit' ); ?></a>
		</p>
	</form>
</div>
